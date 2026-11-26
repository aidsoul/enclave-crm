<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryProofPhotoTest extends TestCase
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
            'name' => 'Proof Test Site',
            'domain' => 'proof.local',
            'token' => 'proof_token',
            'is_active' => true,
        ]);

        $this->courier = User::create([
            'system_name' => 'Courier Max',
            'telegram_user_id' => 20001,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->otherCourier = User::create([
            'system_name' => 'Courier Dan',
            'telegram_user_id' => 20002,
            'role' => UserRole::COURIER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Kate',
            'telegram_user_id' => 20003,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'system_name' => 'Owner Boss',
            'telegram_user_id' => 20004,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'site_id' => $this->site->id,
            'manager_id' => $this->manager->id,
            'total_price' => 2500,
            'purchase_price' => 1500,
            'status' => OrderStatus::AT_WAREHOUSE,
        ]);

        $this->delivery = Delivery::create([
            'order_id' => $this->order->id,
            'type' => DeliveryType::COURIER,
            'user_id' => $this->courier->id,
            'delivery_address' => 'ул. Ленина 10, кв. 5',
        ]);
    }

    protected function createFakeImage(string $name = 'proof.jpg', int $kilobytes = 10, ?string $seed = null): UploadedFile
    {
        $jpeg1x1 = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $tag = $seed !== null ? $seed : $name.'_'.uniqid('', true);
        $content = $jpeg1x1.$tag.str_repeat('A', max(0, ($kilobytes * 1024) - strlen($jpeg1x1) - strlen($tag)));

        return UploadedFile::fake()->createWithContent($name, $content, 'image/jpeg');
    }

    public function test_assigned_courier_can_upload_delivery_proof_photo(): void
    {
        $file = $this->createFakeImage('proof_photo.jpg');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'photo_proof_url',
                'photo_proof_at',
                'order' => [
                    'id',
                    'delivery',
                ],
            ]);

        $this->delivery->refresh();
        $this->assertNotNull($this->delivery->photo_proof_path);
        $this->assertNotNull($this->delivery->photo_proof_at);
        $this->assertStringContainsString('/storage/', $this->delivery->photo_proof_url);
        Storage::disk('public')->assertExists($this->delivery->photo_proof_path);
    }

    public function test_unassigned_courier_cannot_upload_proof(): void
    {
        $file = $this->createFakeImage('fake_proof.jpg');

        $response = $this->actingAs($this->otherCourier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_upload_validates_image_mimes_and_size(): void
    {
        $txtFile = UploadedFile::fake()->create('document.txt', 50, 'text/plain');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $txtFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $hugeFile = $this->createFakeImage('huge.jpg', 15000);

        $responseLarge = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $hugeFile,
            ]);

        $responseLarge->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_uploading_new_photo_appends_to_proof_list(): void
    {
        $firstFile = $this->createFakeImage('first.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $firstFile]);

        $this->delivery->refresh();
        $firstPath = $this->delivery->photo_proof_path;
        Storage::disk('public')->assertExists($firstPath);

        $secondFile = $this->createFakeImage('second.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $secondFile]);

        $this->delivery->refresh();
        $this->assertCount(2, $this->delivery->photo_proof_paths);
        Storage::disk('public')->assertExists($firstPath);
        Storage::disk('public')->assertExists($this->delivery->photo_proof_paths[1]);
    }

    public function test_assigned_courier_can_delete_delivery_proof_photo(): void
    {
        $file = $this->createFakeImage('to_delete.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $this->delivery->refresh();
        $filePath = $this->delivery->photo_proof_path;
        Storage::disk('public')->assertExists($filePath);

        $deleteResponse = $this->actingAs($this->courier)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof");

        $deleteResponse->assertOk();

        $this->delivery->refresh();
        $this->assertNull($this->delivery->photo_proof_path);
        $this->assertNull($this->delivery->photo_proof_at);
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_complete_order_with_proof_photo_attached(): void
    {
        $file = $this->createFakeImage('completion_proof.jpg');

        $response = $this->actingAs($this->courier)
            ->patchJson("/api/orders/{$this->order->id}/complete", [
                'photo' => $file,
            ]);

        $response->assertOk();
        $this->order->refresh();
        $this->delivery->refresh();

        $this->assertEquals(OrderStatus::COMPLETED, $this->order->status);
        $this->assertNotNull($this->delivery->photo_proof_path);
        Storage::disk('public')->assertExists($this->delivery->photo_proof_path);
    }

    public function test_manager_cannot_upload_or_delete_proof(): void
    {
        $file = $this->createFakeImage('manager_proof.jpg');

        $managerResponse = $this->actingAs($this->manager)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $managerResponse->assertForbidden();

        $managerDeleteResponse = $this->actingAs($this->manager)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof");

        $managerDeleteResponse->assertForbidden();
    }

    public function test_manager_can_view_delivery_proof_photos(): void
    {
        $file = $this->createFakeImage('courier_proof.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $res = $this->actingAs($this->manager)
            ->getJson("/api/orders/{$this->order->id}");

        $res->assertOk();
        $deliveryData = $res->json('order.delivery.0');
        $this->assertNotEmpty($deliveryData['photo_proof_urls']);
        $this->assertNotNull($deliveryData['photo_proof_url']);
    }

    public function test_owner_can_upload_and_delete_proof(): void
    {
        $file = $this->createFakeImage('owner_proof.jpg');

        $ownerUploadResponse = $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $ownerUploadResponse->assertOk();

        $ownerDeleteResponse = $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof");

        $ownerDeleteResponse->assertOk();
    }

    public function test_cannot_upload_proof_for_cancelled_order(): void
    {
        $this->order->update(['status' => OrderStatus::CANCELLED]);

        $file = $this->createFakeImage('cancelled_proof.jpg');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $response->assertForbidden();
    }

    public function test_assigned_courier_cannot_upload_or_delete_proof_for_completed_order_unless_owner(): void
    {
        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $file = $this->createFakeImage('completed_proof.jpg');

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file])
            ->assertForbidden();

        $this->actingAs($this->courier)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof")
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file])
            ->assertOk();

        $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof")
            ->assertOk();
    }

    public function test_extension_is_sanitized_from_mime(): void
    {
        $file = $this->createFakeImage('exploit.php.jpg');

        $response = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $response->assertOk();
        $this->delivery->refresh();
        $this->assertMatchesRegularExpression('/\.(jpg|jpeg)$/', $this->delivery->photo_proof_path);
        $this->assertStringNotContainsString('.php', $this->delivery->photo_proof_path);
    }

    public function test_order_show_resource_includes_photo_proof_data(): void
    {
        $file = $this->createFakeImage('proof_show.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $response = $this->actingAs($this->courier)
            ->getJson("/api/orders/{$this->order->id}");

        $response->assertOk();
        $deliveries = $response->json('order.delivery');
        $this->assertNotEmpty($deliveries);
        $this->assertNotNull($deliveries[0]['photo_proof_path']);
        $this->assertNotNull($deliveries[0]['photo_proof_url']);
        $this->assertNotNull($deliveries[0]['photo_proof_at']);
    }

    public function test_deleting_order_deletes_delivery_proof_and_product_images(): void
    {
        $file = $this->createFakeImage('proof_to_delete.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $this->delivery->refresh();
        $proofPath = $this->delivery->photo_proof_path;
        Storage::disk('public')->assertExists($proofPath);

        $productImgPath = 'products/item_photo.jpg';
        Storage::disk('public')->put($productImgPath, 'fake-product-bytes');
        Storage::disk('public')->assertExists($productImgPath);

        $product = Product::create([
            'site_id' => $this->site->id,
            'name' => 'Single Order Product',
            'sku' => 'SKU-SINGLE-IMG',
            'price' => 100,
            'cost_price' => 50,
            'image_url' => '/storage/'.$productImgPath,
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'qty' => 1,
            'price' => 100,
            'purchase_price' => 50,
        ]);

        $this->order->delete();

        Storage::disk('public')->assertMissing($proofPath);
        Storage::disk('public')->assertMissing($productImgPath);
    }

    public function test_owner_can_delete_order_via_api(): void
    {
        $file = $this->createFakeImage('api_delete_proof.jpg');
        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", ['photo' => $file]);

        $this->delivery->refresh();
        $proofPath = $this->delivery->photo_proof_path;
        Storage::disk('public')->assertExists($proofPath);

        $res = $this->actingAs($this->owner)
            ->deleteJson("/api/orders/{$this->order->id}");

        $res->assertOk();
        $this->assertDatabaseMissing('orders', ['id' => $this->order->id]);
        Storage::disk('public')->assertMissing($proofPath);
    }

    public function test_can_upload_multiple_photos_up_to_three(): void
    {
        $photos = [
            $this->createFakeImage('photo_1.jpg'),
            $this->createFakeImage('photo_2.jpg'),
        ];

        $res = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photos' => $photos,
            ]);

        $res->assertOk();
        $this->delivery->refresh();
        $this->assertCount(2, $this->delivery->photo_proof_urls);
        $this->assertCount(2, $this->delivery->photo_proof_paths);

        $morePhotos = [
            $this->createFakeImage('photo_3.jpg'),
            $this->createFakeImage('photo_4_overflow.jpg'),
        ];

        $res2 = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photos' => $morePhotos,
            ]);

        $res2->assertOk();
        $this->delivery->refresh();
        $this->assertCount(3, $this->delivery->photo_proof_urls);
    }

    public function test_can_delete_specific_photo_from_delivery_proofs(): void
    {
        $photos = [
            $this->createFakeImage('del_photo_1.jpg'),
            $this->createFakeImage('del_photo_2.jpg'),
        ];

        $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photos' => $photos,
            ]);

        $this->delivery->refresh();
        $firstPath = $this->delivery->photo_proof_paths[0];
        $secondPath = $this->delivery->photo_proof_paths[1];

        Storage::disk('public')->assertExists($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $delRes = $this->actingAs($this->courier)
            ->deleteJson("/api/orders/{$this->order->id}/delivery-proof?path=".urlencode('/storage/'.$firstPath));

        $delRes->assertOk();
        $this->delivery->refresh();
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertCount(1, $this->delivery->photo_proof_urls);
    }

    public function test_duplicate_photo_upload_is_prevented(): void
    {
        $original = $this->createFakeImage('photo.jpg', 10, 'same-content-seed');
        $uploadRes = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $original,
            ]);
        $uploadRes->assertOk();

        $duplicate = $this->createFakeImage('another_name.jpg', 10, 'same-content-seed');
        $duplicateRes = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $duplicate,
            ]);
        $duplicateRes->assertStatus(422)
            ->assertJsonPath('message', 'Выбранное фото уже прикреплено к этому заказу (дубликат)');

        $this->delivery->refresh();
        $this->assertCount(1, $this->delivery->photo_proof_paths);
    }

    public function test_upload_when_limit_of_three_photos_already_reached(): void
    {
        $photos = [
            $this->createFakeImage('p1.jpg', 10, 'seed-1'),
            $this->createFakeImage('p2.jpg', 10, 'seed-2'),
            $this->createFakeImage('p3.jpg', 10, 'seed-3'),
        ];

        $uploadRes = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photos' => $photos,
            ]);
        $uploadRes->assertOk();

        $fourth = $this->createFakeImage('p4.jpg', 10, 'seed-4');
        $failRes = $this->actingAs($this->courier)
            ->postJson("/api/orders/{$this->order->id}/delivery-proof", [
                'photo' => $fourth,
            ]);

        $failRes->assertStatus(422)
            ->assertJsonPath('message', 'Достигнут лимит фотофиксаций (максимум 3 фото)');
    }
}
