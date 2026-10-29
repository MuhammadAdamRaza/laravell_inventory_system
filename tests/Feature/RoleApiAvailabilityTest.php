<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleApiAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_role_pages_resolve_and_restricted_pages_return_forbidden(string $email, array $extraPages, array $forbiddenPages): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->actingAs(User::query()->where('email', $email)->firstOrFail());
        $commonPages = [
            'products', 'categories', 'warehouses', 'inventory/balances', 'inventory/movements',
            'inventory/stats', 'purchases', 'purchases/options', 'sales-orders', 'sales-orders/options',
            'transfers', 'transfers/options', 'stock-adjustments', 'sales-returns',
            'reports/dashboard', 'reports/options', 'notifications', 'notifications/unread-count',
        ];

        foreach ([...$commonPages, ...$extraPages] as $page) {
            $this->getJson('/api/'.$page)->assertOk();
        }

        foreach ($forbiddenPages as $page) {
            $this->getJson('/api/'.$page)->assertForbidden();
        }
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function roles(): array
    {
        $administration = ['users', 'roles', 'system-settings', 'failed-jobs'];

        return [
            'administrator' => ['admin@example.com', ['suppliers', 'customers', 'report-exports', ...$administration], []],
            'manager' => ['manager@example.com', ['suppliers', 'customers', 'report-exports'], $administration],
            'warehouse staff' => ['staff@example.com', [], ['suppliers', 'customers', 'report-exports', ...$administration]],
            'viewer' => ['viewer@example.com', ['suppliers', 'customers'], ['report-exports', ...$administration]],
        ];
    }

    public function test_viewer_can_open_customer_details_and_history_but_cannot_write(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $customer = Customer::query()->create([
            'name' => 'Customer Contact', 'email' => 'contact@example.test', 'status' => 'active',
        ]);
        $this->actingAs(User::query()->where('email', 'viewer@example.com')->firstOrFail());
        $this->getJson('/api/customers')->assertOk()->assertJsonPath('data.0.id', $customer->id);
        $this->getJson('/api/customers/'.$customer->id)->assertOk();
        $this->getJson('/api/customers/'.$customer->id.'/orders')->assertOk();
        $this->postJson('/api/customers', [])->assertForbidden();
        $this->putJson('/api/customers/'.$customer->id, [])->assertForbidden();
        $this->getJson('/api/customers/999999')->assertNotFound();
    }
}
