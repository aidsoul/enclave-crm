<?php

namespace App\Http\Telegram\Handlers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Inline\InlineQueryResultArticle;
use SergiX44\Nutgram\Telegram\Types\Input\InputTextMessageContent;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class OrderInlineQuery
{
    public function __invoke(Nutgram $bot): void
    {
        $inlineQuery = $bot->inlineQuery();
        if (! $inlineQuery) {
            return;
        }

        $telegramUser = $bot->user();
        if (! $telegramUser) {
            $this->safeAnswer($bot, []);

            return;
        }

        $crmUser = User::where('telegram_user_id', (string) $telegramUser->id)
            ->active()
            ->first();

        if (! $crmUser) {
            $this->safeAnswer($bot, []);

            return;
        }

        $rawQuery = trim($inlineQuery->query ?? '');
        $orderId = null;

        if (preg_match('/(?:order:)?(\d+)/i', $rawQuery, $matches)) {
            $orderId = (int) $matches[1];
        }

        $ordersQuery = Order::with(['customer', 'items', 'site', 'delivery.courier', 'manager'])
            ->latest('id');

        if ($orderId) {
            $ordersQuery->where('id', $orderId);
        } else {
            $ordersQuery->limit(5);
        }

        $orders = $ordersQuery->get();
        $results = [];
        $baseUrl = rtrim((string) (config('nutgram.webapp_url') ?: config('app.url')), '/');
        $botUsername = config('services.telegram.bot_username') ?: config('nutgram.name');

        foreach ($orders as $order) {
            $tmaUrl = $botUsername
                ? "https://t.me/{$botUsername}?startapp=order_{$order->id}"
                : "{$baseUrl}/tma/orders/{$order->id}";

            $summary = $this->buildOrderSummary($order, $tmaUrl);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '📦 Открыть заказ #'.$order->id,
                        url: $tmaUrl
                    )
                );

            $statusText = $order->status?->label() ?? (string) ($order->status?->value ?? '—');
            $customerName = $order->customer?->name ?? 'Клиент';
            $currency = config('app.currency_symbol', 'Br');
            $priceText = number_format((float) $order->total_price, 2, '.', ' ').' '.$currency;

            $results[] = InlineQueryResultArticle::make(
                id: 'order_'.$order->id,
                title: "Заказ #{$order->id} • {$priceText}",
                input_message_content: InputTextMessageContent::make(
                    message_text: $summary,
                    parse_mode: 'HTML'
                ),
                reply_markup: $keyboard,
                description: "{$statusText} • {$customerName}"
            );
        }

        $this->safeAnswer($bot, $results);
    }

    protected function safeAnswer(Nutgram $bot, array $results): void
    {
        try {
            $bot->answerInlineQuery(
                results: $results,
                cache_time: 5,
                is_personal: true
            );
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Telegram answerInlineQuery failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function buildOrderSummary(Order $order, string $tmaUrl): string
    {
        $statusText = $order->status?->label() ?? (string) ($order->status?->value ?? '—');
        $siteDomain = $order->site?->domain ?? $order->site?->name ?? null;
        $siteStr = $siteDomain ? " ({$siteDomain})" : '';
        $customerName = htmlspecialchars($order->customer?->name ?? 'Не указано', ENT_QUOTES, 'UTF-8');
        $customerPhone = htmlspecialchars($order->customer?->formatted_phone ?: ($order->customer?->phone ?? 'Не указан'), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($order->delivery_address ?? ($order->customer?->address ?? 'Не указан'), ENT_QUOTES, 'UTF-8');
        $currency = config('app.currency_symbol', 'Br');
        $totalPrice = number_format((float) $order->total_price, 2, '.', ' ').' '.$currency;

        $lines = [];
        $lines[] = "📦 <b>Заказ #{$order->id}</b>{$siteStr}";
        $lines[] = "Статус: <b>{$statusText}</b>";
        $lines[] = "Клиент: {$customerName} (<code>{$customerPhone}</code>)";
        $lines[] = "Адрес: {$address}";

        if ($order->items && $order->items->isNotEmpty()) {
            $lines[] = "\n<b>Товары:</b>";
            foreach ($order->items as $item) {
                $itemName = htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8');
                $lines[] = "• {$itemName} × {$item->qty} — ".number_format((float) $item->price, 2, '.', ' ')." {$currency}";
            }
        }

        $lines[] = "\nИтого к оплате: <b>{$totalPrice}</b>";

        if ($order->manager) {
            $lines[] = 'Менеджер: '.htmlspecialchars($order->manager->system_name, ENT_QUOTES, 'UTF-8');
        }

        $courier = $order->delivery?->first()?->courier;
        if ($courier) {
            $lines[] = 'Курьер: '.htmlspecialchars($courier->system_name, ENT_QUOTES, 'UTF-8');
        }

        $lines[] = "\n<a href=\"{$tmaUrl}\">🚀 Открыть заказ в приложении</a>";

        return implode("\n", $lines);
    }
}
