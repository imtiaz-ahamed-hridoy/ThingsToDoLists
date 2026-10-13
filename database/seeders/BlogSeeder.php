<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Categories (skip if already exist) ────────────────
        $categories = [
            ['name' => 'Travel Tips', 'slug' => 'travel-tips', 'description' => 'Essential travel advice and hacks', 'sort_order' => 1],
            ['name' => 'Destination Guides', 'slug' => 'destination-guides', 'description' => 'In-depth guides to amazing destinations', 'sort_order' => 2],
            ['name' => 'Culture & History', 'slug' => 'culture-history', 'description' => 'Explore rich cultures and histories', 'sort_order' => 3],
            ['name' => 'Food & Drink', 'slug' => 'food-drink', 'description' => 'Culinary adventures around the world', 'sort_order' => 4],
            ['name' => 'Adventure', 'slug' => 'adventure', 'description' => 'Thrilling adventures and outdoor activities', 'sort_order' => 5],
        ];

        foreach ($categories as $cat) {
            BlogCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // ─── Tags (skip if already exist) ─────────────────────
        $tags = [
            ['name' => 'Italy', 'slug' => 'italy'],
            ['name' => 'Europe', 'slug' => 'europe'],
            ['name' => 'Beach', 'slug' => 'beach'],
            ['name' => 'Culture', 'slug' => 'culture'],
            ['name' => 'Food', 'slug' => 'food'],
            ['name' => 'Budget Travel', 'slug' => 'budget-travel'],
            ['name' => 'Luxury', 'slug' => 'luxury'],
            ['name' => 'Solo Travel', 'slug' => 'solo-travel'],
            ['name' => 'Family', 'slug' => 'family'],
            ['name' => 'Nature', 'slug' => 'nature'],
        ];

        foreach ($tags as $tag) {
            BlogTag::firstOrCreate(['slug' => $tag['slug']], $tag);
        }

        // Skip seeding posts if they already exist
        if (BlogPost::where('slug', 'like', 'best-frescoes-in-italy%')->exists()) {
            return; // Already seeded
        }

        // ─── Featured Post ────────────────────────────────────
        $featured = BlogPost::create([
            'parent_id' => null,
            'category_id' => 3,
            'title' => 'Best frescoes in Italy and where to find them',
            'slug' => 'best-frescoes-in-italy-' . uniqid(),
            'excerpt' => 'What if the walls themselves could tell stories? In Italy, they do. For centuries, artists have used the fresco technique to create masterpieces that have stood the test of time.',
            'content' => '<p>What if the walls themselves could tell stories? In Italy, they do. For centuries, artists have used the fresco technique, which uses water-based pigments to merge with wet plaster to become an integral part of the wall. This method has been around since ancient times, but Italian artists really mastered it during the Renaissance.</p><p>During the medieval era, frescoes became a popular medium for decorating churches and public buildings. They served both decorative and educational purposes, illustrating biblical stories to the general populace.</p><p>Planning a trip to Italy soon? Here is a list of places and churches where you will find some of the best frescoes in Italy.</p>',
            'featured_image' => null,
            'author_name' => 'Maria Rossi',
            'published' => true,
            'published_at' => now()->subDays(2),
            'is_featured' => true,
            'minutes_to_read' => 8,
            'sort_order' => 1,
        ]);

        // Child posts (sub-articles) for the featured post
        $childArticles = [
            [
                'title' => 'Sistine Chapel Ceiling',
                'subtitle' => 'Vatican City',
                'excerpt' => 'Michelangelo spent four years (1508-1512) painting this masterpiece. The whole ceiling tells stories from Genesis through nine big panels.',
                'sort_order' => 1,
            ],
            [
                'title' => 'The Last Judgement',
                'subtitle' => 'Padua',
                'excerpt' => 'Giotto frescoes in the Scrovegni Chapel are nothing short of legendary. Painted between 1303 and 1305 in Padua, Italy.',
                'sort_order' => 2,
            ],
            [
                'title' => 'Brancacci Chapel frescoes',
                'subtitle' => 'Florence',
                'excerpt' => 'The Brancacci Chapel houses one of the most important fresco cycles of the Early Renaissance.',
                'sort_order' => 3,
            ],
            [
                'title' => 'The Allegory of Good and Bad Government',
                'subtitle' => 'Siena',
                'excerpt' => 'Ambrogio Lorenzetti magnificent fresco cycle in the Palazzo Pubblico of Siena is a masterpiece of secular painting.',
                'sort_order' => 4,
            ],
            [
                'title' => 'Scrovegni Chapel frescoes',
                'subtitle' => 'Padua',
                'excerpt' => 'Giotto complete fresco cycle in the Scrovegni Chapel is considered one of the most important masterpieces of Western art.',
                'sort_order' => 5,
            ],
        ];

        foreach ($childArticles as $article) {
            BlogPost::create([
                'parent_id' => $featured->id,
                'category_id' => 3,
                'title' => $article['title'],
                'subtitle' => $article['subtitle'],
                'slug' => Str::slug($article['title']) . '-' . uniqid(),
                'excerpt' => $article['excerpt'],
                'content' => $article['excerpt'],
                'published' => true,
                'published_at' => now()->subDays(2),
                'sort_order' => $article['sort_order'],
            ]);
        }

        $featured->tags()->attach([1, 2, 4]);

        // ─── Regular Posts ────────────────────────────────────
        $posts = [
            [
                'category_id' => 1,
                'title' => '10 Essential Travel Tips for First-Time Visitors to Europe',
                'slug' => 'essential-travel-tips-europe-' . uniqid(),
                'excerpt' => 'Planning your first trip to Europe? Here are the essential tips you need to know before you go.',
                'content' => '<p>Planning your first trip to Europe can be both exciting and overwhelming. Here are our top tips.</p>',
                'author_name' => 'James Wilson',
                'published' => true,
                'published_at' => now()->subDays(5),
                'minutes_to_read' => 5,
                'sort_order' => 2,
            ],
            [
                'category_id' => 2,
                'title' => 'Exploring Venice in January: Events, attractions and travel tips',
                'slug' => 'exploring-venice-in-january-' . uniqid(),
                'excerpt' => 'Discover why Venice in winter is truly magical.',
                'content' => '<p>Venice in January is a completely different experience from the crowded summer months.</p>',
                'author_name' => 'Sofia Bianchi',
                'published' => true,
                'published_at' => now()->subDays(8),
                'minutes_to_read' => 6,
                'sort_order' => 3,
            ],
            [
                'category_id' => 4,
                'title' => 'A Food Lovers Guide to Rome: From Trattorias to Michelin Stars',
                'slug' => 'food-lovers-guide-rome-' . uniqid(),
                'excerpt' => 'Rome is a paradise for food lovers.',
                'content' => '<p>Rome culinary scene is legendary. From humble trattorias to Michelin stars.</p>',
                'author_name' => 'Marco Conti',
                'published' => true,
                'published_at' => now()->subDays(12),
                'minutes_to_read' => 7,
                'sort_order' => 4,
            ],
            [
                'category_id' => 5,
                'title' => 'Hiking the Amalfi Coast: The Best Trails with Breathtaking Views',
                'slug' => 'hiking-amalfi-coast-' . uniqid(),
                'excerpt' => 'Lace up your hiking boots and discover the stunning trails of the Amalfi Coast.',
                'content' => '<p>The Amalfi Coast is famous for its dramatic cliffs, pastel-colored villages, and turquoise waters.</p>',
                'author_name' => 'Elena Ferrari',
                'published' => true,
                'published_at' => now()->subDays(15),
                'minutes_to_read' => 6,
                'sort_order' => 5,
            ],
            [
                'category_id' => 3,
                'title' => 'The Ultimate Pilgrim Guide to Rome Jubilee 2025 Holy Sites',
                'slug' => 'pilgrim-guide-rome-jubilee-2025-' . uniqid(),
                'excerpt' => 'Plan your pilgrimage to Rome for the Jubilee 2025.',
                'content' => '<p>The Jubilee of 2025 is a special year of grace and pilgrimage in the Catholic Church.</p>',
                'author_name' => 'Father Antonio',
                'published' => true,
                'published_at' => now()->subDays(20),
                'minutes_to_read' => 10,
                'sort_order' => 6,
            ],
        ];

        foreach ($posts as $i => $postData) {
            $post = BlogPost::create($postData);
            $tagIds = [rand(1, 3), rand(4, 7), rand(8, 10)];
            $post->tags()->attach(array_unique($tagIds));

            if ($i < 3) {
                $childTopics = [
                    ['Key Highlights', 'Must-see'],
                    ['Practical Tips', 'Plan your visit'],
                ];
                foreach ($childTopics as $j => $topic) {
                    BlogPost::create([
                        'parent_id' => $post->id,
                        'category_id' => $postData['category_id'],
                        'title' => $topic[0],
                        'subtitle' => $topic[1],
                        'slug' => Str::slug($topic[0]) . '-' . uniqid(),
                        'excerpt' => 'This section covers important aspects you should not miss.',
                        'content' => 'This section covers important aspects you should not miss.',
                        'published' => true,
                        'published_at' => now()->subDays(5),
                        'sort_order' => $j + 1,
                    ]);
                }
            }
        }
    }
}
