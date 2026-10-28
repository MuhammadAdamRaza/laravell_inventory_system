<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_administrator_can_view_and_update_system_settings(): void
    {
        $administrator = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($administrator)
            ->getJson('/api/system-settings')
            ->assertOk()
            ->assertJsonPath('data.business_name', config('app.name'));

        $this->actingAs($administrator)
            ->putJson('/api/system-settings', [
                'business_name' => 'Aurelia Inventory',
                'support_email' => 'support@example.com',
                'support_phone' => '+92 300 123 4567',
            ])
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Aurelia Inventory')
            ->assertJsonPath('data.support_email', 'support@example.com');

        $this->assertDatabaseHas('system_settings', [
            'id' => 1,
            'business_name' => 'Aurelia Inventory',
            'support_email' => 'support@example.com',
            'support_phone' => '+92 300 123 4567',
        ]);
    }

    public function test_non_administrator_roles_cannot_access_system_settings(): void
    {
        foreach (['manager@example.com', 'staff@example.com', 'viewer@example.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->actingAs($user)->getJson('/api/system-settings')->assertForbidden();
            $this->actingAs($user)
                ->putJson('/api/system-settings', [
                    'business_name' => 'Unauthorized Update',
                    'support_email' => null,
                    'support_phone' => null,
                ])
                ->assertForbidden();
        }
    }

    public function test_system_settings_validation_prevents_invalid_values(): void
    {
        $administrator = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($administrator)
            ->putJson('/api/system-settings', [
                'business_name' => '',
                'support_email' => 'not-an-email',
                'support_phone' => str_repeat('1', 41),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'business_name',
                'support_email',
                'support_phone',
            ]);
    }
}
