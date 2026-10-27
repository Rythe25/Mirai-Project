<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest checkout; the order is found by its unguessable public_id
    }

    public function rules(): array
    {
        return [
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('method') ?? PaymentMethod::Khqr->value);
    }
}
