<?php

namespace App\Enums;

enum SettingKey: string
{
    case RETENTION_DAYS = 'retention_days';
    case NOTIFY_NEW_ORDERS = 'notify_new_orders';
    case NOTIFY_CHAT_ORDERS = 'notify_chat_orders';
    case DELETE_CHAT_MESSAGES_ON_ACCEPT = 'delete_chat_messages_on_accept';
    case NOTIFY_STALE_ORDERS = 'notify_stale_orders';
    case STALE_ORDERS_THRESHOLD_MINUTES = 'stale_orders_threshold_minutes';
    case NOTIFY_CHAT_LEADS = 'notify_chat_leads';
    case FLOATING_CHAT_ICON = 'floating_chat_icon';
    case NOTIFY_CHAT_MESSAGES = 'notify_chat_messages';

    public function roles(): array
    {
        return match ($this) {
            self::RETENTION_DAYS => [UserRole::OWNER, UserRole::MANAGER],
            self::NOTIFY_NEW_ORDERS => [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER],
            self::NOTIFY_CHAT_ORDERS => [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER],
            self::DELETE_CHAT_MESSAGES_ON_ACCEPT => [UserRole::OWNER],
            self::NOTIFY_STALE_ORDERS => [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER],
            self::STALE_ORDERS_THRESHOLD_MINUTES => [UserRole::OWNER, UserRole::MANAGER],
            self::NOTIFY_CHAT_LEADS => [UserRole::OWNER, UserRole::MANAGER],
            self::FLOATING_CHAT_ICON => [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER],
            self::NOTIFY_CHAT_MESSAGES => [UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER],
        };
    }

    public function default(): mixed
    {
        return match ($this) {
            self::RETENTION_DAYS => 0,
            self::NOTIFY_NEW_ORDERS => true,
            self::NOTIFY_CHAT_ORDERS => true,
            self::DELETE_CHAT_MESSAGES_ON_ACCEPT => false,
            self::NOTIFY_STALE_ORDERS => true,
            self::STALE_ORDERS_THRESHOLD_MINUTES => 120,
            self::NOTIFY_CHAT_LEADS => true,
            self::FLOATING_CHAT_ICON => true,
            self::NOTIFY_CHAT_MESSAGES => true,
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::RETENTION_DAYS => 'int',
            self::NOTIFY_NEW_ORDERS => 'bool',
            self::NOTIFY_CHAT_ORDERS => 'bool',
            self::DELETE_CHAT_MESSAGES_ON_ACCEPT => 'bool',
            self::NOTIFY_STALE_ORDERS => 'bool',
            self::STALE_ORDERS_THRESHOLD_MINUTES => 'int',
            self::NOTIFY_CHAT_LEADS => 'bool',
            self::FLOATING_CHAT_ICON => 'bool',
            self::NOTIFY_CHAT_MESSAGES => 'bool',
        };
    }

    public function isUserScoped(): bool
    {
        return match ($this) {
            self::RETENTION_DAYS => false,
            self::NOTIFY_NEW_ORDERS => true,
            self::NOTIFY_CHAT_ORDERS => true,
            self::DELETE_CHAT_MESSAGES_ON_ACCEPT => false,
            self::NOTIFY_STALE_ORDERS => true,
            self::STALE_ORDERS_THRESHOLD_MINUTES => false,
            self::NOTIFY_CHAT_LEADS => true,
            self::FLOATING_CHAT_ICON => true,
            self::NOTIFY_CHAT_MESSAGES => true,
        };
    }
}
