<?php

namespace App\Services\Payway;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PaywayException extends RuntimeException
{
    public function render(): JsonResponse
    {
        // 503, not 502: Cloudflare swaps origin 502/504 for its own page, which has no CORS headers.
        return response()->json(['message' => 'ABA PayWay is not available. Try again.'], 503);
    }
}
