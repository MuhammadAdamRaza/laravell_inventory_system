<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $moneyString = ['numeric', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'discount_amount' => ['nullable', ...$moneyString],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('status', 'active'),
            ],
            'items.*.ordered_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.unit_price' => ['required', ...$moneyString],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.product_id.distinct' => 'A product can only appear once on a purchase.',
            'items.*.product_id.exists' => 'Each selected product must be active.',
            'discount_amount.string' => 'The discount amount must be sent as an exact decimal string.',
            'items.*.unit_price.string' => 'Unit prices must be sent as exact decimal strings.',
        ];
    }
}
