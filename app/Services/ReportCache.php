<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ReportCache
{
    private const DASHBOARD_TTL_SECONDS = 300;

    private const REPORT_TTL_SECONDS = 600;

    private const INVENTORY_TTL_SECONDS = 60;

    public function __construct(private readonly InventoryVersionStore $inventoryVersions) {}

    /**
     * @template TValue
     *
     * @param  list<int>  $warehouseIds
     * @param  array<string, mixed>  $filters
     * @param  callable(): TValue  $resolver
     * @return TValue
     *
     * @throws JsonException
     */
    public function remember(
        string $report,
        User $user,
        array $warehouseIds,
        array $filters,
        callable $resolver,
    ): mixed {
        sort($warehouseIds);
        ksort($filters);
        $permissionNames = $user->getAllPermissions()->pluck('name')->all();
        sort($permissionNames);

        $scopeHash = hash('sha256', json_encode([
            'warehouses' => $warehouseIds,
            'permissions' => $permissionNames,
        ], JSON_THROW_ON_ERROR));
        $warehouseVersion = $this->inventoryVersions->forWarehouses($warehouseIds);
        $filtersHash = hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        $key = $report === 'dashboard'
            ? sprintf('dash:%s:%s:%s:%s:%s', $warehouseVersion, $scopeHash, $filters['from'] ?? '', $filters['to'] ?? '', $filtersHash)
            : sprintf('report:%s:%s:%s:%s', $report, $warehouseVersion, $scopeHash, $filtersHash);
        $ttl = $report === 'dashboard' ? self::DASHBOARD_TTL_SECONDS : self::REPORT_TTL_SECONDS;

        return $this->rememberSafely($key, $ttl, $resolver, $report);
    }

    /**
     * @template TValue
     *
     * @param  list<int>  $warehouseIds
     * @param  array<string, mixed>  $filters
     * @param  callable(): TValue  $resolver
     * @return TValue
     *
     * @throws JsonException
     */
    public function rememberInventory(
        string $name,
        User $user,
        array $warehouseIds,
        array $filters,
        int $page,
        callable $resolver,
    ): mixed {
        sort($warehouseIds);
        ksort($filters);
        $permissionNames = $user->getAllPermissions()->pluck('name')->all();
        sort($permissionNames);
        $scopeHash = hash('sha256', json_encode([
            'warehouses' => $warehouseIds,
            'permissions' => $permissionNames,
        ], JSON_THROW_ON_ERROR));
        $version = $this->inventoryVersions->forWarehouses($warehouseIds);
        $filtersHash = hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        $key = "inv:{$version}:{$scopeHash}:{$filtersHash}:{$page}:{$name}";

        return $this->rememberSafely($key, self::INVENTORY_TTL_SECONDS, $resolver, $name);
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $resolver
     * @return TValue
     */
    private function rememberSafely(string $key, int $ttl, callable $resolver, string $context): mixed
    {
        try {
            return Cache::remember($key, now()->addSeconds($ttl), $resolver);
        } catch (Throwable $exception) {
            Log::warning('Application cache unavailable; resolving data directly.', [
                'cache_context' => $context,
                'exception' => $exception::class,
            ]);

            return $resolver();
        }
    }
}
