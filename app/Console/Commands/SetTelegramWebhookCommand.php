<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook 
                            {url? : Webhook URL (default: TELEGRAM_WEBHOOK_URL or APP_URL + /api/telegram/webhook)}
                            {--secret= : Secret token for X-Telegram-Bot-Api-Secret-Token}
                            {--drop-pending-updates : Drop all pending updates in Telegram queue}';

    protected $description = 'Register the Telegram bot webhook URL with mandatory secret token verification';

    public function handle(Nutgram $bot): int
    {
        $url = $this->resolveWebhookUrl();

        $secretToken = $this->option('secret') ?: config('nutgram.secret_token');

        if (empty($secretToken) || ! is_string($secretToken)) {
            $this->error('A secret token is required to register the webhook. Provide --secret=<token> or set TELEGRAM_WEBHOOK_SECRET_TOKEN in .env.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secretToken)) {
            $this->error('Invalid secret token format. Only 1-256 characters consisting of A-Z, a-z, 0-9, _ and - are allowed.');

            return self::FAILURE;
        }

        $dropPending = (bool) $this->option('drop-pending-updates');

        $this->info("Setting webhook to: {$url}");
        if ($dropPending) {
            $this->comment('Drop pending updates: true');
        }

        try {
            $result = $bot->setWebhook(
                url: $url,
                drop_pending_updates: $dropPending ?: null,
                secret_token: $secretToken,
            );

            if ($result === false) {
                $this->error('Failed to register webhook: Telegram API returned false.');

                return self::FAILURE;
            }

            $this->info('Webhook registered successfully with secret token!');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to register webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Resolve the target webhook URL without path duplication.
     */
    protected function resolveWebhookUrl(): string
    {
        if ($this->argument('url')) {
            return (string) $this->argument('url');
        }

        $configuredWebhook = config('services.telegram.webhook_url');
        if (! empty($configuredWebhook) && is_string($configuredWebhook)) {
            return str_ends_with($configuredWebhook, '/api/telegram/webhook')
                ? $configuredWebhook
                : rtrim($configuredWebhook, '/').'/api/telegram/webhook';
        }

        return rtrim((string) config('app.url'), '/').'/api/telegram/webhook';
    }
}
