<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Program;
use App\Models\Unit;
use App\Models\User;
use App\Support\ItemSuggestions;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Choosing an item pre-selects the unit that normally goes with it. */
class ItemUnitSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function unit(string $name): int
    {
        return (int) Unit::where('name', $name)->value('id');
    }

    public function test_units_are_learned_from_how_each_item_was_recorded(): void
    {
        $units = ItemSuggestions::units();

        $this->assertSame($this->unit('Pack'), $units['family food packs']);   // 5 records, all in Packs
        $this->assertSame($this->unit('Kit'), $units['hygiene kits']);
        $this->assertSame($this->unit('Box'), $units['bottled water']);        // history (Box) beats the default (Bottle)
        $this->assertSame($this->unit('Sack'), $units['rice']);               // seeded as 60 Sacks of Rice
    }

    public function test_common_goods_without_history_use_the_standard_default(): void
    {
        $units = ItemSuggestions::units();

        $this->assertSame($this->unit('Pack'), $units['noodles']);
        $this->assertSame($this->unit('Bottle'), $units['cooking oil']);
        $this->assertSame($this->unit('Piece'), $units['blankets']);
    }

    public function test_most_used_unit_wins_and_the_default_breaks_a_tie(): void
    {
        $record = fn (string $item, string $unit) => Donation::create([
            'donor_id' => 1, 'type' => 'in_kind', 'item_name' => $item, 'quantity' => 5, 'unit_id' => $this->unit($unit),
            'program_id' => Program::first()->id, 'received_at' => today(), 'recorded_by' => User::where('role', Role::FINANCE)->value('id'),
        ]);

        // "Sardines": 2 x Can vs 1 x Box -> Can wins on count.
        $record('Sardines', 'Can'); $record('Sardines', 'Can'); $record('Sardines', 'Box');
        // "Canned Goods": 1 x Kilogram vs 1 x Can (the default) -> tie, so the default (Can) wins.
        $record('Canned Goods', 'Kilogram'); $record('Canned Goods', 'Can');

        $units = ItemSuggestions::units();
        $this->assertSame($this->unit('Can'), $units['sardines']);
        $this->assertSame($this->unit('Can'), $units['canned goods']);
    }

    public function test_both_forms_receive_the_item_unit_map(): void
    {
        $key = '"hygiene kits":'.$this->unit('Kit');

        $this->actingAs(User::where('role', Role::FINANCE)->first())->get(route('donations.create'))
            ->assertOk()->assertSee($key, false);
        $this->actingAs(User::where('role', Role::COORDINATOR)->first())->get(route('distributions.create'))
            ->assertOk()->assertSee($key, false);
    }
}
