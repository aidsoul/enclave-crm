<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\PanicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliverySignatureTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected User $courier;

    protected User $otherCourier;

    protected User $manager;

    protected User $owner;

    protected Order $order;

    protected Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->site = Site::create([
            'name' => 'Signature Test Site',
            'domain' => 'signature.local',
            'token' => 'sig_token',
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Max',
            'telegram_user_id' => 30001,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->otherCourier = User::create([
            'system_name' => 'Courier Dan',
            'telegram_user_id' => 30002,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 30003,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Admin Owner',
            'telegram_user_id' => 30004,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'site_id' => $this->site->id,
            'courier_id' => $this->courier->id,
            'total_price' => 12500,
            'purchase_price' => 8000,
            'status' => OrderStatus::AT_WAREHOUSE,
            'is_valuable' => false,
        ]);

        $this->delivery = Delivery::create([
            'order_id' => $this->order->id,
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_address' => 'ул. Ленина, д. 10, кв. 5',
        ]);
    }

    protected function createFakePng(string $name = 'signature.png', int $kilobytes = 10): UploadedFile
    {
        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $content = $png1x1.str_repeat('A', max(0, ($kilobytes * 1024) - strlen($png1x1)));

        return UploadedFile::fake()->createWithContent($name, $content, 'image/png');
    }

    public function test_assigned_courier_can_upload_delivery_signature(): void
    {
        $file = $this->createFakePng('signature.png');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Подпись клиента сохранена')
            ->assertJsonStructure([
                'signature_url',
                'signature_at',
                'order' => [
                    'id',
                    'delivery' => [
                        '*' => ['signature_path', 'signature_url', 'signature_at', 'has_signature'],
                    ],
                ],
            ]);

        $this->delivery->refresh();
        $this->assertNotNull($this->delivery->signature_path);
        $this->assertNotNull($this->delivery->signature_at);
        $this->assertTrue($this->delivery->hasSignature());
        Storage::disk('public')->assertExists($this->delivery->signature_path);
    }

    public function test_unassigned_courier_cannot_upload_signature(): void
    {
        $file = $this->createFakePng('signature.png');

        $response = $this->actingAs($this->otherCourier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ]);

        $response->assertForbidden();
        $this->assertNull($this->delivery->fresh()->signature_path);
    }

    public function test_upload_validates_image_mimes_and_size(): void
    {
        $badFile = UploadedFile::fake()->create('signature.txt', 10, 'text/plain');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $badFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signature']);

        $oversizedFile = $this->createFakePng('big_sig.png', 2048);

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $oversizedFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signature']);
    }

    public function test_uploading_new_signature_replaces_old_file_on_disk(): void
    {
        $file1 = $this->createFakePng('sig1.png');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file1,
            ])
            ->assertOk();

        $oldPath = $this->delivery->fresh()->signature_path;
        Storage::disk('public')->assertExists($oldPath);

        $file2 = $this->createFakePng('sig2.png');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file2,
            ])
            ->assertOk();

        $newPath = $this->delivery->fresh()->signature_path;
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_assigned_courier_cannot_delete_signature(): void
    {
        $file = $this->createFakePng('signature.png');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();

        $path = $this->delivery->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($this->courier)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-signature");

        $response->assertForbidden();

        $this->delivery->refresh();
        $this->assertNotNull($this->delivery->signature_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_manager_cannot_upload_or_delete_signature_but_can_view(): void
    {
        $file = $this->createFakePng('signature.png');

        $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertForbidden();

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();

        $this->actingAs($this->manager)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-signature")
            ->assertForbidden();

        $showResponse = $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}");

        $showResponse->assertOk()
            ->assertJsonPath('order.delivery.0.has_signature', true);
    }

    public function test_owner_can_upload_and_delete_signature(): void
    {
        $file = $this->createFakePng('owner_sig.png');

        $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();

        $this->assertTrue($this->delivery->fresh()->hasSignature());

        $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-signature")
            ->assertOk();

        $this->assertFalse($this->delivery->fresh()->hasSignature());
    }

    public function test_cannot_upload_or_delete_signature_for_cancelled_order(): void
    {
        $this->order->update(['status' => OrderStatus::CANCELLED]);

        $file = $this->createFakePng('sig.png');

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-signature")
            ->assertForbidden();
    }

    public function test_cannot_upload_signature_for_completed_order_unless_owner(): void
    {
        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $file = $this->createFakePng('sig.png');

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();
    }

    public function test_valuable_order_requires_signature_before_completion(): void
    {
        $this->order->update(['is_valuable' => true]);
        $this->assertTrue($this->order->requiresClientSignature());

        // Attempt completion without signature
        $response = $this->actingAs($this->courier)
            ->patchJson("/api/orders/{$this->order->id}/complete");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signature'])
            ->assertJsonPath('message', 'Для ценного отправления требуется подпись клиента');

        // Complete with signature attached in request
        $file = $this->createFakePng('signature.png');
        $completeResponse = $this->actingAs($this->courier)
            ->patchJson("/api/orders/{$this->order->id}/complete", [
                'signature' => $file,
            ]);

        $completeResponse->assertOk()
            ->assertJsonPath('order.status', OrderStatus::COMPLETED->value);

        $this->delivery->refresh();
        $this->assertNotNull($this->delivery->signature_path);
        Storage::disk('public')->assertExists($this->delivery->signature_path);
    }

    public function test_non_valuable_order_completes_without_signature(): void
    {
        $this->order->update([
            'is_valuable' => false,
            'total_price' => 5000,
        ]);
        $this->assertFalse($this->order->requiresClientSignature());

        $response = $this->actingAs($this->courier)
            ->patchJson("/api/orders/{$this->order->id}/complete");

        $response->assertOk()
            ->assertJsonPath('order.status', OrderStatus::COMPLETED->value);
    }

    public function test_deleting_order_purges_signature_file(): void
    {
        $file = $this->createFakePng('sig.png');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();

        $path = $this->delivery->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}")
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_panic_wipe_and_retention_purges_signature_files(): void
    {
        $file = $this->createFakePng('sig.png');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-signature", [
                'signature' => $file,
            ])
            ->assertOk();

        $path = $this->delivery->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);

        $panicService = app(PanicService::class);
        $panicService->triggerPanic($this->courier->id);

        Storage::disk('public')->assertMissing($path);
    }
}
