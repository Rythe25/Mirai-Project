<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\PlaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Smoke tests for the backoffice screens (added after the Filament 5 upgrade). */
class BackofficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => UserRole::Employee]));
    }

    public function test_a_user_without_a_role_cannot_open_the_backoffice(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(ProductResource::getUrl('index'))->assertForbidden();
    }

    public function test_every_role_can_open_the_backoffice(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->get(ProductResource::getUrl('index'))->assertOk();
        }
    }

    public function test_backoffice_pages_render(): void
    {
        $product = Product::factory()->create();

        $this->get(ProductResource::getUrl('index'))->assertOk();
        $this->get(ProductResource::getUrl('create'))->assertOk();
        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertOk();
        $this->get(OrderResource::getUrl('index'))->assertOk();
    }

    public function test_products_table_lists_products(): void
    {
        $products = Product::factory()->count(3)->create();

        Livewire::test(ListProducts::class)->assertCanSeeTableRecords($products);
    }

    public function test_staff_can_create_a_product(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Mouse', 'slug' => 'test-mouse', 'sku' => 'TM-001',
                'price' => '12.50', 'stock' => 4, 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'TM-001', 'stock' => 4]);
    }

    public function test_staff_can_edit_a_product(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['stock' => 9])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_orders_table_lists_orders(): void
    {
        $product = Product::factory()->create(['price' => '2.50', 'stock' => 5]);
        $order = app(PlaceOrder::class)->handle(
            ['name' => 'Test Buyer', 'phone' => '012345678'],
            [['product_id' => $product->id, 'qty' => 1]],
        );

        Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$order]);
    }
}
