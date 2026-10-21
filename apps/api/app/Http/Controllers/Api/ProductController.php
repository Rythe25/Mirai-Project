<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()->active()->orderBy('name')->paginate(24)
        );
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        return ProductResource::make($product);
    }
}
