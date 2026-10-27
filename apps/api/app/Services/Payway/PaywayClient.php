<?php

namespace App\Services\Payway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PaywayClient
{
    private const GENERATE_QR = '/api/payment-gateway/v1/payments/generate-qr';

    private const CHECK_TRANSACTION = '/api/payment-gateway/v1/payments/check-transaction-2';

    private const PURCHASE = '/api/payment-gateway/v1/payments/purchase';

    /**
     * Create an ABA KHQR. Keys are in PayWay's hash order; empty fields still count in the hash.
     *
     * @return array{qrString: string, qrImage: string, abapay_deeplink?: ?string}
     */
    public function generateQr(string $tranId, string $amount, string $currency): array
    {
        // Same as the verified Postman request: amount sent as a JSON number, hashed as "5" / "2.5" / "0.01".
        $amount = (string) (float) $amount;
        $callback = config('services.payway.callback_url');

        $body = $this->post(self::GENERATE_QR, [
            'req_time' => $this->reqTime(),
            'merchant_id' => (string) config('services.payway.merchant_id'),
            'tran_id' => $tranId,
            'amount' => 0 + $amount,
            'items' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'purchase_type' => 'purchase',
            'payment_option' => 'abapay_khqr',
            'callback_url' => $callback ? base64_encode($callback) : '',
            'return_deeplink' => '',
            'currency' => $currency,
            'custom_fields' => '',
            'return_params' => '',
            'payout' => '',
            'lifetime' => (int) config('services.payway.lifetime'),
            'qr_image_template' => 'template3_color',
        ]);

        if ((string) ($body['status']['code'] ?? '') !== '0') {
            throw new PaywayException('generate-qr failed: '.json_encode($body['status'] ?? $body));
        }

        return $body;
    }

    /**
     * Signed Purchase form for PayWay's hosted card checkout. The browser posts it to PayWay,
     * so card numbers never reach Mirai. Keys are in PayWay's 24-field hash order.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function cardCheckout(string $tranId, string $amount, string $currency, string $returnTo): array
    {
        $callback = config('services.payway.callback_url');

        $fields = [
            'req_time' => $this->reqTime(),
            'merchant_id' => (string) config('services.payway.merchant_id'),
            'tran_id' => $tranId,
            'amount' => (string) (float) $amount,
            'items' => '',
            'shipping' => '',
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'phone' => '',
            'type' => 'purchase',
            'payment_option' => 'cards',
            'return_url' => $callback ? base64_encode($callback) : '',
            'cancel_url' => $returnTo,
            'continue_success_url' => $returnTo,
            'return_deeplink' => '',
            'currency' => $currency,
            'custom_fields' => '',
            'return_params' => '',
            'payout' => '',
            'lifetime' => (string) config('services.payway.lifetime'),
            'additional_params' => '',
            'google_pay_token' => '',
            'skip_success_page' => '',
        ];
        $hash = $this->sign(implode('', $fields));

        return [
            'action' => config('services.payway.base_url').self::PURCHASE,
            // payment_gate=0: the hosted card checkout even though the profile also has the QR Payment API
            // (without it PayWay answers with a KHQR). Neither payment_gate nor view_type is hashed.
            'fields' => array_filter($fields, fn ($value) => $value !== '')
                + ['hash' => $hash, 'view_type' => 'hosted_view', 'payment_gate' => '0'],
        ];
    }

    /**
     * @return array{status: string, amount: ?string, currency: ?string, apv: ?string}
     */
    public function checkTransaction(string $tranId): array
    {
        $body = $this->post(self::CHECK_TRANSACTION, [
            'req_time' => $this->reqTime(),
            'merchant_id' => (string) config('services.payway.merchant_id'),
            'tran_id' => $tranId,
        ]);

        $code = (string) ($body['status']['code'] ?? '');

        if ($code === '6') {
            return ['status' => 'NOT_FOUND', 'amount' => null, 'currency' => null, 'apv' => null];
        }

        if ($code !== '00') {
            throw new PaywayException('check-transaction failed: '.json_encode($body['status'] ?? $body));
        }

        $data = $body['data'] ?? [];

        return [
            'status' => (string) ($data['payment_status'] ?? 'UNKNOWN'),
            'amount' => isset($data['payment_amount']) ? number_format((float) $data['payment_amount'], 2, '.', '') : null,
            'currency' => $data['payment_currency'] ?? null,
            'apv' => $data['apv'] ?? null,
        ];
    }

    /** PayWay's sample recipe: sort keys, join values (arrays as JSON), HMAC-SHA512, Base64. */
    public function verifyCallback(array $payload, ?string $signature): bool
    {
        ksort($payload);
        $joined = implode('', array_map(
            fn ($value) => is_array($value) ? json_encode($value) : (string) $value,
            $payload,
        ));

        return is_string($signature) && hash_equals($this->sign($joined), $signature);
    }

    /** Hash every field in order, then send only the non-empty ones. */
    private function post(string $path, array $fields): array
    {
        $fields['hash'] = $this->sign(implode('', $fields));

        try {
            $response = Http::baseUrl(config('services.payway.base_url'))
                ->acceptJson()
                ->timeout(15)
                ->post($path, array_filter($fields, fn ($value) => $value !== ''));
        } catch (ConnectionException $e) {
            throw new PaywayException("PayWay {$path} unreachable: {$e->getMessage()}", previous: $e);
        }

        return $response->json()
            ?? throw new PaywayException("PayWay {$path} returned HTTP {$response->status()} without JSON.");
    }

    private function sign(string $data): string
    {
        return base64_encode(hash_hmac('sha512', $data, (string) config('services.payway.api_key'), true));
    }

    private function reqTime(): string
    {
        return now('UTC')->format('YmdHis');
    }
}
