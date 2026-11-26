<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Enums\UserRole;
use App\Models\CourierHandover;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

class TelegramService
{
    protected SettingService $settingService;

    public function __construct(
        protected Nutgram $bot,
        ?SettingService $settingService = null
    ) {
        $this->settingService = $settingService ?? app(SettingService::class);
    }

    public function notifyManagersAboutNewOrder(Order $order): void
    {
        if ($order->manager_id !== null) {
            Log::channel('webhooks')->info('notifyManagersAboutNewOrder skipped: order already has manager', [
                'order_id' => $order->id,
                'manager_id' => $order->manager_id,
            ]);

            return;
        }

        $recipients = User::whereIn('role', [UserRole::OWNER, UserRole::MANAGER])
            ->active()
            ->get();

        Log::channel('webhooks')->info('notifyManagersAboutNewOrder: potential recipients found', [
            'order_id' => $order->id,
            'recipients_count' => $recipients->count(),
        ]);

        $order->loadMissing(['site', 'customer', 'items']);
        $domain = $order->site?->domain ?? '—';
        $domainSuffix = $order->site?->domain ? " ({$order->site->domain})" : '';

        $customerPhone = $order->customer?->formatted_phone ?: ($order->customer?->phone ?? '—');
        $text = "📦 Новый заказ #{$order->id}{$domainSuffix}\n\n";
        $text .= "Сайт: {$domain}\n";
        $text .= 'Клиент: '.($order->customer?->name ?? '—')."\n";
        $text .= "Телефон: {$customerPhone}\n";
        $text .= 'Email: '.($order->customer?->email ?? '—')."\n";
        $text .= 'Адрес: '.($order->customer?->address ?? '—')."\n";
        $currency = config('app.currency_symbol', 'Br');
        $text .= 'Сумма: '.number_format((float) $order->total_price, 2)." {$currency}\n";

        if ($order->items->isNotEmpty()) {
            $text .= "\nСостав:\n";
            foreach ($order->items as $item) {
                $qty = $item->qty;
                $name = $item->name;
                $price = number_format((float) $item->price, 2).' '.$currency;
                $text .= "- {$qty} × {$name} ({$price})\n";
            }
        }

        $baseUrl = $this->getTmaBaseUrl();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ Принять заказ',
                    callback_data: "manager_order:{$order->id}:accept"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👁 Посмотреть',
                    web_app: WebAppInfo::make("{$baseUrl}/tma/orders/{$order->id}")
                )
            );

        $this->settingService->preload(SettingKey::NOTIFY_CHAT_ORDERS, $recipients);

        foreach ($recipients as $recipient) {
            if ($recipient->telegram_user_id === null) {
                Log::channel('webhooks')->warning('notifyManagersAboutNewOrder: user has no telegram_user_id', [
                    'order_id' => $order->id,
                    'user_id' => $recipient->id,
                ]);

                continue;
            }

            $shouldNotify = $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $recipient);
            if (! $shouldNotify) {
                Log::channel('webhooks')->info('notifyManagersAboutNewOrder: skipped by notify_chat_orders setting', [
                    'order_id' => $order->id,
                    'user_id' => $recipient->id,
                ]);

                continue;
            }

            Log::channel('webhooks')->info('notifyManagersAboutNewOrder: sending to user', [
                'order_id' => $order->id,
                'user_id' => $recipient->id,
                'telegram_user_id' => $recipient->telegram_user_id,
            ]);

            $sentMessage = $this->sendToChat($recipient->telegram_user_id, $text, [
                'reply_markup' => $keyboard,
            ]);

            if ($sentMessage && isset($sentMessage->message_id)) {
                $redisKey = "order_messages:{$order->id}";
                Redis::rpush($redisKey, json_encode([
                    'chat_id' => (string) $recipient->telegram_user_id,
                    'message_id' => $sentMessage->message_id,
                ]));
            }
        }

        $redisKey = "order_messages:{$order->id}";
        if (Redis::exists($redisKey)) {
            Redis::expire($redisKey, 86400 * 7);
        }
    }

    public function deleteOrderMessages(int $orderId): void
    {
        $redisKey = "order_messages:{$orderId}";

        $responses = Redis::connection()->pipeline(function ($pipe) use ($redisKey) {
            $pipe->lrange($redisKey, 0, -1);
            $pipe->del($redisKey);
        });

        $items = $responses[0] ?? [];

        if (empty($items)) {
            return;
        }

        Log::channel('webhooks')->info('deleteOrderMessages: deleting messages from Telegram', [
            'order_id' => $orderId,
            'count' => count($items),
        ]);

        foreach ($items as $item) {
            $data = json_decode($item, true);
            if (! empty($data['chat_id']) && ! empty($data['message_id'])) {
                try {
                    $this->bot->deleteMessage(chat_id: $data['chat_id'], message_id: (int) $data['message_id']);
                } catch (\Throwable $e) {
                    Log::channel('webhooks')->warning('deleteOrderMessage failed', [
                        'order_id' => $orderId,
                        'chat_id' => $data['chat_id'],
                        'message_id' => $data['message_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function notifyOwnersAboutNewUser(User $newUser, ?User $inviter): void
    {
        $owners = User::byRole(UserRole::OWNER)
            ->active()
            ->get();

        foreach ($owners as $owner) {
            if ($owner->telegram_user_id === null) {
                continue;
            }

            if (! $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $owner)) {
                Log::channel('webhooks')->info('notifyOwnersAboutNewUser: skipped by notify_chat_orders setting', [
                    'owner_id' => $owner->id,
                ]);

                continue;
            }

            $text = "🆕 Новый пользователь зарегистрировался\n\n";
            $text .= "Имя: {$newUser->system_name}\n";
            $text .= "Роль: {$newUser->role->label()}\n";

            if ($inviter) {
                $text .= "Пригласил: {$inviter->system_name} (Владелец)\n";
            }

            $this->sendToChat($owner->telegram_user_id, $text);
        }
    }

    public function notifyCourierAboutAssignment(Order $order, User $courier): void
    {
        if ($courier->telegram_user_id === null) {
            return;
        }

        if (! $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $courier)) {
            Log::channel('webhooks')->info('notifyCourierAboutAssignment: skipped by notify_chat_orders setting', [
                'order_id' => $order->id,
                'courier_id' => $courier->id,
            ]);

            return;
        }

        $order->loadMissing(['customer', 'delivery', 'items']);
        $delivery = $order->delivery->first();
        $address = $delivery?->delivery_address ?: ($order->customer?->address ?? '—');

        $customerPhone = $order->customer?->formatted_phone ?: ($order->customer?->phone ?? '—');
        $text = "🚚 Вам назначен заказ #{$order->id}\n\n";
        $text .= 'Клиент: '.($order->customer?->name ?? '—')."\n";
        $text .= "Телефон: {$customerPhone}\n";
        if (! empty($order->customer?->email)) {
            $text .= 'Email: '.$order->customer->email."\n";
        }
        $text .= "Адрес: {$address}\n";
        if (! empty($delivery?->courier_comment)) {
            $text .= "Комментарий: {$delivery->courier_comment}\n";
        }
        $currency = config('app.currency_symbol', 'Br');
        $text .= 'Сумма: '.number_format((float) $order->total_price, 2)." {$currency}\n";

        if ($order->items->isNotEmpty()) {
            $text .= "\nСостав:\n";
            foreach ($order->items as $item) {
                $qty = $item->qty;
                $name = $item->name;
                $price = number_format((float) $item->price, 2).' '.$currency;
                $text .= "- {$qty} × {$name} ({$price})\n";
            }
        }

        $baseUrl = $this->getTmaBaseUrl();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '👁 Посмотреть',
                    web_app: WebAppInfo::make("{$baseUrl}/tma/orders/{$order->id}")
                )
            );

        $this->sendToChat($courier->telegram_user_id, $text, [
            'reply_markup' => $keyboard,
        ]);
    }

    public function notifyManagersAboutNewLead(Lead $lead): void
    {
        $managers = User::whereIn('role', [UserRole::OWNER, UserRole::MANAGER])
            ->active()
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        $lead->loadMissing(['site', 'customer']);
        $siteName = e($lead->site?->name ?? 'Сайт');
        $typeLabel = e($lead->type_label);

        $leadPhone = e($lead->formatted_phone ?: ($lead->phone ?: '—'));
        $text = "<b>Новая заявка с сайта</b> ({$typeLabel})\n\n";
        $text .= "Сайт: {$siteName}\n";
        $text .= 'Клиент: '.e($lead->name ?: '—')."\n";
        $text .= "Телефон: {$leadPhone}\n";
        if ($lead->email) {
            $text .= 'Email: '.e($lead->email)."\n";
        }
        if ($lead->product_name) {
            $text .= 'Товар: '.e($lead->product_name)."\n";
        }
        if ($lead->product_sku) {
            $text .= 'Артикул: '.e($lead->product_sku)."\n";
        }
        if ($lead->message) {
            $text .= "\nСообщение: ".e($lead->message)."\n";
        }
        if ($lead->page_url) {
            $pageLabel = e($lead->page_title ?: $lead->page_url);
            $text .= "\nСтраница: {$pageLabel}\n";
        }

        $baseUrl = $this->getTmaBaseUrl();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: 'Открыть заявки',
                    web_app: WebAppInfo::make("{$baseUrl}/tma/leads")
                )
            );

        $this->settingService->preload(SettingKey::NOTIFY_CHAT_ORDERS, $managers);
        $this->settingService->preload(SettingKey::NOTIFY_CHAT_LEADS, $managers);

        foreach ($managers as $manager) {
            if ($manager->telegram_user_id === null) {
                continue;
            }

            if (! $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $manager)) {
                Log::channel('webhooks')->info('notifyManagersAboutNewLead: skipped because notify_chat_orders is false', [
                    'manager_id' => $manager->id,
                ]);

                continue;
            }

            if (! $this->settingService->get(SettingKey::NOTIFY_CHAT_LEADS, $manager)) {
                Log::channel('webhooks')->info('notifyManagersAboutNewLead: skipped by notify_chat_leads setting', [
                    'manager_id' => $manager->id,
                ]);

                continue;
            }

            Log::channel('webhooks')->info('notifyManagersAboutNewLead: sending to user', [
                'lead_id' => $lead->id,
                'user_id' => $manager->id,
                'telegram_user_id' => $manager->telegram_user_id,
            ]);

            $this->sendToChat($manager->telegram_user_id, $text, [
                'reply_markup' => $keyboard,
                'parse_mode' => 'HTML',
            ]);
        }
    }

    public function notifyStaleOrder(Order $order, User $recipient, string $durationText): void
    {
        if ($recipient->telegram_user_id === null) {
            return;
        }

        if (! $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $recipient)) {
            Log::channel('webhooks')->info('notifyStaleOrder: skipped by notify_chat_orders setting', [
                'order_id' => $order->id,
                'recipient_id' => $recipient->id,
            ]);

            return;
        }

        $order->loadMissing(['site', 'customer', 'delivery.courier']);
        $domain = $order->site?->domain ?? '';
        $domainSuffix = $domain ? " ({$domain})" : '';
        $statusLabel = $order->status->label();

        $customerPhone = $order->customer?->formatted_phone ?: ($order->customer?->phone ?? '—');
        $text = "⏳ Напоминание: заказ #{$order->id}{$domainSuffix} завис в статусе\n\n";
        $text .= "Статус: «{$statusLabel}» (уже {$durationText})\n";
        $text .= 'Клиент: '.($order->customer?->name ?? '—')."\n";
        $text .= "Телефон: {$customerPhone}\n";

        $deliveryAddress = $order->delivery->first()?->delivery_address ?? $order->customer?->address;
        if ($deliveryAddress) {
            $text .= "Адрес: {$deliveryAddress}\n";
        }
        $currency = config('app.currency_symbol', 'Br');
        $text .= 'Сумма: '.number_format((float) $order->total_price, 2)." {$currency}\n";

        $baseUrl = $this->getTmaBaseUrl();

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '📦 Открыть заказ',
                    web_app: WebAppInfo::make("{$baseUrl}/tma/orders/{$order->id}")
                )
            );

        $this->sendToChat($recipient->telegram_user_id, $text, [
            'reply_markup' => $keyboard,
        ]);
    }

    public function notifyAboutCourierHandover(CourierHandover $handover): void
    {
        $handover->loadMissing(['fromCourier', 'toCourier', 'orders']);
        $from = $handover->fromCourier;
        $to = $handover->toCourier;
        $currency = config('app.currency_symbol', 'Br');

        $isDisputed = $handover->status->value === 'disputed';
        $icon = $isDisputed ? '⚠️' : '🔄';
        $title = $isDisputed
            ? '<b>Внимание: передача с расхождением кассы!</b>'
            : '<b>Оперативная передача заказов между курьерами</b>';

        $ordersList = $handover->orders->pluck('id')->map(fn ($id) => "#{$id}")->implode(', ');
        $reasonLabel = $handover->reason?->label() ?? 'Форс-мажор';

        $text = "{$icon} {$title}\n\n";
        $text .= "<b>Причина:</b> {$reasonLabel}\n";
        if ($handover->reason_comment) {
            $text .= "<b>Комментарий:</b> ".e($handover->reason_comment)."\n";
        }
        $text .= "<b>От:</b> ".e($from?->system_name ?? '—')."\n";
        $text .= "<b>Кому:</b> ".e($to?->system_name ?? '—')."\n";
        $text .= "<b>Заказов передано:</b> {$handover->orders->count()} шт. ({$ordersList})\n";

        if ($handover->transfer_cash) {
            $text .= "\n<b>Сверка кассы:</b>\n";
            $text .= "• Заявлено передать: ".number_format((float) $handover->declared_cash_amount, 2)." {$currency}\n";
            $text .= "• Фактически принято: ".number_format((float) $handover->actual_cash_amount, 2)." {$currency}\n";
            if ($isDisputed) {
                $diff = (float) $handover->discrepancy;
                $diffText = ($diff > 0 ? "+{$diff}" : "{$diff}")." {$currency}";
                $text .= "⚠️ <b>Расхождение: {$diffText}</b>\n";
                if ($handover->discrepancy_reason) {
                    $text .= "• Причина расхождения: ".e($handover->discrepancy_reason)."\n";
                }
            } else {
                $text .= "✅ <i>Касса сошлась без расхождений</i>\n";
            }
        } else {
            $text .= "<i>Касса не передавалась (только заказы)</i>\n";
        }

        // Notify managers and owners
        $recipients = User::whereIn('role', [UserRole::OWNER, UserRole::MANAGER])
            ->active()
            ->whereNotNull('telegram_user_id')
            ->get();

        foreach ($recipients as $recipient) {
            $this->sendToChat($recipient->telegram_user_id, $text, [
                'parse_mode' => 'HTML',
            ]);
        }

        // Notify fromCourier
        if ($from?->telegram_user_id) {
            $courierText = "✅ <b>Ваша смена/заказы успешно переданы!</b>\n\n";
            $courierText .= "Принял: ".e($to?->system_name ?? 'Курьер')."\n";
            $courierText .= "Заказов: {$handover->orders->count()} шт.\n";
            if ($handover->transfer_cash) {
                $courierText .= "Касса: ".number_format((float) $handover->actual_cash_amount, 2)." {$currency}\n";
                if ($isDisputed) {
                    $courierText .= "⚠️ Зафиксировано расхождение: {$handover->discrepancy} {$currency}\n";
                }
            }
            $this->sendToChat($from->telegram_user_id, $courierText, ['parse_mode' => 'HTML']);
        }
    }

    public function notifyAboutChatMessage(OrderMessage $message): void
    {
        $order = $message->order;
        if (! $order) {
            return;
        }

        $sender = $message->user;
        $baseUrl = $this->getTmaBaseUrl();
        $chatUrl = "{$baseUrl}/tma/orders/{$order->id}/chat";

        $keyboard = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(
                text: '💬 Открыть чат',
                web_app: WebAppInfo::make($chatUrl)
            )
        );

        $text = "💬 <b>Новое сообщение по заказу #{$order->id}</b>";

        $recipients = collect();

        // Assigned manager of this order
        if ($order->manager_id) {
            $manager = $order->manager ?: User::find($order->manager_id);
            if ($manager && $manager->is_active) {
                $recipients->push($manager);
            }
        }

        // Assigned courier/driver of this order (only for courier channel)
        if ($message->channel === 'courier') {
            $courier = null;
            if ($order->courier_id) {
                $courier = User::find($order->courier_id);
            }
            if (! $courier) {
                $delivery = $order->delivery()->first();
                if ($delivery) {
                    $courierId = $delivery->user_id ?: $delivery->courier_id;
                    if ($courierId) {
                        $courier = User::find($courierId);
                    }
                }
            }
            if ($courier && $courier->is_active && $courier->isCourier()) {
                $recipients->push($courier);
            }
        }

        // Owners who have previously sent at least one message in this order
        $participatingOwnerIds = OrderMessage::where('order_messages.order_id', $order->id)
            ->where('order_messages.id', '!=', $message->id)
            ->join('users', 'users.id', '=', 'order_messages.user_id')
            ->where('users.role', UserRole::OWNER)
            ->distinct()
            ->pluck('order_messages.user_id');

        if ($participatingOwnerIds->isNotEmpty()) {
            $participatingOwners = User::whereIn('id', $participatingOwnerIds)
                ->where('role', UserRole::OWNER)
                ->active()
                ->get();
            $recipients = $recipients->merge($participatingOwners);
        }

        $uniqueRecipients = $recipients->unique('id');

        $this->settingService->preload(SettingKey::NOTIFY_CHAT_ORDERS, $uniqueRecipients);
        $this->settingService->preload(SettingKey::NOTIFY_CHAT_MESSAGES, $uniqueRecipients);

        foreach ($uniqueRecipients as $recipient) {
            if ($sender && (int) $recipient->id === (int) $sender->id) {
                continue;
            }

            if ($recipient->telegram_user_id === null) {
                continue;
            }

            // Global bot notification setting must be enabled
            if (! (bool) $this->settingService->get(SettingKey::NOTIFY_CHAT_ORDERS, $recipient)) {
                continue;
            }

            // Chat messages notification setting must be enabled
            if (! (bool) $this->settingService->get(SettingKey::NOTIFY_CHAT_MESSAGES, $recipient)) {
                continue;
            }

            $this->sendToChat((string) $recipient->telegram_user_id, $text, [
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard,
            ]);
        }
    }

    public function sendDesktopLoginLink(User $user, string $url): bool
    {
        if (! $user->telegram_user_id) {
            return false;
        }

        try {
            $this->bot->sendMessage(
                text: "🖥 <b>Вход в CRM на компьютере</b>\n\nСсылка действует 5 минут и работает 1 раз:\n<a href=\"{$url}\">{$url}</a>",
                chat_id: $user->telegram_user_id,
                parse_mode: 'HTML',
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(text: '💻 Войти в CRM на ПК', url: $url)
                )
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to send desktop link via bot', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendSessionSecurityAlert(
        User $user,
        string $ip,
        string $country,
        string $userAgent,
        ?string $code,
        string $sessionHash,
        bool $hasCloudPassword = false
    ): bool {
        if (! $user->telegram_user_id || ! config('nutgram.token')) {
            return false;
        }

        if (app()->runningUnitTests() && ! ($this->bot instanceof MockInterface)) {
            return true;
        }

        try {
            $device = htmlspecialchars(Str::limit($userAgent, 80), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $userName = htmlspecialchars($user->system_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeIp = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeCountry = htmlspecialchars($country, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $text = "🚨 <b>Внимание: вход с нового IP-адреса!</b>\n\n";
            $text .= "👤 Пользователь: <b>{$userName}</b>\n";
            $text .= "🌐 IP-адрес: <code>{$safeIp}</code>\n";
            $text .= "🏳️ Страна: <b>{$safeCountry}</b>\n";
            $text .= "📱 Устройство: <i>{$device}</i>\n\n";

            if ($hasCloudPassword) {
                $text .= "🔒 <b>Требуется ввод облачного пароля в приложении CRM.</b>\n\n";
                $text .= "<i>Если это вы — введите ваш облачный пароль на экране входа.\nЕсли это были не вы — немедленно заблокируйте сессию!</i>";
            } else {
                $text .= "🛡️ <b>Требуется установка облачного пароля в приложении CRM.</b>\n\n";
                $text .= '<i>Если это были не вы — немедленно заблокируйте сессию!</i>';
            }

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🚨 Заблокировать сессию',
                        callback_data: "sec_blk:{$sessionHash}"
                    )
                );

            try {
                $this->bot->sendMessage(
                    text: $text,
                    chat_id: $user->telegram_user_id,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard
                );
            } catch (\Throwable $sendError) {
                Log::warning('TelegramService: failed to send alert with keyboard, retrying plain text: '.$sendError->getMessage(), [
                    'user_id' => $user->id,
                    'ip' => $ip,
                ]);

                $this->bot->sendMessage(
                    text: $text,
                    chat_id: $user->telegram_user_id,
                    parse_mode: 'HTML'
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('TelegramService: failed to send session security alert: '.$e->getMessage(), [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return false;
        }
    }

    public function sendFailedIpAttemptsAlert(User $user, string $ip, string $country): bool
    {
        if (! $user->telegram_user_id || ! config('nutgram.token')) {
            return false;
        }

        if (app()->runningUnitTests() && ! ($this->bot instanceof MockInterface)) {
            return true;
        }

        try {
            $durationMinutes = ceil(((int) config('security.temp_block_duration', 900)) / 60);
            $userName = htmlspecialchars($user->system_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeIp = htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeCountry = htmlspecialchars($country, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $text = "⚠️ <b>Подозрительная активность!</b>\n\n";
            $text .= "Зафиксированы многократные попытки ввода неверного ключа подтверждения для аккаунта <b>{$userName}</b>.\n\n";
            $text .= "🌐 IP: <code>{$safeIp}</code>\n";
            $text .= "🏳️ Страна: <b>{$safeCountry}</b>\n\n";
            $text .= "🔒 <b>Доступ с этого IP временно заблокирован на {$durationMinutes} минут.</b>";

            $this->bot->sendMessage(
                text: $text,
                chat_id: $user->telegram_user_id,
                parse_mode: 'HTML'
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('TelegramService: failed to send failed attempts alert: '.$e->getMessage(), [
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return false;
        }
    }

    protected function sendToChat(string $chatId, string $text, array $options = []): ?Message
    {
        try {
            return $this->bot->sendMessage($text, ...array_merge(['chat_id' => $chatId], $options));
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'text' => Str::limit($text, 200),
            ]);

            return null;
        }
    }

    protected function getTmaBaseUrl(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $webappUrl = rtrim((string) config('nutgram.webapp_url'), '/');
        $webhookUrl = rtrim((string) config('services.telegram.webhook_url'), '/');

        $baseUrl = $webappUrl;
        if (! $baseUrl && $webhookUrl) {
            $baseUrl = preg_replace('#/api/telegram/webhook$#', '', $webhookUrl);
        }
        if (! $baseUrl) {
            $baseUrl = $appUrl;
        }

        return $baseUrl;
    }
}
