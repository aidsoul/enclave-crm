<?php

namespace Tests\Feature\Couriers;

use App\Enums\CourierHandoverReason;
use App\Enums\CourierHandoverStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $manager;

    protected User $courierA;

    protected User $courierB;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'name' => 'Store 1',
            'domain' => 'store1.local',
            'token' => 'token123',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Max',
            'telegram_user_id' => 99999,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->courierA = User::create([
            'system_name' => 'Courier A',
            'role' => UserRole::COURIER,
            'is_active' => true,
            'telegram_user_id' => 11111,
        ]);

        $this->courierB = User::create([
            'system_name' => 'Courier B',
            'role' => UserRole::COURIER,
            'is_active' => true,
            'telegram_user_id' => 22222,
        ]);

        $this->customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Ivan Petrov',
            'phone' => '+375291234567',
            'address' => 'Minsk, Lenina 10, apt 5',
        ]);
    }

    protected function createOrder(User $courier, OrderStatus $status = OrderStatus::ASSIGNED, float $price = 50.0): Order
    {
        $order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'courier_id' => $courier->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'total_price' => $price,
            'purchase_price' => 30.0,
            'comment' => 'Test handover order',
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'user_id' => $courier->id,
            'type' => DeliveryType::COURIER->value,
            'delivery_address' => 'Minsk, Lenina 10, apt 5',
            'delivery_cost' => 5.0,
            'delivery_date' => now()->toDateString(),
        ]);

        return $order;
    }

    public function test_courier_can_view_transferable_orders_and_cash(): void
    {
        // 1 completed order (collected 100 cash today)
        $completedOrder = $this->createOrder($this->courierA, OrderStatus::COMPLETED, 100.0);
        Payment::create([
            'order_id' => $completedOrder->id,
            'amount' => 100.0,
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PAID,
        ]);

        // 2 active orders
        $order1 = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 40.0);
        $order2 = $this->createOrder($this->courierA, OrderStatus::AT_WAREHOUSE, 60.0);

        $response = $this->actingAs($this->courierA)->getJson('/api/courier/handover/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'orders',
                'current_cash',
            ]);

        $this->assertEquals(100.0, $response->json('current_cash'));
        $this->assertCount(2, $response->json('orders'));
    }

    public function test_courier_can_initiate_handover_with_orders_and_cash(): void
    {
        $order1 = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 50.0);
        $order2 = $this->createOrder($this->courierA, OrderStatus::AT_WAREHOUSE, 70.0);

        $response = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order1->id, $order2->id],
            'transfer_cash' => true,
            'declared_cash' => 150.0,
            'reason' => CourierHandoverReason::VEHICLE_BREAKDOWN->value,
            'reason_comment' => 'Провал педали сцепления, стою на МКАД',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'handover' => [
                    'orders_count' => 2,
                    'declared_cash_amount' => 150.0,
                    'transfer_cash' => true,
                ],
            ]);

        $transferCode = $response->json('handover.transfer_code');
        $this->assertNotEmpty($transferCode);

        // Check active endpoint returns it
        $activeRes = $this->actingAs($this->courierA)->getJson('/api/courier/handover/active');
        $activeRes->assertOk()
            ->assertJsonPath('handover.transfer_code', $transferCode);
    }

    public function test_second_courier_can_preview_and_accept_handover_without_discrepancy(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 85.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => true,
            'declared_cash' => 120.0,
            'reason' => CourierHandoverReason::ACCIDENT->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        // Courier B previews handover
        $previewRes = $this->actingAs($this->courierB)->getJson("/api/courier/handover/preview/{$code}");
        $previewRes->assertOk()
            ->assertJsonPath('from_courier.id', $this->courierA->id)
            ->assertJsonPath('declared_cash_amount', 120)
            ->assertJsonCount(1, 'orders');

        // Courier B accepts with exact cash match
        $acceptRes = $this->actingAs($this->courierB)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 120.0,
        ]);

        $acceptRes->assertOk()
            ->assertJson([
                'success' => true,
                'status' => CourierHandoverStatus::COMPLETED->value,
                'discrepancy' => 0.0,
                'orders_reassigned' => 1,
            ]);

        // Order should now belong to Courier B
        $order->refresh();
        $this->assertEquals($this->courierB->id, $order->courier_id);
    }

    public function test_accept_handover_with_discrepancy_marks_as_disputed(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 50.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => true,
            'declared_cash' => 100.0,
            'reason' => CourierHandoverReason::HEALTH->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        // Courier B received only 80 cash (shortage 20)
        $acceptRes = $this->actingAs($this->courierB)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 80.0,
            'discrepancy_reason' => 'Купюры не хватило, пересчитали дважды',
        ]);

        $acceptRes->assertOk()
            ->assertJson([
                'success' => true,
                'status' => CourierHandoverStatus::DISPUTED->value,
                'discrepancy' => -20.0,
            ]);
    }

    public function test_courier_cannot_accept_own_handover(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 50.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => false,
            'reason' => CourierHandoverReason::OTHER->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        $previewRes = $this->actingAs($this->courierA)->getJson("/api/courier/handover/preview/{$code}");
        $previewRes->assertStatus(422);

        $acceptRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 0.0,
        ]);
        $acceptRes->assertStatus(422);
    }

    public function test_courier_can_cancel_pending_handover(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 50.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => false,
            'reason' => CourierHandoverReason::OTHER->value,
        ]);
        $handoverId = $initRes->json('handover.id');

        $cancelRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/cancel', [
            'handover_id' => $handoverId,
        ]);
        $cancelRes->assertOk()->assertJson(['success' => true]);

        // Active should now return null
        $activeRes = $this->actingAs($this->courierA)->getJson('/api/courier/handover/active');
        $activeRes->assertOk()->assertJson(['handover' => null]);
    }

    public function test_shift_summary_adjusts_cash_balance_after_handover(): void
    {
        // Courier A delivered order for 200 cash
        $completedOrder = $this->createOrder($this->courierA, OrderStatus::COMPLETED, 200.0);
        Payment::create([
            'order_id' => $completedOrder->id,
            'amount' => 200.0,
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::PAID,
        ]);

        // Handover: Courier A transfers 1 order and 150 cash to Courier B
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 40.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => true,
            'declared_cash' => 150.0,
            'reason' => CourierHandoverReason::VEHICLE_BREAKDOWN->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        $this->actingAs($this->courierB)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 150.0,
        ])->assertOk();

        // Check Courier A shift summary: cash should be 200 - 150 = 50
        $resA = $this->actingAs($this->courierA)->getJson('/api/courier/shift-summary');
        $resA->assertOk();
        $this->assertEquals(50.0, (float) $resA->json('couriers.0.cash_amount'));
        $this->assertEquals(150.0, (float) $resA->json('couriers.0.cash_transferred_out'));

        // Check Courier B shift summary: cash should be 0 + 150 = 150
        $resB = $this->actingAs($this->courierB)->getJson('/api/courier/shift-summary');
        $resB->assertOk();
        $this->assertEquals(150.0, (float) $resB->json('couriers.0.cash_amount'));
        $this->assertEquals(150.0, (float) $resB->json('couriers.0.cash_transferred_in'));
    }

    public function test_non_courier_cannot_accept_handover(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 40.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => false,
            'reason' => CourierHandoverReason::OTHER->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        // Manager tries to accept as courier
        $res = $this->actingAs($this->manager)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 0.0,
        ]);

        $res->assertStatus(403);
    }

    public function test_handover_generates_self_hosted_qr_svg(): void
    {
        $order = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 40.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order->id],
            'transfer_cash' => false,
            'reason' => CourierHandoverReason::OTHER->value,
        ]);

        $qrSvg = $initRes->json('handover.qr_svg');
        $this->assertNotEmpty($qrSvg);
        $this->assertStringContainsString('<svg', $qrSvg);
    }

    public function test_accept_safely_skips_cancelled_order(): void
    {
        $order1 = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 40.0);
        $order2 = $this->createOrder($this->courierA, OrderStatus::ASSIGNED, 50.0);

        $initRes = $this->actingAs($this->courierA)->postJson('/api/courier/handover/initiate', [
            'order_ids' => [$order1->id, $order2->id],
            'transfer_cash' => false,
            'reason' => CourierHandoverReason::OTHER->value,
        ]);
        $code = $initRes->json('handover.transfer_code');

        // Order 2 gets cancelled by client before Courier B arrives
        $order2->update(['status' => OrderStatus::CANCELLED]);

        // Courier B accepts
        $acceptRes = $this->actingAs($this->courierB)->postJson('/api/courier/handover/accept', [
            'code' => $code,
            'actual_cash' => 0.0,
        ]);

        $acceptRes->assertOk();

        // Order 1 reassigned to Courier B
        $order1->refresh();
        $this->assertEquals($this->courierB->id, $order1->courier_id);

        // Order 2 remains CANCELLED and was not resurrected
        $order2->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order2->status);
    }
}
