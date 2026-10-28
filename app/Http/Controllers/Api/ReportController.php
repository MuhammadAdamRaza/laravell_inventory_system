<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFiltersRequest;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\ReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function options(ReportFiltersRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->filterOptions($request->user())]);
    }

    public function purchases(ReportFiltersRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json($reports->purchases($request->user(), $request->filters()));
    }

    public function exportPurchases(
        ReportFiltersRequest $request,
        ReportService $reports,
        CsvExporter $csv,
    ): JsonResponse|StreamedResponse {
        $filters = $request->filters();

        return $this->downloadOrQueue(
            $request->user(),
            'purchases',
            $filters,
            $reports->purchaseExportQuery($request->user(), $filters),
            $csv,
            ['Purchase number', 'Supplier', 'Warehouse', 'Status', 'Order date', 'Total'],
            fn (object $row): array => [
                $row->purchase_number,
                $row->supplier_name,
                $row->warehouse_name,
                $row->status,
                $row->order_date,
                $row->grand_total,
            ],
        );
    }

    public function sales(ReportFiltersRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json($reports->sales($request->user(), $request->filters()));
    }

    public function exportSales(
        ReportFiltersRequest $request,
        ReportService $reports,
        CsvExporter $csv,
    ): JsonResponse|StreamedResponse {
        $filters = $request->filters();

        return $this->downloadOrQueue(
            $request->user(),
            'sales',
            $filters,
            $reports->salesExportQuery($request->user(), $filters),
            $csv,
            ['Order number', 'Customer', 'Warehouse', 'Order date', 'Total'],
            fn (object $row): array => [
                $row->order_number,
                $row->customer_name,
                $row->warehouse_name,
                $row->order_date,
                $row->grand_total,
            ],
        );
    }

    public function topProducts(ReportFiltersRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json($reports->topSellingProducts($request->user(), $request->filters()));
    }

    public function exportTopProducts(
        ReportFiltersRequest $request,
        ReportService $reports,
        CsvExporter $csv,
    ): JsonResponse|StreamedResponse {
        $filters = $request->filters();

        return $this->downloadOrQueue(
            $request->user(),
            'top-products',
            $filters,
            $reports->topProductsExportQuery($request->user(), $filters),
            $csv,
            ['SKU', 'Product', 'Units sold', 'Sales total'],
            fn (object $row): array => [
                $row->sku,
                $row->product_name,
                $row->units_sold,
                $row->sales_total,
            ],
        );
    }

    public function inventoryValuation(ReportFiltersRequest $request, ReportService $reports): JsonResponse
    {
        return response()->json($reports->inventoryValuation($request->user(), $request->filters()));
    }

    public function exportInventoryValuation(
        ReportFiltersRequest $request,
        ReportService $reports,
        CsvExporter $csv,
    ): JsonResponse|StreamedResponse {
        $filters = $request->filters();

        return $this->downloadOrQueue(
            $request->user(),
            'inventory-valuation',
            $filters,
            $reports->valuationExportQuery($request->user(), $filters),
            $csv,
            ['SKU', 'Product', 'Category', 'Warehouse', 'Units', 'Unit cost', 'Inventory value'],
            fn (object $row): array => [
                $row->sku,
                $row->product_name,
                $row->category_name,
                $row->warehouse_name,
                $row->quantity,
                number_format(round((float) $row->purchase_price, 2), 2, '.', ''),
                number_format(round((float) $row->inventory_value, 2), 2, '.', ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $headers
     * @param  callable(object): array<int, mixed>  $mapRow
     */
    private function downloadOrQueue(
        User $user,
        string $report,
        array $filters,
        Builder $query,
        CsvExporter $csv,
        array $headers,
        callable $mapRow,
    ): JsonResponse|StreamedResponse {
        if ((clone $query)->count() > 5000) {
            $export = ReportExport::query()->create([
                'id' => (string) Str::uuid(),
                'created_by' => $user->getKey(),
                'report' => $report,
                'filters' => $filters,
                'status' => 'queued',
            ]);

            GenerateReportExport::dispatch($export->id)->afterCommit();

            return response()->json([
                'message' => 'The report is large and is being generated. Check your report exports page shortly.',
                'data' => [
                    'id' => $export->id,
                    'report' => $export->report,
                    'status' => $export->status,
                ],
            ], 202);
        }

        return $csv->download($query, $headers, $mapRow, $this->filename($report, $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filename(string $report, array $filters): string
    {
        return sprintf('%s-%s-to-%s.csv', $report, $filters['from'], $filters['to']);
    }
}
