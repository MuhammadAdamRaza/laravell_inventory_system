<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    /**
     * Display a listing of warehouses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Warehouse::accessibleBy($request->user())
            ->withCount('balances');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortField, ['name', 'location', 'status', 'created_at'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return WarehouseResource::collection($query->paginate(15));
    }

    /**
     * Store a newly created warehouse.
     */
    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified warehouse.
     */
    public function show(Request $request, Warehouse $warehouse): WarehouseResource|JsonResponse
    {
        if ($request->user()->hasRole('Warehouse Staff') && ! $request->user()->warehouses()->where('warehouses.id', $warehouse->id)->exists()) {
            return response()->json(['message' => 'Unauthorized warehouse access.'], 403);
        }

        $warehouse->loadCount('balances');

        return new WarehouseResource($warehouse);
    }

    /**
     * Update the specified warehouse.
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }

    /**
     * Deactivate or delete warehouse safely.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $hasOperationalHistory = $warehouse->balances()->exists()
            || $warehouse->stockMovements()->exists()
            || $warehouse->purchases()->exists()
            || $warehouse->salesOrders()->exists()
            || DB::table('warehouse_transfers')->where('source_warehouse_id', $warehouse->id)->orWhere('destination_warehouse_id', $warehouse->id)->exists()
            || DB::table('stock_adjustments')->where('warehouse_id', $warehouse->id)->exists()
            || DB::table('sales_returns')->where('warehouse_id', $warehouse->id)->exists();

        if ($hasOperationalHistory) {
            $warehouse->update(['status' => 'inactive']);

            return response()->json([
                'message' => 'Warehouse has operational history and was deactivated instead of deleted.',
                'data' => new WarehouseResource($warehouse),
            ]);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully.',
        ]);
    }
}
