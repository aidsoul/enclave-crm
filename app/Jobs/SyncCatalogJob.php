<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 300;

    public function __construct(
        public int $siteId,
        public array $productsData
    ) {
        $this->onQueue('default');
    }

    public function handle(CatalogSynchronizer $synchronizer): void
    {
        $site = Site::find($this->siteId);

        if (! $site) {
            Log::channel('webhooks')->warning('SyncCatalogJob: site not found', [
                'site_id' => $this->siteId,
            ]);

            return;
        }

        $lock = Cache::lock("lock:catalog_sync:{$this->siteId}", 300);

        try {
            $lock->block(30);

            $result = $synchronizer->sync($this->siteId, $this->productsData);

            Log::channel('webhooks')->info('Catalog sync completed', [
                'site_id' => $this->siteId,
                'synced_products' => $result['synced_products'],
                'synced_variants' => $result['synced_variants'],
            ]);
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Catalog sync failed', [
                'site_id' => $this->siteId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
