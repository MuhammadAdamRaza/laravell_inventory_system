<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ReportExportReady;
use App\Services\CsvExporter;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public string $exportId)
    {
        $this->onQueue('reports');
    }

    public function handle(ReportService $reports, CsvExporter $csv): void
    {
        $export = ReportExport::query()->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);
        $user = User::query()->findOrFail($export->created_by);
        $filters = $export->filters;

        [$query, $headers, $mapRow] = match ($export->report) {
            'purchases' => [
                $reports->purchaseExportQuery($user, $filters),
                ['Purchase number', 'Supplier', 'Warehouse', 'Status', 'Order date', 'Total'],
                static fn (object $row): array => [$row->purchase_number, $row->supplier_name, $row->warehouse_name, $row->status, $row->order_date, $row->grand_total],
            ],
            'sales' => [
                $reports->salesExportQuery($user, $filters),
                ['Order number', 'Customer', 'Warehouse', 'Order date', 'Total'],
                static fn (object $row): array => [$row->order_number, $row->customer_name, $row->warehouse_name, $row->order_date, $row->grand_total],
            ],
            'top-products' => [
                $reports->topProductsExportQuery($user, $filters),
                ['SKU', 'Product', 'Units sold', 'Sales total'],
                static fn (object $row): array => [$row->sku, $row->product_name, $row->units_sold, $row->sales_total],
            ],
            'inventory-valuation' => [
                $reports->valuationExportQuery($user, $filters),
                ['SKU', 'Product', 'Category', 'Warehouse', 'Units', 'Unit cost', 'Inventory value'],
                static fn (object $row): array => [
                    $row->sku,
                    $row->product_name,
                    $row->category_name,
                    $row->warehouse_name,
                    $row->quantity,
                    number_format(round((float) $row->purchase_price, 2), 2, '.', ''),
                    number_format(round((float) $row->inventory_value, 2), 2, '.', ''),
                ],
            ],
            default => throw new \InvalidArgumentException('Unsupported report export type.'),
        };

        $fileName = sprintf('%s-%s-to-%s.csv', $export->report, $filters['from'], $filters['to']);
        $filePath = 'report-exports/'.$export->id.'.csv';
        $csv->store($query, $headers, $mapRow, $filePath);

        $export->update([
            'status' => 'ready',
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);

        $user->notify(new ReportExportReady($export->report));
    }

    public function failed(?Throwable $exception): void
    {
        ReportExport::query()->whereKey($this->exportId)->update(['status' => 'failed']);
    }
}
