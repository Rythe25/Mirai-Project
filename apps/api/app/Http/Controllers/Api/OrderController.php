<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\PlaceOrder;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $order = $placeOrder->handle(
            $request->validated('customer'),
            $request->validated('items'),
        );

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order->load('items'));
    }
}
