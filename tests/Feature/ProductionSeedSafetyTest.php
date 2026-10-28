<?php

namespace Tests\Feature;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ProductionSeedSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeding_creates_only_the_configured_administrator(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.bootstrap_admin.name' => 'Configured Administrator',
            'app.bootstrap_admin.email' => 'owner@inventory.example',
            'app.bootstrap_admin.password' => 'a-strong-production-password',
        ]);

        $this->app->make(RoleAndPermissionSeeder::class)->run();

        $this->assertDatabaseHas('users', [
            'name' => 'Configured Administrator',
            'email' => 'owner@inventory.example',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_production_seeding_refuses_missing_bootstrap_credentials(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.bootstrap_admin.name' => null,
            'app.bootstrap_admin.email' => null,
            'app.bootstrap_admin.password' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Production seeding requires INITIAL_ADMIN_NAME');

        $this->app->make(RoleAndPermissionSeeder::class)->run();
    }

    public function test_database_notifications_have_a_migrated_table(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
    }
}
