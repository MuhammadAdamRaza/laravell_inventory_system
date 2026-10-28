<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class WarehouseReportScope
{
    /**
     * @return list<int>
     */
    public function ids(User $user, ?int $requestedWarehouseId = null): array
    {
        $warehouseIds = $user->hasRole('Warehouse Staff')
            ? $user->warehouses()->orderBy('warehouses.id')->pluck('warehouses.id')->map(fn ($id): int => (int) $id)->all()
            : Warehouse::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($requestedWarehouseId !== null) {
            abort_unless(in_array($requestedWarehouseId, $warehouseIds, true), 403, 'You cannot view reports for this warehouse.');

            return [$requestedWarehouseId];
        }

        return $warehouseIds;
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return Collection<int, Warehouse>
     */
    public function warehouses(array $warehouseIds): Collection
    {
        return Warehouse::query()
            ->whereIn('id', $warehouseIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
