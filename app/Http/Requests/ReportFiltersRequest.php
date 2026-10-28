<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'status' => [
                'sometimes',
                'nullable',
                Rule::in([
                    'draft',
                    'pending_approval',
                    'approved',
                    'partially_received',
                    'received',
                    'cancelled',
                ]),
            ],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = CarbonImmutable::parse($this->input('from') ?: today()->startOfMonth()->toDateString());
                $to = CarbonImmutable::parse($this->input('to') ?: today()->toDateString());

                if ($to->lessThan($from)) {
                    $validator->errors()->add('to', 'The end date must be on or after the start date.');

                    return;
                }

                if ($from->addYears(2)->lessThan($to)) {
                    $validator->errors()->add('to', 'The selected date range cannot exceed two years.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = array_merge([
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'limit' => 10,
        ], $this->validated());

        $filters['from'] = empty($filters['from'])
            ? today()->startOfMonth()->toDateString()
            : CarbonImmutable::parse($filters['from'])->toDateString();
        $filters['to'] = empty($filters['to'])
            ? today()->toDateString()
            : CarbonImmutable::parse($filters['to'])->toDateString();

        return $filters;
    }

    public function hasCustomDateRange(): bool
    {
        return $this->filled('from') || $this->filled('to');
    }
}
