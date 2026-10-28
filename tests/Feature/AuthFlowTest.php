<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_new_user_can_register_and_receives_only_viewer_access(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Inventory User',
            'email' => 'new-user@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'role' => 'Administrator',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Your account has been created.')
            ->assertJsonPath('role', 'Viewer');

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', $user->email);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
        $this->assertFalse($user->hasRole('Administrator'));
    }

    public function test_each_tab_token_keeps_its_own_role_and_logout_is_tab_scoped(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $adminToken = $admin->createToken('admin-tab')->plainTextToken;
        $managerToken = $manager->createToken('manager-tab')->plainTextToken;

        Auth::forgetGuards();
        $this->withToken($adminToken)->getJson('/api/me')->assertOk()->assertJsonPath('role', 'Administrator');

        Auth::forgetGuards();
        $this->withToken($managerToken)->getJson('/api/me')->assertOk()->assertJsonPath('role', 'Manager');

        Auth::forgetGuards();
        $this->withToken($adminToken)->postJson('/api/logout')->assertOk();

        Auth::forgetGuards();
        $this->withToken($adminToken)->getJson('/api/me')->assertUnauthorized();

        Auth::forgetGuards();
        $this->withToken($managerToken)->getJson('/api/me')->assertOk()->assertJsonPath('role', 'Manager');
    }

    public function test_duplicate_email_and_unconfirmed_password_are_rejected(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Duplicate User',
            'email' => 'admin@example.com',
            'password' => 'StrongPassword123',
            'password_confirmation' => 'not-the-same',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_forgot_password_sends_a_reset_notification_with_the_spa_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset-user@example.com']);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $actionUrl = $notification->toMail($user)->actionUrl;
            $parts = parse_url($actionUrl);
            parse_str($parts['query'] ?? '', $query);

            return ($parts['path'] ?? null) === '/reset-password'
                && ($query['email'] ?? null) === $user->email
                && filled($query['token'] ?? null);
        });
    }

    public function test_password_reset_token_updates_the_users_password(): void
    {
        $user = User::factory()->create(['email' => 'password-user@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ChangedPassword123',
            'password_confirmation' => 'ChangedPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
    }

    public function test_deactivating_an_account_revokes_its_existing_api_tokens(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $viewerRole = Role::findByName('Viewer', 'web');
        $user = User::factory()->create();
        $user->assignRole($viewerRole);
        $token = $user->createToken('existing-browser-tab')->plainTextToken;

        $this->actingAs($admin)->putJson('/api/users/'.$user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'Viewer',
            'is_active' => false,
        ])->assertOk();

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_last_active_administrator_cannot_be_demoted_or_deactivated(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $payload = [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Viewer',
            'is_active' => true,
        ];

        $this->actingAs($admin)->putJson('/api/users/'.$admin->id, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->deleteJson('/api/users/'.$admin->id)
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->assertTrue($admin->fresh()->hasRole('Administrator'));
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }
}
