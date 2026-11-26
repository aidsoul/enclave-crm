<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\ActionLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\FinancialStat;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class DataRetentionService
{
    public function run(int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $lock = Cache::lock('data_retention_running', 600);
        if (! $lock->get()) {
            Log::channel('single')->warning('Data retention is already running, skipping execution.');

            return;
        }

        try {
            $cutoff = now()->subDays($retentionDays)->startOfDay();

            $this->aggregateStats($cutoff);

            $deletedOrders = $this->deleteOrdersInChunks($cutoff);
            $deletedLeads = $this->deleteLeadsInChunks($cutoff);
            $this->deleteOrphanedCustomers();
            $deletedLogs = $this->deleteActionLogsInChunks($cutoff);

            Log::channel('single')->info('Data retention completed', [
                'retention_days' => $retentionDays,
                'cutoff_date' => $cutoff->toDateString(),
                'deleted_orders' => $deletedOrders,
                'deleted_leads' => $deletedLeads,
                'deleted_logs' => $deletedLogs,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function burnEverything(int $actorId): void
    {
        $actor = User::findOrFail($actorId);

        if (! $actor->isOwner() || ! $actor->isActive()) {
            throw new \InvalidArgumentException('Only active owner can burn archives');
        }

        $tables = self::wipeTableNames();
        $this->wipeTables($tables, $actorId);
        $this->purgeAllBackups();
    }

    public static function wipeTableNames(): array
    {
        return [
            'leads',
            'order_comments',
            'order_messages',
            'order_discounts',
            'order_items',
            'payments',
            'deliveries',
            'orders',
            'customers',
            'product_variants',
            'products',
            'daily_user_kpis',
            'financial_stats',
            'action_logs',
            'manager_sites',
            'invitations',
            'failed_jobs',
            'job_batches',
            'settings',
            'users',
            'sites',
        ];
    }

    public function wipeTables(array $tables, int $actorId): array
    {
        $actor = User::findOrFail($actorId);

        if (! $actor->isOwner() || ! $actor->isActive()) {
            throw new \InvalidArgumentException('Only active owner can wipe tables');
        }

        $lock = Cache::lock('emergency_wipe_running', 120);
        if (! $lock->get()) {
            throw new \RuntimeException('Emergency wipe is already in progress');
        }

        try {
            $allowedTables = self::wipeTableNames();
            $validatedTables = array_values(array_intersect($allowedTables, $tables));

            $deletedCounts = [];

            DB::beginTransaction();
            try {
                $driver = DB::getDriverName();
                foreach ($validatedTables as $table) {
                    $deletedCounts[$table] = DB::table($table)->count();
                }

                if ($driver === 'pgsql' && count($validatedTables) === count($allowedTables)) {
                    $tableList = implode(', ', array_map(fn ($t) => '"'.$t.'"', $validatedTables));
                    DB::statement("TRUNCATE TABLE {$tableList} CASCADE");
                } else {
                    foreach ($validatedTables as $table) {
                        DB::table($table)->delete();
                    }
                }
                DB::commit();

                $this->flushRedisSessions();
                $this->clearLogFiles();
                $this->purgeDeliveryProofFiles();
                $this->purgeProductImagesFiles();
                $this->purgeChatAttachmentFiles();

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return $deletedCounts;
        } finally {
            $lock->release();
        }
    }

    public function clearLogFiles(): void
    {
        $logPath = storage_path('logs');
        if (! is_dir($logPath)) {
            return;
        }

        $files = glob($logPath.'/*.log*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file) && is_writable($file)) {
                    @file_put_contents($file, '');
                }
            }
        }
    }

    protected function deleteOrdersInChunks($cutoff): int
    {
        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $ids = Order::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deliveries = DB::table('deliveries')
                ->whereIn('order_id', $ids)
                ->where(function ($q) {
                    $q->whereNotNull('photo_proof_path')
                        ->orWhereNotNull('photo_proof_paths')
                        ->orWhereNotNull('signature_path');
                })
                ->get(['photo_proof_path', 'photo_proof_paths', 'signature_path']);

            foreach ($deliveries as $del) {
                $paths = [];
                if (! empty($del->photo_proof_paths)) {
                    $decoded = is_string($del->photo_proof_paths) ? json_decode($del->photo_proof_paths, true) : $del->photo_proof_paths;
                    if (is_array($decoded)) {
                        $paths = $decoded;
                    }
                }
                if ($del->photo_proof_path && ! in_array($del->photo_proof_path, $paths, true)) {
                    $paths[] = $del->photo_proof_path;
                }
                if (! empty($del->signature_path) && ! in_array($del->signature_path, $paths, true)) {
                    $paths[] = $del->signature_path;
                }

                foreach ($paths as $filePath) {
                    if ($filePath && Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }
            }

            $messageAttachments = DB::table('order_messages')
                ->whereIn('order_id', $ids)
                ->whereNotNull('attachment_path')
                ->pluck('attachment_path');

            foreach ($messageAttachments as $msgPath) {
                if ($msgPath && Storage::disk('public')->exists($msgPath)) {
                    Storage::disk('public')->delete($msgPath);
                }
            }

            foreach ($ids as $orderId) {
                if (Storage::disk('public')->exists("chat/{$orderId}")) {
                    Storage::disk('public')->deleteDirectory("chat/{$orderId}");
                }
                $orderChatDir = storage_path("app/public/chat/{$orderId}");
                if (File::isDirectory($orderChatDir)) {
                    File::deleteDirectory($orderChatDir);
                }
            }

            DB::table('order_messages')->whereIn('order_id', $ids)->delete();

            $productIds = DB::table('order_items')
                ->whereIn('order_id', $ids)
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->unique();

            if ($productIds->isNotEmpty()) {
                $productsWithOtherOrders = DB::table('order_items')
                    ->whereIn('product_id', $productIds)
                    ->whereNotIn('order_id', $ids)
                    ->pluck('product_id')
                    ->unique();

                $toDeleteProductIds = $productIds->diff($productsWithOtherOrders);
                if ($toDeleteProductIds->isNotEmpty()) {
                    $products = Product::whereIn('id', $toDeleteProductIds)->get();
                    foreach ($products as $prod) {
                        $prod->deleteImageFile();
                    }
                }
            }

            $deleted = Order::whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;
        } while ($ids->count() === $batchSize);

        return $totalDeleted;
    }

    protected function deleteOrphanedCustomers(): int
    {
        return Customer::whereDoesntHave('orders')->whereDoesntHave('leads')->delete();
    }

    protected function deleteLeadsInChunks($cutoff): int
    {
        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $ids = Lead::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = Lead::whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;
        } while ($ids->count() === $batchSize);

        return $totalDeleted;
    }

    protected function deleteActionLogsInChunks($cutoff): int
    {
        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $ids = ActionLog::where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = ActionLog::whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;
        } while ($ids->count() === $batchSize);

        return $totalDeleted;
    }

    protected function aggregateStats($cutoff): void
    {
        $stats = Order::where('orders.created_at', '<', $cutoff)
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->groupBy(DB::raw('orders.site_id, DATE(orders.created_at)'))
            ->selectRaw('
                orders.site_id,
                DATE(orders.created_at) as order_date,
                COALESCE(SUM(orders.total_price), 0) as total_revenue,
                COALESCE(SUM(orders.purchase_price), 0) as total_purchase
            ')
            ->get();

        if ($stats->isEmpty()) {
            return;
        }

        $courierPayouts = Delivery::where('orders.created_at', '<', $cutoff)
            ->where('orders.status', '!=', OrderStatus::CANCELLED)
            ->where(function ($dq) {
                $dq->where('deliveries.type', DeliveryType::COURIER)
                    ->orWhereNull('deliveries.type');
            })
            ->join('orders', 'orders.id', '=', 'deliveries.order_id')
            ->groupBy(DB::raw('orders.site_id, DATE(orders.created_at)'))
            ->selectRaw('orders.site_id, DATE(orders.created_at) as order_date, COALESCE(SUM(deliveries.delivery_cost), 0) as courier_payouts')
            ->get()
            ->keyBy(fn ($item) => $item->site_id.'_'.$item->order_date);

        $siteIds = $stats->pluck('site_id')->unique()->all();
        $dates = $stats->pluck('order_date')->unique()->all();

        $existingStats = FinancialStat::whereIn('site_id', $siteIds)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn ($item) => $item->site_id.'_'.($item->date?->format('Y-m-d') ?? (string) $item->date));

        $recordsToUpsert = [];
        $now = now();

        foreach ($stats as $stat) {
            $siteId = (int) $stat->site_id;
            $date = (string) $stat->order_date;
            $totalRevenue = (float) $stat->total_revenue;
            $totalPurchase = (float) $stat->total_purchase;
            $key = $siteId.'_'.$date;

            $existing = $existingStats->get($key);
            $unforeseen = $existing ? (float) $existing->unforeseen_expenses : 0.0;
            $courierPayout = isset($courierPayouts[$key])
                ? (float) $courierPayouts[$key]->courier_payouts
                : ($existing ? (float) $existing->courier_payouts : 0.0);

            $netProfit = $totalRevenue - $totalPurchase - $courierPayout - $unforeseen;

            $recordsToUpsert[] = [
                'site_id' => $siteId,
                'date' => $date,
                'total_revenue' => $totalRevenue,
                'total_purchase' => $totalPurchase,
                'courier_payouts' => $courierPayout,
                'unforeseen_expenses' => $unforeseen,
                'net_profit' => $netProfit,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($recordsToUpsert)) {
            FinancialStat::upsert(
                $recordsToUpsert,
                ['site_id', 'date'],
                ['total_revenue', 'total_purchase', 'courier_payouts', 'unforeseen_expenses', 'net_profit', 'updated_at']
            );
        }
    }

    public function purgeAllBackups(): void
    {
        $backupName = config('backup.backup.name', 'laravel-backup');
        $disks = config('backup.backup.destination.disks', ['local']);

        if (! in_array('local', $disks, true)) {
            $disks[] = 'local';
        }
        if (! in_array('s3', $disks, true)) {
            $disks[] = 's3';
        }

        foreach ($disks as $diskName) {
            try {
                if ($diskName === 's3' && empty(config('filesystems.disks.s3.bucket'))) {
                    continue;
                }

                $disk = Storage::disk($diskName);

                if ($disk->exists($backupName)) {
                    $disk->deleteDirectory($backupName);
                }

                $files = $disk->allFiles();
                $zipFiles = array_filter($files, fn ($file) => str_ends_with($file, '.zip'));
                if (! empty($zipFiles)) {
                    $disk->delete($zipFiles);
                }
            } catch (\Throwable $e) {
                Log::channel('single')->warning("Failed to purge backups on disk [{$diskName}]: ".$e->getMessage());
            }
        }

        $tempDir = storage_path('app/backup-temp');
        if (File::isDirectory($tempDir)) {
            File::deleteDirectory($tempDir);
        }

        $localBackupDir = storage_path('app/'.$backupName);
        if (File::isDirectory($localBackupDir)) {
            File::deleteDirectory($localBackupDir);
        }

        $this->purgeDeliveryProofFiles();
    }

    public function purgeDeliveryProofFiles(): void
    {
        try {
            $disk = Storage::disk('public');
            foreach (['delivery-proofs', 'delivery-signatures'] as $directory) {
                if ($disk->exists($directory)) {
                    $files = $disk->allFiles($directory);
                    if (! empty($files)) {
                        $disk->delete($files);
                    }
                    $subDirs = $disk->allDirectories($directory);
                    foreach ($subDirs as $subDir) {
                        $disk->deleteDirectory($subDir);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to purge delivery proofs/signatures from storage disk: '.$e->getMessage());
        }

        foreach (['delivery-proofs', 'delivery-signatures'] as $folder) {
            $dir = storage_path('app/public/'.$folder);
            if (File::isDirectory($dir)) {
                File::cleanDirectory($dir);
            } else {
                File::ensureDirectoryExists($dir, 0775);
            }
        }
    }

    public function purgeProductImagesFiles(): void
    {
        try {
            $disk = Storage::disk('public');
            if ($disk->exists('products')) {
                $files = $disk->allFiles('products');
                if (! empty($files)) {
                    $disk->delete($files);
                }
                $directories = $disk->allDirectories('products');
                foreach ($directories as $subDir) {
                    $disk->deleteDirectory($subDir);
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to purge product images: '.$e->getMessage());
        }

        $dir = storage_path('app/public/products');
        if (File::isDirectory($dir)) {
            File::cleanDirectory($dir);
        }
    }

    public function purgeChatAttachmentFiles(): void
    {
        try {
            $disk = Storage::disk('public');
            if ($disk->exists('chat')) {
                $disk->deleteDirectory('chat');
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to purge chat attachments: '.$e->getMessage());
        }

        $dir = storage_path('app/public/chat');
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    protected function flushRedisSessions(): void
    {
        try {
            Redis::connection('cache')->flushdb();

            $cursor = '0';
            do {
                [$cursor, $keys] = Redis::scan($cursor, ['match' => '*', 'count' => 100]);
                foreach ($keys as $key) {
                    if (str_contains($key, 'emergency_wipe_running')
                        || str_contains($key, 'queues:')
                        || str_contains($key, 'horizon')) {
                        continue;
                    }
                    Redis::del($key);
                }
            } while ($cursor !== '0' && ! empty($cursor));
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to flush redis: '.$e->getMessage());
        }
    }
}
