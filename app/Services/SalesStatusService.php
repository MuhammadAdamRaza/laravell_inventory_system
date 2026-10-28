<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Validation\ValidationException;

class SalesStatusService
{
    public function transition(SalesOrder $order, string $targetStatus): SalesOrder
    {
        $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($order->getKey());
        $transitions = ['draft' => ['confirmed', 'cancelled'], 'confirmed' => [], 'cancelled' => []];

        if (! in_array($targetStatus, $transitions[$lockedOrder->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A sales order cannot move from {$lockedOrder->status} to {$targetStatus}.",
            ]);
        }

        $lockedOrder->update(['status' => $targetStatus]);

        return $lockedOrder->refresh();
    }
}
