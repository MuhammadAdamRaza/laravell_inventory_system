<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DocumentTotals
{
    private const MAX_COLUMN_CENTS = 999999999999;

    /**
     * @param  list<array{product_id: int|string, quantity: int|string, unit_price: string|int}>  $items
     * @return array{subtotal: string, discount_amount: string, tax_amount: string, grand_total: string, items: list<array{product_id: int, quantity: int, unit_price: string, line_total: string}>}
     */
    public function calculate(array $items, string|int $discountAmount, string|int $taxRate, string $documentName): array
    {
        $normalizedItems = [];
        $subtotalCents = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPriceCents = MoneyAmount::toCents($item['unit_price']);

            if ($quantity < 1 || $unitPriceCents > intdiv(self::MAX_COLUMN_CENTS, $quantity)) {
                throw ValidationException::withMessages(['items' => "A {$documentName} line exceeds the supported monetary range."]);
            }

            $lineTotalCents = $quantity * $unitPriceCents;
            $subtotalCents = MoneyAmount::add($subtotalCents, $lineTotalCents);
            if ($subtotalCents > self::MAX_COLUMN_CENTS) {
                throw ValidationException::withMessages(['items' => "The {$documentName} subtotal exceeds the supported monetary range."]);
            }

            $normalizedItems[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => MoneyAmount::asDecimal($unitPriceCents),
                'line_total' => MoneyAmount::asDecimal($lineTotalCents),
            ];
        }

        $discountCents = MoneyAmount::toCents($discountAmount);
        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount cannot be greater than the document subtotal.']);
        }

        $taxRateBasisPoints = MoneyAmount::toCents((string) $taxRate);
        if ($taxRateBasisPoints > 10000) {
            throw ValidationException::withMessages(['discount_amount' => 'The configured tax rate must be between 0 and 100 percent.']);
        }

        $taxableCents = $subtotalCents - $discountCents;
        $taxCents = intdiv(($taxableCents * $taxRateBasisPoints) + 5000, 10000);
        $grandTotalCents = MoneyAmount::add($taxableCents, $taxCents);
        if ($grandTotalCents > self::MAX_COLUMN_CENTS) {
            throw ValidationException::withMessages(['discount_amount' => "The {$documentName} total exceeds the supported monetary range."]);
        }

        return [
            'subtotal' => MoneyAmount::asDecimal($subtotalCents),
            'discount_amount' => MoneyAmount::asDecimal($discountCents),
            'tax_amount' => MoneyAmount::asDecimal($taxCents),
            'grand_total' => MoneyAmount::asDecimal($grandTotalCents),
            'items' => $normalizedItems,
        ];
    }
}
