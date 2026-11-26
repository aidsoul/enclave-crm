<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class CheckTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook-info';

    protected $description = 'Show current Telegram bot webhook info';

    public function handle(Nutgram $bot): int
    {
        $info = $bot->getWebhookInfo();

        if (! $info) {
            $this->error('Could not fetch webhook info.');

            return self::FAILURE;
        }

        $this->line('Webhook URL: '.($info->url ?? '(not set)'));
        $this->line('Has custom certificate: '.($info->has_custom_certificate ? 'yes' : 'no'));
        $this->line('Pending update count: '.($info->pending_update_count ?? 0));
        $this->line('Last error date: '.(is_int($info->last_error_date) ? date('Y-m-d H:i:s', $info->last_error_date) : ($info->last_error_date?->toDateTimeString() ?? 'never')));
        $this->line('Last error message: '.($info->last_error_message ?? 'none'));
        $this->line('Max connections: '.($info->max_connections ?? 'default'));

        return self::SUCCESS;
    }
}
