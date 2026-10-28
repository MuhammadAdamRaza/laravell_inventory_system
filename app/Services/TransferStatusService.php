<?php

namespace App\Services;

use App\Models\User;
use App\Models\WarehouseTransfer;
use Illuminate\Validation\ValidationException;

class TransferStatusService
{
    public function transition(WarehouseTransfer $transfer, string $targetStatus, User $actor): WarehouseTransfer
    {
        $lockedTransfer = WarehouseTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
        $transitions = [
            'draft' => ['in_transit', 'cancelled'],
            'in_transit' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
        if (! in_array($targetStatus, $transitions[$lockedTransfer->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A warehouse transfer cannot move from {$lockedTransfer->status} to {$targetStatus}.",
            ]);
        }

        $attributes = ['status' => $targetStatus];
        if ($targetStatus === 'in_transit') {
            $attributes['dispatched_by'] = $actor->id;
            $attributes['dispatched_at'] = now();
        } elseif ($targetStatus === 'completed') {
            $attributes['received_by'] = $actor->id;
            $attributes['received_at'] = now();
        } elseif ($targetStatus === 'cancelled') {
            $attributes['cancelled_at'] = now();
        }

        $lockedTransfer->update($attributes);

        return $lockedTransfer->refresh();
    }
}
