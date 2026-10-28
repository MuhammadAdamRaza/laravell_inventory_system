<?php

use App\Models\User;
use App\Notifications\LowStockDigest;
use App\Services\DashboardService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static function (): void {
    $items = DB::table('inventory_balances as b')
        ->join('products as p', 'p.id', '=', 'b.product_id')
        ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
        ->where('p.status', 'active')
        ->where('w.status', 'active')
        ->whereColumn('b.quantity', '<=', 'p.minimum_stock')
        ->orderBy('p.name')
        ->orderBy('w.name')
        ->get(['p.name as product_name', 'p.sku', 'w.name as warehouse_name', 'b.quantity', 'p.minimum_stock'])
        ->map(static fn (object $item): array => [
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'warehouse_name' => $item->warehouse_name,
            'quantity' => (int) $item->quantity,
            'minimum_stock' => (int) $item->minimum_stock,
        ])
        ->all();

    if ($items === []) {
        return;
    }

    User::role(['Administrator', 'Manager'])
        ->where('is_active', true)
        ->with('roles.permissions')
        ->get()
        ->filter(static fn (User $user): bool => $user->can('inventory.view'))
        ->each(static fn (User $user) => $user->notify(new LowStockDigest($items)));
})->dailyAt('08:00')->name('inventory-low-stock-digest')->withoutOverlapping();

Schedule::call(static function (): void {
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('read_at', '<', now()->subDays(90))
        ->delete();
})->dailyAt('02:15')->name('prune-read-notifications')->withoutOverlapping();

Schedule::call(static function (DashboardService $dashboard): void {
    $user = User::role(['Administrator', 'Manager'])
        ->where('is_active', true)
        ->with('roles.permissions')
        ->get()
        ->first(static fn (User $candidate): bool => $candidate->can('reports.view'));

    if ($user === null) {
        return;
    }

    $today = now()->toDateString();
    $dashboard->build($user, [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => $today,
    ], false);
})->dailyAt('07:55')->name('warm-all-warehouse-dashboard')->withoutOverlapping();
