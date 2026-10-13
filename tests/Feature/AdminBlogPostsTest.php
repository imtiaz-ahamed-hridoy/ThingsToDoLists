<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_blogs_index()
    {
        $response = $this->actingAs($this->admin())->get('/admin/cms-settings/blogs');
        $response->assertOk()->assertSee('Blog Posts');
    }

    public function test_admin_can_create_blog_with_featured_image()
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('feature.jpg');

        $response = $this->actingAs($this->admin())->post('/admin/cms-settings/blogs/store', [
            'title' => 'My Blog Post',
            'excerpt' => 'Short excerpt',
            'content' => '<p>Content</p>',
            'featured_image' => $image,
            'published' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.blogs.index'));

        $this->assertDatabaseHas('blog_posts', ['title' => 'My Blog Post']);

        $post = BlogPost::where('title', 'My Blog Post')->first();
        $this->assertNotNull($post->getRawOriginal('featured_image'));
        Storage::disk('public')->assertExists($post->getRawOriginal('featured_image'));
    }

    public function test_admin_can_create_blog_with_tags_and_child_articles()
    {
        $response = $this->actingAs($this->admin())->post('/admin/cms-settings/blogs/store', [
            'title' => 'Parent Post',
            'excerpt' => 'Short',
            'content' => '<p>OK</p>',
            'tags_new' => 'Travel, Tips',
            'child_title' => ['Child Article 1', 'Child Article 2'],
            'child_subtitle' => ['Location 1', 'Location 2'],
            'child_description' => ['Description 1', 'Description 2'],
            'published' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.blogs.index'));

        $post = BlogPost::where('title', 'Parent Post')->first();
        $this->assertNotNull($post);
        $this->assertNull($post->parent_id);

        // Check tags
        $this->assertDatabaseHas('blog_tags', ['name' => 'Travel']);
        $this->assertDatabaseHas('blog_post_tag', ['post_id' => $post->id]);

        // Check child articles
        $this->assertDatabaseHas('blog_posts', ['title' => 'Child Article 1', 'parent_id' => $post->id]);
        $this->assertDatabaseHas('blog_posts', ['title' => 'Child Article 2', 'parent_id' => $post->id]);
    }

    public function test_admin_can_create_blog_with_new_category()
    {
        $response = $this->actingAs($this->admin())->post('/admin/cms-settings/blogs/store', [
            'title' => 'Blog with new category',
            'excerpt' => 'Short',
            'content' => '<p>OK</p>',
            'category_new' => 'New Dynamic Category',
            'published' => 1,
        ]);

        $response->assertRedirect(route('cmsSettings.blogs.index'));

        $this->assertDatabaseHas('blog_categories', ['name' => 'New Dynamic Category', 'slug' => 'new-dynamic-category']);
        
        $category = \App\Models\BlogCategory::where('name', 'New Dynamic Category')->first();
        $this->assertDatabaseHas('blog_posts', ['title' => 'Blog with new category', 'category_id' => $category->id]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin_', true) . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'is_agree' => true,
        ]);
    }
}
