<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\SyncPayment;
use App\Services\Payway\PaywayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaywayCallbackController extends Controller
{
    /** PayWay pushback. Only proves "something happened"; SyncPayment asks PayWay what. */
    public function __invoke(Request $request, PaywayClient $payway, SyncPayment $sync): JsonResponse
    {
        // Raw body: Laravel's input middleware trims strings and turns '' into null, which would change the hash.
        $payload = json_decode($request->getContent(), true) ?? $request->request->all();

        if (! $payway->verifyCallback($payload, $request->header('X-PayWay-HMAC-SHA512'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payment = Payment::query()->where('tran_id', (string) ($payload['tran_id'] ?? ''))->first();

        if (! $payment) {
            return response()->json(['message' => 'Unknown transaction.'], 404);
        }

        $sync->handle($payment); // PaywayException → 503, so PayWay retries

        return response()->json(['ok' => true]);
    }
}
