<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;

class CreateSiteCommand extends Command
{
    protected $signature = 'site:create {name} {domain} {--inactive : Create site as inactive}';

    protected $description = 'Create a new site with API token';

    public function handle(): int
    {
        $name = $this->argument('name');
        $domain = $this->argument('domain');
        $isActive = ! $this->option('inactive');

        $site = Site::firstOrCreate(
            ['domain' => $domain],
            [
                'name' => $name,
                'is_active' => $isActive,
            ]
        );

        if (! $site->wasRecentlyCreated) {
            $this->warn("Site with domain '{$domain}' already exists.");
            $this->info("ID: {$site->id}");
            $this->info("Name: {$site->name}");
            $this->info("Domain: {$site->domain}");
            $this->info('Active: '.($site->is_active ? 'yes' : 'no'));
            $this->info("API Token: {$site->api_token} (хеширован SHA-256)");

            return Command::SUCCESS;
        }

        $this->info('Site created successfully!');
        $this->warn('Внимание: Сохраните API-токен сейчас! В БД хранится только SHA-256 хеш, открытый токен больше не будет показан.');
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $site->id],
                ['Name', $site->name],
                ['Domain', $site->domain],
                ['Active', $site->is_active ? 'yes' : 'no'],
                ['API Token', $site->api_token],
            ]
        );

        return Command::SUCCESS;
    }
}
