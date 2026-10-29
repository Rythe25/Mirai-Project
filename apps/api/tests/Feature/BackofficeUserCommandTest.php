<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackofficeUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_with_a_role(): void
    {
        $this->artisan('backoffice:user', ['email' => 'Owner@Example.test', 'role' => 'owner'])
            ->expectsQuestion('Name', 'Test Owner')
            ->expectsQuestion('Password', 'a-long-test-password')
            ->expectsQuestion('Confirm password', 'a-long-test-password')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.test')->sole();
        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertSame('Test Owner', $user->name);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
    }

    public function test_it_rejects_mismatched_passwords(): void
    {
        $this->artisan('backoffice:user', ['email' => 'staff@example.test', 'role' => 'employee'])
            ->expectsQuestion('Name', 'Test Staff')
            ->expectsQuestion('Password', 'a-long-test-password')
            ->expectsQuestion('Confirm password', 'another-password')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'staff@example.test']);
    }

    public function test_it_changes_the_role_of_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.test', 'role' => UserRole::Employee]);

        $this->artisan('backoffice:user', ['email' => 'staff@example.test', 'role' => 'admin'])
            ->assertSuccessful();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);
    }

    public function test_none_removes_backoffice_access(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.test', 'role' => UserRole::Employee]);

        $this->artisan('backoffice:user', ['email' => 'staff@example.test', 'role' => 'none'])
            ->assertSuccessful();

        $this->assertNull($user->fresh()->role);
    }

    public function test_it_rejects_an_unknown_role_or_a_bad_email(): void
    {
        $this->artisan('backoffice:user', ['email' => 'staff@example.test', 'role' => 'superuser'])
            ->assertFailed();
        $this->artisan('backoffice:user', ['email' => 'not-an-email', 'role' => 'employee'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_none_needs_an_existing_account(): void
    {
        $this->artisan('backoffice:user', ['email' => 'nobody@example.test', 'role' => 'none'])
            ->assertFailed();
    }
}
