<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_products(): void
    {
        Product::factory()->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_finds_product_by_slug(): void
    {
        $product = Product::factory()->create(['price' => '2.50']);

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.price', '2.50');
    }

    public function test_inactive_product_is_not_found(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->slug}")->assertNotFound();
    }
}
