<?php

namespace Tests\Feature;

use App\Models\FeatureType;
use App\Models\HomePageBanner;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFeatureApisTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_banners_returns_active_only()
    {
        HomePageBanner::create(['title' => 'A', 'status' => true, 'sort_order' => 1]);
        HomePageBanner::create(['title' => 'B', 'status' => false, 'sort_order' => 2]);

        $response = $this->getJson('/api/v1/home-page-banners');
        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(1, $json['data']);
    }

    public function test_public_feature_types_and_products_pagination()
    {
        $type = FeatureType::create(['name' => 'Featured', 'slug' => 'featured', 'status' => true, 'sort_order' => 1]);

        $p1 = Product::create(['provider' => 'supplier', 'slug' => 'p1', 'title' => 'P1', 'status' => 'active', 'published' => true]);
        $p2 = Product::create(['provider' => 'supplier', 'slug' => 'p2', 'title' => 'P2', 'status' => 'active', 'published' => true]);

        $type->products()->attach([$p1->id, $p2->id]);

        $resp = $this->getJson('/api/v1/feature-types');
        $resp->assertOk();
        $json = $resp->json();
        $this->assertArrayHasKey('pagination', $json);

        $resp2 = $this->getJson('/api/v1/feature-types/featured/products');
        $resp2->assertOk();
        $json2 = $resp2->json();
        $this->assertArrayHasKey('pagination', $json2);
        $this->assertCount(2, $json2['data']);
    }
}
