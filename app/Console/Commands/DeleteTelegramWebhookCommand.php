<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class DeleteTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:delete-webhook 
                            {--drop-pending : Drop pending updates}';

    protected $description = 'Delete the Telegram bot webhook';

    public function handle(Nutgram $bot): int
    {
        $dropPending = $this->option('drop-pending');

        $this->warn('Deleting webhook...');

        $result = $bot->deleteWebhook($dropPending);

        if ($result) {
            $this->info('Webhook deleted successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed to delete webhook.');

        return self::FAILURE;
    }
}
