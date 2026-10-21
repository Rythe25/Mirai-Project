<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /** Deterministic: same SKUs every run, safe to re-run. */
    public function run(): void
    {
        $products = [
            ['sku' => 'TEST-CABLE-01', 'name' => 'Test USB-C Cable', 'price' => '1.00', 'stock' => 50],
            ['sku' => 'TEST-MOUSE-01', 'name' => 'Test Wireless Mouse', 'price' => '2.50', 'stock' => 20],
            ['sku' => 'TEST-KEYB-01', 'name' => 'Test Mechanical Keyboard', 'price' => '5.00', 'stock' => 10],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['sku' => $product['sku']], $product + [
                'slug' => Str::slug($product['name']),
                'description' => 'Sandbox test product.',
                'currency' => 'USD',
                'is_active' => true,
            ]);
        }
    }
}
