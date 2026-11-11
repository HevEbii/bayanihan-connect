<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\Donation;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Automated non-functional checks (security and reliability) for the A2 testing document.
 */
class NonFunctionalTestCasesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function coordinator(): User
    {
        return User::where('email', 'coordinator@bayanihan.test')->firstOrFail();
    }

    public function test_NFT_SEC_01_passwords_are_stored_hashed(): void
    {
        foreach (User::all() as $user) {
            $this->assertStringStartsWith('$2y$', $user->getAuthPassword());
            $this->assertTrue(Hash::check('password', $user->getAuthPassword()));
            $this->assertNotSame('password', $user->getAuthPassword());
        }
    }

    public function test_NFT_SEC_02_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'coordinator@bayanihan.test', 'password' => 'wrong-'.$i]);
        }
        $this->post('/login', ['email' => 'coordinator@bayanihan.test', 'password' => 'wrong-again'])->assertStatus(429);
    }

    public function test_NFT_SEC_03_sql_injection_in_search_is_harmless(): void
    {
        $this->actingAs($this->coordinator());
        $total = Beneficiary::count();
        $this->get(route('beneficiaries.index', ['q' => "' OR '1'='1' --"]))->assertOk()->assertSee('No beneficiaries match');
        $this->assertSame($total, Beneficiary::count());
    }

    public function test_NFT_SEC_04_script_input_is_escaped(): void
    {
        $this->actingAs($this->coordinator());
        $this->post(route('beneficiaries.store'), ['first_name' => '<script>alert(1)</script>', 'last_name' => 'Xss', 'barangay' => 'Brgy. Test',
            'household_size' => 1, 'category' => 'family']);
        $b = Beneficiary::where('last_name', 'Xss')->firstOrFail();
        $this->get(route('beneficiaries.show', $b))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_NFT_SEC_05_unauthenticated_users_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/beneficiaries', '/donations', '/reports', '/users'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_NFT_REL_01_balance_cannot_be_overdrawn_by_repeated_entries(): void
    {
        $this->actingAs($this->coordinator());
        $d = Donation::where('item_name', 'Family Food Packs')->firstOrFail();
        $half = (int) floor($d->remaining() / 2) + 1; // two draws of this size exceed the balance
        $activity = \App\Models\Activity::where('title', 'like', '%Tumana Food Pack%')->firstOrFail();
        $payload = fn () => ['activity_id' => $activity->id, 'item_name' => 'Family Food Packs', 'quantity' => $half,
            'unit_id' => $d->unit_id, 'distributed_at' => today()->format('Y-m-d'),
            'funding' => [['donation_id' => $d->id, 'amount' => $half]]];
        $this->post(route('distributions.store'), $payload())->assertSessionHasNoErrors();
        $this->post(route('distributions.store'), $payload())->assertSessionHasErrors('funding.0.amount');
        $this->assertGreaterThanOrEqual(0, $d->fresh()->remaining());
    }

    public function test_NFT_REL_02_failed_save_leaves_no_partial_records(): void
    {
        $this->actingAs($this->coordinator());
        $before = [DB::table('distributions')->count(), DB::table('distribution_funding')->count(), DB::table('beneficiary_distribution')->count()];
        $d = Donation::where('item_name', 'Family Food Packs')->firstOrFail();
        $activity = \App\Models\Activity::where('title', 'like', '%Tumana Food Pack%')->firstOrFail();
        $this->post(route('distributions.store'), ['activity_id' => $activity->id, 'item_name' => 'Packs', 'quantity' => 999999,
            'unit_id' => $d->unit_id, 'distributed_at' => today()->format('Y-m-d'),
            'beneficiaries' => Beneficiary::limit(2)->pluck('id')->all(),
            'funding' => [['donation_id' => $d->id, 'amount' => 999999]]]);
        $after = [DB::table('distributions')->count(), DB::table('distribution_funding')->count(), DB::table('beneficiary_distribution')->count()];
        $this->assertSame($before, $after);
    }

    public function test_NFT_REL_03_offline_replay_creates_no_duplicates(): void
    {
        $this->actingAs($this->coordinator());
        $payload = ['client_uuid' => (string) Str::uuid(), 'first_name' => 'Replay', 'last_name' => 'Test', 'barangay' => 'Brgy. Test',
            'household_size' => 1, 'category' => 'family', 'program_id' => Program::first()->id];
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('beneficiaries.store'), $payload)->assertOk();
        }
        $this->assertSame(1, Beneficiary::where('last_name', 'Test')->count());
    }

    public function test_NFT_USE_01_every_save_ends_on_a_confirmation(): void
    {
        $this->actingAs($this->coordinator());
        $this->followingRedirects()->post(route('beneficiaries.store'), ['first_name' => 'Nena', 'last_name' => 'Ocampo', 'barangay' => 'Brgy. Concepcion',
            'household_size' => 2, 'category' => 'senior'])
            ->assertSee('Beneficiary Registered Successfully')->assertSee('Entry Log Snapshot')
            ->assertSee('Record Another Entry')->assertSee('Back to Dashboard');
    }
}
