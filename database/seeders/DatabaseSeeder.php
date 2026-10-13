<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign key checks in a driver-agnostic way.
        Schema::disableForeignKeyConstraints();

        // Clear existing data
        DB::table('users')->truncate();
        DB::table('system_settings')->truncate();
        DB::table('mail_settings')->truncate();
        DB::table('dynamic_pages')->truncate();
        DB::table('categories')->truncate();

        // Re-enable foreign key checks.
        Schema::enableForeignKeyConstraints();

        // Call seeders
        $this->call([
            UserSeeder::class,
            SystemSettingSeeder::class,
            MailSettingSeeder::class,
            DynamicPageSeeder::class,
            CitySeeder::class,
            HomePageSectionSeeder::class,
            BlogSeeder::class,
        ]);
    }
}
