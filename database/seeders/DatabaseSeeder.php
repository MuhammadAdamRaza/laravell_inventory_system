<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(SampleInventorySeeder::class);
        }
    }
}
