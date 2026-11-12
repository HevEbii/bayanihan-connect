<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Unit;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Production setup: units and the first Administrator, with no demo data. */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Starts every test from a brand-new, empty site (the shared test database may hold demo data). */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'messages', 'volunteer_assignments', 'distribution_funding', 'beneficiary_distribution',
            'distributions', 'donations', 'activities', 'beneficiaries', 'volunteers', 'donors', 'programs', 'users', 'units'] as $table) {
            DB::table($table)->delete();
        }
    }

    public function test_creates_units_and_one_administrator_without_demo_data(): void
    {
        $this->artisan('bayanihan:install', ['--email' => 'admin@foundation.test', '--password' => 'Str0ng-Pass!', '--name' => 'Foundation Admin'])
            ->expectsOutputToContain('Administrator account created')->assertSuccessful();

        $this->assertGreaterThanOrEqual(8, Unit::count());
        $this->assertSame(1, User::count());
        $admin = User::first();
        $this->assertSame(Role::ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Str0ng-Pass!', $admin->password));
        $this->assertSame(0, Beneficiary::count());
        $this->assertDatabaseMissing('users', ['email' => 'coordinator@bayanihan.test']);
    }

    public function test_reads_credentials_from_settings(): void
    {
        config(['bayanihan.admin' => ['name' => 'Env Admin', 'email' => 'env@foundation.test', 'password' => 'An0ther-Pass!']]);

        $this->artisan('bayanihan:install')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'env@foundation.test', 'role' => Role::ADMIN, 'name' => 'Env Admin']);
    }

    public function test_is_safe_to_run_again_and_never_changes_an_existing_password(): void
    {
        $args = ['--email' => 'admin@foundation.test', '--password' => 'Str0ng-Pass!'];
        $this->artisan('bayanihan:install', $args)->assertSuccessful();
        $units = Unit::count();

        $this->artisan('bayanihan:install', ['--email' => 'admin@foundation.test', '--password' => 'Different-Pass1'])
            ->expectsOutputToContain('left unchanged')->assertSuccessful();
        $this->artisan('bayanihan:install')->expectsOutputToContain('already exists')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame($units, Unit::count());
        $this->assertTrue(Hash::check('Str0ng-Pass!', User::first()->password));
    }

    public function test_rejects_the_demo_password_and_short_passwords(): void
    {
        $this->artisan('bayanihan:install', ['--email' => 'admin@foundation.test', '--password' => 'password'])->assertFailed();
        $this->artisan('bayanihan:install', ['--email' => 'admin@foundation.test', '--password' => 'short'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_fails_clearly_when_no_administrator_and_no_credentials(): void
    {
        $this->artisan('bayanihan:install', ['--no-interaction' => true])
            ->expectsOutputToContain('Set ADMIN_EMAIL and ADMIN_PASSWORD')->assertFailed();
        $this->assertSame(0, User::count());
    }
}
