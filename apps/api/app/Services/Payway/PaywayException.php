<?php

namespace App\Services\Payway;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaywayException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'ABA PayWay is not available. Try again.'], 502);
    }
}
