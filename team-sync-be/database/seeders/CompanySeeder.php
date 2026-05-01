<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['slug' => 'team-sync-pro'],
            [
                'name' => 'Team Sync Pro',
                'slug' => 'team-sync-pro',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
                'currency' => 'IDR',
                'is_active' => true,
            ]
        );
    }
}
