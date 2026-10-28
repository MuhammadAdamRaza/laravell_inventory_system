<?php

namespace App\Services;

class PurchaseCalculator
{
    public function __construct(private DocumentTotals $totals) {}

    /**
     * @param  list<array{product_id: int|string, ordered_quantity: int|string, unit_price: int|string}>  $items
     * @return array{subtotal: string, discount_amount: string, tax_amount: string, grand_total: string, items: list<array{product_id: int, ordered_quantity: int, unit_price: string, line_total: string}>}
     */
    public function calculate(array $items, string|int $discountAmount): array
    {
        $totals = $this->totals->calculate(
            array_map(static fn (array $item): array => [
                'product_id' => $item['product_id'],
                'quantity' => $item['ordered_quantity'],
                'unit_price' => $item['unit_price'],
            ], $items),
            $discountAmount,
            (string) config('purchases.tax_rate', '0'),
            'purchase',
        );

        $totals['items'] = array_map(static fn (array $item): array => [
            'product_id' => $item['product_id'],
            'ordered_quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total'],
        ], $totals['items']);

        return $totals;
    }
}
