<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (int) $this->quantity;
        $minStock = (int) ($this->product?->minimum_stock ?? 0);

        $stockStatus = 'ok';
        if ($quantity === 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($quantity <= $minStock) {
            $stockStatus = 'low_stock';
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'warehouse_name' => $this->warehouse?->name,
            'quantity' => $quantity,
            'minimum_stock' => $minStock,
            'stock_status' => $stockStatus,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
