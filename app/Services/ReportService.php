<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private readonly ReportCache $reportCache,
        private readonly WarehouseReportScope $warehouseScope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{summary: array<string, mixed>, rows: LengthAwarePaginator}
     */
    public function purchases(User $user, array $filters): array
    {
        $warehouseIds = $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters));

        return $this->reportCache->remember(
            'purchases',
            $user,
            $warehouseIds,
            $filters,
            function () use ($filters, $warehouseIds): array {
                $tableQuery = $this->purchaseQuery($filters, $warehouseIds);
                $rows = $tableQuery
                    ->orderByDesc('p.order_date')
                    ->orderByDesc('p.id')
                    ->paginate(15, ['*'], 'page', (int) ($filters['page'] ?? 1))
                    ->through(fn (object $row): object => $this->formatPurchaseRow($row));

                return [
                    'summary' => $this->purchaseSummary($filters, $warehouseIds),
                    'rows' => $rows,
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{summary: array<string, mixed>, rows: LengthAwarePaginator}
     */
    public function sales(User $user, array $filters): array
    {
        $warehouseIds = $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters));

        return $this->reportCache->remember(
            'sales',
            $user,
            $warehouseIds,
            $filters,
            function () use ($filters, $warehouseIds): array {
                $rows = $this->salesQuery($filters, $warehouseIds)
                    ->orderByDesc('so.order_date')
                    ->orderByDesc('so.id')
                    ->paginate(15, ['*'], 'page', (int) ($filters['page'] ?? 1))
                    ->through(fn (object $row): object => $this->formatSalesRow($row));

                return [
                    'summary' => $this->salesSummary($filters, $warehouseIds),
                    'rows' => $rows,
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{limit: int, rows: LengthAwarePaginator}
     */
    public function topSellingProducts(User $user, array $filters): array
    {
        $warehouseIds = $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters));
        $limit = (int) ($filters['limit'] ?? 10);

        return $this->reportCache->remember(
            'top-products',
            $user,
            $warehouseIds,
            $filters,
            function () use ($filters, $warehouseIds, $limit): array {
                $rows = $this->topSellingQuery($filters, $warehouseIds, $limit)
                    ->paginate(15, ['*'], 'page', (int) ($filters['page'] ?? 1))
                    ->through(fn (object $row): object => $this->formatTopProductRow($row));

                return [
                    'limit' => $limit,
                    'rows' => $rows,
                ];
            },
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{summary: array<string, mixed>, rows: LengthAwarePaginator}
     */
    public function inventoryValuation(User $user, array $filters): array
    {
        $warehouseIds = $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters));

        return $this->reportCache->remember(
            'inventory-valuation',
            $user,
            $warehouseIds,
            $filters,
            function () use ($filters, $warehouseIds): array {
                $rows = $this->valuationQuery($filters, $warehouseIds)
                    ->orderBy('p.name')
                    ->orderBy('w.name')
                    ->paginate(15, ['*'], 'page', (int) ($filters['page'] ?? 1))
                    ->through(fn (object $row): object => $this->formatValuationRow($row));

                return [
                    'summary' => [
                        'total_value' => $this->valuationTotal($filters, $warehouseIds),
                        'warehouses' => $this->valuationByWarehouse($filters, $warehouseIds),
                    ],
                    'rows' => $rows,
                ];
            },
        );
    }

    /**
     * @return array{
     *     warehouses: list<array{id: int, name: string}>,
     *     suppliers: list<array{id: int, name: string}>,
     *     customers: list<array{id: int, name: string}>,
     *     categories: list<array{id: int, name: string}>
     * }
     */
    public function filterOptions(User $user): array
    {
        $warehouseIds = $this->warehouseScope->ids($user);

        $suppliers = $user->can('suppliers.view') && $user->can('purchases.view')
            ? DB::table('suppliers')
                ->whereIn('id', DB::table('purchases')->whereIn('warehouse_id', $warehouseIds)->select('supplier_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])
                ->all()
            : [];

        $customers = $user->can('customers.view') && $user->can('sales.view')
            ? DB::table('customers')
                ->whereIn('id', DB::table('sales_orders')->whereIn('warehouse_id', $warehouseIds)->select('customer_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])
                ->all()
            : [];

        $categories = DB::table('categories')
            ->whereIn('id', DB::table('products as p')
                ->join('inventory_balances as b', 'b.product_id', '=', 'p.id')
                ->whereIn('b.warehouse_id', $warehouseIds)
                ->select('p.category_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name])
            ->all();

        return [
            'warehouses' => $this->warehouseScope->warehouses($warehouseIds)
                ->map(fn ($warehouse): array => ['id' => (int) $warehouse->id, 'name' => $warehouse->name])
                ->all(),
            'suppliers' => $suppliers,
            'customers' => $customers,
            'categories' => $categories,
        ];
    }

    /**
     * Fresh, scoped query used by the streamed CSV endpoint.
     *
     * @param  array<string, mixed>  $filters
     */
    public function purchaseExportQuery(User $user, array $filters): Builder
    {
        return $this->purchaseQuery(
            $filters,
            $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters)),
        )->orderByDesc('p.order_date')->orderByDesc('p.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesExportQuery(User $user, array $filters): Builder
    {
        return $this->salesQuery(
            $filters,
            $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters)),
        )->orderByDesc('so.order_date')->orderByDesc('so.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function topProductsExportQuery(User $user, array $filters): Builder
    {
        return $this->topSellingQuery(
            $filters,
            $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters)),
            (int) ($filters['limit'] ?? 10),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function valuationExportQuery(User $user, array $filters): Builder
    {
        return $this->valuationQuery(
            $filters,
            $this->warehouseScope->ids($user, $this->requestedWarehouseId($filters)),
        )->orderBy('p.name')->orderBy('w.name');
    }

    /**
     * Query builder shared with the dashboard and query-plan acceptance test.
     *
     * @param  list<int>  $warehouseIds
     */
    public function recentMovementsQuery(array $warehouseIds): Builder
    {
        return DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sm.warehouse_id')
            ->whereIn('sm.warehouse_id', $warehouseIds)
            ->select([
                'sm.id',
                'sm.movement_type',
                'sm.quantity_change',
                'sm.balance_after',
                'sm.created_at',
                'p.id as product_id',
                'p.sku',
                'p.name as product_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
            ])
            ->orderByDesc('sm.created_at')
            ->orderByDesc('sm.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function purchaseQuery(array $filters, array $warehouseIds): Builder
    {
        $query = DB::table('purchases as p')
            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->join('warehouses as w', 'w.id', '=', 'p.warehouse_id')
            ->whereIn('p.warehouse_id', $warehouseIds)
            ->whereBetween('p.order_date', [$filters['from'], $filters['to']])
            ->select([
                'p.id',
                'p.purchase_number',
                'p.status',
                'p.order_date',
                'p.grand_total',
                's.name as supplier_name',
                'w.name as warehouse_name',
            ]);

        if (! empty($filters['supplier_id'])) {
            $query->where('p.supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function salesQuery(array $filters, array $warehouseIds): Builder
    {
        $query = DB::table('sales_orders as so')
            ->join('customers as c', 'c.id', '=', 'so.customer_id')
            ->join('warehouses as w', 'w.id', '=', 'so.warehouse_id')
            ->whereIn('so.warehouse_id', $warehouseIds)
            ->where('so.status', 'confirmed')
            ->whereBetween('so.order_date', [$filters['from'], $filters['to']])
            ->select([
                'so.id',
                'so.order_number',
                'so.order_date',
                'so.grand_total',
                'c.name as customer_name',
                'w.name as warehouse_name',
            ]);

        if (! empty($filters['customer_id'])) {
            $query->where('so.customer_id', (int) $filters['customer_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function topSellingQuery(array $filters, array $warehouseIds, int $limit): Builder
    {
        $query = DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->whereIn('so.warehouse_id', $warehouseIds)
            ->where('so.status', 'confirmed')
            ->whereBetween('so.order_date', [$filters['from'], $filters['to']])
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name as product_name',
            ])
            ->selectRaw('SUM(soi.quantity) as units_sold')
            ->selectRaw('ROUND(COALESCE(SUM(soi.line_total), 0), 2) as sales_total')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->orderByDesc('units_sold')
            ->orderBy('p.name');

        return $query->limit($limit);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function valuationQuery(array $filters, array $warehouseIds): Builder
    {
        $query = DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->whereIn('b.warehouse_id', $warehouseIds)
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name as product_name',
                'c.name as category_name',
                'w.id as warehouse_id',
                'w.name as warehouse_name',
                'b.quantity',
                'p.purchase_price',
            ])
            ->selectRaw('ROUND(COALESCE(b.quantity * p.purchase_price, 0), 2) as inventory_value');

        if (! empty($filters['category_id'])) {
            $query->where('p.category_id', (int) $filters['category_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function purchaseSummary(array $filters, array $warehouseIds): array
    {
        $query = DB::table('purchases as p')
            ->whereIn('p.warehouse_id', $warehouseIds)
            ->whereBetween('p.order_date', [$filters['from'], $filters['to']])
            ->where('p.status', '!=', 'cancelled');

        if (! empty($filters['supplier_id'])) {
            $query->where('p.supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }

        return [
            'purchase_total' => $this->money((clone $query)
                ->selectRaw('ROUND(COALESCE(SUM(p.grand_total), 0), 2) as total')
                ->value('total')),
            'purchase_count' => (clone $query)->count('p.id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function salesSummary(array $filters, array $warehouseIds): array
    {
        $query = DB::table('sales_orders as so')
            ->whereIn('so.warehouse_id', $warehouseIds)
            ->where('so.status', 'confirmed')
            ->whereBetween('so.order_date', [$filters['from'], $filters['to']]);

        if (! empty($filters['customer_id'])) {
            $query->where('so.customer_id', (int) $filters['customer_id']);
        }

        return [
            'sales_total' => $this->money((clone $query)
                ->selectRaw('ROUND(COALESCE(SUM(so.grand_total), 0), 2) as total')
                ->value('total')),
            'sales_count' => (clone $query)->count('so.id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     * @return list<array{warehouse_id: int, warehouse_name: string, value: string}>
     */
    private function valuationByWarehouse(array $filters, array $warehouseIds): array
    {
        $query = DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->whereIn('b.warehouse_id', $warehouseIds)
            ->groupBy('w.id', 'w.name')
            ->orderBy('w.name')
            ->select(['w.id as warehouse_id', 'w.name as warehouse_name'])
            ->selectRaw('ROUND(COALESCE(SUM(b.quantity * p.purchase_price), 0), 2) as value');

        if (! empty($filters['category_id'])) {
            $query->where('p.category_id', (int) $filters['category_id']);
        }

        return $query->get()
            ->map(fn (object $row): array => [
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'value' => $this->money($row->value),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    private function valuationTotal(array $filters, array $warehouseIds): string
    {
        $query = DB::table('inventory_balances as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->whereIn('b.warehouse_id', $warehouseIds);

        if (! empty($filters['category_id'])) {
            $query->where('p.category_id', (int) $filters['category_id']);
        }

        $total = $query->selectRaw('ROUND(COALESCE(SUM(b.quantity * p.purchase_price), 0), 2) as total')
            ->value('total');

        return $this->money($total);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    public function purchaseTotalForRange(array $filters, array $warehouseIds): string
    {
        return $this->purchaseSummary($filters, $warehouseIds)['purchase_total'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $warehouseIds
     */
    public function salesTotalForRange(array $filters, array $warehouseIds): string
    {
        return $this->salesSummary($filters, $warehouseIds)['sales_total'];
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return list<array{month: string, amount: string}>
     */
    public function monthlyPurchaseTotals(array $warehouseIds, string $from, string $to): array
    {
        return $this->monthlyTotals('purchases', 'p', 'purchases', $warehouseIds, $from, $to);
    }

    /**
     * @param  list<int>  $warehouseIds
     * @return list<array{month: string, amount: string}>
     */
    public function monthlySalesTotals(array $warehouseIds, string $from, string $to): array
    {
        return $this->monthlyTotals('sales_orders', 'so', 'sales', $warehouseIds, $from, $to);
    }

    /**
     * @param  list<int>  $warehouseIds
     */
    public function inventoryValueForScope(array $warehouseIds): string
    {
        return $this->valuationTotal([], $warehouseIds);
    }

    /**
     * @param  'purchases'|'sales_orders'  $table
     * @param  'p'|'so'  $alias
     * @param  'purchases'|'sales'  $reportType
     * @param  list<int>  $warehouseIds
     * @return list<array{month: string, amount: string}>
     */
    private function monthlyTotals(
        string $table,
        string $alias,
        string $reportType,
        array $warehouseIds,
        string $from,
        string $to,
    ): array {
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', {$alias}.order_date)"
            : "DATE_FORMAT({$alias}.order_date, '%Y-%m')";

        $query = DB::table("{$table} as {$alias}")
            ->whereIn("{$alias}.warehouse_id", $warehouseIds)
            ->whereBetween("{$alias}.order_date", [$from, $to]);

        if ($reportType === 'purchases') {
            $query->where("{$alias}.status", '!=', 'cancelled');
        } else {
            $query->where("{$alias}.status", 'confirmed');
        }

        return $query
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw("ROUND(COALESCE(SUM({$alias}.grand_total), 0), 2) as amount")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn (object $row): array => [
                'month' => (string) $row->month,
                'amount' => $this->money($row->amount),
            ])
            ->all();
    }

    private function formatPurchaseRow(object $row): object
    {
        $row->grand_total = $this->money($row->grand_total);

        return $row;
    }

    private function formatSalesRow(object $row): object
    {
        $row->grand_total = $this->money($row->grand_total);

        return $row;
    }

    private function formatTopProductRow(object $row): object
    {
        $row->units_sold = (int) $row->units_sold;
        $row->sales_total = $this->money($row->sales_total);

        return $row;
    }

    private function formatValuationRow(object $row): object
    {
        $row->quantity = (int) $row->quantity;
        $row->purchase_price = $this->money($row->purchase_price);
        $row->inventory_value = $this->money($row->inventory_value);

        return $row;
    }

    private function money(mixed $value): string
    {
        return number_format(round((float) ($value ?? 0), 2), 2, '.', '');
    }

    private function requestedWarehouseId(array $filters): ?int
    {
        return empty($filters['warehouse_id']) ? null : (int) $filters['warehouse_id'];
    }
}
