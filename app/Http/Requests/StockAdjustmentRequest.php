<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity_change' => ['required', 'integer', 'not_in:0', 'min:-2147483648', 'max:2147483647'],
            'reason_code' => ['required', Rule::in(['count_correction', 'damage', 'loss', 'found', 'other'])],
            'reason' => ['required', 'string', 'min:10', 'max:10000'],
        ];
    }
}
