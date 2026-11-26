<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::firstOrCreate(
            ['domain' => 'test.com'],
            [
                'name' => 'test',
                'is_active' => true,
            ]
        );

        $this->command->info('Site seeded: '.$site->name.' | domain: '.$site->domain);
        $this->command->info('API token: '.$site->api_token);
    }
}
