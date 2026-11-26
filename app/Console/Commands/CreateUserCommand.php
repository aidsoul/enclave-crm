<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
        {role? : Role of the user (owner, manager, courier)}
        {telegram_user_id? : Telegram user ID}
        {system_name? : System display name}
        {--r|role= : Role of the user (owner, manager, courier)}
        {--t|telegram-id= : Telegram user ID}
        {--name= : System display name}
        {--inactive : Create user as inactive}';

    protected $description = 'Create a new user with a specified role';

    public function handle(): int
    {
        $validRoles = array_map(fn (UserRole $role) => $role->value, UserRole::cases());

        $roleInput = $this->option('role') ?? $this->argument('role');
        if (! $roleInput) {
            $roleInput = $this->choice('Select user role', $validRoles, 0);
        }

        $role = UserRole::tryFrom(strtolower($roleInput));
        if (! $role) {
            $this->error("Invalid role '{$roleInput}'. Allowed roles: ".implode(', ', $validRoles));

            return Command::FAILURE;
        }

        $telegramUserId = $this->option('telegram-id') ?? $this->argument('telegram_user_id');
        if (! $telegramUserId) {
            $telegramUserId = $this->ask('Enter Telegram User ID');
        }

        if (empty($telegramUserId)) {
            $this->error('Telegram User ID is required.');

            return Command::FAILURE;
        }

        $telegramUserId = (string) $telegramUserId;

        if (User::where('telegram_user_id', $telegramUserId)->exists()) {
            $this->error("User with Telegram ID '{$telegramUserId}' already exists.");

            return Command::FAILURE;
        }

        $systemName = $this->option('name') ?? $this->argument('system_name');
        if (! $systemName) {
            $systemName = $this->ask('Enter system name', $role->label().' '.$telegramUserId);
        }

        $user = User::create([
            'role' => $role,
            'telegram_user_id' => $telegramUserId,
            'system_name' => $systemName,
            'is_active' => ! $this->option('inactive'),
        ]);

        $this->info('User created successfully!');
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $user->id],
                ['Role', "{$user->role->value} ({$user->role->label()})"],
                ['Telegram ID', $user->telegram_user_id],
                ['System Name', $user->system_name],
                ['Active', $user->is_active ? 'yes' : 'no'],
            ]
        );

        return Command::SUCCESS;
    }
}
