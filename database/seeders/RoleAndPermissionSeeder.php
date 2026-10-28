<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            'users.manage',
            'roles.manage',
            'products.view',
            'products.create',
            'products.update',
            'products.archive',
            'categories.view',
            'categories.manage',
            'suppliers.view',
            'suppliers.manage',
            'warehouses.view',
            'warehouses.manage',
            'inventory.view',
            'inventory.adjust',
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.submit',
            'purchases.approve',
            'purchases.cancel',
            'purchases.receive',
            'customers.view',
            'customers.manage',
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.confirm',
            'sales.cancel',
            'transfers.view',
            'transfers.create',
            'transfers.dispatch',
            'transfers.receive',
            'transfers.cancel',
            'adjustments.view',
            'returns.view',
            'returns.create',
            'returns.update',
            'returns.complete',
            'returns.cancel',
            'reports.view',
            'reports.export',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Roles
        $adminRole = Role::findOrCreate('Administrator', 'web');
        $managerRole = Role::findOrCreate('Manager', 'web');
        $staffRole = Role::findOrCreate('Warehouse Staff', 'web');
        $viewerRole = Role::findOrCreate('Viewer', 'web');

        // Administrator: ALL
        $adminRole->syncPermissions($permissions);

        // Manager
        $managerRole->syncPermissions([
            'products.view',
            'products.create',
            'products.update',
            'products.archive',
            'categories.view',
            'categories.manage',
            'suppliers.view',
            'suppliers.manage',
            'warehouses.view',
            'inventory.view',
            'inventory.adjust',
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'purchases.submit',
            'purchases.approve',
            'purchases.cancel',
            'purchases.receive',
            'customers.view',
            'customers.manage',
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.confirm',
            'sales.cancel',
            'transfers.view',
            'transfers.create',
            'transfers.dispatch',
            'transfers.receive',
            'transfers.cancel',
            'adjustments.view',
            'returns.view',
            'returns.create',
            'returns.update',
            'returns.complete',
            'returns.cancel',
            'reports.view',
            'reports.export',
        ]);

        // Warehouse Staff
        $staffRole->syncPermissions([
            'products.view',
            'categories.view',
            'warehouses.view',
            'inventory.view',
            'purchases.view',
            'purchases.receive',
            'sales.view',
            'sales.confirm',
            'transfers.view',
            'transfers.dispatch',
            'transfers.receive',
            'adjustments.view',
            'returns.view',
            'returns.complete',
            'reports.view',
        ]);
        // Viewer
        $viewerRole->syncPermissions([
            'products.view',
            'categories.view',
            'suppliers.view',
            'warehouses.view',
            'inventory.view',
            'customers.view',
            'purchases.view',
            'sales.view',
            'transfers.view',
            'adjustments.view',
            'returns.view',
            'reports.view',
        ]);

        if (! app()->environment(['local', 'testing'])) {
            $adminName = config('app.bootstrap_admin.name');
            $adminEmail = config('app.bootstrap_admin.email');
            $adminPassword = config('app.bootstrap_admin.password');

            if (! is_string($adminName) || trim($adminName) === '' || ! is_string($adminEmail) || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || ! is_string($adminPassword) || mb_strlen($adminPassword) < 16) {
                throw new \RuntimeException('Production seeding requires INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and an INITIAL_ADMIN_PASSWORD of at least 16 characters.');
            }

            $admin = User::query()->firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => Hash::make($adminPassword),
                    'is_active' => true,
                ]
            );
            $admin->syncRoles([$adminRole]);

            return;
        }

        // Create isolated local verification accounts with one shared password.
        $testPassword = Hash::make('password');

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => $testPassword, 'is_active' => true],
        );
        $admin->syncRoles([$adminRole]);

        $manager = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Manager 1', 'password' => $testPassword, 'is_active' => true],
        );
        $manager->syncRoles([$managerRole]);

        $manager2 = User::updateOrCreate(
            ['email' => 'manager2@example.com'],
            ['name' => 'Manager 2', 'password' => $testPassword, 'is_active' => true],
        );
        $manager2->syncRoles([$managerRole]);

        $staffA = User::updateOrCreate(
            ['email' => 'staffa@example.com'],
            ['name' => 'Staff A', 'password' => $testPassword, 'is_active' => true],
        );
        $staffA->syncRoles([$staffRole]);

        $staffB = User::updateOrCreate(
            ['email' => 'staffb@example.com'],
            ['name' => 'Staff B', 'password' => $testPassword, 'is_active' => true],
        );
        $staffB->syncRoles([$staffRole]);
        // Keep the original staff fixture used by the existing feature tests.
        $staff = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            ['name' => 'Warehouse Staff', 'password' => $testPassword, 'is_active' => true],
        );
        $staff->syncRoles([$staffRole]);

        $viewer = User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Viewer', 'password' => $testPassword, 'is_active' => true],
        );
        $viewer->syncRoles([$viewerRole]);

        $inactive = User::updateOrCreate(
            ['email' => 'inactive@example.com'],
            ['name' => 'Inactive', 'password' => $testPassword, 'is_active' => false],
        );
        $inactive->syncRoles([$viewerRole]);

        $mainWarehouse = Warehouse::updateOrCreate(
            ['name' => 'Main Central Warehouse'],
            [
                'location' => 'Sundar Industrial Estate, Lahore',
                'contact_person' => 'Warehouse Contact A',
                'phone' => '+92 300 555 0201',
                'email' => 'warehouse01@example.test',
                'status' => 'active',
            ],
        );

        $northWarehouse = Warehouse::updateOrCreate(
            ['name' => 'North Distribution Center'],
            [
                'location' => 'Shahdara, Lahore',
                'contact_person' => 'Warehouse Contact B',
                'phone' => '+92 300 555 0202',
                'email' => 'warehouse02@example.test',
                'status' => 'active',
            ],
        );

        $warehouseA = Warehouse::updateOrCreate(
            ['name' => 'Warehouse A'],
            [
                'location' => 'Gulberg I, Lahore',
                'contact_person' => 'Warehouse A Contact',
                'phone' => '+92 300 555 0301',
                'email' => 'warehouse-a@example.test',
                'status' => 'active',
            ],
        );

        $warehouseB = Warehouse::updateOrCreate(
            ['name' => 'Warehouse B'],
            [
                'location' => 'Gulberg III, Lahore',
                'contact_person' => 'Warehouse B Contact',
                'phone' => '+92 300 555 0302',
                'email' => 'warehouse-b@example.test',
                'status' => 'active',
            ],
        );

        $staffA->warehouses()->sync([$warehouseA->id]);
        $staffB->warehouses()->sync([$warehouseB->id]);
        $staff->warehouses()->sync([$mainWarehouse->id]);

        // Master Data Seeds
        $mainWarehouse = Warehouse::updateOrCreate(
            ['name' => 'Main Central Warehouse'],
            [
                'location' => 'Sundar Industrial Estate, Lahore',
                'contact_person' => 'Zain Qureshi',
                'phone' => '+92 300 555 0201',
                'email' => 'warehouse01@example.test',
                'status' => 'active',
            ]
        );

        $northWarehouse = Warehouse::updateOrCreate(
            ['name' => 'North Distribution Center'],
            [
                'location' => 'Shahdara, Lahore',
                'contact_person' => 'Saad Mahmood',
                'phone' => '+92 300 555 0202',
                'email' => 'warehouse02@example.test',
                'status' => 'active',
            ]
        );

        $generalCategory = Category::firstOrCreate(
            ['name' => 'Electronics'],
            [
                'description' => 'Electronic appliances and computer accessories',
                'status' => 'active',
            ]
        );

        Category::firstOrCreate(
            ['name' => 'Raw Materials'],
            [
                'description' => 'Manufacturing and packaging raw materials',
                'status' => 'active',
            ]
        );

        Supplier::updateOrCreate(
            ['name' => 'Apex Tech Distributors'],
            [
                'company_name' => 'Apex Technology Distributors (Pvt.) Ltd.',
                'contact_person' => 'Faraz Ali',
                'phone' => '+92 300 555 0101',
                'email' => 'supplier01@example.test',
                'address' => 'Main Boulevard, Gulberg I, Lahore',
                'status' => 'active',
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'PROD-ELEC-001'],
            [
                'name' => 'Wireless Ergonomic Mouse',
                'description' => '2.4GHz USB wireless optical mouse with quiet click switches.',
                'category_id' => $generalCategory->id,
                'brand' => 'TechPro',
                'unit' => 'pcs',
                'barcode' => '8901234567890',
                'purchase_price' => 15.00,
                'selling_price' => 28.50,
                'minimum_stock' => 10,
                'status' => 'active',
            ]
        );
    }
}
