<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Program;
use App\Models\Unit;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** FR02 / FR05 - donations linked to a program or activity, in-kind goods in standardized units. */
class DonationController extends Controller
{
    public function index(Request $request)
    {
        $donations = Donation::withUsed()->with(['donor', 'unit', 'program'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('reference_no', 'like', $term)->orWhere('item_name', 'like', $term)
                    ->orWhereHas('donor', fn ($d) => $d->where('name', 'like', $term)));
            })
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest('received_at')->latest('id')
            ->paginate(15)->withQueryString();

        return view('donations.index', [
            'donations' => $donations,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('donations.create', [
            'donors' => Donor::orderBy('name')->get(),
            'programs' => Program::where('status', 'active')->orderBy('name')->get(),
            'activities' => Activity::with('program')->where('status', '!=', 'cancelled')->orderByDesc('activity_date')->get(),
            'units' => Unit::orderBy('name')->get(),
            'selectedDonor' => $request->integer('donor') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'donor_id' => ['required', 'exists:donors,id'],
            'type' => ['required', Rule::in(array_keys(Donation::TYPES))],
            'item_name' => ['required_if:type,in_kind', 'nullable', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit_id' => ['required_if:type,in_kind', 'nullable', 'exists:units,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'activity_id' => ['nullable', 'exists:activities,id'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'donor_id.required' => 'Please select the donor.',
            'program_id.required' => 'Please select the project the donation is for.',
            'item_name.required_if' => 'Please describe the goods donated.',
            'unit_id.required_if' => 'Please select a unit.',
            'quantity.gt' => 'The amount or quantity must be greater than zero.',
        ]);

        $validator->after(function (Validator $v) use ($request) {
            if ($request->filled('activity_id')) {
                $activity = Activity::find($request->integer('activity_id'));
                if ($activity && (int) $activity->program_id !== $request->integer('program_id')) {
                    $v->errors()->add('activity_id', 'The selected activity belongs to a different program.');
                }
            }
        });

        $data = $validator->validate();
        if ($data['type'] === 'cash') {
            $data['item_name'] = null;
            $data['unit_id'] = null;
        }

        $donation = Donation::create([...$data, 'recorded_by' => $request->user()->id])->load(['donor', 'program', 'unit', 'activity']);
        Audit::record('donation.created', "Recorded donation {$donation->reference_no} from {$donation->donor->name}: {$donation->formatAmount()}", $donation);

        return $this->saved($request, 'Donation Recorded Successfully', $donation->reference_no, [
            'Donor' => $donation->donor->name,
            'Program' => $donation->program->name,
            'Activity' => $donation->activity?->title ?? 'Program-wide',
            'Donation' => $donation->description(),
            'Committed Amount' => $donation->formatAmount(),
            'Reference No. Assigned' => $donation->reference_no,
        ], route('donations.create'), route('donations.show', $donation));
    }

    public function show(Donation $donation)
    {
        $donation->load(['donor', 'unit', 'program', 'activity', 'recorder',
            'fundings.distribution' => fn ($q) => $q->with(['activity', 'unit'])->withCount('beneficiaries')]);

        return view('donations.show', compact('donation'));
    }
}
