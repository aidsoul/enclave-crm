<?php

namespace App\Providers;

use App\Enums\SettingKey;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\LeadObserver;
use App\Observers\OrderCommentObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\SettingObserver;
use App\Observers\SiteObserver;
use App\Observers\UserObserver;
use App\Services\DataRetentionService;
use App\Services\Orders\ActionLogger;
use App\Services\Orders\CustomerResolver;
use App\Services\Orders\OrderAssigner;
use App\Services\Orders\OrderCreator;
use App\Services\Orders\OrderStatusMachine;
use App\Services\Orders\OrderUpdater;
use App\Services\PanicService;
use App\Services\RoleService;
use App\Services\SettingService;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleService::class);
        $this->app->singleton(PanicService::class);
        $this->app->singleton(DataRetentionService::class);
        $this->app->singleton(SettingService::class);

        $this->app->singleton(OrderCreator::class);
        $this->app->singleton(OrderUpdater::class);
        $this->app->singleton(OrderAssigner::class);
        $this->app->singleton(OrderStatusMachine::class);
        $this->app->singleton(ActionLogger::class);
        $this->app->singleton(CustomerResolver::class);
    }

    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        User::observe(UserObserver::class);
        OrderComment::observe(OrderCommentObserver::class);
        Site::observe(SiteObserver::class);
        Setting::observe(SettingObserver::class);
        Lead::observe(LeadObserver::class);
        Customer::observe(CustomerObserver::class);
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);

        View::addNamespace('errors', resource_path('views/errors'));

        View::composer('tma.layout', function ($view) {
            try {
                $days = app(SettingService::class)->get(SettingKey::RETENTION_DAYS, null) ?? 0;
                $view->with('retentionDays', $days);
            } catch (\Throwable $e) {
                $view->with('retentionDays', 0);
            }

            $data = $view->getData();
            $user = $data['user'] ?? auth()->user();
            $userData = null;

            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                $userData = $user->toArray();
            } elseif (is_array($user)) {
                $userData = $user;
            } elseif (is_object($user) && method_exists($user, 'toArray')) {
                $userData = $user->toArray();
            }

            if ($userData !== null) {
                // Ensure sensitive authentication hashes are never exposed to client state
                unset($userData['wipe_confirm_code_hash'], $userData['cloud_password_hash']);

                $token = session('tma_user_id');
                if (! empty($token)) {
                    $userData['session_token'] = (string) $token;
                }
            }

            $view->with('appUserData', $userData);
        });

        app(Vite::class)->createAssetPathsUsing(function ($path) {
            return '/'.ltrim($path, '/');
        });

        $proofDir = storage_path('app/public/delivery-proofs');
        if (! is_dir($proofDir)) {
            @mkdir($proofDir, 0775, true);
        }

        $this->configureBackupDisks();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\WayfinderGenerateCommand::class,
            ]);
        }
    }

    private function configureBackupDisks(): void
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $hasS3Bucket = ! empty(config('filesystems.disks.s3.bucket'));

        $validDisks = array_values(array_filter($disks, function ($disk) use ($hasS3Bucket) {
            return $disk === 's3' ? $hasS3Bucket : true;
        }));

        $validDisks = ! empty($validDisks) ? $validDisks : ['local'];

        config([
            'backup.backup.destination.disks' => $validDisks,
            'backup.monitor_backups.0.disks' => $validDisks,
        ]);
    }
}
