<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $can = static fn (string $permission): bool => $user?->can($permission) ?? false;

        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'status' => $this->status,
            'source_warehouse' => $this->whenLoaded('sourceWarehouse', fn (): array => ['id' => $this->sourceWarehouse->id, 'name' => $this->sourceWarehouse->name]),
            'destination_warehouse' => $this->whenLoaded('destinationWarehouse', fn (): array => ['id' => $this->destinationWarehouse->id, 'name' => $this->destinationWarehouse->name]),
            'notes' => $this->notes,
            'creator' => $this->whenLoaded('creator', fn (): array => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'dispatcher' => $this->whenLoaded('dispatcher', fn (): ?array => $this->dispatcher ? ['id' => $this->dispatcher->id, 'name' => $this->dispatcher->name] : null),
            'receiver' => $this->whenLoaded('receiver', fn (): ?array => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn (): array => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product ? ['id' => $item->product->id, 'sku' => $item->product->sku, 'name' => $item->product->name, 'unit' => $item->product->unit] : null,
                'dispatched_quantity' => $item->dispatched_quantity,
                'accepted_quantity' => $item->accepted_quantity,
                'returned_quantity' => $item->returned_quantity,
            ])->all()),
            'allowed_actions' => [
                'edit' => $this->status === 'draft' && $can('transfers.create'),
                'dispatch' => $this->status === 'draft' && $can('transfers.dispatch'),
                'receive' => $this->status === 'in_transit' && $can('transfers.receive'),
                'cancel' => $this->status === 'draft' && $can('transfers.cancel'),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
