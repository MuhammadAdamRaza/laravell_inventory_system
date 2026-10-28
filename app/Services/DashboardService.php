<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly ReportCache $reportCache,
        private readonly ReportService $reports,
        private readonly WarehouseReportScope $warehouseScope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters, bool $hasCustomDateRange): array
    {
        $requestedWarehouseId = empty($filters['warehouse_id'])
            ? null
            : (int) $filters['warehouse_id'];
        $warehouseIds = $this->warehouseScope->ids($user, $requestedWarehouseId);
        $cacheFilters = $filters;
        $cacheFilters['has_custom_date_range'] = $hasCustomDateRange;

        return $this->reportCache->remember(
            'dashboard',
            $user,
            $warehouseIds,
            $cacheFilters,
            fn (): array => $this->dashboardPayload(
                $user,
                $filters,
                $warehouseIds,
                $hasCustomDateRange,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     * @return array<string, mixed>
     */
    private function dashboardPayload(
        User $user,
        array $filters,
        array $warehouseIds,
        bool $hasCustomDateRange,
    ): array {
        $requestedWarehouseId = empty($filters['warehouse_id'])
            ? null
            : (int) $filters['warehouse_id'];
        $canViewPurchases = $user->can('purchases.view');
        $canViewSales = $user->can('sales.view');
        $canViewSuppliers = $user->can('suppliers.view');
        $canViewProducts = $user->can('products.view');
        $canViewWarehouses = $user->can('warehouses.view');
        $canViewInventory = $user->can('inventory.view');

        $trendStart = $hasCustomDateRange
            ? CarbonImmutable::parse($filters['from'])
            : CarbonImmutable::parse($filters['to'])->startOfMonth()->subMonths(5);
        $trendFrom = $trendStart->startOfMonth();
        $trendTo = CarbonImmutable::parse($filters['to'])->startOfMonth();

        $months = [];
        for ($month = $trendFrom; $month->lessThanOrEqualTo($trendTo); $month = $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        $purchaseByMonth = collect($canViewPurchases
            ? $this->reports->monthlyPurchaseTotals($warehouseIds, $trendStart->toDateString(), $filters['to'])
            : [])->keyBy('month');
        $salesByMonth = collect($canViewSales
            ? $this->reports->monthlySalesTotals($warehouseIds, $trendStart->toDateString(), $filters['to'])
            : [])->keyBy('month');

        $lowStockBase = $this->stockAlertQuery($warehouseIds)
            ->where('b.quantity', '>', 0)
            ->whereColumn('b.quantity', '<=', 'p.minimum_stock');
        $outOfStockBase = $this->stockAlertQuery($warehouseIds)
            ->where('b.quantity', '=', 0);

        $recentMovements = $canViewInventory
            ? $this->reports->recentMovementsQuery($warehouseIds)
                ->limit(10)
                ->get()
                ->map(fn (object $movement): array => [
                    'id' => (int) $movement->id,
                    'movement_type' => $movement->movement_type,
                    'quantity_change' => (int) $movement->quantity_change,
                    'balance_after' => (int) $movement->balance_after,
                    'created_at' => (string) $movement->created_at,
                    'product' => [
                        'id' => (int) $movement->product_id,
                        'sku' => $movement->sku,
                        'name' => $movement->product_name,
                    ],
                    'warehouse' => [
                        'id' => (int) $movement->warehouse_id,
                        'name' => $movement->warehouse_name,
                    ],
                ])
                ->all()
            : [];

        return [
            'totals' => [
                'products' => $canViewProducts
                    ? (int) DB::table('inventory_balances')
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->distinct()
                        ->count('product_id')
                    : null,
                'suppliers' => $canViewSuppliers
                    ? (int) DB::table('purchases')
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->distinct()
                        ->count('supplier_id')
                    : null,
                'warehouses' => $canViewWarehouses ? count($warehouseIds) : null,
                'inventory_value' => $canViewInventory
                    ? $this->reports->inventoryValueForScope($warehouseIds)
                    : null,
                'purchases_total' => $canViewPurchases
                    ? $this->reports->purchaseTotalForRange($filters, $warehouseIds)
                    : null,
                'sales_total' => $canViewSales
                    ? $this->reports->salesTotalForRange($filters, $warehouseIds)
                    : null,
            ],
            'stock_alerts' => [
                'low_stock_count' => $canViewInventory ? (clone $lowStockBase)->count('b.id') : 0,
                'out_of_stock_count' => $canViewInventory ? (clone $outOfStockBase)->count('b.id') : 0,
                'low_stock_items' => $canViewInventory
                    ? $this->stockAlertRows(clone $lowStockBase)
                    : [],
                'out_of_stock_items' => $canViewInventory
                    ? $this->stockAlertRows(clone $outOfStockBase)
                    : [],
            ],
            'recent_movements' => $recentMovements,
            'warehouse_summary' => $canViewInventory
                ? $this->warehouseSummary($warehouseIds)
                : [],
            'trend' => [
                'months' => $months,
                'sales' => $canViewSales
                    ? array_map(fn (string $key): string => $salesByMonth->get($key)['amount'] ?? '0.00', $months)
                    : null,
                'purchases' => $canViewPurchases
                    ? array_map(fn (string $key): string => $purchaseByMonth->get($key)['amount'] ?? '0.00', $months)
                    : null,
            ],
            'filters' => [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'warehouse_id' => $requestedWarehouseId,
                'warehouses' => $this->warehouseScope->warehouses($warehouseIds)
                    ->map(fn ($warehouse): array => [
                        'id' => (int) $warehouse->id,
                        'name' => $warehouse->name,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @param  list<int>  $warehouseIds
     */
    private function stockAlertQuery(array $warehouseIds): Builder
    {
        return DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->whereIn('b.warehouse_id', $warehouseIds);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockAlertRows(Builder $query): array
    {
        return $query
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name as product_name',
                'p.minimum_stock',
                'b.quantity',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
            ])
            ->orderBy('p.name')
            ->orderBy('w.name')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'minimum_stock' => (int) $row->minimum_stock,
                'quantity' => (int) $row->quantity,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return list<array{warehouse_id: int, name: string, products: int, units: int, value: string}>
     */
    private function warehouseSummary(array $warehouseIds): array
    {
        return DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->whereIn('b.warehouse_id', $warehouseIds)
            ->groupBy('w.id', 'w.name')
            ->orderBy('w.name')
            ->select(['w.id as warehouse_id', 'w.name'])
            ->selectRaw('COUNT(DISTINCT p.id) as products')
            ->selectRaw('COALESCE(SUM(b.quantity), 0) as units')
            ->selectRaw('ROUND(COALESCE(SUM(b.quantity * p.purchase_price), 0), 2) as value')
            ->get()
            ->map(fn (object $row): array => [
                'warehouse_id' => (int) $row->warehouse_id,
                'name' => $row->name,
                'products' => (int) $row->products,
                'units' => (int) $row->units,
                'value' => number_format(round((float) $row->value, 2), 2, '.', ''),
            ])
            ->all();
    }
}
