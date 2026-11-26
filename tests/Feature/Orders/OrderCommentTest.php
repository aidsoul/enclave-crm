<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCommentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.example.com',
            'token' => 'test_token',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner',
            'role' => UserRole::OWNER,
            'telegram_user_id' => 12345678,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'site_id' => $site->id,
            'status' => OrderStatus::CREATED,
            'client_name' => 'Test Client',
            'client_phone' => '+375 29 111-22-33',
            'total_price' => 100,
            'purchase_price' => 50,
        ]);
    }

    public function test_cannot_add_empty_comment(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/comments", [
                'body' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertSame(
            'Введите текст комментария.',
            $response->json('errors.body.0')
        );
    }

    public function test_cannot_add_comment_exceeding_max_length(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/comments", [
                'body' => str_repeat('a', 2001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertSame(
            'Комментарий не должен превышать 2000 символов.',
            $response->json('errors.body.0')
        );
    }

    public function test_can_add_valid_comment(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/comments", [
                'body' => 'Новый комментарий к заказу',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('comment.body', 'Новый комментарий к заказу');

        $this->assertDatabaseHas('order_comments', [
            'order_id' => $this->order->id,
            'body' => 'Новый комментарий к заказу',
        ]);
    }

    public function test_courier_cannot_view_order_comments(): void
    {
        $courier = User::create([
            'system_name' => 'Courier',
            'role' => UserRole::COURIER,
            'telegram_user_id' => 999999,
            'is_active' => true,
        ]);

        $this->order->delivery()->create([
            'type' => 'courier',
            'user_id' => $courier->id,
            'delivery_address' => 'Адрес',
        ]);

        $response = $this->actingAs($courier)
            ->getJson("/api/orders/{$this->order->id}/comments");

        $response->assertStatus(403);
    }

    public function test_courier_cannot_add_order_comment(): void
    {
        $courier = User::create([
            'system_name' => 'Courier',
            'role' => UserRole::COURIER,
            'telegram_user_id' => 888888,
            'is_active' => true,
        ]);

        $this->order->delivery()->create([
            'type' => 'courier',
            'user_id' => $courier->id,
            'delivery_address' => 'Адрес',
        ]);

        $response = $this->actingAs($courier)
            ->postJson("/api/orders/{$this->order->id}/comments", [
                'body' => 'Попытка курьера оставить комментарий',
            ]);

        $response->assertStatus(403);
    }
}
