<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ModuleSixPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['reports.view', 'reports.export'] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Role::findByName('Administrator', 'web')->givePermissionTo([
            'reports.view',
            'reports.export',
        ]);

        Role::findByName('Manager', 'web')->givePermissionTo([
            'reports.view',
            'reports.export',
        ]);

        Role::findByName('Warehouse Staff', 'web')->givePermissionTo('reports.view');
        Role::findByName('Viewer', 'web')->givePermissionTo('reports.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
