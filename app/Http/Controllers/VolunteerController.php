<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Volunteer;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VolunteerController extends Controller
{
    public function index(Request $request)
    {
        $volunteers = Volunteer::withCount([
            'assignments as upcoming_count' => fn ($q) => $q->where('status', 'assigned')
                ->whereHas('activity', fn ($a) => $a->whereDate('activity_date', '>=', today())),
            'assignments as completed_count' => fn ($q) => $q->where('status', 'completed'),
        ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('skills', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('last_name')->paginate(15)->withQueryString();

        return view('volunteers.index', compact('volunteers'));
    }

    public function create()
    {
        return view('volunteers.form', ['volunteer' => new Volunteer(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $volunteer = Volunteer::create($this->validated($request));
        Audit::record('volunteer.created', "Registered volunteer {$volunteer->fullName()}", $volunteer);

        return redirect()->route('volunteers.show', $volunteer)->with('status', 'Volunteer registered.');
    }

    public function show(Volunteer $volunteer)
    {
        $volunteer->load(['user', 'assignments' => fn ($q) => $q->with('activity.program')
            ->join('activities', 'activities.id', '=', 'volunteer_assignments.activity_id')
            ->orderByDesc('activities.activity_date')->select('volunteer_assignments.*')]);

        return view('volunteers.show', [
            'volunteer' => $volunteer,
            'openActivities' => Activity::upcoming()->with('program')
                ->whereNotIn('id', $volunteer->assignments->where('status', '!=', 'cancelled')->pluck('activity_id'))->get(),
        ]);
    }

    public function edit(Volunteer $volunteer)
    {
        return view('volunteers.form', compact('volunteer'));
    }

    public function update(Request $request, Volunteer $volunteer)
    {
        $volunteer->update($this->validated($request, $volunteer));
        Audit::record('volunteer.updated', "Updated volunteer {$volunteer->fullName()}", $volunteer);

        return redirect()->route('volunteers.show', $volunteer)->with('status', 'Volunteer details updated.');
    }

    private function validated(Request $request, ?Volunteer $volunteer = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('volunteers', 'email')->ignore($volunteer?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'skills' => ['nullable', 'string', 'max:255'],
            'availability' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
