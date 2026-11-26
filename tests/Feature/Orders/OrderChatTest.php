<?php

namespace Tests\Feature\Orders;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Events\OrderMessageDeleted;
use App\Events\OrderMessageSent;
use App\Events\OrderMessageUpdated;
use App\Jobs\NotifyOrderChatMessageJob;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use App\Services\SettingService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use Tests\TestCase;

class OrderChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected User $courier;

    protected User $otherCourier;

    protected Order $order;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->site = Site::create([
            'name' => 'Shop Site',
            'domain' => 'shop.example.com',
            'token' => 'site_token_chat',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'OwnerUser',
            'role' => UserRole::OWNER,
            'telegram_user_id' => 10001,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'ManagerUser',
            'role' => UserRole::MANAGER,
            'telegram_user_id' => 10002,
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'CourierDriver',
            'role' => UserRole::COURIER,
            'telegram_user_id' => 10003,
            'is_active' => true,
        ]);

        $this->otherCourier = User::create([
            'system_name' => 'OtherDriver',
            'role' => UserRole::COURIER,
            'telegram_user_id' => 10004,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'John Doe',
            'phone' => '+375 29 999-88-77',
            'address' => 'Minsk, Lenina 1',
        ]);

        $this->order = Order::create([
            'site_id' => $this->site->id,
            'customer_id' => $customer->id,
            'manager_id' => $this->manager->id,
            'courier_id' => $this->courier->id,
            'status' => OrderStatus::ASSIGNED,
            'total_price' => 200,
            'purchase_price' => 120,
        ]);

        Delivery::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'courier_id' => $this->courier->id,
            'delivery_address' => 'Minsk, Lenina 1',
        ]);
    }

    protected function createFakeImage(string $name = 'test.jpg'): UploadedFile
    {
        $jpeg1x1 = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $content = $jpeg1x1.str_repeat('A', 1024);

        return UploadedFile::fake()->createWithContent($name, $content, 'image/jpeg');
    }

    public function test_courier_can_view_and_send_messages_in_courier_channel(): void
    {
        Event::fake([OrderMessageSent::class]);
        Queue::fake([NotifyOrderChatMessageJob::class]);

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'Подъехал к дому, жду у подъезда 2.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message', 'Подъехал к дому, жду у подъезда 2.')
            ->assertJsonPath('data.channel', 'courier')
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('order_messages', [
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Подъехал к дому, жду у подъезда 2.',
        ]);

        $this->order->refresh();
        $this->assertTrue($this->order->has_unread_driver_message);
        $this->assertNotNull($this->order->last_message_at);

        Event::assertDispatched(OrderMessageSent::class);
        Queue::assertPushed(NotifyOrderChatMessageJob::class);

        $listResponse = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier");

        $listResponse->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Подъехал к дому, жду у подъезда 2.');
    }

    public function test_courier_cannot_access_or_post_to_internal_channel(): void
    {
        $postResponse = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'internal',
                'message' => 'Secret message',
            ]);

        $postResponse->assertStatus(403);

        $getResponse = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=internal");

        $getResponse->assertStatus(403);
    }

    public function test_courier_cannot_access_unassigned_order_chat(): void
    {
        $response = $this->actingAs($this->otherCourier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier");

        $response->assertStatus(403);

        $postResponse = $this->actingAs($this->otherCourier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'Test unassigned',
            ]);

        $postResponse->assertStatus(403);
    }

    public function test_manager_can_access_both_internal_and_courier_channels(): void
    {
        Event::fake([OrderMessageSent::class]);
        Queue::fake([NotifyOrderChatMessageJob::class]);

        $internalPost = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'internal',
                'message' => 'Внутренний комментарий: клиент просил чек.',
            ]);
        $internalPost->assertStatus(201);

        $courierPost = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'Код от домофона 45К.',
            ]);
        $courierPost->assertStatus(201);

        $internalGet = $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=internal");
        $internalGet->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Внутренний комментарий: клиент просил чек.');

        $courierGet = $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier");
        $courierGet->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Код от домофона 45К.');
    }

    public function test_manager_private_data_is_masked_in_courier_channel(): void
    {
        OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Позвоните клиенту за 10 минут.',
        ]);

        $response = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier");

        $response->assertOk()
            ->assertJsonPath('messages.0.sender.name', 'Менеджер')
            ->assertJsonPath('messages.0.sender.role', 'manager');

        $content = $response->getContent();
        $this->assertStringNotContainsString('ManagerUser', $content);
        $this->assertStringNotContainsString('10002', $content);
    }

    public function test_cursor_pagination_with_before_id_and_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            OrderMessage::create([
                'order_id' => $this->order->id,
                'user_id' => $this->courier->id,
                'channel' => 'courier',
                'message' => "Message #{$i}",
            ]);
        }

        $response = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier&limit=2");

        $response->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('has_more', true);

        $oldestId = $response->json('oldest_id');

        $nextResponse = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier&limit=2&before_id={$oldestId}");

        $nextResponse->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('has_more', true);
    }

    public function test_message_send_clean_text(): void
    {
        Event::fake([OrderMessageSent::class]);
        Queue::fake([NotifyOrderChatMessageJob::class]);

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'Не пускают через шлагбаум',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message', 'Не пускают через шлагбаум');

        $created = OrderMessage::where('order_id', $this->order->id)->latest('id')->first();
        $this->assertNull($created->attachment_path);
    }

    public function test_emergency_wipe_cleans_order_messages_and_purges_attachments(): void
    {
        $file = $this->createFakeImage('proof.jpg');
        $path = $file->storeAs("chat/{$this->order->id}", 'proof.jpg', 'public');

        OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Secret message to wipe',
            'attachment_path' => $path,
        ]);

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('order_messages', ['order_id' => $this->order->id]);

        $service = app(DataRetentionService::class);
        $service->wipeTables(['order_messages'], $this->owner->id);

        $this->assertDatabaseMissing('order_messages', ['order_id' => $this->order->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_order_cleans_message_attachment(): void
    {
        $file = $this->createFakeImage('note.jpg');
        $path = $file->storeAs("chat/{$this->order->id}", 'note.jpg', 'public');

        OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Will be deleted with order',
            'attachment_path' => $path,
        ]);

        Storage::disk('public')->assertExists($path);

        $this->order->delete();

        $this->assertDatabaseMissing('order_messages', ['order_id' => $this->order->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_data_retention_purges_expired_order_chat_attachments_and_messages(): void
    {
        $oldDate = now()->subDays(45);
        $oldOrder = Order::create([
            'site_id' => $this->site->id,
            'total_price' => 100,
            'purchase_price' => 50,
            'status' => OrderStatus::COMPLETED,
        ]);
        Order::where('id', $oldOrder->id)->update([
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        $file = $this->createFakeImage('archive.jpg');
        $path = $file->storeAs("chat/{$oldOrder->id}", 'archive.jpg', 'public');

        OrderMessage::create([
            'order_id' => $oldOrder->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Old chat message',
            'attachment_path' => $path,
            'created_at' => $oldDate,
        ]);

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('order_messages', ['order_id' => $oldOrder->id]);

        $service = app(DataRetentionService::class);
        $service->run(30);

        $this->assertDatabaseMissing('orders', ['id' => $oldOrder->id]);
        $this->assertDatabaseMissing('order_messages', ['order_id' => $oldOrder->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_reverb_channel_authorization(): void
    {
        $response1 = $this->actingAs($this->courier)->post('/broadcasting/auth', [
            'channel_name' => "private-order.{$this->order->id}.courier",
            'socket_id' => '1234.5678',
        ]);
        $response1->assertOk();

        $response2 = $this->actingAs($this->otherCourier)->post('/broadcasting/auth', [
            'channel_name' => "private-order.{$this->order->id}.courier",
            'socket_id' => '1234.5678',
        ]);
        $response2->assertForbidden();

        $response3 = $this->actingAs($this->courier)->post('/broadcasting/auth', [
            'channel_name' => "private-order.{$this->order->id}.internal",
            'socket_id' => '1234.5678',
        ]);
        $response3->assertForbidden();

        $response4 = $this->actingAs($this->manager)->post('/broadcasting/auth', [
            'channel_name' => "private-order.{$this->order->id}.internal",
            'socket_id' => '1234.5678',
        ]);
        $response4->assertOk();
    }

    public function test_full_screen_chat_page_access(): void
    {
        $response1 = $this->actingAs($this->courier)->get("/tma/orders/{$this->order->id}/chat");
        $response1->assertOk()->assertViewIs('tma.orders.chat');

        $response2 = $this->actingAs($this->manager)->get("/tma/orders/{$this->order->id}/chat");
        $response2->assertOk()->assertViewIs('tma.orders.chat');

        $response3 = $this->actingAs($this->otherCourier)->get("/tma/orders/{$this->order->id}/chat");
        $response3->assertForbidden();
    }

    public function test_unread_count_increments_and_resets(): void
    {
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'First message',
            ])->assertStatus(201);

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => 'Second message',
            ])->assertStatus(201);

        $this->order->refresh();
        $this->assertSame(2, $this->order->unread_driver_messages_count);

        $resource = $this->actingAs($this->manager)->getJson("/api/orders/{$this->order->id}");
        $resource->assertOk()
            ->assertJsonPath('order.unread_driver_messages_count', 2)
            ->assertJsonPath('order.unread_chat_count', 2);

        $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier")
            ->assertOk();

        $this->order->refresh();
        $this->assertSame(0, $this->order->unread_driver_messages_count);
        $this->assertFalse($this->order->has_unread_driver_message);
    }

    public function test_owner_can_edit_any_message_and_audited(): void
    {
        Event::fake([OrderMessageUpdated::class]);

        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Original courier text',
        ]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Edited by owner text',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Edited by owner text')
            ->assertJsonPath('data.is_edited', true);

        $message->refresh();
        $this->assertSame('Edited by owner text', $message->message);
        $this->assertNotNull($message->edited_at);

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'order_id' => $this->order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'chat_message',
            'old_value' => 'Original courier text',
            'new_value' => 'Edited by owner text',
        ]);

        Event::assertDispatched(OrderMessageUpdated::class, function ($event) use ($message) {
            return $event->message->id === $message->id;
        });

        // Submitting unchanged message should not trigger a new edit or audit log
        $actionLogsBefore = ActionLog::where('order_id', $this->order->id)->count();

        $unchangedRes = $this->actingAs($this->owner)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Edited by owner text',
            ]);
        $unchangedRes->assertOk();

        $actionLogsAfter = ActionLog::where('order_id', $this->order->id)->count();
        $this->assertSame($actionLogsBefore, $actionLogsAfter);
    }

    public function test_message_validation_limits_empty_and_tag_stripping(): void
    {
        // Whitespace only fails validation when creating without attachment
        $emptyRes = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => '    ',
            ]);
        $emptyRes->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // Message over 2000 characters fails validation
        $tooLongRes = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => str_repeat('a', 2001),
            ]);
        $tooLongRes->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        // HTML / script tags are stripped safely on create
        $tagRes = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/messages", [
                'channel' => 'courier',
                'message' => '<b>Hello</b> <script>alert("xss")</script>world!',
            ]);
        $tagRes->assertStatus(201)
            ->assertJsonPath('data.message', 'Hello alert("xss")world!');

        $createdId = $tagRes->json('data.id');
        $msg = OrderMessage::find($createdId);
        $this->assertSame('Hello alert("xss")world!', $msg->message);

        // HTML tags stripped on edit, and empty after strip returns 422
        $editTagsRes = $this->actingAs($this->owner)
            ->putJson("/api/orders/{$this->order->id}/messages/{$createdId}", [
                'message' => '<i>Clean text</i>',
            ]);
        $editTagsRes->assertOk()
            ->assertJsonPath('data.message', 'Clean text');

        $editOnlyTagsRes = $this->actingAs($this->owner)
            ->putJson("/api/orders/{$this->order->id}/messages/{$createdId}", [
                'message' => '<div>   </div>',
            ]);
        $editOnlyTagsRes->assertStatus(422);
    }

    public function test_owner_can_delete_any_message_and_audited(): void
    {
        Event::fake([OrderMessageDeleted::class]);

        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'internal',
            'message' => 'Manager internal text to delete',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}/messages/{$message->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('order_messages', [
            'id' => $message->id,
        ]);

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->owner->id,
            'order_id' => $this->order->id,
            'action_type' => ActionType::DELETE->value,
            'field_name' => 'chat_message',
            'old_value' => 'Manager internal text to delete',
            'new_value' => null,
        ]);

        Event::assertDispatched(OrderMessageDeleted::class, function ($event) use ($message) {
            return $event->messageId === $message->id;
        });
    }

    public function test_manager_can_edit_own_message_within_10_minutes(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Manager initial text',
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Manager edited text',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Manager edited text')
            ->assertJsonPath('data.is_edited', false);

        // Owner sees is_edited = true, while manager and courier see is_edited = false
        $this->actingAs($this->owner)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier")
            ->assertOk()
            ->assertJsonPath('messages.0.is_edited', true);

        $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}/messages?channel=courier")
            ->assertOk()
            ->assertJsonPath('messages.0.is_edited', false);

        $this->assertDatabaseHas('action_logs', [
            'actor_id' => $this->manager->id,
            'order_id' => $this->order->id,
            'action_type' => ActionType::UPDATE->value,
            'field_name' => 'chat_message',
            'old_value' => 'Manager initial text',
            'new_value' => 'Manager edited text',
        ]);
    }

    public function test_manager_cannot_edit_own_message_after_10_minutes(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Old message text',
        ]);

        OrderMessage::where('id', $message->id)->update([
            'created_at' => now()->subMinutes(11),
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Trying to edit expired message',
            ]);

        $response->assertStatus(403);

        $message->refresh();
        $this->assertSame('Old message text', $message->message);
    }

    public function test_manager_cannot_edit_other_user_message(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Courier text',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Manager hijacked courier message',
            ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_delete_messages(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Manager message',
        ]);

        $response = $this->actingAs($this->manager)
            ->deleteJson("/api/orders/{$this->order->id}/messages/{$message->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('order_messages', [
            'id' => $message->id,
        ]);
    }

    public function test_courier_cannot_edit_or_delete_messages(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Courier message',
        ]);

        $this->actingAs($this->courier)
            ->putJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
                'message' => 'Courier edit attempt',
            ])->assertStatus(403);

        $this->actingAs($this->courier)
            ->deleteJson("/api/orders/{$this->order->id}/messages/{$message->id}")
            ->assertStatus(403);
    }

    public function test_chat_unread_summary_for_manager_and_courier(): void
    {
        $this->order->update([
            'unread_driver_messages_count' => 3,
            'unread_manager_messages_count' => 2,
            'last_message_at' => now(),
        ]);

        $managerResponse = $this->actingAs($this->manager)
            ->getJson('/api/orders/chat-unread-summary');

        $managerResponse->assertOk()
            ->assertJsonPath('unread_count', 3)
            ->assertJsonPath('latest_order_id', $this->order->id);

        $courierResponse = $this->actingAs($this->courier)
            ->getJson('/api/orders/chat-unread-summary');

        $courierResponse->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('latest_order_id', $this->order->id);
    }

    public function test_floating_chat_icon_setting(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/api/settings');
        $response->assertOk()
            ->assertJsonPath('settings.floating_chat_icon.value', true);

        $updateResponse = $this->actingAs($this->manager)->postJson('/api/settings/floating_chat_icon', [
            'value' => false,
            'scope' => 'user',
        ]);

        $updateResponse->assertOk();

        $afterResponse = $this->actingAs($this->manager)->getJson('/api/settings');
        $afterResponse->assertOk()
            ->assertJsonPath('settings.floating_chat_icon.value', false);
    }

    public function test_cannot_send_messages_when_order_is_completed(): void
    {
        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $ownerRes = $this->actingAs($this->owner)->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'New message from owner',
            'channel' => 'courier',
        ]);
        $ownerRes->assertStatus(422)
            ->assertJson(['error' => 'Чат закрыт для новых сообщений, так как заказ завершён']);

        $managerRes = $this->actingAs($this->manager)->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'New message from manager',
            'channel' => 'courier',
        ]);
        $managerRes->assertStatus(422)
            ->assertJson(['error' => 'Чат закрыт для новых сообщений, так как заказ завершён']);

        $courierRes = $this->actingAs($this->courier)->postJson("/api/orders/{$this->order->id}/messages", [
            'message' => 'New message from courier',
            'channel' => 'courier',
        ]);
        $courierRes->assertStatus(422)
            ->assertJson(['error' => 'Чат закрыт для новых сообщений, так как заказ завершён']);
    }

    public function test_owner_can_edit_and_delete_when_order_is_completed(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Original text',
        ]);

        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $editRes = $this->actingAs($this->owner)->patchJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
            'message' => 'Owner edited text',
        ]);
        $editRes->assertOk()
            ->assertJsonPath('data.message', 'Owner edited text');

        $deleteRes = $this->actingAs($this->owner)->deleteJson("/api/orders/{$this->order->id}/messages/{$message->id}");
        $deleteRes->assertOk();
        $this->assertDatabaseMissing('order_messages', ['id' => $message->id]);
    }

    public function test_manager_cannot_edit_messages_when_order_is_completed(): void
    {
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'Manager original text',
        ]);

        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $editRes = $this->actingAs($this->manager)->patchJson("/api/orders/{$this->order->id}/messages/{$message->id}", [
            'message' => 'Manager trying to edit',
        ]);
        $editRes->assertStatus(403);
    }

    public function test_notify_chat_messages_setting_and_telegram_notification(): void
    {
        $settingsRes = $this->actingAs($this->courier)->getJson('/api/settings');
        $settingsRes->assertOk()
            ->assertJsonPath('settings.notify_chat_messages.value', true);

        // Other manager not assigned to this order
        $otherManager = User::create([
            'system_name' => 'OtherManager',
            'role' => UserRole::MANAGER,
            'telegram_user_id' => 99001,
            'is_active' => true,
        ]);

        $mockBot = \Mockery::mock(Nutgram::class);
        // First message: Manager sends to courier. Courier gets notified.
        // Owner has NOT sent any messages yet, so Owner is NOT notified. OtherManager is NOT notified.
        $mockBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($text, ...$args) {
                $chatId = $args['chat_id'] ?? ($args[0] ?? null);

                return (int) $chatId === (int) $this->courier->telegram_user_id
                    && str_contains($text, "заказу #{$this->order->id}")
                    && ! str_contains($text, 'SecretSensitiveText');
            })
            ->andReturn(\Mockery::mock(Message::class));

        $settingService = app(SettingService::class);
        $telegramService = new TelegramService($mockBot, $settingService);

        // Manager sends a message to courier
        $message = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->manager->id,
            'channel' => 'courier',
            'message' => 'SecretSensitiveText',
        ]);

        $telegramService->notifyAboutChatMessage($message);

        // Now courier disables notify_chat_messages
        $settingService->set(
            SettingKey::NOTIFY_CHAT_MESSAGES,
            false,
            'user',
            (string) $this->courier->id
        );

        // Second notification: courier skipped because notify_chat_messages is false
        $telegramService->notifyAboutChatMessage($message);

        // Restore courier's notify_chat_messages to true, but disable parent notify_chat_orders
        $settingService->set(
            SettingKey::NOTIFY_CHAT_MESSAGES,
            true,
            'user',
            (string) $this->courier->id
        );
        $settingService->set(
            SettingKey::NOTIFY_CHAT_ORDERS,
            false,
            'user',
            (string) $this->courier->id
        );

        // Third notification: courier skipped because parent notify_chat_orders is false
        $telegramService->notifyAboutChatMessage($message);

        // Restore parent setting
        $settingService->set(
            SettingKey::NOTIFY_CHAT_ORDERS,
            true,
            'user',
            (string) $this->courier->id
        );

        // Now owner sends a message in this order
        OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->owner->id,
            'channel' => 'courier',
            'message' => 'Owner joins the conversation',
        ]);

        // When courier replies, both assigned manager AND the participating owner must receive notifications!
        $mockBot2 = \Mockery::mock(Nutgram::class);
        $notifiedChatIds = [];
        $mockBot2->shouldReceive('sendMessage')
            ->andReturnUsing(function (...$args) use (&$notifiedChatIds) {
                $chatId = $args['chat_id'] ?? ($args[1] ?? null);
                $notifiedChatIds[] = (int) $chatId;

                return \Mockery::mock(Message::class);
            });

        $telegramService2 = new TelegramService($mockBot2, $settingService);

        $courierReply = OrderMessage::create([
            'order_id' => $this->order->id,
            'user_id' => $this->courier->id,
            'channel' => 'courier',
            'message' => 'Courier reply',
        ]);

        $telegramService2->notifyAboutChatMessage($courierReply);

        $this->assertContains((int) $this->manager->telegram_user_id, $notifiedChatIds);
        $this->assertContains((int) $this->owner->telegram_user_id, $notifiedChatIds);
        $this->assertNotContains((int) $otherManager->telegram_user_id, $notifiedChatIds);

        // If participating owner turns off notify_chat_messages, owner is skipped
        $settingService->set(
            SettingKey::NOTIFY_CHAT_MESSAGES,
            false,
            'user',
            (string) $this->owner->id
        );

        $mockBot3 = \Mockery::mock(Nutgram::class);
        $notifiedChatIds3 = [];
        $mockBot3->shouldReceive('sendMessage')
            ->andReturnUsing(function (...$args) use (&$notifiedChatIds3) {
                $chatId = $args['chat_id'] ?? ($args[1] ?? null);
                $notifiedChatIds3[] = (int) $chatId;

                return \Mockery::mock(Message::class);
            });

        $telegramService3 = new TelegramService($mockBot3, $settingService);
        $telegramService3->notifyAboutChatMessage($courierReply);

        $this->assertContains((int) $this->manager->telegram_user_id, $notifiedChatIds3);
        $this->assertNotContains((int) $this->owner->telegram_user_id, $notifiedChatIds3);

        // If participating owner turns on notify_chat_messages but turns off parent notify_chat_orders, owner is still skipped
        $settingService->set(
            SettingKey::NOTIFY_CHAT_MESSAGES,
            true,
            'user',
            (string) $this->owner->id
        );
        $settingService->set(
            SettingKey::NOTIFY_CHAT_ORDERS,
            false,
            'user',
            (string) $this->owner->id
        );

        $mockBot4 = \Mockery::mock(Nutgram::class);
        $notifiedChatIds4 = [];
        $mockBot4->shouldReceive('sendMessage')
            ->andReturnUsing(function (...$args) use (&$notifiedChatIds4) {
                $chatId = $args['chat_id'] ?? ($args[1] ?? null);
                $notifiedChatIds4[] = (int) $chatId;

                return \Mockery::mock(Message::class);
            });

        $telegramService4 = new TelegramService($mockBot4, $settingService);
        $telegramService4->notifyAboutChatMessage($courierReply);

        $this->assertContains((int) $this->manager->telegram_user_id, $notifiedChatIds4);
        $this->assertNotContains((int) $this->owner->telegram_user_id, $notifiedChatIds4);
    }

    public function test_active_and_search_chat_orders(): void
    {
        // Unassigned order belonging to another courier
        $otherCourier = User::create([
            'system_name' => 'OtherDriver',
            'role' => UserRole::COURIER,
            'telegram_user_id' => 99002,
            'is_active' => true,
        ]);

        $otherOrder = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'courier_id' => $otherCourier->id,
            'status' => OrderStatus::ASSIGNED,
            'total_price' => 500,
            'purchase_price' => 300,
            'last_message_at' => now(),
        ]);

        // Manager sees both orders in active list
        $managerActive = $this->actingAs($this->manager)->getJson('/api/orders/chat-active-orders');
        $managerActive->assertOk()
            ->assertJsonPath('orders.0.id', $otherOrder->id);

        // Courier ONLY sees their assigned order, NOT the other order
        $courierActive = $this->actingAs($this->courier)->getJson('/api/orders/chat-active-orders');
        $courierActive->assertOk();
        $courierOrderIds = collect($courierActive->json('orders'))->pluck('id');
        $this->assertContains($this->order->id, $courierOrderIds);
        $this->assertNotContains($otherOrder->id, $courierOrderIds);

        // Search by order ID
        // Courier searches for their order ID -> finds it
        $courierSearchOwn = $this->actingAs($this->courier)->getJson('/api/orders/chat-search-orders?q='.$this->order->id);
        $courierSearchOwn->assertOk()
            ->assertJsonPath('orders.0.id', $this->order->id);

        // Courier searches for other courier's order ID -> returns empty (security restricted)
        $courierSearchOther = $this->actingAs($this->courier)->getJson('/api/orders/chat-search-orders?q='.$otherOrder->id);
        $courierSearchOther->assertOk()
            ->assertJsonCount(0, 'orders');

        // Manager searches with '#' prefix -> succeeds
        $managerSearchHash = $this->actingAs($this->manager)->getJson('/api/orders/chat-search-orders?q='.urlencode('#'.$otherOrder->id));
        $managerSearchHash->assertOk()
            ->assertJsonPath('orders.0.id', $otherOrder->id);
    }

    public function test_manager_cannot_access_or_search_unassigned_order_chat(): void
    {
        $otherManager = User::create([
            'system_name' => 'OtherManager',
            'role' => UserRole::MANAGER,
            'telegram_user_id' => 99003,
            'is_active' => true,
        ]);

        $unassignedOrder = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $otherManager->id,
            'courier_id' => $this->courier->id,
            'status' => OrderStatus::ASSIGNED,
            'total_price' => 300,
            'purchase_price' => 150,
        ]);

        // Manager cannot view TMA chat of other manager's order
        $this->actingAs($this->manager)
            ->get("/tma/orders/{$unassignedOrder->id}/chat")
            ->assertForbidden();

        // Manager cannot view messages API
        $this->actingAs($this->manager)
            ->getJson("/api/orders/{$unassignedOrder->id}/messages")
            ->assertForbidden();

        // Manager cannot post messages
        $this->actingAs($this->manager)
            ->postJson("/api/orders/{$unassignedOrder->id}/messages", [
                'channel' => 'internal',
                'message' => 'Should fail',
            ])
            ->assertForbidden();

        // Manager cannot search other manager's order in chat search
        $searchRes = $this->actingAs($this->manager)
            ->getJson('/api/orders/chat-search-orders?q='.$unassignedOrder->id);
        $searchRes->assertOk()->assertJsonCount(0, 'orders');

        // Manager does not see other manager's order in active chat orders
        $activeRes = $this->actingAs($this->manager)
            ->getJson('/api/orders/chat-active-orders');
        $activeRes->assertOk();
        $managerOrderIds = collect($activeRes->json('orders'))->pluck('id');
        $this->assertNotContains($unassignedOrder->id, $managerOrderIds);

        // Owner CAN search and access other manager's order
        $ownerSearchRes = $this->actingAs($this->owner)
            ->getJson('/api/orders/chat-search-orders?q='.$unassignedOrder->id);
        $ownerSearchRes->assertOk()->assertJsonPath('orders.0.id', $unassignedOrder->id);

        $this->actingAs($this->owner)
            ->get("/tma/orders/{$unassignedOrder->id}/chat")
            ->assertOk();

        // Broadcast auth for other manager is forbidden
        $this->actingAs($this->manager)->post('/broadcasting/auth', [
            'channel_name' => "private-order.{$unassignedOrder->id}.internal",
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }
}
