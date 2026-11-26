<?php

namespace Tests\Feature\Audit;

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\Invitation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\Telegram\TelegramInitDataValidator;
use App\Services\UserSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuditCoverageExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 99901,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Store Main',
            'domain' => 'main.example.com',
            'token' => 'main_token',
            'is_active' => true,
        ]);
    }

    public function test_product_variant_lifecycle_is_audited(): void
    {
        $this->actingAs($this->owner);

        $product = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Футболка',
            'price' => 50,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TSHIRT-RED-XL',
            'title' => 'Красная XL',
            'price' => 55,
            'purchase_price' => 30,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'product_variant',
            'new_value' => 'Красная XL TSHIRT-RED-XL',
        ]);

        $variant->update([
            'price' => 60,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'product_variant.price',
            'old_value' => '55.00',
            'new_value' => '60.00',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::STATUS_CHANGE->value,
            'field_name' => 'product_variant.is_active',
            'old_value' => 'true',
            'new_value' => 'false',
        ]);

        $variant->delete();

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::DELETE->value,
            'field_name' => 'product_variant',
            'old_value' => 'Красная XL TSHIRT-RED-XL',
        ]);
    }

    public function test_user_updates_are_audited_in_observer(): void
    {
        $this->actingAs($this->owner);

        $manager = User::create([
            'system_name' => 'Иван Менеджер',
            'telegram_user_id' => 99902,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $manager->update([
            'system_name' => 'Иван Старший Менеджер',
            'telegram_username' => 'ivan_senior',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'target_user_id' => $manager->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'system_name',
            'old_value' => 'Иван Менеджер',
            'new_value' => 'Иван Старший Менеджер',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'target_user_id' => $manager->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'telegram_username',
            'new_value' => 'ivan_senior',
        ]);
    }

    public function test_user_scoped_settings_are_audited_with_target_user(): void
    {
        $this->actingAs($this->owner);

        $setting = Setting::create([
            'key' => 'ui_theme',
            'value' => 'dark',
            'scope' => 'user',
            'user_id' => $this->owner->id,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'target_user_id' => $this->owner->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'setting.user.ui_theme',
            'new_value' => 'dark',
        ]);

        $setting->update(['value' => 'light']);

        $this->assertDatabaseHas('action_logs', [
            'target_user_id' => $this->owner->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'setting.user.ui_theme',
            'old_value' => 'dark',
            'new_value' => 'light',
        ]);
    }

    public function test_auth_actions_and_sessions_are_audited(): void
    {
        $sessionService = app(UserSessionService::class);
        $token = 'test_token_1234567890123456789012345678901234567890';
        $sessionService->registerSession($this->owner, $token, (int) $this->owner->telegram_user_id);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'target_user_id' => $this->owner->id,
            'action_type' => ActionType::AUTH->value,
            'field_name' => 'logout',
        ]);

        $token2 = 'test_token_2234567890123456789012345678901234567890';
        $sessionService->registerSession($this->owner, $token2, (int) $this->owner->telegram_user_id);

        $this->withToken($token2)
            ->deleteJson('/api/auth/sessions', ['revoke_all' => true])
            ->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'target_user_id' => $this->owner->id,
            'action_type' => ActionType::AUTH->value,
            'field_name' => 'revoke_session',
        ]);
    }

    public function test_financial_export_is_audited(): void
    {
        $sessionService = app(UserSessionService::class);
        $token = 'test_export_owner_session_token_12345678901234567890';
        $sessionService->registerSession($this->owner, $token, (int) $this->owner->telegram_user_id);

        $response = $this->withToken($token)
            ->getJson('/api/exports/financial?from=2026-10-01&to=2026-10-31');

        $response->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'action_type' => ActionType::EXPORT->value,
            'field_name' => 'financial_export',
        ]);
    }

    public function test_invitation_acceptance_is_audited(): void
    {
        $invitation = Invitation::create([
            'token' => 'valid_invite_token_123',
            'role' => UserRole::COURIER,
            'system_name' => 'Новый Курьер',
            'max_activations' => 1,
            'expires_at' => now()->addDay(),
            'created_by' => $this->owner->id,
        ]);

        $validatorMock = Mockery::mock(TelegramInitDataValidator::class);
        $validatorMock->shouldReceive('getTelegramUser')
            ->andReturn((object) [
                'id' => 99999,
                'first_name' => 'Сергей',
                'username' => 'sergey_courier',
            ]);
        $this->app->instance(TelegramInitDataValidator::class, $validatorMock);

        $service = app(InvitationService::class);
        $result = $service->accept('valid_init_data', 'valid_invite_token_123');

        $this->assertArrayHasKey('user', $result);
        $newUser = $result['user'];

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $newUser->id,
            'target_user_id' => $newUser->id,
            'action_type' => ActionType::AUTH->value,
            'field_name' => 'invitation_accept',
        ]);
    }

    public function test_action_logs_api_filters_by_auth_and_exports(): void
    {
        $sessionService = app(UserSessionService::class);
        $token = 'test_audit_api_owner_token_12345678901234567890';
        $sessionService->registerSession($this->owner, $token, (int) $this->owner->telegram_user_id);

        ActionLog::create([
            'actor_id' => $this->owner->id,
            'action_type' => ActionType::AUTH,
            'field_name' => 'login',
            'created_at' => now(),
        ]);

        ActionLog::create([
            'actor_id' => $this->owner->id,
            'action_type' => ActionType::EXPORT,
            'field_name' => 'financial_export',
            'created_at' => now(),
        ]);

        $authResponse = $this->withToken($token)
            ->getJson('/api/audit/logs?filter=auth')
            ->assertOk();

        $authItems = $authResponse->json('logs');
        $this->assertNotEmpty($authItems);
        foreach ($authItems as $item) {
            $this->assertEquals('auth', $item['action_type']);
        }

        $exportResponse = $this->withToken($token)
            ->getJson('/api/audit/logs?filter=exports')
            ->assertOk();

        $exportItems = $exportResponse->json('logs');
        $this->assertNotEmpty($exportItems);
        foreach ($exportItems as $item) {
            $this->assertEquals('export', $item['action_type']);
        }
    }
}
