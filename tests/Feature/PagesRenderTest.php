<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Program;
use App\Models\User;
use App\Models\Volunteer;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** Every screen renders for every role allowed to open it. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_and_logout(): void
    {
        $this->post('/login', ['email' => 'director@bayanihan.test', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => 'director@bayanihan.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    public function test_all_pages_render_for_each_permitted_role(): void
    {
        $pages = [
            [null, route('dashboard')],
            ['beneficiaries.view', route('beneficiaries.index')],
            ['beneficiaries.manage', route('beneficiaries.create')],
            ['beneficiaries.view', route('beneficiaries.show', Beneficiary::first())],
            ['beneficiaries.manage', route('beneficiaries.edit', Beneficiary::first())],
            ['donors.view', route('donors.index')],
            ['donors.manage', route('donors.create')],
            ['donors.view', route('donors.show', Donor::first())],
            ['donations.view', route('donations.index')],
            ['donations.manage', route('donations.create')],
            ['donations.view', route('donations.show', Donation::first())],
            ['distributions.view', route('distributions.index')],
            ['distributions.manage', route('distributions.create')],
            ['distributions.view', route('distributions.show', Distribution::first())],
            ['programs.view', route('programs.index')],
            ['programs.manage', route('programs.create')],
            ['programs.view', route('programs.show', Program::first())],
            ['programs.manage', route('activities.create')],
            ['programs.view', route('activities.show', Activity::first())],
            ['programs.manage', route('activities.edit', Activity::first())],
            ['volunteers.view', route('volunteers.index')],
            ['volunteers.manage', route('volunteers.create')],
            ['volunteers.view', route('volunteers.show', Volunteer::first())],
            ['schedule.own', route('schedule.index')],
            ['messages.use', route('messages.index')],
            ['reports.view', route('reports.index', ['from' => today()->subYear()->format('Y-m-d'), 'to' => today()->format('Y-m-d')])],
            ['impact.view', route('impact')],
            ['users.manage', route('users.index')],
            ['users.manage', route('users.create')],
            ['users.manage', route('users.edit', User::first())],
            ['audit.view', route('audit.index')],
            ['settings.manage', route('units.index')],
        ];

        foreach (Role::all() as $role) {
            $user = User::where('role', $role)->first();
            foreach ($pages as [$ability, $url]) {
                $this->actingAs($user);
                $allowed = $ability === null || Gate::forUser($user)->allows($ability);
                $response = $this->get($url);
                if ($role === Role::DONOR && $url === route('dashboard')) {
                    $response->assertRedirect(route('impact'));
                    continue;
                }
                $allowed
                    ? $this->assertSame(200, $response->status(), "$role should open $url")
                    : $this->assertSame(403, $response->status(), "$role should be blocked from $url");
            }
        }
    }

    public function test_confirmation_page_shows_snapshot_after_save(): void
    {
        $coordinator = User::where('email', 'coordinator@bayanihan.test')->first();
        $this->actingAs($coordinator)->followingRedirects()
            ->post(route('beneficiaries.store'), [
                'first_name' => 'Nena', 'last_name' => 'Ocampo', 'barangay' => 'Brgy. Concepcion',
                'household_size' => 2, 'category' => 'senior',
            ])
            ->assertOk()->assertSee('Beneficiary Registered Successfully')->assertSee('Entry Log Snapshot')->assertSee('Nena Ocampo');
    }

    public function test_admin_creates_volunteer_account_with_linked_profile(): void
    {
        $admin = User::where('role', Role::ADMIN)->first();
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Lito Gomez', 'email' => 'lito@volunteer.test', 'role' => Role::VOLUNTEER,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1,
        ])->assertRedirect(route('users.index'));

        $this->assertNotNull(User::where('email', 'lito@volunteer.test')->first()->volunteer);
    }

    public function test_director_cannot_grant_admin_role(): void
    {
        $director = User::where('role', Role::DIRECTOR)->first();
        $this->actingAs($director)->post(route('users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@x.test', 'role' => Role::ADMIN,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1,
        ])->assertForbidden();
    }
}
