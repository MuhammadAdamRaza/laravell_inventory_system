<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'amount' => ['required', 'numeric', 'string', 'regex:/^\d+(?:\.\d{1,2})?$/', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:191'],
        ];
    }
}
