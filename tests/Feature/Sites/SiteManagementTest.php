<?php

namespace Tests\Feature\Sites;

use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 12001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Alex',
            'telegram_user_id' => 12002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_create_and_view_sites_with_tokens(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/sites', [
                'name' => 'Новый Магазин',
                'domain' => 'newshop.by',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('site.name', 'Новый Магазин')
            ->assertJsonPath('site.domain', 'newshop.by');

        $this->assertNotEmpty($response->json('site.api_token'));

        $listRes = $this->actingAs($this->owner)->getJson('/api/sites');
        $listRes->assertOk();
        $this->assertNotEmpty($listRes->json('sites.0.api_token'));
    }

    public function test_owner_can_rotate_and_revoke_token(): void
    {
        $site = Site::create([
            'name' => 'MyShop',
            'domain' => 'myshop.com',
            'is_active' => true,
        ]);

        $oldToken = $site->api_token;
        $this->assertNotNull(Site::findByTokenCached($oldToken));

        $rotateRes = $this->actingAs($this->owner)
            ->postJson("/api/sites/{$site->id}/rotate-token");

        $rotateRes->assertOk();
        $newToken = $rotateRes->json('api_token');
        $this->assertNotEquals($oldToken, $newToken);
        $this->assertNull(Site::findByTokenCached($oldToken));
        $this->assertNotNull(Site::findByTokenCached($newToken));

        $revokeRes = $this->actingAs($this->owner)
            ->postJson("/api/sites/{$site->id}/revoke-token");

        $revokeRes->assertOk();
        $this->assertNull($site->fresh()->api_token);
        $this->assertNull(Site::findByTokenCached($newToken));
    }

    public function test_owner_can_access_sites_page(): void
    {
        $response = $this->actingAs($this->owner)->get('/tma/sites');
        $response->assertOk()
            ->assertViewIs('tma.owner.sites');
    }

    public function test_non_owner_blocked_from_sites_page(): void
    {
        $response = $this->actingAs($this->manager)->get('/tma/sites');
        $response->assertForbidden()
            ->assertViewIs('tma.blocked');
    }

    public function test_owner_can_search_and_paginate_sites(): void
    {
        Site::create(['name' => 'Альфа Магазин', 'domain' => 'alpha.com', 'is_active' => true]);
        Site::create(['name' => 'Бета Шоп', 'domain' => 'beta.org', 'is_active' => true]);
        Site::create(['name' => 'Гамма Стор', 'domain' => 'gamma.net', 'is_active' => true]);

        // Search by name
        $searchRes = $this->actingAs($this->owner)
            ->getJson('/api/sites?search=альфа');
        $searchRes->assertOk()
            ->assertJsonCount(1, 'sites')
            ->assertJsonPath('sites.0.domain', 'alpha.com');

        // Search by domain
        $searchDomainRes = $this->actingAs($this->owner)
            ->getJson('/api/sites?search=beta.org');
        $searchDomainRes->assertOk()
            ->assertJsonCount(1, 'sites')
            ->assertJsonPath('sites.0.name', 'Бета Шоп');

        // Pagination
        $pageRes = $this->actingAs($this->owner)
            ->getJson('/api/sites?per_page=2&page=1');
        $pageRes->assertOk()
            ->assertJsonCount(2, 'sites')
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_owner_can_configure_site_default_purchase_percent(): void
    {
        $createRes = $this->actingAs($this->owner)
            ->postJson('/api/sites', [
                'name' => 'Процент Магазин',
                'domain' => 'percent.by',
                'default_purchase_percent' => 22.50,
                'is_active' => true,
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('site.default_purchase_percent', '22.50');

        $siteId = $createRes->json('site.id');

        $updateRes = $this->actingAs($this->owner)
            ->patchJson("/api/sites/{$siteId}", [
                'default_purchase_percent' => 35.00,
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('site.default_purchase_percent', '35.00');

        $this->assertDatabaseHas('sites', [
            'id' => $siteId,
            'default_purchase_percent' => 35.00,
        ]);
    }
}
