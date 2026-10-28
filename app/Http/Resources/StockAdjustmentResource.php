<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'product' => $this->whenLoaded('product', fn (): array => ['id' => $this->product->id, 'sku' => $this->product->sku, 'name' => $this->product->name]),
            'quantity_change' => $this->quantity_change,
            'reason_code' => $this->reason_code,
            'reason' => $this->reason,
            'creator' => $this->whenLoaded('creator', fn (): array => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
