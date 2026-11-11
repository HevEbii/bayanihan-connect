<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Program;
use App\Models\Unit;
use App\Services\DistributionRecorder;
use App\Support\Audit;
use Illuminate\Http\Request;

/** FR03 - distributions logged against the donations that funded them. */
class DistributionController extends Controller
{
    public function index(Request $request)
    {
        $distributions = Distribution::with(['activity.program', 'unit', 'donations.donor'])->withCount('beneficiaries')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('reference_no', 'like', $term)->orWhere('item_name', 'like', $term)
                    ->orWhereHas('activity', fn ($a) => $a->where('title', 'like', $term)));
            })
            ->when($request->filled('program'), fn ($q) => $q->whereHas('activity', fn ($a) => $a->where('program_id', $request->integer('program'))))
            ->when($request->filled('range'), fn ($q) => $q->whereDate('distributed_at', '>=', today()->subDays($request->integer('range'))))
            ->latest('distributed_at')->latest('id')
            ->paginate(15)->withQueryString();

        return view('distributions.index', [
            'distributions' => $distributions,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $donations = Donation::withUsed()->with(['donor', 'unit'])->latest('received_at')->get()
            ->filter(fn (Donation $d) => $d->remaining() > 0)->values();

        return view('distributions.create', [
            'activities' => Activity::with('program')->where('status', '!=', 'cancelled')
                ->whereHas('program', fn ($q) => $q->where('status', 'active'))
                ->orderByDesc('activity_date')->get(),
            'units' => Unit::orderBy('name')->get(),
            'donations' => $donations,
            'beneficiaries' => Beneficiary::orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'code', 'first_name', 'last_name', 'barangay', 'program_id']),
            'selectedActivity' => $request->integer('activity') ?: null,
        ]);
    }

    public function store(Request $request, DistributionRecorder $recorder)
    {
        $data = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'activity_id' => ['required', 'exists:activities,id'],
            'item_name' => ['required', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit_id' => ['required', 'exists:units,id'],
            'distributed_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'beneficiaries' => ['nullable', 'array'],
            'beneficiaries.*' => ['integer', 'exists:beneficiaries,id'],
            'funding' => ['required', 'array', 'min:1'],
            'funding.*.donation_id' => ['nullable', 'integer'],
            'funding.*.amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'activity_id.required' => 'Please select the activity where goods were distributed.',
            'unit_id.required' => 'Please select a unit.',
            'funding.required' => 'Select at least one funding donation.',
        ]);

        if (! empty($data['client_uuid']) && $existing = Distribution::where('client_uuid', $data['client_uuid'])->first()) {
            return $this->confirm($request, $existing, 'Distribution already logged');
        }

        $distribution = $recorder->record($data, $request->user());
        Audit::record('distribution.created', "Logged distribution {$distribution->reference_no}: {$distribution->formatQuantity()} {$distribution->item_name}", $distribution);

        return $this->confirm($request, $distribution, 'Record Saved Successfully');
    }

    public function show(Distribution $distribution)
    {
        $distribution->load(['activity.program', 'unit', 'recorder', 'fundings.donation' => fn ($q) => $q->with(['donor', 'unit']), 'beneficiaries']);

        return view('distributions.show', compact('distribution'));
    }

    private function confirm(Request $request, Distribution $distribution, string $title)
    {
        $distribution->load(['activity.program', 'unit', 'donations.donor']);

        return $this->saved($request, $title, $distribution->reference_no, [
            'Funded by' => $distribution->donations->map(fn ($d) => $d->donor->name)->unique()->join(', '),
            'Relief Program' => $distribution->activity->program->name,
            'Activity' => $distribution->activity->title,
            'Distribution Item' => $distribution->item_name,
            'Committed Amount' => $distribution->formatQuantity(),
            'Beneficiaries Served' => (string) $distribution->beneficiaries()->count(),
            'Ledger ID Assigned' => $distribution->reference_no,
        ], route('distributions.create'), route('distributions.show', $distribution));
    }
}
