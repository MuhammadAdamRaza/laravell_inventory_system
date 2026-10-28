<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesReturnRequest extends FormRequest
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
            'return_date' => ['sometimes', 'required', 'date'],
            'reason_summary' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'items.*.sales_order_item_id' => ['required_with:items', 'integer', 'distinct', 'exists:sales_order_items,id'],
            'items.*.quantity_returned' => ['required_with:items', 'integer', 'min:1', 'max:2147483647'],
            'items.*.reason' => ['required_with:items', Rule::in(['damaged_in_transit', 'wrong_item', 'customer_changed_mind', 'defective', 'other'])],
            'items.*.condition' => ['required_with:items', Rule::in(['new_unopened', 'opened', 'damaged', 'defective'])],
            'items.*.restock_eligible' => ['required_with:items', 'boolean'],
        ];
    }
}
