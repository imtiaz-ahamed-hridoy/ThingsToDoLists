<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SystemSetting::create([
            'title' => 'Things To Do Lists',
            'system_name' => 'Things To Do Lists',
            'email' => 'info@example.com',
            'number' => '+15551234567',
            'logo' => null,
            'favicon' => null,
            'address' => null,
            'copyright_text' => 'Copyright 2025. All Rights Reserved. Powered by Tour & Experience Platform.',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
    }
}
