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

class FunctionalRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function coordinator(): User { return $this->user('coordinator@bayanihan.test'); }
    private function finance(): User { return $this->user('finance@bayanihan.test'); }
    private function director(): User { return $this->user('director@bayanihan.test'); }
    private function volunteer(): User { return $this->user('volunteer@bayanihan.test'); }
    private function donor(): User { return $this->user('donor@bayanihan.test'); }

    private function beneficiaryPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Lorna', 'last_name' => 'Pacheco', 'barangay' => 'Brgy. Tumana',
            'household_size' => 4, 'category' => 'family', 'program_id' => Program::first()->id,
        ], $overrides);
    }

    // ---------------- FR01 ----------------

    public function test_fr01_coordinator_registers_and_updates_a_beneficiary(): void
    {
        $this->actingAs($this->coordinator())
            ->post(route('beneficiaries.store'), $this->beneficiaryPayload())
            ->assertRedirect(route('confirmation'));

        $b = Beneficiary::where('last_name', 'Pacheco')->firstOrFail();
        $this->assertMatchesRegularExpression('/^BN-\d{5}$/', $b->code);

        $this->put(route('beneficiaries.update', $b), $this->beneficiaryPayload(['household_size' => 6]))
            ->assertRedirect(route('beneficiaries.show', $b));
        $this->assertSame(6, (int) $b->fresh()->household_size);
    }

    public function test_fr01_missing_required_field_returns_to_form_with_error(): void
    {
        $this->actingAs($this->coordinator())
            ->from(route('beneficiaries.create'))
            ->post(route('beneficiaries.store'), $this->beneficiaryPayload(['first_name' => '']))
            ->assertRedirect(route('beneficiaries.create'))
            ->assertSessionHasErrors('first_name');
        $this->assertDatabaseMissing('beneficiaries', ['last_name' => 'Pacheco']);
    }

    public function test_fr01_duplicate_beneficiary_is_rejected(): void
    {
        $existing = Beneficiary::first();
        $this->actingAs($this->coordinator())
            ->post(route('beneficiaries.store'), $this->beneficiaryPayload([
                'first_name' => strtoupper($existing->first_name), 'last_name' => $existing->last_name, 'barangay' => $existing->barangay,
            ]))
            ->assertSessionHasErrors('first_name');
    }

    // ---------------- FR02 / FR05 ----------------

    public function test_fr02_finance_records_donation_linked_to_program_with_reference(): void
    {
        $program = Program::first();
        $this->actingAs($this->finance())
            ->post(route('donations.store'), [
                'donor_id' => 1, 'type' => 'cash', 'quantity' => 5000, 'program_id' => $program->id,
                'received_at' => today()->format('Y-m-d'),
            ])->assertRedirect(route('confirmation'));

        $donation = Donation::latest('id')->first();
        $this->assertSame($program->id, (int) $donation->program_id);
        $this->assertMatchesRegularExpression('/^DN-\d{4}-\d{4}$/', $donation->reference_no);
        $this->assertNull($donation->unit_id);
    }

    public function test_fr05_in_kind_donation_requires_a_standard_unit(): void
    {
        $this->actingAs($this->finance())
            ->post(route('donations.store'), [
                'donor_id' => 1, 'type' => 'in_kind', 'item_name' => 'Canned Sardines', 'quantity' => 100,
                'program_id' => Program::first()->id, 'received_at' => today()->format('Y-m-d'),
            ])->assertSessionHasErrors(['unit_id' => 'Please select a unit.']);

        $this->post(route('donations.store'), [
            'donor_id' => 1, 'type' => 'in_kind', 'item_name' => 'Canned Sardines', 'quantity' => 100, 'unit_id' => 99999,
            'program_id' => Program::first()->id, 'received_at' => today()->format('Y-m-d'),
        ])->assertSessionHasErrors('unit_id');
    }

    public function test_fr02_activity_must_belong_to_selected_program(): void
    {
        $otherProgramActivity = Activity::where('program_id', '!=', Program::first()->id)->first();
        $this->actingAs($this->finance())
            ->post(route('donations.store'), [
                'donor_id' => 1, 'type' => 'cash', 'quantity' => 100, 'program_id' => Program::first()->id,
                'activity_id' => $otherProgramActivity->id, 'received_at' => today()->format('Y-m-d'),
            ])->assertSessionHasErrors('activity_id');
    }

    // ---------------- FR03 ----------------

    private function packDonation(): Donation
    {
        return Donation::where('item_name', 'Family Food Packs')->firstOrFail();
    }

    private function distributionPayload(array $funding, array $overrides = []): array
    {
        $activity = Activity::where('title', 'like', '%Tumana Food Pack%')->firstOrFail();

        return array_merge([
            'activity_id' => $activity->id, 'item_name' => 'Family Food Packs', 'quantity' => 100,
            'unit_id' => Unit::where('name', 'Pack')->value('id'), 'distributed_at' => today()->format('Y-m-d'),
            'beneficiaries' => Beneficiary::where('program_id', $activity->program_id)->limit(3)->pluck('id')->all(),
            'funding' => $funding,
        ], $overrides);
    }

    public function test_fr03_distribution_deducts_from_the_funding_donation(): void
    {
        $donation = $this->packDonation();
        $before = $donation->remaining();

        $this->actingAs($this->coordinator())
            ->post(route('distributions.store'), $this->distributionPayload([['donation_id' => $donation->id, 'amount' => 100]]))
            ->assertRedirect(route('confirmation'));

        $this->assertEquals($before - 100, $donation->fresh()->remaining());
        $distribution = Distribution::latest('id')->first();
        $this->assertCount(3, $distribution->beneficiaries);
        $this->assertTrue($distribution->donations->contains($donation));
    }

    public function test_fr03_insufficient_balance_prompts_for_another_source(): void
    {
        $donation = $this->packDonation();
        $tooMuch = $donation->remaining() + 1;

        $this->actingAs($this->coordinator())
            ->post(route('distributions.store'), $this->distributionPayload([['donation_id' => $donation->id, 'amount' => $tooMuch]], ['quantity' => $tooMuch]))
            ->assertSessionHasErrors(['funding.0.amount']);

        $this->assertStringContainsString('different or additional funding source', session('errors')->first('funding.0.amount'));
        $this->assertSame(5, Distribution::count());
    }

    public function test_fr03_shortfall_can_be_covered_by_an_additional_cash_source(): void
    {
        $donation = $this->packDonation();
        $cash = Donation::where('type', 'cash')->where('program_id', $donation->program_id)->firstOrFail();
        $qty = $donation->remaining() + 50;

        $this->actingAs($this->coordinator())
            ->post(route('distributions.store'), $this->distributionPayload([
                ['donation_id' => $donation->id, 'amount' => $donation->remaining()],
                ['donation_id' => $cash->id, 'amount' => 7500],
            ], ['quantity' => $qty]))
            ->assertRedirect(route('confirmation'));

        $this->assertEquals(0, $donation->fresh()->remaining());
    }

    public function test_fr03_in_kind_unit_must_match_and_program_must_match(): void
    {
        $water = Donation::where('item_name', 'Bottled Water')->firstOrFail(); // relief program, Box unit

        $this->actingAs($this->coordinator())
            ->post(route('distributions.store'), $this->distributionPayload([['donation_id' => $water->id, 'amount' => 10]], ['quantity' => 10]))
            ->assertSessionHasErrors('funding.0.donation_id');
    }

    // ---------------- FR04 ----------------

    public function test_fr04_conflicting_assignment_is_rejected_and_free_slot_confirmed(): void
    {
        $paolo = Volunteer::where('first_name', 'Paolo')->firstOrFail();
        $booked = VolunteerAssignment::where('volunteer_id', $paolo->id)->where('status', 'assigned')->firstOrFail()->activity;

        $clash = Activity::create([
            'program_id' => $booked->program_id, 'title' => 'Overlapping Activity', 'activity_date' => $booked->activity_date,
            'start_time' => '10:00:00', 'end_time' => '14:00:00', 'volunteer_slots' => 5, 'status' => 'scheduled',
        ]);
        $this->actingAs($this->coordinator())
            ->post(route('assignments.store'), ['volunteer_id' => $paolo->id, 'activity_id' => $clash->id])
            ->assertSessionHasErrors('volunteer_id');
        $this->assertStringContainsString('Schedule conflict', session('errors')->first('volunteer_id'));

        $free = Activity::create([
            'program_id' => $booked->program_id, 'title' => 'Afternoon Activity', 'activity_date' => $booked->activity_date,
            'start_time' => '13:00:00', 'end_time' => '15:00:00', 'volunteer_slots' => 5, 'status' => 'scheduled',
        ]);
        $this->post(route('assignments.store'), ['volunteer_id' => $paolo->id, 'activity_id' => $free->id, 'task' => 'Driver'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('volunteer_assignments', ['volunteer_id' => $paolo->id, 'activity_id' => $free->id, 'status' => 'assigned']);
    }

    public function test_fr04_volunteer_can_sign_up_and_withdraw(): void
    {
        $user = $this->volunteer();
        $activity = Activity::create([
            'program_id' => Program::first()->id, 'title' => 'Weekend Packing', 'activity_date' => today()->addDays(20),
            'start_time' => '08:00:00', 'end_time' => '10:00:00', 'volunteer_slots' => 2, 'status' => 'scheduled',
        ]);

        $this->actingAs($user)->post(route('schedule.signup', $activity))->assertSessionHasNoErrors();
        $assignment = VolunteerAssignment::where('activity_id', $activity->id)->firstOrFail();
        $this->delete(route('schedule.withdraw', $assignment))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $assignment->fresh()->status);
    }

    // ---------------- FR06 ----------------

    public function test_fr06_director_generates_and_exports_consolidated_report(): void
    {
        $params = ['from' => today()->subMonths(2)->format('Y-m-d'), 'to' => today()->format('Y-m-d')];

        $this->actingAs($this->director())->get(route('reports.index', $params))
            ->assertOk()->assertSee('Beneficiaries served')->assertSee('Family Food Packs')->assertSee('Noche Buena Food Pack Drive');

        $csv = $this->get(route('reports.export', $params))->assertOk()->streamedContent();
        $this->assertStringContainsString('Beneficiaries served', $csv);
        $this->assertStringContainsString('Typhoon Emergency Relief', $csv);
    }

    public function test_fr06_report_includes_records_on_the_end_date(): void
    {
        $donation = $this->packDonation();
        $this->actingAs($this->coordinator())
            ->post(route('distributions.store'), $this->distributionPayload([['donation_id' => $donation->id, 'amount' => 10]], ['quantity' => 10]));

        $report = app(\App\Services\ReportBuilder::class)->build(today()->startOfDay(), today()->endOfDay());
        $this->assertSame(1, $report['totals']['distributions']);
    }

    public function test_fr06_empty_range_prompts_to_adjust_parameters(): void
    {
        $this->actingAs($this->director())
            ->get(route('reports.index', ['from' => '2001-01-01', 'to' => '2001-12-31']))
            ->assertOk()->assertSee('Try a wider date range');
    }

    // ---------------- FR07 ----------------

    public function test_fr07_offline_entry_replay_is_saved_once(): void
    {
        $uuid = (string) Str::uuid();
        $payload = $this->beneficiaryPayload(['client_uuid' => $uuid, 'first_name' => 'Offline']);

        $this->actingAs($this->coordinator());
        $this->postJson(route('beneficiaries.store'), $payload)->assertOk()->assertJson(['ok' => true]);
        $this->postJson(route('beneficiaries.store'), $payload)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, Beneficiary::where('client_uuid', $uuid)->count());
    }

    public function test_fr07_invalid_offline_entry_returns_validation_errors_as_json(): void
    {
        $this->actingAs($this->coordinator())
            ->postJson(route('beneficiaries.store'), $this->beneficiaryPayload(['client_uuid' => (string) Str::uuid(), 'barangay' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('barangay');
    }

    public function test_fr07_service_worker_and_manifest_are_served(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->actingAs($this->coordinator())->get(route('beneficiaries.create'))->assertSee('data-offline', false);
    }

    // ---------------- FR08 ----------------

    public function test_fr08_role_restrictions_and_denials_are_logged(): void
    {
        $this->actingAs($this->finance())->get(route('beneficiaries.index'))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->finance()->id, 'action' => 'access.denied']);

        $this->actingAs($this->volunteer())->get(route('donations.index'))->assertForbidden();
        $this->actingAs($this->volunteer())->get(route('beneficiaries.index'))->assertForbidden();
        $this->actingAs($this->coordinator())->post(route('donations.store'), [])->assertForbidden();
        $this->actingAs($this->donor())->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->coordinator())->get(route('users.index'))->assertForbidden();
    }

    public function test_fr08_granted_changes_are_logged(): void
    {
        $this->actingAs($this->coordinator())->post(route('beneficiaries.store'), $this->beneficiaryPayload());
        $this->assertTrue(AuditLog::where('action', 'beneficiary.created')->where('user_id', $this->coordinator()->id)->exists());
    }

    public function test_fr08_donor_portal_shows_only_own_donations_without_beneficiary_names(): void
    {
        $response = $this->actingAs($this->donor())->get(route('dashboard'));
        $response->assertRedirect(route('impact'));

        $page = $this->get(route('impact'))->assertOk();
        $page->assertSee('GMA Kapuso Foundation')->assertSee('Family Food Packs');
        $page->assertDontSee('Maria Clara Center');
        $page->assertDontSee(Beneficiary::first()->last_name.', '.Beneficiary::first()->first_name);
    }

    public function test_fr08_deactivated_user_is_signed_out(): void
    {
        $user = $this->coordinator();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // ---------------- FR09 ----------------

    public function test_fr09_coordinator_and_volunteer_exchange_messages(): void
    {
        $coordinator = $this->coordinator();
        $volunteer = $this->volunteer();

        $this->actingAs($coordinator)
            ->post(route('messages.store'), ['recipient_id' => $volunteer->id, 'body' => 'Please bring 4 tables tomorrow.'])
            ->assertRedirect(route('messages.show', $volunteer->id));

        $this->actingAs($volunteer)->get(route('messages.show', $coordinator))->assertOk()->assertSee('Please bring 4 tables tomorrow.');
        $this->assertSame(0, Message::where('recipient_id', $volunteer->id)->whereNull('read_at')->count());
    }

    public function test_fr09_empty_message_is_rejected(): void
    {
        $this->actingAs($this->coordinator())
            ->post(route('messages.store'), ['recipient_id' => $this->volunteer()->id, 'body' => '   '])
            ->assertSessionHasErrors(['body' => 'Message cannot be empty.']);
    }

    public function test_fr09_cannot_message_donor_accounts(): void
    {
        $this->actingAs($this->coordinator())
            ->post(route('messages.store'), ['recipient_id' => $this->donor()->id, 'body' => 'Hello'])
            ->assertSessionHasErrors('recipient_id');
    }
}
