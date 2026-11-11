<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Program;
use App\Models\Volunteer;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function create(Request $request)
    {
        return view('activities.form', [
            'activity' => new Activity(['program_id' => $request->integer('program') ?: null, 'status' => 'scheduled',
                'start_time' => '08:00', 'end_time' => '12:00', 'volunteer_slots' => 5]),
            'programs' => Program::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $activity = Activity::create($this->validated($request));
        Audit::record('activity.created', "Scheduled activity {$activity->title}", $activity);

        return redirect()->route('activities.show', $activity)->with('status', 'Activity scheduled. You can now assign volunteers.');
    }

    public function show(Activity $activity)
    {
        $activity->load(['program', 'assignments' => fn ($q) => $q->with('volunteer')->orderBy('status'),
            'distributions' => fn ($q) => $q->with('unit')->withCount('beneficiaries')]);

        $assignedIds = $activity->assignments->where('status', '!=', 'cancelled')->pluck('volunteer_id');

        return view('activities.show', [
            'activity' => $activity,
            'availableVolunteers' => Volunteer::where('status', 'active')->whereNotIn('id', $assignedIds)
                ->orderBy('last_name')->get(),
        ]);
    }

    public function edit(Activity $activity)
    {
        return view('activities.form', [
            'activity' => $activity,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        $activity->update($this->validated($request));
        Audit::record('activity.updated', "Updated activity {$activity->title}", $activity);

        return redirect()->route('activities.show', $activity)->with('status', 'Activity updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'title' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:200'],
            'activity_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'volunteer_slots' => ['required', 'integer', 'min:0', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Activity::STATUSES)],
        ], ['end_time.after' => 'The end time must be later than the start time.']);

        // Stored as HH:MM:SS so time-slot comparisons are consistent across databases.
        $data['start_time'] .= ':00';
        $data['end_time'] .= ':00';

        return $data;
    }
}
