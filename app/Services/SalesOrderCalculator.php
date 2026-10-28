<?php

namespace App\Services;

class SalesOrderCalculator
{
    public function __construct(private DocumentTotals $totals) {}

    /**
     * @param  list<array{product_id: int|string, quantity: int|string, unit_price: string|int}>  $items
     * @return array{subtotal: string, discount_amount: string, tax_amount: string, grand_total: string, items: list<array{product_id: int, quantity: int, unit_price: string, line_total: string}>}
     */
    public function calculate(array $items, string|int $discountAmount): array
    {
        return $this->totals->calculate($items, $discountAmount, (string) config('sales.tax_rate', '0.00'), 'sales order');
    }
}
