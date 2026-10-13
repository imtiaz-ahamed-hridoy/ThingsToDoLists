<?php

namespace Database\Seeders;

use App\Models\HomePageSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomePageSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing
        Schema::disableForeignKeyConstraints();
        DB::table('home_page_sections')->truncate();
        Schema::enableForeignKeyConstraints();

        $sections = [
            [
                'id' => 1,
                'title' => 'Travelers\' Choice Awards',
                'short_description' => 'Among our top 1% of experiences-decided by travelers',
                'image' => null,
                'product_ids' => json_encode([17, 41, 68, 75, 76, 79, 82, 87, 108, 110, 112, 125, 131]),
                'sort_order' => 1,
                'status' => true,
                'section_type' => 'products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'title' => 'Wildlife & Nature',
                'short_description' => 'Get up close with Australia\'s incredible wildlife. Sanctuary visits, nature walks, and island explorations await.',
                'image' => null,
                'product_ids' => json_encode([698, 2276, 2317, 3010, 3052]),
                'sort_order' => 2,
                'status' => true,
                'section_type' => 'products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'title' => 'Get a hike in',
                'short_description' => 'Trails and guided routes for every level, with views worth the climb.',
                'image' => null,
                'product_ids' => json_encode([6, 7, 8, 9, 10]),
                'sort_order' => 3,
                'status' => true,
                'section_type' => 'products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'title' => 'Top-Rated Experiences',
                'short_description' => 'Discover the highest-rated tours and activities, carefully curated for unforgettable memories.',
                'image' => null,
                'product_ids' => json_encode([100, 233, 235, 1160, 1431, 1731, 1927, 2083, 2514, 3079]),
                'sort_order' => 4,
                'status' => true,
                'section_type' => 'products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'title' => 'Popular Water Activities',
                'short_description' => 'Dive into the best water-based experiences - surf lessons, kayak tours, sunset cruises and more.',
                'image' => null,
                'product_ids' => json_encode([233, 585, 664, 1925, 1927, 1947, 2276, 2283]),
                'sort_order' => 5,
                'status' => true,
                'section_type' => 'products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'title' => 'Explore Destinations',
                'short_description' => 'Discover incredible destinations and start planning your next journey.',
                'image' => null,
                'product_ids' => null,
                'sort_order' => 6,
                'status' => true,
                'section_type' => 'locations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        HomePageSection::insert($sections);

        $this->command->info('Home Page Sections seeded successfully!');
    }
}
