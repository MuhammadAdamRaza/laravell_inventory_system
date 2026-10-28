<?php

namespace Tests\Unit;

use App\Services\DocumentTotals;
use App\Services\PurchaseCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseCalculatorTest extends TestCase
{
    public function test_it_calculates_exact_cents_with_discount_and_tax(): void
    {
        config(['purchases.tax_rate' => '10.00']);
        $calculation = (new PurchaseCalculator(new DocumentTotals))->calculate([
            ['product_id' => 1, 'ordered_quantity' => 3, 'unit_price' => '12.50'],
        ], '2.50');

        $this->assertSame('37.50', $calculation['subtotal']);
        $this->assertSame('3.50', $calculation['tax_amount']);
        $this->assertSame('38.50', $calculation['grand_total']);
        $this->assertSame('37.50', $calculation['items'][0]['line_total']);
    }

    public function test_it_rejects_a_discount_above_the_subtotal(): void
    {
        $this->expectException(ValidationException::class);

        (new PurchaseCalculator(new DocumentTotals))->calculate([
            ['product_id' => 1, 'ordered_quantity' => 1, 'unit_price' => '1.00'],
        ], '1.01');
    }
}
