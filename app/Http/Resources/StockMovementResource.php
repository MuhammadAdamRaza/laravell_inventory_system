<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'product_name' => $this->product?->name,
            'sku' => $this->product?->sku,
            'warehouse_name' => $this->warehouse?->name,
            'movement_type' => $this->movement_type,
            'quantity_change' => (int) $this->quantity_change,
            'balance_after' => (int) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reason' => $this->reason,
            'user_name' => $this->user?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
