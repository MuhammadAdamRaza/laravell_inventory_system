<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
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
        return [
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'return_date' => ['nullable', 'date'],
            'reason_summary' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sales_order_item_id' => ['required', 'integer', 'distinct', 'exists:sales_order_items,id'],
            'items.*.quantity_returned' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.reason' => ['required', Rule::in(['damaged_in_transit', 'wrong_item', 'customer_changed_mind', 'defective', 'other'])],
            'items.*.condition' => ['required', Rule::in(['new_unopened', 'opened', 'damaged', 'defective'])],
            'items.*.restock_eligible' => ['required', 'boolean'],
        ];
    }
}
