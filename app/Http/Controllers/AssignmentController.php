<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Volunteer;
use App\Models\VolunteerAssignment;
use App\Services\VolunteerScheduler;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** FR04 - coordinators assign volunteers to activity time slots. */
class AssignmentController extends Controller
{
    public function store(Request $request, VolunteerScheduler $scheduler)
    {
        $data = $request->validate([
            'volunteer_id' => ['required', 'exists:volunteers,id'],
            'activity_id' => ['required', 'exists:activities,id'],
            'task' => ['nullable', 'string', 'max:150'],
        ], ['volunteer_id.required' => 'Please select a volunteer.', 'activity_id.required' => 'Please select an activity.']);

        $volunteer = Volunteer::findOrFail($data['volunteer_id']);
        $activity = Activity::findOrFail($data['activity_id']);
        $scheduler->assign($volunteer, $activity, $data['task'] ?? null, $request->user());

        Audit::record('assignment.created', "Assigned {$volunteer->fullName()} to {$activity->title}", $activity);

        return back()->with('status', "{$volunteer->fullName()} is confirmed for {$activity->label()}.");
    }

    public function update(Request $request, VolunteerAssignment $assignment)
    {
        $data = $request->validate(['status' => ['required', Rule::in(VolunteerAssignment::STATUSES)]]);
        $assignment->update($data);
        $assignment->load('volunteer', 'activity');

        Audit::record('assignment.updated', "Marked {$assignment->volunteer->fullName()} as {$data['status']} for {$assignment->activity->title}", $assignment->activity);

        return back()->with('status', 'Assignment updated.');
    }
}
