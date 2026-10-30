<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Checkout needs a signed-in customer.
        $this->customer = User::factory()->create();
        $this->actingAs($this->customer);
    }

    private function payload(array $items, array $customer = []): array
    {
        return [
            'customer' => array_merge(['name' => 'Test Buyer', 'phone' => '012345678'], $customer),
            'items' => $items,
        ];
    }

    public function test_it_creates_a_pending_order_using_database_prices(): void
    {
        $mouse = Product::factory()->create(['price' => '2.50', 'stock' => 5]);
        $cable = Product::factory()->create(['price' => '1.00', 'stock' => 5]);

        $response = $this->postJson('/api/orders', $this->payload([
            ['product_id' => $mouse->id, 'qty' => 2, 'price' => '0.01'], // client price is ignored
            ['product_id' => $cable->id, 'qty' => 1],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.total', '6.00');

        $this->assertMatchesRegularExpression('/^MR-\d{6}$/', $response->json('data.order_no'));
        $this->assertSame(5, $mouse->fresh()->stock); // reduced only when paid
    }

    public function test_guests_cannot_check_out(): void
    {
        auth()->logout();
        $product = Product::factory()->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ]))->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_order_belongs_to_the_signed_in_customer(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ]))->assertCreated();

        $this->assertTrue(Order::sole()->user->is($this->customer));
    }

    public function test_duplicate_lines_are_merged(): void
    {
        $product = Product::factory()->create(['price' => '1.00', 'stock' => 5]);

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
            ['product_id' => $product->id, 'qty' => 2],
        ]))
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.qty', 3)
            ->assertJsonPath('data.total', '3.00');
    }

    public function test_it_rejects_quantity_above_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 2],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_rejects_inactive_products(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_customer_phone_is_required(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/orders', $this->payload(
            [['product_id' => $product->id, 'qty' => 1]],
            ['phone' => ''],
        ))->assertUnprocessable()->assertJsonValidationErrors('customer.phone');
    }

    public function test_order_is_shown_by_public_id_without_contact_details(): void
    {
        $product = Product::factory()->create();
        $publicId = $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ]))->json('data.public_id');

        $this->getJson("/api/orders/{$publicId}")
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Test Buyer')
            ->assertJsonMissingPath('data.customer.phone');

        $numericId = Order::first()->id;
        $this->getJson("/api/orders/{$numericId}")->assertNotFound();
    }
}
