<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['api', 'tma.auth']]);

        Broadcast::resolveAuthenticatedUserUsing(function ($request) {
            return $request->attributes->get('user');
        });

        require base_path('routes/channels.php');
    }
}
