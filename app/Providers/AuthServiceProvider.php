<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Policies\SettingsPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Order::class => OrderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('change-order-status', function ($user) {
            return $user->isOwner();
        });

        Gate::define('view-setting', [SettingsPolicy::class, 'view']);
        Gate::define('edit-setting', [SettingsPolicy::class, 'edit']);
    }
}
