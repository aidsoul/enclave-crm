<?php

namespace App\Providers;

use App\Http\Telegram\Handlers\DesktopCommand;
use App\Http\Telegram\Handlers\OrderCallbacks;
use App\Http\Telegram\Handlers\OrderInlineQuery;
use App\Http\Telegram\Handlers\PanicCallback;
use App\Http\Telegram\Handlers\SessionSecurityCallback;
use App\Http\Telegram\Handlers\StartCommand;
use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Nutgram::class, function ($app) {
            $token = config('nutgram.token');

            $bot = new Nutgram($token);

            $bot->setRunningMode(new Webhook);

            $bot->onCommand('start', StartCommand::class);
            $bot->onCommand('start {token}', StartCommand::class);
            $bot->onCommand('desktop', DesktopCommand::class);
            $bot->onCommand('pc', DesktopCommand::class);

            $bot->onCallbackQueryData('manager_order:{orderId}:{action}', [OrderCallbacks::class, 'acceptManager']);
            $bot->onCallbackQueryData('order:{orderId}:accept', [OrderCallbacks::class, 'acceptCourier']);
            $bot->onCallbackQueryData('order:{orderId}:reject', [OrderCallbacks::class, 'rejectCourier']);
            $bot->onCallbackQueryData('panic:{targetUserId}', PanicCallback::class);
            $bot->onCallbackQueryData('panic_confirm:{targetUserId}', PanicCallback::class);
            $bot->onCallbackQueryData('session_ip_approve:{hash}:{code}', [SessionSecurityCallback::class, 'approve']);
            $bot->onCallbackQueryData('session_ip_block:{hash}', [SessionSecurityCallback::class, 'block']);
            $bot->onCallbackQueryData('sec_app:{hash}:{code}', [SessionSecurityCallback::class, 'approve']);
            $bot->onCallbackQueryData('sec_blk:{hash}', [SessionSecurityCallback::class, 'block']);

            $bot->onInlineQuery(OrderInlineQuery::class);

            return $bot;
        });
    }

    public function boot(): void
    {
        //
    }
}
