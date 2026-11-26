<?php

namespace Tests\Feature\Customers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Ivan',
            'telegram_user_id' => 11001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Olga',
            'telegram_user_id' => 11002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Main Site',
            'domain' => 'site1.example.com',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_lists_customers_with_ltv_and_search(): void
    {
        $c1 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Александр Иванов',
            'phone' => '+375 29 111-22-33',
            'email' => 'alex@example.com',
            'address' => 'Минск, ул. Ленина 1',
        ]);

        Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $c1->id,
            'total_price' => 150.00,
            'purchase_price' => 90.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $c1->id,
            'total_price' => 50.00,
            'purchase_price' => 30.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/customers?search=Александр');

        $response->assertOk()
            ->assertJsonPath('customers.0.name', 'Александр Иванов')
            ->assertJsonPath('customers.0.ltv', 200)
            ->assertJsonPath('customers.0.orders_count', 2);
    }

    public function test_shows_customer_card_with_order_history(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Дмитрий Петров',
            'phone' => '+375 29 555-66-77',
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $customer->id,
            'total_price' => 320.00,
            'purchase_price' => 200.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $order->items()->create([
            'name' => 'Смартфон X',
            'qty' => 1,
            'price' => 320.00,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'Дмитрий Петров')
            ->assertJsonPath('customer.orders.0.id', $order->id)
            ->assertJsonPath('customer.orders.0.items.0.name', 'Смартфон X');
    }

    public function test_updates_customer_information(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Сергей',
            'phone' => '+375 29 000-00-00',
        ]);

        $response = $this->actingAs($this->manager)
            ->patchJson("/api/customers/{$customer->id}", [
                'name' => 'Сергей Сидоров',
                'address' => 'Гродно, ул. Советская 10',
            ]);

        $response->assertOk()
            ->assertJsonPath('customer.name', 'Сергей Сидоров')
            ->assertJsonPath('customer.address', 'Гродно, ул. Советская 10');
    }

    public function test_sorts_customers_by_orders_and_ltv(): void
    {
        $c1 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Клиент с 1 заказом',
            'phone' => '+375 29 111-11-11',
        ]);
        Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $c1->id,
            'total_price' => 50.00,
            'purchase_price' => 30.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $c2 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Клиент с 2 заказами',
            'phone' => '+375 29 222-22-22',
        ]);
        Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $c2->id,
            'total_price' => 100.00,
            'purchase_price' => 60.00,
            'status' => OrderStatus::COMPLETED,
        ]);
        Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $c2->id,
            'total_price' => 200.00,
            'purchase_price' => 120.00,
            'status' => OrderStatus::COMPLETED,
        ]);

        $resOrders = $this->actingAs($this->manager)
            ->getJson('/api/customers?sort=orders_desc');
        $resOrders->assertOk()
            ->assertJsonPath('customers.0.id', $c2->id)
            ->assertJsonPath('customers.1.id', $c1->id);

        $resLtv = $this->actingAs($this->manager)
            ->getJson('/api/customers?sort=ltv_desc');
        $resLtv->assertOk()
            ->assertJsonPath('customers.0.id', $c2->id);
    }

    public function test_shows_only_latest_5_orders_in_customer_card(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Много Заказов',
            'phone' => '+375 29 999-00-11',
        ]);

        for ($i = 1; $i <= 7; $i++) {
            Order::create([
                'site_id' => $this->site->id,
                'customer_id' => $customer->id,
                'total_price' => 10.00 * $i,
                'purchase_price' => 5.00 * $i,
                'status' => OrderStatus::COMPLETED,
            ]);
        }

        $res = $this->actingAs($this->manager)->getJson("/api/customers/{$customer->id}");
        $res->assertOk()
            ->assertJsonCount(5, 'customer.orders')
            ->assertJsonPath('customer.orders_count', 7);

        // Filter orders by customer_id on orders API
        $ordersRes = $this->actingAs($this->manager)->getJson("/api/orders?customer_id={$customer->id}");
        $ordersRes->assertOk();
        $this->assertCount(7, $ordersRes->json('orders'));
    }

    public function test_updates_customer_blacklist_and_tags(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Неблагонадежный клиент',
            'phone' => '+375 29 777-88-99',
        ]);

        $response = $this->actingAs($this->manager)
            ->patchJson("/api/customers/{$customer->id}", [
                'is_blacklisted' => true,
                'blacklist_reason' => 'Невыкуп и частые отмены',
                'tags' => ['невыкуп', 'частые отмены', 'спам'],
            ]);

        $response->assertOk()
            ->assertJsonPath('customer.is_blacklisted', true)
            ->assertJsonPath('customer.blacklist_reason', 'Невыкуп и частые отмены')
            ->assertJsonPath('customer.tags', ['невыкуп', 'частые отмены', 'спам']);

        $customer->refresh();
        $this->assertTrue($customer->isBlacklisted());
        $this->assertTrue($customer->hasTag('невыкуп'));
        $this->assertEquals('Невыкуп и частые отмены', $customer->blacklist_reason);
    }

    public function test_filters_customers_by_blacklist_and_tag(): void
    {
        $c1 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Хороший клиент',
            'phone' => '+375 29 111-00-01',
            'is_blacklisted' => false,
            'tags' => ['vip'],
        ]);

        $c2 = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Плохой клиент',
            'phone' => '+375 29 111-00-02',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Спам',
            'tags' => ['спам', 'невыкуп'],
        ]);

        $resBlacklist = $this->actingAs($this->manager)
            ->getJson('/api/customers?is_blacklisted=1');
        $resBlacklist->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.id', $c2->id);

        $resTag = $this->actingAs($this->manager)
            ->getJson('/api/customers?tag=спам');
        $resTag->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.id', $c2->id);

        $resTagVip = $this->actingAs($this->manager)
            ->getJson('/api/customers?tag=vip');
        $resTagVip->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.id', $c1->id);
    }

    public function test_looks_up_customer_by_phone(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Иван Телефонный',
            'phone' => '+7 (999) 123-45-67',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Невыкуп',
            'tags' => ['невыкуп'],
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/customers/lookup?phone=+79991234567');

        $response->assertOk()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.name', 'Иван Телефонный')
            ->assertJsonPath('customer.is_blacklisted', true)
            ->assertJsonPath('customer.blacklist_reason', 'Невыкуп')
            ->assertJsonPath('customer.tags', ['невыкуп']);
    }

    public function test_order_resource_includes_customer_blacklist_and_tags(): void
    {
        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Опасный покупатель',
            'phone' => '+375 29 444-55-66',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Частые отмены',
            'tags' => ['частые отмены'],
        ]);

        $order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $customer->id,
            'total_price' => 100.00,
            'purchase_price' => 50.00,
            'status' => OrderStatus::CREATED,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('order.customer.is_blacklisted', true)
            ->assertJsonPath('order.customer.blacklist_reason', 'Частые отмены')
            ->assertJsonPath('order.customer.tags', ['частые отмены']);
    }

    public function test_customers_endpoint_respects_limit_parameter(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            Customer::create([
                'site_id' => $this->site->id,
                'name' => "Клиент {$i}",
                'phone' => "+375 29 111-00-0{$i}",
            ]);
        }

        $resLimit = $this->actingAs($this->manager)
            ->getJson('/api/customers?limit=5');

        $resLimit->assertOk()
            ->assertJsonCount(5, 'customers')
            ->assertJsonPath('pagination.per_page', 5);

        $resPerPage = $this->actingAs($this->manager)
            ->getJson('/api/customers?per_page=5');

        $resPerPage->assertOk()
            ->assertJsonCount(5, 'customers')
            ->assertJsonPath('pagination.per_page', 5);
    }

    public function test_customers_search_by_formatted_phone_digits(): void
    {
        Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Михаил Тест',
            'phone' => '375299998877',
        ]);

        $res = $this->actingAs($this->manager)
            ->getJson('/api/customers?search=+375 (29) 999-88-77&limit=5');

        $res->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Михаил Тест');
    }
}
