<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_published_blogs()
    {
        $cat = BlogCategory::create(['name' => 'Travel', 'slug' => 'travel']);
        BlogPost::create(['title' => 'Public Post 1', 'slug' => 'public-post-1', 'excerpt' => 'ex', 'content' => 'c', 'published' => true, 'category_id' => $cat->id]);
        BlogPost::create(['title' => 'Draft Post', 'slug' => 'draft-post', 'excerpt' => 'ex', 'content' => 'c', 'published' => false]);

        $response = $this->getJson('/api/v1/blogs');
        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('pagination', $json);
        $this->assertNotEmpty($json['data']);
    }

    public function test_public_can_view_blog_detail()
    {
        $cat = BlogCategory::create(['name' => 'Travel', 'slug' => 'travel']);
        $post = BlogPost::create(['title' => 'Detail Post', 'slug' => 'detail-post', 'excerpt' => 'ex', 'content' => '<p>c</p>', 'published' => true, 'category_id' => $cat->id]);

        // Create a child article (sub-article)
        BlogPost::create([
            'parent_id' => $post->id,
            'title' => 'Child Article',
            'slug' => 'child-article',
            'excerpt' => 'Child excerpt',
            'content' => 'Child content',
            'published' => true,
            'category_id' => $cat->id,
        ]);

        // Attach tag
        $post->tags()->create(['name' => 'Travel', 'slug' => 'travel']);

        $response = $this->getJson('/api/v1/blogs/' . $post->slug);
        $response->assertOk()->assertJsonPath('data.title', 'Detail Post');
        $json = $response->json();
        $this->assertArrayHasKey('tags', $json['data']);
        $this->assertArrayHasKey('articles', $json['data']);
        $this->assertCount(1, $json['data']['articles']);
    }
}
