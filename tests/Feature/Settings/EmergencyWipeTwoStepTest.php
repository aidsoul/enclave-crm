<?php

namespace Tests\Feature\Settings;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\PurgeAllBackupsJob;
use App\Models\Order;
use App\Models\Site;
use App\Models\User;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmergencyWipeTwoStepTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $manager;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'system_name' => 'Owner Admin',
            'telegram_user_id' => 99001,
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'system_name' => 'Manager Admin',
            'telegram_user_id' => 99002,
            'role' => UserRole::MANAGER,
            'is_active' => true,
        ]);

        $this->site = Site::create([
            'name' => 'Test Store',
            'domain' => 'store.test',
            'api_token' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    public function test_owner_can_set_and_check_wipe_pin(): void
    {
        $statusRes = $this->actingAs($this->owner)
            ->getJson('/api/emergency-wipe/status');
        $statusRes->assertOk()->assertJson(['has_pin' => false]);

        $pinRes = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/pin', [
                'pin' => '9876',
                'pin_confirmation' => '9876',
            ]);
        $pinRes->assertOk()->assertJson(['success' => true]);

        $statusResAfter = $this->actingAs($this->owner)
            ->getJson('/api/emergency-wipe/status');
        $statusResAfter->assertOk()->assertJson(['has_pin' => true]);
    }

    public function test_updating_existing_pin_requires_current_pin(): void
    {
        $this->owner->setWipeConfirmCode('1234');

        $failRes = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/pin', [
                'current_pin' => 'wrong',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);
        $failRes->assertStatus(422)
            ->assertJsonValidationErrors(['current_pin']);

        $successRes = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/pin', [
                'current_pin' => '1234',
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);
        $successRes->assertOk();
    }

    public function test_two_step_wipe_flow_requires_pin_and_confirmation(): void
    {
        $this->owner->setWipeConfirmCode('2468');

        Order::create([
            'site_id' => $this->site->id,
            'order_number' => 'ORD-WIPE-001',
            'customer_name' => 'Wipe Customer',
            'customer_phone' => '+1234567890',
            'total_price' => 500,
            'purchase_price' => 200,
            'status' => OrderStatus::CREATED,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseCount('orders', 1);

        // Direct execute without step 1 token or PIN fails
        $failExecute = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'confirm_code' => 'Owner Admin',
            ]);
        $failExecute->assertStatus(422);

        // Step 1: Wrong PIN fails
        $failStep1 = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '0000',
            ]);
        $failStep1->assertStatus(422);

        // Step 1: Correct PIN succeeds and returns wipe_token
        $step1Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '2468',
            ]);
        $step1Res->assertOk()->assertJsonStructure(['success', 'wipe_token']);
        $wipeToken = $step1Res->json('wipe_token');

        // Step 2: Wrong confirm code fails
        $failStep2 = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'wipe_token' => $wipeToken,
                'confirm_code' => 'WrongName',
            ]);
        $failStep2->assertStatus(422);

        // Step 2: Correct confirm code (system_name) succeeds and wipes tables
        $step2Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'wipe_token' => $wipeToken,
                'confirm_code' => 'Owner Admin',
            ]);
        $step2Res->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_two_step_wipe_flow_with_pin_confirmation(): void
    {
        $this->owner->setWipeConfirmCode('3344');

        Order::create([
            'site_id' => $this->site->id,
            'order_number' => 'ORD-WIPE-002',
            'customer_name' => 'Wipe Customer 2',
            'customer_phone' => '+1234567891',
            'total_price' => 700,
            'purchase_price' => 300,
            'status' => OrderStatus::CREATED,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseCount('orders', 1);

        $step1Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '3344',
            ]);
        $step1Res->assertOk();
        $wipeToken = $step1Res->json('wipe_token');

        $step2Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'wipe_token' => $wipeToken,
                'confirm_code' => '3344',
            ]);
        $step2Res->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('action_logs', 0);
    }

    public function test_non_owner_cannot_access_emergency_wipe(): void
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/emergency-wipe/status');
        $response->assertStatus(403);

        $initiateRes = $this->actingAs($this->manager)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '1234',
            ]);
        $initiateRes->assertStatus(403);
    }

    public function test_repeated_wrong_pin_triggers_temporary_lockout(): void
    {
        $this->owner->setWipeConfirmCode('7788');

        for ($i = 0; $i < 5; $i++) {
            $res = $this->actingAs($this->owner)
                ->postJson('/api/emergency-wipe/initiate', ['pin' => '0000']);
            $res->assertStatus(422);
        }

        $blockedRes = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', ['pin' => '7788']);
        $blockedRes->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
        $this->assertStringContainsString('временно заблокировано', $blockedRes->json('errors.pin.0'));
    }

    public function test_repeated_wrong_current_pin_on_set_pin_triggers_lockout(): void
    {
        $this->owner->setWipeConfirmCode('1234');

        for ($i = 0; $i < 5; $i++) {
            $res = $this->actingAs($this->owner)
                ->postJson('/api/emergency-wipe/pin', [
                    'current_pin' => '0000',
                    'pin' => '9999',
                    'pin_confirmation' => '9999',
                ]);
            $res->assertStatus(422);
        }

        $blockedRes = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/pin', [
                'current_pin' => '1234',
                'pin' => '9999',
                'pin_confirmation' => '9999',
            ]);
        $blockedRes->assertStatus(422)
            ->assertJsonValidationErrors(['current_pin']);
        $this->assertStringContainsString('временно заблокировано', $blockedRes->json('errors.current_pin.0'));
    }

    public function test_two_step_wipe_flow_with_delete_backups_dispatches_high_priority_job(): void
    {
        Queue::fake([PurgeAllBackupsJob::class]);

        $this->owner->setWipeConfirmCode('5566');

        $step1Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '5566',
            ]);
        $step1Res->assertOk();
        $wipeToken = $step1Res->json('wipe_token');

        $step2Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'wipe_token' => $wipeToken,
                'confirm_code' => '5566',
                'delete_backups' => true,
            ]);
        $step2Res->assertOk()->assertJson([
            'success' => true,
            'backups_purged' => true,
        ]);

        Queue::assertPushed(PurgeAllBackupsJob::class, function (PurgeAllBackupsJob $job) {
            return $job->queue === 'high' && $job->connection === 'redis';
        });
    }

    public function test_two_step_wipe_flow_without_delete_backups_does_not_dispatch_job(): void
    {
        Queue::fake([PurgeAllBackupsJob::class]);

        $this->owner->setWipeConfirmCode('5566');

        $step1Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/initiate', [
                'pin' => '5566',
            ]);
        $step1Res->assertOk();
        $wipeToken = $step1Res->json('wipe_token');

        $step2Res = $this->actingAs($this->owner)
            ->postJson('/api/emergency-wipe/execute', [
                'wipe_token' => $wipeToken,
                'confirm_code' => '5566',
                'delete_backups' => false,
            ]);
        $step2Res->assertOk()->assertJson([
            'success' => true,
            'backups_purged' => false,
        ]);

        Queue::assertNotPushed(PurgeAllBackupsJob::class);
    }

    public function test_purge_all_backups_service_deletes_files_from_disks_and_temp_folders(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        config([
            'backup.backup.name' => 'test-backup',
            'backup.backup.destination.disks' => ['local', 's3'],
            'filesystems.disks.s3.bucket' => 'test-bucket',
        ]);

        Storage::disk('local')->put('test-backup/backup-1.zip', 'content-1');
        Storage::disk('local')->put('archive-legacy.zip', 'legacy-content');
        Storage::disk('s3')->put('test-backup/backup-s3.zip', 's3-content');
        Storage::disk('s3')->put('s3-archive.zip', 's3-legacy-content');

        $tempDir = storage_path('app/backup-temp');
        File::ensureDirectoryExists($tempDir);
        File::put($tempDir.'/test.dump', 'dump-data');

        $service = app(DataRetentionService::class);
        $service->purgeAllBackups();

        $this->assertFalse(Storage::disk('local')->exists('test-backup/backup-1.zip'));
        $this->assertFalse(Storage::disk('local')->exists('archive-legacy.zip'));
        $this->assertFalse(Storage::disk('s3')->exists('test-backup/backup-s3.zip'));
        $this->assertFalse(Storage::disk('s3')->exists('s3-archive.zip'));
        $this->assertFalse(File::exists($tempDir.'/test.dump'));
    }

    public function test_wipe_tables_purges_delivery_proof_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('delivery-proofs/proof-1.jpg', 'fake-image-bytes');
        Storage::disk('public')->put('delivery-proofs/sub/proof-2.jpg', 'fake-image-bytes-2');

        $this->assertTrue(Storage::disk('public')->exists('delivery-proofs/proof-1.jpg'));

        $service = app(DataRetentionService::class);
        $service->wipeTables(['deliveries'], $this->owner->id);

        $this->assertFalse(Storage::disk('public')->exists('delivery-proofs/proof-1.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('delivery-proofs/sub/proof-2.jpg'));
    }
}
