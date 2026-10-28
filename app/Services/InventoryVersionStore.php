<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InventoryVersionStore
{
    /**
     * @param  list<int>  $warehouseIds
     */
    public function forWarehouses(array $warehouseIds): string
    {
        sort($warehouseIds);
        $scopeKeys = ['all', ...array_map(static fn (int $id): string => "warehouse:{$id}", $warehouseIds)];
        $now = now();

        DB::table('inventory_cache_versions')->insertOrIgnore(array_map(
            static fn (string $scopeKey): array => [
                'scope_key' => $scopeKey,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $scopeKeys,
        ));

        $versions = DB::table('inventory_cache_versions')
            ->whereIn('scope_key', $scopeKeys)
            ->pluck('version', 'scope_key')
            ->map(static fn (mixed $version): int => (int) $version)
            ->all();

        foreach ($versions as $scopeKey => $version) {
            $this->synchronizeRedisCounter($scopeKey, $version);
        }

        return hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR));
    }

    public function incrementForWarehouse(int $warehouseId): void
    {
        $scopeKeys = ['all', "warehouse:{$warehouseId}"];
        $now = now();

        DB::table('inventory_cache_versions')->insertOrIgnore(array_map(
            static fn (string $scopeKey): array => [
                'scope_key' => $scopeKey,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $scopeKeys,
        ));

        foreach ($scopeKeys as $scopeKey) {
            DB::table('inventory_cache_versions')
                ->where('scope_key', $scopeKey)
                ->increment('version', 1, ['updated_at' => $now]);

            $version = (int) DB::table('inventory_cache_versions')
                ->where('scope_key', $scopeKey)
                ->value('version');

            $this->synchronizeRedisCounter($scopeKey, $version);
        }
    }

    private function synchronizeRedisCounter(string $scopeKey, int $version): void
    {
        try {
            Cache::store('redis_connection')->put($this->redisKey($scopeKey), $version, now()->addYears(5));
        } catch (Throwable $exception) {
            Log::warning('Redis inventory version sync failed; database version remains authoritative.', [
                'scope_key' => $scopeKey,
                'exception' => $exception::class,
            ]);
        }
    }

    private function redisKey(string $scopeKey): string
    {
        return $scopeKey === 'all'
            ? 'inv:all:version'
            : 'inv:wh:'.substr($scopeKey, strlen('warehouse:')).':version';
    }
}
