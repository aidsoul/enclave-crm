<?php

namespace Tests\Feature\Sites;

use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteTokenHashSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 90001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);
    }

    public function test_sites_table_stores_sha256_hash_and_does_not_contain_plaintext_column(): void
    {
        $this->assertTrue(Schema::hasColumn('sites', 'api_token_hash'));
        $this->assertFalse(Schema::hasColumn('sites', 'api_token'));

        $site = Site::create([
            'name' => 'Security Test Store',
            'domain' => 'secstore.com',
            'is_active' => true,
        ]);

        $plainToken = $site->api_token;
        $this->assertNotNull($plainToken);
        $this->assertTrue(Str::isUuid($plainToken));

        // Query raw database row to verify plaintext token is NOT stored anywhere in the DB
        $rawSite = DB::table('sites')->where('id', $site->id)->first();
        $this->assertNotNull($rawSite->api_token_hash);
        $this->assertEquals(64, strlen($rawSite->api_token_hash));
        $this->assertEquals(hash('sha256', $plainToken), $rawSite->api_token_hash);
        $this->assertObjectNotHasProperty('api_token', $rawSite);
    }

    public function test_incoming_webhook_resolves_site_by_sha256_hash_of_plain_token(): void
    {
        $site = Site::create([
            'name' => 'Store Webhook Test',
            'domain' => 'hookstore.com',
            'is_active' => true,
        ]);

        $plainToken = $site->api_token;
        $expectedHash = hash('sha256', $plainToken);

        $this->assertEquals($expectedHash, $site->api_token_hash);

        // Plain token resolves site
        $found = Site::findByTokenCached($plainToken);
        $this->assertNotNull($found);
        $this->assertEquals($site->id, $found->id);

        // Verification via incoming webhook endpoint
        $response = $this->withHeader('X-API-Token', $plainToken)
            ->postJson('/api/orders/incoming', [
                'external_id' => 'sec-order-1',
                'client_name' => 'Иван Иванов',
                'client_phone' => '+375291112233',
                'delivery_address' => 'г. Минск, ул. Кирова 1',
                'order_items' => [
                    ['name' => 'Товар 1', 'price' => 100, 'qty' => 1],
                ],
            ]);

        $response->assertAccepted();
    }

    public function test_pass_the_hash_attack_is_rejected(): void
    {
        $site = Site::create([
            'name' => 'Store Anti Hash Pass',
            'domain' => 'antihash.com',
            'is_active' => true,
        ]);

        $rawHash = $site->api_token_hash;

        // An attacker who dumps the database and gets api_token_hash tries to use it directly
        $found = Site::findByTokenCached($rawHash);
        $this->assertNull($found);

        $response = $this->withHeader('X-API-Token', $rawHash)
            ->postJson('/api/orders/incoming', [
                'external_id' => 'sec-order-attack',
                'client_name' => 'Hacker',
                'client_phone' => '+375299999999',
                'delivery_address' => 'Unknown',
                'order_items' => [
                    ['name' => 'Item', 'price' => 10, 'qty' => 1],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_token_rotation_updates_hash_and_invalidates_old_plain_token(): void
    {
        $site = Site::create([
            'name' => 'Rotate Store',
            'domain' => 'rotatestore.com',
            'is_active' => true,
        ]);

        $oldPlainToken = $site->api_token;
        $oldHash = $site->api_token_hash;

        $newToken = $site->rotateApiToken();
        $newHash = $site->api_token_hash;

        $this->assertNotEquals($oldPlainToken, $newToken);
        $this->assertNotEquals($oldHash, $newHash);
        $this->assertEquals(hash('sha256', $newToken), $newHash);

        // Old token must fail
        $this->assertNull(Site::findByTokenCached($oldPlainToken));

        // New token must succeed
        $found = Site::findByTokenCached($newToken);
        $this->assertNotNull($found);
        $this->assertEquals($site->id, $found->id);

        // Verify DB only holds the new hash
        $rawSite = DB::table('sites')->where('id', $site->id)->first();
        $this->assertEquals($newHash, $rawSite->api_token_hash);
    }

    public function test_token_revocation_sets_hash_to_null_in_database(): void
    {
        $site = Site::create([
            'name' => 'Revoke Store',
            'domain' => 'revokestore.com',
            'is_active' => true,
        ]);

        $plainToken = $site->api_token;
        $site->revokeApiToken();

        $this->assertNull($site->api_token_hash);
        $this->assertNull($site->api_token);
        $this->assertFalse($site->has_token);

        $rawSite = DB::table('sites')->where('id', $site->id)->first();
        $this->assertNull($rawSite->api_token_hash);

        $this->assertNull(Site::findByTokenCached($plainToken));
    }

    public function test_api_token_hash_is_protected_from_mass_assignment(): void
    {
        $fakeHash = str_repeat('a', 64);

        $site = Site::create([
            'name' => 'Mass Assign Store',
            'domain' => 'massassign.com',
            'api_token_hash' => $fakeHash,
            'is_active' => true,
        ]);

        // Mass assignment should NOT overwrite generated hash with fakeHash
        $this->assertNotEquals($fakeHash, $site->api_token_hash);
        $this->assertEquals(64, strlen($site->api_token_hash));
    }

    public function test_deactivated_site_token_is_immediately_invalidated(): void
    {
        $site = Site::create([
            'name' => 'Deactivate Store',
            'domain' => 'deactivate.com',
            'is_active' => true,
        ]);

        $plainToken = $site->api_token;

        // Warm up cache
        $this->assertNotNull(Site::findByTokenCached($plainToken));

        // Deactivate site
        $site->update(['is_active' => false]);

        // Token lookup must now return null immediately
        $this->assertNull(Site::findByTokenCached($plainToken));

        // Webhook request must be forbidden
        $response = $this->withHeader('X-API-Token', $plainToken)
            ->postJson('/api/orders/incoming', [
                'external_id' => 'deact-order-1',
                'client_name' => 'Клиент',
                'client_phone' => '+375293334455',
                'delivery_address' => 'Адрес',
                'order_items' => [
                    ['name' => 'Item', 'price' => 50, 'qty' => 1],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_negative_caching_protects_against_cache_penetration(): void
    {
        $fakeUuid = (string) Str::uuid();
        $hash = hash('sha256', $fakeUuid);

        $this->assertNull(Site::findByTokenCached($fakeUuid));

        // Negative cache sentinel is set
        $this->assertEquals('__missing__', \Illuminate\Support\Facades\Cache::get("site:token_hash:{$hash}"));

        // Subsequent call returns null via cache
        $this->assertNull(Site::findByTokenCached($fakeUuid));
    }
}
