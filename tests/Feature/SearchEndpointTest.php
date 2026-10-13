<?php

namespace Tests\Feature;

use App\Models\Categories;
use App\Models\City;
use App\Models\Location;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_headout_style_dropdown_suggestions(): void
    {
        $category = Categories::create([
            'name' => 'Dubai Adventure',
            'slug' => 'dubai-adventure',
            'type' => 'things_to_do',
            'status' => 1,
        ]);

        $dubaiProduct = $this->product('dubai-desert-safari', [
            'category_id' => $category->id,
            'title' => 'Dubai Desert Safari',
            'from_price' => 80,
            'original_price' => 100,
            'reviewRating' => 4.8,
            'reviewCount' => 120,
            'durationText' => '6 hrs',
            'is_cancellable' => true,
        ]);

        $this->location($dubaiProduct, 'Dubai', 'United Arab Emirates', 'AE');
        $this->image($dubaiProduct, 'https://example.test/dubai-safari.jpg');
        City::create([
            'city_name' => 'Dubai',
            'slug' => 'dubai-db-slug',
            'country_name' => 'United Arab Emirates',
            'country_short_code' => 'AE',
            'country_slug' => 'united-arab-emirates-db-slug',
            'item_status' => '1',
        ]);

        $parisProduct = $this->product('paris-food-tour', [
            'title' => 'Paris Food Tour',
            'from_price' => 120,
            'reviewRating' => 4.5,
            'reviewCount' => 50,
        ]);

        $this->location($parisProduct, 'Paris', 'France', 'FR');

        $response = $this->getJson('/api/v1/search?query=Dubai&currency=USD&language=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'Dubai')
            ->assertJsonPath('data.language', 'EN')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.results.0.type', 'CITY')
            ->assertJsonPath('data.results.0.values.0.displayName', 'Dubai')
            ->assertJsonPath('data.results.0.values.0.country.code', 'AE')
            ->assertJsonPath('data.results.0.values.0.url', rtrim(config('app.url'), '/') . '/api/v1/search/destination/dubai-db-slug')
            ->assertJsonPath('data.results.1.values.0.id', $dubaiProduct->id)
            ->assertJsonPath('data.results.1.values.0.displayName', 'Dubai Desert Safari')
            ->assertJsonPath('data.results.1.values.0.city.displayName', 'Dubai')
            ->assertJsonPath('data.results.1.values.0.country.displayName', 'United Arab Emirates')
            ->assertJsonPath('data.results.1.values.0.imageUrl', 'https://example.test/dubai-safari.jpg')
            ->assertJsonPath('data.results.1.values.0.url', rtrim(config('app.frontend_url'), '/') . '/products/dubai-desert-safari')
            ->assertJsonPath('data.results.2.values.0.id', $category->id)
            ->assertJsonPath('data.results.2.values.0.displayName', 'Dubai Adventure')
            ->assertJsonPath('data.results.2.values.0.city.displayName', 'Dubai')
            ->assertJsonPath('data.results.2.values.0.country.displayName', 'United Arab Emirates')
            ->assertJsonPath('data.results.2.values.0.imageUrl', 'https://example.test/dubai-safari.jpg')
            ->assertJsonPath('data.results.2.values.0.url', rtrim(config('app.url'), '/') . '/api/v1/search/collection/dubai-adventure')
            ->assertJsonPath('data.action.type', 'ACTION')
            ->assertJsonPath('data.action.displayName', 'Search Dubai')
            ->assertJsonPath('data.action.query', 'Dubai')
            ->assertJsonPath('data.action.url', rtrim(config('app.url'), '/') . '/api/v1/search?c=worldwide&q=Dubai')
            ->assertJsonMissingPath('data.productCards')
            ->assertJsonMissingPath('data.sections')
            ->assertJsonMissingPath('pagination')
            ->assertJsonMissingPath('data.active_tab')
            ->assertJsonMissingPath('data.tabs')
            ->assertJsonMissingPath('data.suggestions')
            ->assertJsonMissingPath('data.autocomplete')
            ->assertJsonMissingPath('data.products')
            ->assertJsonMissingPath('data.filters');

        $this->assertSame(['CITY', 'PRODUCT', 'COLLECTION'], collect($response->json('data.results'))->pluck('type')->all());
    }

    public function test_empty_search_returns_city_product_and_collection_suggestions(): void
    {
        $category = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'things_to_do',
            'status' => 1,
        ]);

        $product = $this->product('singapore-attraction-pass', [
            'category_id' => $category->id,
            'title' => 'Singapore Attraction Pass',
            'reviewCount' => 15,
        ]);

        $this->location($product, 'Singapore', 'Singapore', 'SG');
        $this->image($product, 'https://example.test/singapore-attraction.jpg');
        City::create([
            'city_name' => 'Singapore',
            'slug' => 'singapore-db-slug',
            'country_name' => 'Singapore',
            'country_short_code' => 'SG',
            'country_slug' => 'singapore-country-db-slug',
            'item_status' => '1',
        ]);

        $response = $this->getJson('/api/v1/search?currency=USD&language=EN');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', '')
            ->assertJsonPath('data.results.0.type', 'CITY')
            ->assertJsonPath('data.results.0.values.0.displayName', 'Singapore')
            ->assertJsonPath('data.results.0.values.0.url', rtrim(config('app.url'), '/') . '/api/v1/search/destination/singapore-db-slug')
            ->assertJsonPath('data.results.1.type', 'PRODUCT')
            ->assertJsonPath('data.results.1.values.0.id', $product->id)
            ->assertJsonPath('data.results.1.values.0.url', rtrim(config('app.frontend_url'), '/') . '/products/singapore-attraction-pass')
            ->assertJsonPath('data.results.2.type', 'COLLECTION')
            ->assertJsonPath('data.results.2.values.0.id', $category->id)
            ->assertJsonPath('data.results.2.values.0.displayName', 'Attraction')
            ->assertJsonPath('data.results.2.values.0.productsCount', 1)
            ->assertJsonPath('data.action', null);
    }

    public function test_search_returns_collection_for_matching_product_category(): void
    {
        $category = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'Product',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $product = $this->product('gapi-singapore', [
            'category_id' => $category->id,
            'title' => 'GAPI SINGAPORE',
            'product_api_id' => '53081',
            'platform' => 'globaltix',
            'reviewRating' => 4,
            'reviewCount' => 15,
        ]);

        $this->location($product, 'Singapore', 'Singapore', 'SG');
        $this->image($product, 'https://example.test/gapi-singapore.jpg');

        $response = $this->getJson('/api/v1/search?query=gapi&currency=USD&language=EN');

        $response->assertOk()
            ->assertJsonPath('data.results.1.values.0.id', $product->id)
            ->assertJsonPath('data.results.1.values.0.displayName', 'GAPI SINGAPORE')
            ->assertJsonPath('data.results.2.type', 'COLLECTION')
            ->assertJsonPath('data.results.2.values.0.id', $category->id)
            ->assertJsonPath('data.results.2.values.0.displayName', 'Attraction')
            ->assertJsonPath('data.results.2.values.0.productsCount', 1);
    }

    public function test_short_search_query_returns_matching_collections(): void
    {
        $category = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'Product',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $product = $this->product('gapi-singapore', [
            'category_id' => $category->id,
            'title' => 'GAPI SINGAPORE',
        ]);

        $this->location($product, 'Singapore', 'Singapore', 'SG');

        $response = $this->getJson('/api/v1/search?query=g&currency=USD&language=EN');

        $response->assertOk()
            ->assertJsonPath('data.results.1.values.0.id', $product->id)
            ->assertJsonPath('data.results.2.values.0.id', $category->id)
            ->assertJsonPath('data.results.2.values.0.displayName', 'Attraction');
    }

    public function test_search_query_matches_collection_name_without_products(): void
    {
        $category = Categories::create([
            'name' => 'Sightseeing',
            'slug' => 'sightseeing',
            'type' => 'Product',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $response = $this->getJson('/api/v1/search?query=g&currency=USD&language=EN');

        $response->assertOk()
            ->assertJsonPath('data.results.2.values.0.id', $category->id)
            ->assertJsonPath('data.results.2.values.0.displayName', 'Sightseeing');
    }

    public function test_trending_search_returns_collections_with_destinations_and_products(): void
    {
        $category = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'things_to_do',
            'status' => 1,
        ]);

        $product = $this->product('gapi-singapore', [
            'category_id' => $category->id,
            'title' => 'GAPI SINGAPORE',
            'reviewRating' => 4,
            'reviewCount' => 15,
        ]);

        $this->location($product, 'Singapore', 'Singapore', 'SG');
        $this->image($product, 'https://example.test/gapi-singapore.jpg');

        $response = $this->getJson('/api/v1/search/trending?currency=USD&language=EN');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.destinations.0.displayName', 'Singapore')
            ->assertJsonPath('data.collections.0.id', $category->id)
            ->assertJsonPath('data.collections.0.displayName', 'Attraction')
            ->assertJsonPath('data.collections.0.productsCount', 1)
            ->assertJsonPath('data.products.0.id', $product->id);
    }

    public function test_global_search_returns_headout_style_results_page(): void
    {
        $category = Categories::create([
            'name' => 'Dubai Adventure',
            'slug' => 'dubai-adventure',
            'type' => 'things_to_do',
            'status' => 1,
        ]);

        $dubaiProduct = $this->product('dubai-desert-safari', [
            'category_id' => $category->id,
            'title' => 'Dubai Desert Safari',
            'from_price' => 80,
            'original_price' => 100,
            'reviewRating' => 4.8,
            'reviewCount' => 120,
            'durationText' => '6 hrs',
            'is_cancellable' => true,
        ]);

        $this->location($dubaiProduct, 'Dubai', 'United Arab Emirates', 'AE');
        $this->image($dubaiProduct, 'https://example.test/dubai-safari.jpg');

        $parisProduct = $this->product('paris-food-tour', [
            'title' => 'Paris Food Tour',
            'from_price' => 120,
            'reviewRating' => 4.5,
            'reviewCount' => 50,
        ]);

        $this->location($parisProduct, 'Paris', 'France', 'FR');

        $response = $this->getJson('/api/v1/search/global?query=Dubai&currency=USD&language=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.query', 'Dubai')
            ->assertJsonPath('data.heading', 'Showing results for dubai')
            ->assertJsonPath('data.language', 'EN')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.sections.0.title', 'World Wide Collections')
            ->assertJsonPath('data.sections.1.title', 'World Wide Products')
            ->assertJsonPath('data.sections.0.items.0.displayName', 'Dubai Adventure')
            ->assertJsonPath('data.sections.1.items.0.id', $dubaiProduct->id)
            ->assertJsonPath('data.sections.1.items.0.title', 'Dubai Desert Safari')
            ->assertJsonPath('data.sections.1.items.0.image', 'https://example.test/dubai-safari.jpg')
            ->assertJsonPath('data.sections.1.items.0.rating', 4.8)
            ->assertJsonPath('data.sections.1.items.0.reviewCount', 120)
            ->assertJsonPath('data.sections.1.items.0.duration', '6 hrs')
            ->assertJsonPath('data.sections.1.items.0.city', 'Dubai')
            ->assertJsonPath('data.sections.1.items.0.price', 80)
            ->assertJsonPath('data.sections.1.items.0.currency', 'USD')
            ->assertJsonPath('data.sections.1.items.0.collection', 'Dubai Adventure')
            ->assertJsonPath('data.sections.1.items.0.url', rtrim(config('app.frontend_url'), '/') . '/products/dubai-desert-safari')
            ->assertJsonPath('data.sections.0.items.0.productsCount', 1)
            ->assertJsonMissingPath('data.result')
            ->assertJsonMissingPath('data.collectionCards')
            ->assertJsonMissingPath('data.productCards')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonMissingPath('data.results')
            ->assertJsonMissingPath('data.action')
            ->assertJsonMissingPath('data.active_tab')
            ->assertJsonMissingPath('data.tabs')
            ->assertJsonMissingPath('data.suggestions')
            ->assertJsonMissingPath('data.autocomplete')
            ->assertJsonMissingPath('data.products')
            ->assertJsonMissingPath('data.filters');

        $this->assertSame(['COLLECTIONS', 'PRODUCTS'], collect($response->json('data.sections'))->pluck('type')->all());
        $collectionKeys = array_keys($response->json('data.sections.0.items.0'));
        foreach (['id', 'displayName', 'city', 'country', 'imageUrl', 'url', 'productsCount', 'alternateDisplayName', 'primaryCity', 'language', 'medias', 'listingPrice', 'ratings', 'personas', 'content', 'durationInMinutes', 'subcategory', 'category', 'lastMonthBookingCount', 'guestCount', 'apiQuery'] as $key) {
            $this->assertContains($key, $collectionKeys);
        }

        $productKeys = array_keys($response->json('data.sections.1.items.0'));
        foreach (['id', 'title', 'image', 'rating', 'reviewCount', 'duration', 'city', 'price', 'currency', 'collection', 'url'] as $key) {
            $this->assertContains($key, $productKeys);
        }
    }

    public function test_destination_page_returns_lightweight_hero_collections_and_product_cards(): void
    {
        $category = Categories::create([
            'name' => 'Museums',
            'slug' => 'museums',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $product = $this->product('louvre-museum-small-group-tour', [
            'category_id' => $category->id,
            'title' => 'Louvre Museum Small Group Tour',
            'from_price' => 65,
            'reviewRating' => 4.8,
            'reviewCount' => 892,
            'durationText' => '3 Hours',
        ]);

        $this->location($product, 'Paris', 'France', 'FR');
        $this->image($product, 'https://example.test/louvre.jpg');

        City::create([
            'city_name' => 'Paris',
            'slug' => 'paris-db-slug',
            'country_name' => 'France',
            'country_short_code' => 'FR',
            'country_slug' => 'france-db-slug',
            'item_status' => '1',
        ]);

        $response = $this->getJson('/api/v1/search/destination/paris');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hero.title', 'Discover the best things to do in Paris')
            ->assertJsonPath('data.hero.destination.city', 'Paris')
            ->assertJsonPath('data.hero.destination.country', 'France')
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.0.items.0.active', true)
            ->assertJsonPath('data.sections.0.items.0.url', rtrim(config('app.url'), '/') . '/api/v1/search/destination/paris-db-slug/collections/museums')
            ->assertJsonPath('data.sections.1.type', 'PRODUCTS')
            ->assertJsonPath('data.sections.1.items.0.title', 'Louvre Museum Small Group Tour')
            ->assertJsonPath('data.sections.1.items.0.image', 'https://example.test/louvre.jpg')
            ->assertJsonPath('data.sections.1.items.0.rating', 4.8)
            ->assertJsonPath('data.sections.1.items.0.reviewCount', 892)
            ->assertJsonPath('data.sections.1.items.0.duration', '3 Hours')
            ->assertJsonPath('data.sections.1.items.0.city', 'Paris')
            ->assertJsonPath('data.sections.1.items.0.price', 65)
            ->assertJsonPath('data.sections.1.items.0.collection', 'Museums')
            ->assertJsonPath('data.sections.1.items.0.url', rtrim(config('app.frontend_url'), '/') . '/products/louvre-museum-small-group-tour')
            ->assertJsonMissingPath('data.destination')
            ->assertJsonMissingPath('data.products');
    }

    public function test_destination_collection_page_returns_only_selected_collection_products(): void
    {
        $museums = Categories::create([
            'name' => 'Museums',
            'slug' => 'museums',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $walking = Categories::create([
            'name' => 'Walking Tours',
            'slug' => 'walking-tours',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $museumProduct = $this->product('louvre-museum-small-group-tour', [
            'category_id' => $museums->id,
            'title' => 'Louvre Museum Small Group Tour',
            'from_price' => 65,
            'reviewRating' => 4.8,
            'reviewCount' => 892,
            'durationText' => '3 Hours',
        ]);
        $otherProduct = $this->product('paris-walking-tour', [
            'category_id' => $walking->id,
            'title' => 'Paris Walking Tour',
            'from_price' => 25,
            'reviewRating' => 4.3,
            'reviewCount' => 50,
            'durationText' => '2 Hours',
        ]);

        $this->location($museumProduct, 'Paris', 'France', 'FR');
        $this->location($otherProduct, 'Paris', 'France', 'FR');
        $this->image($museumProduct, 'https://example.test/louvre.jpg');
        $this->image($otherProduct, 'https://example.test/walking.jpg');

        City::create([
            'city_name' => 'Paris',
            'slug' => 'paris-db-slug',
            'country_name' => 'France',
            'country_short_code' => 'FR',
            'country_slug' => 'france-db-slug',
            'item_status' => '1',
        ]);

        $response = $this->getJson('/api/v1/search/destination/paris/collections/museums');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.collection.slug', 'museums')
            ->assertJsonPath('data.collection.active', true)
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.0.items.0.active', true)
            ->assertJsonPath('data.sections.1.type', 'PRODUCTS')
            ->assertJsonCount(1, 'data.sections.1.items')
            ->assertJsonPath('data.sections.1.items.0.title', 'Louvre Museum Small Group Tour')
            ->assertJsonPath('data.sections.1.items.0.url', rtrim(config('app.frontend_url'), '/') . '/products/louvre-museum-small-group-tour')
            ->assertJsonPath('data.sections.1.items.0.collection', 'Museums')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonMissingPath('data.sections.1.items.1');
    }

    public function test_card_search_empty_query_returns_collections_without_legacy_result_wrapper(): void
    {
        $category = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'Product',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $product = $this->product('gapi-singapore', [
            'category_id' => $category->id,
            'title' => 'GAPI SINGAPORE',
        ]);

        $this->location($product, 'Singapore', 'Singapore', 'SG');
        $this->image($product, 'https://example.test/gapi-singapore.jpg');

        $response = $this->getJson('/api/v1/search/card');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.0.items.0.id', $category->id)
            ->assertJsonPath('data.sections.1.items.0.id', $product->id)
            ->assertJsonMissingPath('data.result')
            ->assertJsonMissingPath('data.collectionCards')
            ->assertJsonMissingPath('data.productCards');
    }

    public function test_card_search_short_query_returns_categories_from_matching_products(): void
    {
        $category = Categories::create([
            'name' => 'Activity',
            'slug' => 'activity',
            'type' => 'Product',
            'status' => '1',
            'show_in_search' => 1,
        ]);

        $product = $this->product('private-walking-tour', [
            'category_id' => $category->id,
            'title' => 'Private Walking Tour',
        ]);

        $this->location($product, 'Attard', 'Malta', 'MT');

        $response = $this->getJson('/api/v1/search/card?query=p');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.0.items.0.id', $category->id)
            ->assertJsonPath('data.sections.0.items.0.displayName', 'Activity')
            ->assertJsonPath('data.sections.1.items.0.id', $product->id)
            ->assertJsonMissingPath('data.result')
            ->assertJsonMissingPath('data.collectionCards')
            ->assertJsonMissingPath('data.productCards');
    }

    public function test_city_page_returns_categories_from_products_and_location_categories(): void
    {
        $productCategory = Categories::create([
            'name' => 'Attraction',
            'slug' => 'attraction',
            'type' => 'Product',
            'status' => '1',
            'show_in_search' => 1,
        ]);

        $city = City::create([
            'city_name' => 'All Cities',
            'slug' => 'all-cities',
            'country_name' => 'Worldwide',
            'country_short_code' => 'WW',
            'country_slug' => 'worldwide',
            'item_status' => '1',
        ]);

        $locationCategory = Categories::create([
            'name' => 'All Cities Specials',
            'slug' => 'all-cities-specials',
            'type' => 'Product',
            'status' => '1',
            'show_in_search' => 1,
            'city_id' => $city->id,
        ]);

        $product = $this->product('all-cities-attraction', [
            'category_id' => $productCategory->id,
            'title' => 'All Cities Attraction',
        ]);

        $this->location($product, 'All Cities', 'Worldwide', 'WW');

        $response = $this->getJson('/api/v1/search/city/All%20Cities');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.1.type', 'PRODUCTS')
            ->assertJsonPath('data.sections.1.items.0.id', $product->id)
            ->assertJsonMissingPath('data.result')
            ->assertJsonMissingPath('data.collectionCards')
            ->assertJsonMissingPath('data.productCards');

        $collectionIds = collect($response->json('data.sections.0.items'))->pluck('id')->all();

        $this->assertContains($productCategory->id, $collectionIds);
        $this->assertContains($locationCategory->id, $collectionIds);
    }

    public function test_global_search_matches_all_words_when_phrase_is_not_exact(): void
    {
        $product = $this->product('dubai-desert-safari', [
            'title' => 'Dubai Desert Safari',
            'reviewCount' => 120,
        ]);

        $this->location($product, 'Dubai', 'United Arab Emirates', 'AE');

        $response = $this->getJson('/api/v1/search/global?search=Dubai%20Safari');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.sections.1.items.0.id', $product->id);
    }

    public function test_global_search_matches_comma_separated_product_api_ids(): void
    {
        $firstProduct = $this->product('supplier-show-1', [
            'title' => 'Evening Theatre Show',
        ]);
        $secondProduct = $this->product('supplier-show-2', [
            'title' => 'Morning Food Walk',
        ]);
        $otherProduct = $this->product('supplier-show-3', [
            'title' => 'Museum Pass',
        ]);

        $this->location($firstProduct, 'London', 'United Kingdom', 'GB');
        $this->location($secondProduct, 'Paris', 'France', 'FR');
        $this->location($otherProduct, 'Rome', 'Italy', 'IT');

        $response = $this->getJson('/api/v1/search/global?query=supplier-show-1,supplier-show-2');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 2);

        $ids = collect($response->json('data.sections.1.items'))->pluck('id')->all();

        $this->assertContains($firstProduct->id, $ids);
        $this->assertContains($secondProduct->id, $ids);
        $this->assertNotContains($otherProduct->id, $ids);
    }

    public function test_global_search_matches_category_products_without_category_tab(): void
    {
        $category = Categories::create([
            'name' => 'Water Sports',
            'slug' => 'water-sports',
            'type' => 'things_to_do',
            'status' => 1,
        ]);

        $product = $this->product('snorkeling-tour', [
            'category_id' => $category->id,
            'title' => 'Snorkeling Tour',
            'reviewRating' => 4.9,
            'reviewCount' => 80,
        ]);

        $this->location($product, 'Bali', 'Indonesia', 'ID');

        $response = $this->getJson('/api/v1/search/global?search=Water&tab=categories');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.sections.0.items.0.id', $category->id)
            ->assertJsonPath('data.sections.1.items.0.id', $product->id)
            ->assertJsonMissingPath('data.active_tab')
            ->assertJsonMissingPath('data.tabs');
    }

    public function test_price_rows_are_used_for_headout_listing_price_payload(): void
    {
        $product = $this->product('london-eye', [
            'title' => 'London Eye Admission Tickets',
            'from_price' => 70,
            'original_price' => 90,
            'default_currency' => 'USD',
            'reviewRating' => 4.7,
            'reviewCount' => 200,
        ]);

        Price::create([
            'product_id' => $product->id,
            'provider' => 'supplier',
            'selling_price' => 45,
            'discounted_price' => 40,
            'original_price' => 55,
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        $this->location($product, 'London', 'United Kingdom', 'GB');

        $response = $this->getJson('/api/v1/search/global?q=London%20Eye&currency=USD');

        $response->assertOk()
            ->assertJsonPath('data.sections.1.items.0.price', 70)
            ->assertJsonPath('data.sections.1.items.0.currency', 'USD')
            ->assertJsonPath('data.sections.1.items.0.title', 'London Eye Admission Tickets')
            ->assertJsonPath('data.sections.1.items.0.url', rtrim(config('app.frontend_url'), '/') . '/products/london-eye');
    }

    public function test_admin_search_display_fields_control_collection_rows(): void
    {
        $visibleCategory = Categories::create([
            'name' => 'Theatre',
            'slug' => 'theatre',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => true,
            'search_label' => 'London Theatre Tickets',
            'search_subtitle' => '86 experiences',
            'search_icon' => 'music',
            'search_sort_order' => 1,
        ]);

        $hiddenCategory = Categories::create([
            'name' => 'Hidden Theatre',
            'slug' => 'hidden-theatre',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => false,
        ]);

        $visibleProduct = $this->product('london-theatre-ticket', [
            'category_id' => $visibleCategory->id,
            'title' => 'Evening Show Ticket',
        ]);
        $hiddenProduct = $this->product('hidden-theatre-ticket', [
            'category_id' => $hiddenCategory->id,
            'title' => 'Hidden Show Ticket',
        ]);

        $this->location($visibleProduct, 'London', 'United Kingdom', 'GB');
        $this->location($hiddenProduct, 'London', 'United Kingdom', 'GB');

        $response = $this->getJson('/api/v1/search/global?query=London%20Theatre');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.items.0.displayName', 'London Theatre Tickets')
            ->assertJsonPath('data.sections.0.items.0.content.subtext', '86 experiences');

        $collectionIds = collect($response->json('data.sections.0.items'))->pluck('id')->all();
        $productIds = collect($response->json('data.sections.1.items'))->pluck('id')->all();

        $this->assertContains($visibleCategory->id, $collectionIds);
        $this->assertNotContains($hiddenCategory->id, $collectionIds);
        $this->assertContains($visibleProduct->id, $productIds);
        $this->assertContains($hiddenProduct->id, $productIds);

        $response->assertJsonPath('data.sections.1.items.0.title', 'Evening Show Ticket');
        $response->assertJsonPath('data.sections.1.items.0.collection', 'London Theatre Tickets');
    }

    public function test_provider_category_codes_are_humanized_for_search_display(): void
    {
        $category = Categories::create([
            'name' => 'TRANSFERS_AND_GROUND_TRANSPORT',
            'slug' => 'transfers-and-ground-transport',
            'type' => 'things_to_do',
            'status' => 1,
            'show_in_search' => true,
        ]);

        $product = $this->product('hurghada-airport-transfer', [
            'category_id' => $category->id,
            'title' => 'Hurghada Airport Transfer',
            'from_price' => 28,
            'original_price' => 40,
        ]);

        $this->location($product, 'Hurghada', 'Egypt', 'EG');
        $this->image($product, 'https://example.test/hurghada-transfer.jpg');

        $response = $this->getJson('/api/v1/search/global?search=Egypt');

        $response->assertOk()
            ->assertJsonPath('data.sections.0.items.0.displayName', 'Transfers and Ground Transport')
            ->assertJsonPath('data.sections.0.items.0.alternateDisplayName', 'Transfers and Ground Transport')
            ->assertJsonPath('data.sections.0.items.0.content.subtext', '1 product')
            ->assertJsonPath('data.sections.0.items.0.category.displayName', 'Transfers and Ground Transport')
            ->assertJsonPath('data.sections.1.items.0.collection', 'Transfers and Ground Transport')
            ->assertJsonMissing(['displayName' => 'TRANSFERS_AND_GROUND_TRANSPORT']);
    }

    public function test_legacy_tab_parameters_are_ignored_for_global_search(): void
    {
        $product = $this->product('dubai-hotel-style-tour', [
            'title' => 'Dubai Hotel Style Tour',
            'reviewCount' => 20,
        ]);

        $this->location($product, 'Dubai', 'United Arab Emirates', 'AE');

        $response = $this->getJson('/api/v1/search/global?search=Dubai&tab=hotels');

        $response->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.sections.0.type', 'COLLECTIONS')
            ->assertJsonPath('data.sections.1.items.0.id', $product->id)
            ->assertJsonMissing(['tab' => 'hotels']);
    }

    public function test_legacy_split_search_routes_are_removed(): void
    {
        $this->getJson('/api/v1/search/experience')->assertNotFound();
        $this->getJson('/api/v1/search/activity')->assertNotFound();
        $this->postJson('/api/v1/search/all-activitys')->assertNotFound();
    }

    // ─── FULLTEXT Fallback Tests ────────────────────────────────────
    //
    // These tests verify that applyProductSearchFields() correctly falls back
    // to LIKE queries when MATCH...AGAINST with the products_search_ft index
    // cannot handle very short search terms (< MySQL's ft_min_word_len, default 3).

    public function test_two_char_term_in_title_matches_via_like_fallback(): void
    {
        // Product whose title starts with "Ab" (a 2-char term below ft_min_word_len).
        // MySQL FULLTEXT index ignores terms shorter than ft_min_word_len,
        // so MATCH...AGAINST would not find it. The LIKE fallback on title must.
        $product = $this->product('abu-dhabi-tour', [
            'title' => 'Abu Dhabi Tour',
            'description' => 'Visit the grand mosque and desert dunes',
        ]);

        // Set slug to something that does NOT contain 'ab', to prove the
        // match comes from the title LIKE fallback, not the slug LIKE.
        $product->update(['slug' => 'xyz-non-matching-slug']);

        $this->location($product, 'Abu Dhabi', 'UAE', 'AE');

        $response = $this->getJson('/api/v1/search/global?search=Ab');

        $response->assertOk();
        $productIds = collect($response->json('data.sections.1.items'))->pluck('id')->all();
        $this->assertContains($product->id, $productIds, 'Two-char term "Ab" in title should match via LIKE fallback');
    }

    public function test_two_char_term_in_description_matches_via_like_fallback(): void
    {
        // Short term only in description (not title).
        // The LIKE fallback on description should catch this.
        $product = $this->product('safari-experience', [
            'title' => 'Safari Experience',
            'description' => 'Abu Dhabi desert adventure with bbq dinner',
        ]);

        // Title and slug don't contain 'bb', only description does
        $product->update(['slug' => 'safari-exp', 'title' => 'Safari Experience']);

        $this->location($product, 'Dubai', 'UAE', 'AE');

        $response = $this->getJson('/api/v1/search/global?search=bb');

        $response->assertOk();
        $productIds = collect($response->json('data.sections.1.items'))->pluck('id')->all();
        $this->assertContains($product->id, $productIds, 'Two-char term "bb" in description should match via LIKE fallback');
    }

    public function test_special_chars_only_query_does_not_crash(): void
    {
        // A query consisting entirely of special characters is cleaned to ''
        // by the regex sanitizer, triggering the empty-fallback path.
        $response = $this->getJson('/api/v1/search/global?search=!%40%23%24%25');

        $response->assertOk();
        // Should return empty results without crashing
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    public function test_special_char_cleaned_term_still_matches_via_like_token(): void
    {
        // Search term "hello!world" is cleaned to "hello world" (2 tokens).
        // With count($tokens) > 1, the per-token LIKE loop runs.
        // Each token's LIKE fallback catches matches in slug/description columns.
        $product = $this->product('hello-world-experience', [
            'title' => 'Hello World Experience',
            'description' => 'A wonderful hello world experience for everyone',
        ]);

        $this->location($product, 'Dubai', 'UAE', 'AE');

        // Query "hello!world" — the "!" is stripped, leaving "hello world"
        $response = $this->getJson('/api/v1/search/global?search=hello%21world');

        $response->assertOk();
        $productIds = collect($response->json('data.sections.1.items'))->pluck('id')->all();
        $this->assertContains($product->id, $productIds, 'Cleaned term "hello world" should match via token LIKE fallback');
    }

    private function product(string $slug, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_api_id' => $slug,
            'platform' => 'supplier',
            'provider' => 'supplier',
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => 'active',
            'published' => true,
            'default_currency' => 'USD',
            'from_price' => 50,
            'original_price' => 70,
            'reviewRating' => 4.0,
            'reviewCount' => 10,
        ], $overrides));
    }

    private function location(Product $product, string $city, string $country, string $countryCode): Location
    {
        return Location::create([
            'product_id' => $product->id,
            'city' => $city,
            'country' => $country,
            'countryCode' => $countryCode,
            'is_primary' => true,
        ]);
    }

    private function image(Product $product, string $url): ProductImage
    {
        return ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'supplier',
            'original_url' => $url,
            'large_url' => $url,
            'preview_url' => $url,
            'alt_text' => $product->title,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}
