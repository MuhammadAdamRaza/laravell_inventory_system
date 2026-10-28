<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningStockRequest;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\ReportCache;
use App\Services\WarehouseReportScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        private readonly ReportCache $reportCache,
        private readonly WarehouseReportScope $warehouseScope,
    ) {}

    /**
     * Get inventory summary metrics for professional dashboard display.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $warehouseIds = $this->warehouseScope->ids($user);
        $stats = $this->reportCache->rememberInventory(
            'stats',
            $user,
            $warehouseIds,
            $request->query(),
            1,
            function () use ($user): array {
                $baseQuery = InventoryBalance::accessibleBy($user);
                $totalUnits = (int) (clone $baseQuery)->sum('quantity');
                $outOfStock = (clone $baseQuery)->where('quantity', '<=', 0)->count();
                $lowStock = (clone $baseQuery)->where('quantity', '>', 0)
                    ->whereHas('product', function ($query): void {
                        $query->whereColumn('inventory_balances.quantity', '<=', 'products.minimum_stock');
                    })->count();

                return [
                    'total_units' => $totalUnits,
                    'total_products' => Product::query()->where('status', 'active')->count(),
                    'total_warehouses' => Warehouse::accessibleBy($user)->where('status', 'active')->count(),
                    'low_stock_count' => $lowStock,
                    'out_of_stock_count' => $outOfStock,
                ];
            },
        );

        return response()->json($stats);
    }

    /**
     * Get inventory balances with filtering, search, and warehouse access isolation.
     */
    public function balances(Request $request): JsonResponse
    {
        $query = InventoryBalance::accessibleBy($request->user())
            ->with(['product', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('product', function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'out_of_stock') {
                $query->where('quantity', '<=', 0);
            } elseif ($status === 'low_stock') {
                $query->where('quantity', '>', 0)
                    ->whereHas('product', function ($q): void {
                        $q->whereColumn('inventory_balances.quantity', '<=', 'products.minimum_stock');
                    });
            } elseif ($status === 'ok') {
                $query->whereHas('product', function ($q): void {
                    $q->whereColumn('inventory_balances.quantity', '>', 'products.minimum_stock');
                });
            }
        }

        $user = $request->user();
        $warehouseIds = $this->warehouseScope->ids(
            $user,
            $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null,
        );
        $filters = $request->query();
        $page = max(1, (int) ($filters['page'] ?? 1));
        unset($filters['page']);

        $payload = $this->reportCache->rememberInventory(
            'balances',
            $user,
            $warehouseIds,
            $filters,
            $page,
            fn (): array => InventoryBalanceResource::collection($query->latest('updated_at')->paginate(15))
                ->response()
                ->getData(true),
        );

        return response()->json($payload);
    }

    /**
     * Get stock movements audit log with filtering and warehouse access isolation.
     */
    public function movements(Request $request): AnonymousResourceCollection
    {
        $query = StockMovement::accessibleBy($request->user())
            ->with(['product', 'warehouse', 'user']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($sub) use ($search): void {
                $sub->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })
                    ->orWhereHas('warehouse', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return StockMovementResource::collection($query->latest('created_at')->paginate(15));
    }

    /**
     * Set opening stock or manual adjustment for a product in a warehouse.
     */
    public function openingStock(OpeningStockRequest $request): JsonResponse
    {
        $warehouseId = (int) $request->input('warehouse_id');
        $productId = (int) $request->input('product_id');
        $quantity = (int) $request->input('quantity');
        $reason = $request->input('reason', 'Opening stock');

        // Check user warehouse isolation
        if ($request->user()->hasRole('Warehouse Staff') && ! $request->user()->warehouses()->where('warehouses.id', $warehouseId)->exists()) {
            return response()->json(['message' => 'Unauthorized warehouse operation.'], 403);
        }

        $movement = $this->inventoryService->adjustStock(
            productId: $productId,
            warehouseId: $warehouseId,
            quantityChange: $quantity,
            movementType: MovementType::Adjustment,
            reason: $reason,
            userId: $request->user()->id
        );

        $movement->load(['product', 'warehouse', 'user']);

        return response()->json([
            'message' => 'Opening stock adjusted successfully.',
            'movement' => new StockMovementResource($movement),
        ], 201);
    }
}
