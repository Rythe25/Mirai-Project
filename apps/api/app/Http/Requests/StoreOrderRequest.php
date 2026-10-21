<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest checkout
    }

    public function rules(): array
    {
        return [
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'customer.email' => ['nullable', 'email', 'max:190'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
