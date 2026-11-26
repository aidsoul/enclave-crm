<?php

namespace Tests\Feature\Audit;

use App\Enums\ActionType;
use App\Enums\DeliveryType;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Services\Orders\OrderUpdater;
use App\Services\PanicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditComprehensiveTest extends TestCase
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
            'name' => 'Store 1',
            'domain' => 'store1.example.com',
            'token' => 'store1_token',
            'is_active' => true,
        ]);
    }

    public function test_customer_creation_update_and_blacklist_are_audited(): void
    {
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Иван Тестов',
            'phone' => '+79991112233',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'customer_id' => $customer->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'customer',
        ]);

        $customer->update([
            'is_blacklisted' => true,
            'blacklist_reason' => 'Невыкуп товара',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'customer_id' => $customer->id,
            'action_type' => ActionType::STATUS_CHANGE->value,
            'field_name' => 'is_blacklisted',
            'old_value' => 'Не в ЧС',
            'new_value' => 'В чёрном списке',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'customer_id' => $customer->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'blacklist_reason',
            'new_value' => 'Невыкуп товара',
        ]);
    }

    public function test_user_changes_and_panic_store_target_user_id(): void
    {
        $this->actingAs($this->owner);

        $courier = User::create([
            'system_name' => 'Быстрый Курьер',
            'telegram_user_id' => 99902,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'target_user_id' => $courier->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'user',
        ]);

        app(PanicService::class)->triggerPanic($courier->id, $this->owner->id);

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'target_user_id' => $courier->id,
            'action_type' => ActionType::PANIC->value,
            'field_name' => 'is_active',
            'old_value' => 'true',
            'new_value' => 'false',
        ]);
    }

    public function test_order_delivery_payment_and_item_changes_are_audited(): void
    {
        $this->actingAs($this->owner);

        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 1000,
            'purchase_price' => 850,
            'status' => OrderStatus::ACCEPTED,
        ]);

        $order->delivery()->create([
            'type' => DeliveryType::COURIER->value,
            'delivery_address' => 'Старый адрес 1',
            'delivery_cost' => 10,
        ]);

        $order->payment()->create([
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PENDING,
            'amount' => 1000,
        ]);

        $orderUpdater = app(OrderUpdater::class);
        $orderUpdater->update($order->id, [
            'delivery_address' => 'Новый адрес 99',
            'delivery_cost' => 25,
            'payment_status' => PaymentStatus::PAID->value,
        ], $this->owner->id);

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'delivery.address',
            'old_value' => 'Старый адрес 1',
            'new_value' => 'Новый адрес 99',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'delivery.cost',
            'old_value' => '10.00',
            'new_value' => '25',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'order_id' => $order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'payment.status',
            'old_value' => PaymentStatus::PENDING->value,
            'new_value' => PaymentStatus::PAID->value,
        ]);
    }

    public function test_order_deletion_preserves_audit_logs(): void
    {
        $this->actingAs($this->owner);

        $order = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 500,
            'purchase_price' => 425,
            'status' => OrderStatus::CREATED,
        ]);

        ActionLog::create([
            'actor_id' => $this->owner->id,
            'order_id' => $order->id,
            'action_type' => ActionType::CREATE,
            'field_name' => 'order',
            'created_at' => now(),
        ]);

        $orderId = $order->id;
        $order->delete();

        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseHas('action_logs', [
            'order_id' => $orderId,
            'action_type' => ActionType::CREATE->value,
        ]);
        $this->assertDatabaseHas('action_logs', [
            'order_id' => $orderId,
            'action_type' => ActionType::DELETE->value,
        ]);
    }

    public function test_site_and_setting_changes_are_audited(): void
    {
        $this->actingAs($this->owner);

        $this->site->rotateApiToken();

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'api_token',
        ]);

        $this->site->update(['default_purchase_percent' => 20]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'site.default_purchase_percent',
            'new_value' => '20.00',
        ]);

        $setting = Setting::create([
            'key' => 'retention_days',
            'value' => '30',
        ]);

        $this->assertDatabaseHas('action_logs', [
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'setting.retention_days',
            'new_value' => '30',
        ]);

        $setting->update(['value' => '60']);

        $this->assertDatabaseHas('action_logs', [
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'setting.retention_days',
            'old_value' => '30',
            'new_value' => '60',
        ]);
    }

    public function test_product_and_lead_changes_are_audited_with_site_id(): void
    {
        $this->actingAs($this->owner);

        $product = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Test Product',
            'sku' => 'TP-01',
            'price' => 100,
            'cost_price' => 40,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'product',
        ]);

        $product->update([
            'cost_price' => 45,
            'price' => 110,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'product.cost_price',
            'old_value' => '40.00',
            'new_value' => '45.00',
        ]);

        $lead = Lead::create([
            'site_id' => $this->site->id,
            'name' => 'Лид Тест',
            'phone' => '+79998887766',
            'status' => LeadStatus::NEW,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'site_id' => $this->site->id,
            'lead_id' => $lead->id,
            'action_type' => ActionType::CREATE->value,
            'field_name' => 'lead',
        ]);
    }
}
