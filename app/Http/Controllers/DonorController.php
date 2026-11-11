<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DonorController extends Controller
{
    public function index(Request $request)
    {
        $donors = Donor::withCount('donations')
            ->withSum(['donations as cash_total' => fn ($q) => $q->where('type', 'cash')], 'quantity')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->trim().'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('donors.index', compact('donors'));
    }

    public function create()
    {
        return view('donors.form', ['donor' => new Donor(['type' => 'individual'])]);
    }

    public function store(Request $request)
    {
        $donor = Donor::create($this->validated($request));
        Audit::record('donor.created', "Registered donor {$donor->name}", $donor);

        if ($request->filled('return_to_donation')) {
            return redirect()->route('donations.create', ['donor' => $donor->id])->with('status', "Donor {$donor->name} registered.");
        }

        return redirect()->route('donors.show', $donor)->with('status', 'Donor registered.');
    }

    public function show(Donor $donor)
    {
        $donor->load(['donations' => fn ($q) => $q->withUsed()->with(['unit', 'program', 'distributions.activity'])->latest('received_at')]);

        return view('donors.show', compact('donor'));
    }

    public function edit(Donor $donor)
    {
        return view('donors.form', compact('donor'));
    }

    public function update(Request $request, Donor $donor)
    {
        $donor->update($this->validated($request, $donor));
        Audit::record('donor.updated', "Updated donor {$donor->name}", $donor);

        return redirect()->route('donors.show', $donor)->with('status', 'Donor details updated.');
    }

    private function validated(Request $request, ?Donor $donor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('donors', 'name')->ignore($donor?->id)],
            'type' => ['required', Rule::in(array_keys(Donor::TYPES))],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'A donor with this name is already registered.']);
    }
}
