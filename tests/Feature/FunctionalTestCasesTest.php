<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Message;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Functional test cases for the A2 testing document. Each method is one test case: TC-FRxx-yy.
 */
class FunctionalTestCasesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function as(string $role): User
    {
        $email = ['coordinator' => 'coordinator@bayanihan.test', 'finance' => 'finance@bayanihan.test',
            'director' => 'director@bayanihan.test', 'volunteer' => 'volunteer@bayanihan.test',
            'donor' => 'donor@bayanihan.test', 'admin' => 'admin@bayanihan.test'][$role];
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    private function beneficiary(array $o = []): array
    {
        return array_merge(['first_name' => 'Lorna', 'last_name' => 'Pacheco', 'barangay' => 'Brgy. Tumana',
            'household_size' => 4, 'category' => 'family', 'program_id' => Program::first()->id], $o);
    }

    private function packs(): Donation
    {
        return Donation::where('item_name', 'Family Food Packs')->firstOrFail();
    }

    private function distribution(array $funding, array $o = []): array
    {
        $activity = Activity::where('title', 'like', '%Tumana Food Pack%')->firstOrFail();

        return array_merge(['activity_id' => $activity->id, 'item_name' => 'Family Food Packs', 'quantity' => 100,
            'unit_id' => Unit::where('name', 'Pack')->value('id'), 'distributed_at' => today()->format('Y-m-d'),
            'beneficiaries' => Beneficiary::where('program_id', $activity->program_id)->limit(3)->pluck('id')->all(),
            'funding' => $funding], $o);
    }

    private function newActivity(array $o = []): Activity
    {
        return Activity::create(array_merge(['program_id' => Program::first()->id, 'title' => 'Test Activity',
            'activity_date' => today()->addDays(20), 'start_time' => '08:00:00', 'end_time' => '10:00:00',
            'volunteer_slots' => 5, 'status' => 'scheduled'], $o));
    }

    // ---------- FR01 Beneficiaries ----------

    public function test_TC_FR01_01_register_valid_beneficiary(): void
    {
        $this->as('coordinator');
        $this->followingRedirects()->post(route('beneficiaries.store'), $this->beneficiary())
            ->assertOk()->assertSee('Beneficiary Registered Successfully');
        $this->assertMatchesRegularExpression('/^BN-\d{5}$/', Beneficiary::where('last_name', 'Pacheco')->value('code'));
    }

    public function test_TC_FR01_02_missing_required_field_is_rejected(): void
    {
        $this->as('coordinator');
        $this->post(route('beneficiaries.store'), $this->beneficiary(['first_name' => '']))
            ->assertSessionHasErrors(['first_name' => "Please enter the beneficiary's first name."]);
        $this->assertDatabaseMissing('beneficiaries', ['last_name' => 'Pacheco']);
    }

    public function test_TC_FR01_03_duplicate_beneficiary_is_rejected(): void
    {
        $this->as('coordinator');
        $b = Beneficiary::first();
        $this->post(route('beneficiaries.store'), $this->beneficiary(['first_name' => $b->first_name, 'last_name' => $b->last_name, 'barangay' => $b->barangay]))
            ->assertSessionHasErrors('first_name');
        $this->assertStringContainsString('already registered as '.$b->code, session('errors')->first('first_name'));
    }

    public function test_TC_FR01_04_update_existing_beneficiary(): void
    {
        $user = $this->as('coordinator');
        $b = Beneficiary::first();
        $this->put(route('beneficiaries.update', $b), $this->beneficiary(['first_name' => $b->first_name, 'last_name' => $b->last_name,
            'barangay' => $b->barangay, 'household_size' => 9]))->assertRedirect(route('beneficiaries.show', $b));
        $this->assertSame(9, (int) $b->fresh()->household_size);
        $this->assertSame($user->id, (int) $b->fresh()->updated_by);
    }

    // ---------- FR02 Donations ----------

    public function test_TC_FR02_01_record_cash_donation_linked_to_program(): void
    {
        $this->as('finance');
        $program = Program::first();
        $this->followingRedirects()->post(route('donations.store'), ['donor_id' => 1, 'type' => 'cash', 'quantity' => 5000,
            'program_id' => $program->id, 'received_at' => today()->format('Y-m-d')])
            ->assertOk()->assertSee('Donation Recorded Successfully');
        $d = Donation::latest('id')->first();
        $this->assertMatchesRegularExpression('/^DN-\d{4}-\d{4}$/', $d->reference_no);
        $this->assertSame($program->id, (int) $d->program_id);
    }

    public function test_TC_FR02_02_missing_donor_is_rejected(): void
    {
        $this->as('finance');
        $count = Donation::count();
        $this->post(route('donations.store'), ['type' => 'cash', 'quantity' => 5000, 'program_id' => Program::first()->id,
            'received_at' => today()->format('Y-m-d')])->assertSessionHasErrors(['donor_id' => 'Please select the donor.']);
        $this->assertSame($count, Donation::count());
    }

    public function test_TC_FR02_03_activity_from_another_program_is_rejected(): void
    {
        $this->as('finance');
        $other = Activity::where('program_id', '!=', Program::first()->id)->first();
        $this->post(route('donations.store'), ['donor_id' => 1, 'type' => 'cash', 'quantity' => 100, 'program_id' => Program::first()->id,
            'activity_id' => $other->id, 'received_at' => today()->format('Y-m-d')])
            ->assertSessionHasErrors(['activity_id' => 'The selected activity belongs to a different program.']);
    }

    // ---------- FR03 Distributions ----------

    public function test_TC_FR03_01_distribution_deducts_donation_balance(): void
    {
        $this->as('coordinator');
        $d = $this->packs();
        $before = $d->remaining();
        $this->followingRedirects()->post(route('distributions.store'), $this->distribution([['donation_id' => $d->id, 'amount' => 100]]))
            ->assertOk()->assertSee('Record Saved Successfully');
        $this->assertEquals($before - 100, $d->fresh()->remaining());
        $this->assertMatchesRegularExpression('/^RC-\d{4}-\d{4}$/', Distribution::latest('id')->value('reference_no'));
    }

    public function test_TC_FR03_02_insufficient_balance_is_rejected(): void
    {
        $this->as('coordinator');
        $d = $this->packs();
        $over = $d->remaining() + 1;
        $count = Distribution::count();
        $this->post(route('distributions.store'), $this->distribution([['donation_id' => $d->id, 'amount' => $over]], ['quantity' => $over]))
            ->assertSessionHasErrors('funding.0.amount');
        $this->assertStringContainsString('Insufficient balance', session('errors')->first('funding.0.amount'));
        $this->assertSame($count, Distribution::count());
    }

    public function test_TC_FR03_03_unit_or_program_mismatch_is_rejected(): void
    {
        $this->as('coordinator');
        $water = Donation::where('item_name', 'Bottled Water')->firstOrFail();
        $this->post(route('distributions.store'), $this->distribution([['donation_id' => $water->id, 'amount' => 10]], ['quantity' => 10]))
            ->assertSessionHasErrors('funding.0.donation_id');
    }

    public function test_TC_FR03_04_shortfall_covered_by_additional_source(): void
    {
        $this->as('coordinator');
        $d = $this->packs();
        $cash = Donation::where('type', 'cash')->where('program_id', $d->program_id)->firstOrFail();
        $this->post(route('distributions.store'), $this->distribution([
            ['donation_id' => $d->id, 'amount' => $d->remaining()], ['donation_id' => $cash->id, 'amount' => 7500],
        ], ['quantity' => $d->remaining() + 50]))->assertRedirect(route('confirmation'));
        $this->assertEquals(0, $d->fresh()->remaining());
    }

    // ---------- FR04 Volunteers & schedules ----------

    public function test_TC_FR04_01_assign_volunteer_without_conflict(): void
    {
        $this->as('coordinator');
        $v = Volunteer::where('first_name', 'Andrea')->firstOrFail();
        $a = $this->newActivity();
        $this->post(route('assignments.store'), ['volunteer_id' => $v->id, 'activity_id' => $a->id, 'task' => 'Registration desk'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('volunteer_assignments', ['volunteer_id' => $v->id, 'activity_id' => $a->id, 'status' => 'assigned']);
    }

    public function test_TC_FR04_02_schedule_conflict_is_rejected(): void
    {
        $this->as('coordinator');
        $v = Volunteer::where('first_name', 'Paolo')->firstOrFail();
        $booked = VolunteerAssignment::where('volunteer_id', $v->id)->where('status', 'assigned')->firstOrFail()->activity;
        $clash = $this->newActivity(['activity_date' => $booked->activity_date, 'start_time' => '10:00:00', 'end_time' => '14:00:00']);
        $this->post(route('assignments.store'), ['volunteer_id' => $v->id, 'activity_id' => $clash->id])->assertSessionHasErrors('volunteer_id');
        $this->assertStringContainsString('Schedule conflict', session('errors')->first('volunteer_id'));
    }

    public function test_TC_FR04_03_full_activity_is_rejected(): void
    {
        $this->as('coordinator');
        $a = $this->newActivity(['volunteer_slots' => 1]);
        $vols = Volunteer::whereIn('first_name', ['Andrea', 'Bea'])->get();
        $this->post(route('assignments.store'), ['volunteer_id' => $vols[0]->id, 'activity_id' => $a->id])->assertSessionHasNoErrors();
        $this->post(route('assignments.store'), ['volunteer_id' => $vols[1]->id, 'activity_id' => $a->id])
            ->assertSessionHasErrors(['activity_id' => 'All volunteer slots for this activity are filled.']);
    }

    public function test_TC_FR04_04_volunteer_signs_up_and_withdraws(): void
    {
        $this->as('volunteer');
        $a = $this->newActivity();
        $this->post(route('schedule.signup', $a))->assertSessionHasNoErrors();
        $assignment = VolunteerAssignment::where('activity_id', $a->id)->firstOrFail();
        $this->delete(route('schedule.withdraw', $assignment))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $assignment->fresh()->status);
    }

    // ---------- FR05 Standard units ----------

    public function test_TC_FR05_01_in_kind_without_unit_is_rejected(): void
    {
        $this->as('finance');
        $this->post(route('donations.store'), ['donor_id' => 1, 'type' => 'in_kind', 'item_name' => 'Canned Sardines', 'quantity' => 100,
            'program_id' => Program::first()->id, 'received_at' => today()->format('Y-m-d')])
            ->assertSessionHasErrors(['unit_id' => 'Please select a unit.']);
    }

    public function test_TC_FR05_02_in_kind_with_standard_unit_is_saved(): void
    {
        $this->as('finance');
        $box = Unit::where('name', 'Box')->value('id');
        $this->post(route('donations.store'), ['donor_id' => 1, 'type' => 'in_kind', 'item_name' => 'Canned Sardines', 'quantity' => 100,
            'unit_id' => $box, 'program_id' => Program::first()->id, 'received_at' => today()->format('Y-m-d')])
            ->assertRedirect(route('confirmation'));
        $d = Donation::latest('id')->first();
        $this->assertSame($box, (int) $d->unit_id);
        $this->assertSame('100 Boxes', $d->formatAmount());
    }

    public function test_TC_FR05_03_unit_not_in_standard_list_is_rejected(): void
    {
        $this->as('finance');
        $this->post(route('donations.store'), ['donor_id' => 1, 'type' => 'in_kind', 'item_name' => 'Canned Sardines', 'quantity' => 100,
            'unit_id' => 99999, 'program_id' => Program::first()->id, 'received_at' => today()->format('Y-m-d')])
            ->assertSessionHasErrors('unit_id');
    }

    // ---------- FR06 Reports ----------

    public function test_TC_FR06_01_generate_consolidated_report(): void
    {
        $this->as('director');
        $this->get(route('reports.index', ['from' => today()->subMonths(2)->format('Y-m-d'), 'to' => today()->format('Y-m-d')]))
            ->assertOk()->assertSee('Cash received')->assertSee('Beneficiaries served')->assertSee('By Program')->assertSee('Goods Distributed');
    }

    public function test_TC_FR06_02_no_data_prompts_to_adjust_parameters(): void
    {
        $this->as('director');
        $this->get(route('reports.index', ['from' => '2001-01-01', 'to' => '2001-12-31']))->assertOk()->assertSee('Try a wider date range');
    }

    public function test_TC_FR06_03_end_date_before_start_is_rejected(): void
    {
        $this->as('director');
        $this->get(route('reports.index', ['from' => '2026-10-31', 'to' => '2026-10-01']))
            ->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    public function test_TC_FR06_04_export_report_to_csv(): void
    {
        $this->as('director');
        $res = $this->get(route('reports.export', ['from' => today()->subMonths(2)->format('Y-m-d'), 'to' => today()->format('Y-m-d')]))->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->streamedContent();
        foreach (['Summary', 'By program', 'Goods received', 'Goods distributed'] as $section) {
            $this->assertStringContainsString($section, $csv);
        }
    }

    // ---------- FR07 Offline capture ----------

    public function test_TC_FR07_01_offline_entry_is_synced_once(): void
    {
        $this->as('coordinator');
        $payload = $this->beneficiary(['client_uuid' => (string) Str::uuid(), 'first_name' => 'Offline']);
        $this->postJson(route('beneficiaries.store'), $payload)->assertOk()->assertJson(['ok' => true]);
        $this->postJson(route('beneficiaries.store'), $payload)->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, Beneficiary::where('client_uuid', $payload['client_uuid'])->count());
    }

    public function test_TC_FR07_02_rejected_offline_entry_returns_reason(): void
    {
        $this->as('coordinator');
        $this->postJson(route('beneficiaries.store'), $this->beneficiary(['client_uuid' => (string) Str::uuid(), 'barangay' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('barangay');
    }

    public function test_TC_FR07_03_field_forms_are_offline_capable(): void
    {
        $this->as('coordinator');
        foreach ([route('beneficiaries.create'), route('distributions.create'), route('messages.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('data-offline=', false)->assertSee('data-client-uuid', false);
        }
        $this->assertFileExists(public_path('sw.js'));
    }

    // ---------- FR08 Role-based access ----------

    public function test_TC_FR08_01_finance_cannot_open_beneficiary_records(): void
    {
        $user = $this->as('finance');
        $this->get(route('beneficiaries.index'))->assertForbidden();
        $this->assertTrue(AuditLog::where('user_id', $user->id)->where('action', 'access.denied')->exists());
    }

    public function test_TC_FR08_02_menu_shows_only_permitted_modules(): void
    {
        $this->as('volunteer');
        $this->get(route('dashboard'))->assertOk()->assertSee('My Schedule')->assertSee('Messages')
            ->assertDontSee('>Donations<', false)->assertDontSee('>Beneficiaries<', false)->assertDontSee('>Reports<', false);
    }

    public function test_TC_FR08_03_donor_sees_only_own_donations(): void
    {
        $this->as('donor');
        $page = $this->get(route('impact'))->assertOk()->assertSee('GMA Kapuso Foundation');
        $page->assertDontSee('Maria Clara Center');
        $b = Beneficiary::first();
        $page->assertDontSee($b->last_name.', '.$b->first_name);
        $this->get(route('donations.index'))->assertForbidden();
    }

    public function test_TC_FR08_04_record_changes_are_logged_for_audit(): void
    {
        $user = $this->as('coordinator');
        $this->post(route('beneficiaries.store'), $this->beneficiary());
        $this->assertTrue(AuditLog::where('user_id', $user->id)->where('action', 'beneficiary.created')->exists());
    }

    public function test_TC_FR08_05_deactivated_account_is_signed_out(): void
    {
        $user = User::where('email', 'coordinator@bayanihan.test')->first();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // ---------- FR09 Messaging ----------

    public function test_TC_FR09_01_send_message_and_mark_seen(): void
    {
        $coordinator = $this->as('coordinator');
        $volunteer = User::where('email', 'volunteer@bayanihan.test')->first();
        $this->post(route('messages.store'), ['recipient_id' => $volunteer->id, 'body' => 'Please bring 4 tables.'])
            ->assertRedirect(route('messages.show', $volunteer->id));
        $this->actingAs($volunteer)->get(route('messages.show', $coordinator))->assertOk()->assertSee('Please bring 4 tables.');
        $this->assertNotNull(Message::where('body', 'Please bring 4 tables.')->value('read_at'));
    }

    public function test_TC_FR09_02_empty_message_is_rejected(): void
    {
        $this->as('coordinator');
        $volunteer = User::where('email', 'volunteer@bayanihan.test')->first();
        $this->post(route('messages.store'), ['recipient_id' => $volunteer->id, 'body' => '   '])
            ->assertSessionHasErrors(['body' => 'Message cannot be empty.']);
    }

    public function test_TC_FR09_03_message_linked_to_activity(): void
    {
        $this->as('coordinator');
        $volunteer = User::where('email', 'volunteer@bayanihan.test')->first();
        $a = Activity::upcoming()->first();
        $this->post(route('messages.store'), ['recipient_id' => $volunteer->id, 'body' => 'About this activity', 'activity_id' => $a->id]);
        $this->get(route('messages.show', $volunteer))->assertSee('Re: '.$a->title);
    }

    public function test_TC_FR09_04_cannot_message_donor_accounts(): void
    {
        $this->as('coordinator');
        $donor = User::where('email', 'donor@bayanihan.test')->first();
        $this->post(route('messages.store'), ['recipient_id' => $donor->id, 'body' => 'Hello'])->assertSessionHasErrors('recipient_id');
    }
}
