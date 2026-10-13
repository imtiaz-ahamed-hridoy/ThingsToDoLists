<?php

namespace Database\Seeders;

use App\Models\DynamicPage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DynamicPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'page_title' => 'Terms & Conditions',
                'page_slug' => 'terms-conditions',
                'page_content' => 'Terms and Conditions',
            ],
            [
                'page_title' => 'Privacy & Policy',
                'page_slug' => 'privacy-policy',
                'page_content' => 'Privacy Policy',
            ]
        ];

        foreach ($pages as $page) {
            DynamicPage::create([
                'page_title' => $page['page_title'],
                'page_slug' => $page['page_slug'],
                'page_content' => $page['page_content'],
            ]);
        }
    }
}
