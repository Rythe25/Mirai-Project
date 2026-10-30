<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * @param  array{name: string, phone: string, email?: ?string}  $customer
     * @param  list<array{product_id: int, qty: int}>  $items
     * @param  ?User  $user  the signed-in customer (POST /orders always passes one)
     */
    public function handle(array $customer, array $items, ?User $user = null): Order
    {
        // Merge duplicate lines for the same product.
        $lines = collect($items)
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->sum('qty'));

        return DB::transaction(function () use ($customer, $lines, $user) {
            $products = Product::query()
                ->active()
                ->whereKey($lines->keys())
                ->get()
                ->keyBy('id');

            foreach ($lines as $productId => $qty) {
                $product = $products->get($productId)
                    ?? throw ValidationException::withMessages([
                        'items' => "Product {$productId} is not available.",
                    ]);

                // Advisory check only — stock is reduced when the order is paid.
                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Only {$product->stock} left of {$product->name}.",
                    ]);
                }
            }

            $order = Order::create([
                'user_id' => $user?->id,
                'status' => OrderStatus::Pending,
                'currency' => 'USD',
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
            ]);

            $subtotal = '0.00';

            foreach ($lines as $productId => $qty) {
                $product = $products[$productId];
                $lineTotal = bcmul($product->price, (string) $qty, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $product->price,
                    'qty' => $qty,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $order->load('items');
        });
    }
}
