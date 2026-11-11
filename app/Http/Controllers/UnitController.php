<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Unit;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Settings: the standardized units of measure used for in-kind goods (FR05). */
class UnitController extends Controller
{
    public function index()
    {
        return view('settings.units', ['units' => Unit::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:units,name'],
            'abbreviation' => ['required', 'string', 'max:15', 'unique:units,abbreviation'],
        ]);
        $unit = Unit::create($data);
        Audit::record('unit.created', "Added unit of measure {$unit->name}", $unit);

        return back()->with('status', "Unit \"{$unit->name}\" added.");
    }

    public function destroy(Unit $unit)
    {
        if (Donation::where('unit_id', $unit->id)->exists() || Distribution::where('unit_id', $unit->id)->exists()) {
            return back()->withErrors(['unit' => "\"{$unit->name}\" is already used by recorded donations or distributions and cannot be removed."]);
        }
        $unit->delete();
        Audit::record('unit.deleted', "Removed unit of measure {$unit->name}");

        return back()->with('status', "Unit \"{$unit->name}\" removed.");
    }
}
