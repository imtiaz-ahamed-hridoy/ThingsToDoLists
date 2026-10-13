<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_seeder_populates_supplier_portal_api_data(): void
    {
        $this->seed(SupplierSeeder::class);

        $supplier = User::query()
            ->where('email', 'supplier@example.com')
            ->firstOrFail();

        $this->postJson('/api/v1/supplier/login', [
            'email' => 'supplier@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login Successful')
            ->assertJsonPath('data.role', 'Vendor')
            ->assertJsonPath('data.vendor.supplier_id', 'SUP-DEMO-001');

        Sanctum::actingAs($supplier);

        $this->getJson('/api/v1/supplier/profile')
            ->assertOk()
            ->assertJsonPath('data.supplier_id', 'SUP-DEMO-001')
            ->assertJsonPath('data.currency', 'EUR');

        $this->getJson('/api/v1/supplier-dashboard/home')
            ->assertOk()
            ->assertJsonPath('data.vendor_name', 'Royal Demo Experiences')
            ->assertJsonPath('data.published_count', 3)
            ->assertJsonCount(1, 'data.draft_products');

        $productsResponse = $this->getJson('/api/v1/supplier-dashboard/products')
            ->assertOk()
            ->assertJsonPath('data.total', 4);

        $this->assertContains(
            'London Thames Sunset Cruise',
            collect($productsResponse->json('data.data'))->pluck('title')->all()
        );

        $this->getJson('/api/v1/supplier-dashboard/bookings')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.data.0.product_title', 'London Thames Sunset Cruise');

        $this->getJson('/api/v1/supplier-dashboard/reviews')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.data.0.status', 'Active');

        $this->getJson('/api/v1/supplier-dashboard/finance')
            ->assertOk()
            ->assertJsonPath('data.invoices.0.invoice', 'INV-SUP-2026-002')
            ->assertJsonPath('data.payment_confirmations.0.payment_confirmation', 'PAY-SUP-2026-001')
            ->assertJsonPath('data.tax_details.vat_number', 'GB123456789');

        $this->getJson('/api/v1/supplier/finance/payouts')
            ->assertOk()
            ->assertJsonPath('data.payouts.0.booking_reference', 'SUP-DEMO-BOOK-1001');

        $this->getJson('/api/v1/supplier-dashboard/create-products')
            ->assertOk()
            ->assertJsonCount(3, 'data.active_products')
            ->assertJsonCount(1, 'data.draft_products');

        $draft = Product::query()
            ->where('product_api_id', 'SUP-DXB-DESERT-DRAFT')
            ->firstOrFail();

        $this->getJson('/api/v1/supplier-dashboard/create-products/draft/' . $draft->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Dubai Desert Safari Draft')
            ->assertJsonPath('data.locations.0.city', 'Dubai')
            ->assertJsonPath('data.images.0.url', 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1200&q=80');
    }
}
