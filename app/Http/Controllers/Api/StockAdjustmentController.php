<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'reason_code' => ['nullable', Rule::in(['count_correction', 'damage', 'loss', 'found', 'other'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = StockAdjustment::query()->with(['warehouse:id,name', 'product:id,sku,name', 'creator:id,name']);
        $this->scopeToAssignedWarehouses($query, $request->user());
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $builder) => $builder->where('adjustment_number', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")));
        }
        foreach (['warehouse_id', 'reason_code'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return StockAdjustmentResource::collection($query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString());
    }

    public function store(StockAdjustmentRequest $request, InventoryService $inventory): JsonResponse
    {
        $data = $request->validated();
        $adjustment = DB::transaction(function () use ($request, $data, $inventory): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'adjustment_number' => 'ADJ-TMP-'.Str::lower(Str::random(12)),
                ...$data,
                'created_by' => $request->user()->id,
            ]);
            $adjustment->update(['adjustment_number' => sprintf('ADJ-%06d', $adjustment->id)]);
            $inventory->adjustStock(
                (int) $adjustment->product_id,
                (int) $adjustment->warehouse_id,
                (int) $adjustment->quantity_change,
                MovementType::Adjustment,
                $adjustment->reason,
                (int) $request->user()->id,
                $adjustment,
            );

            return $adjustment;
        }, attempts: 3);

        return (new StockAdjustmentResource($adjustment->load(['warehouse', 'product', 'creator'])))->response()->setStatusCode(201);
    }

    public function show(StockAdjustment $adjustment, Request $request): StockAdjustmentResource
    {
        $this->ensureWarehouseAccess($adjustment, $request->user());

        return new StockAdjustmentResource($adjustment->load(['warehouse', 'product', 'creator']));
    }

    private function scopeToAssignedWarehouses(Builder $query, User $user): void
    {
        if ($user->hasRole('Warehouse Staff')) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
    }

    private function ensureWarehouseAccess(StockAdjustment $adjustment, User $user): void
    {
        if ($user->hasRole('Warehouse Staff') && ! $user->warehouses()->whereKey($adjustment->warehouse_id)->exists()) {
            abort(404);
        }
    }
}
