<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\VolunteerAssignment;
use App\Services\VolunteerScheduler;
use App\Support\Audit;
use Illuminate\Http\Request;

/** A volunteer's own schedule and self sign-up for open activities. */
class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $volunteer = $request->user()->volunteer;
        abort_unless($volunteer, 403, 'Your account is not linked to a volunteer profile yet. Please contact your coordinator.');

        $assignments = $volunteer->assignments()->with('activity.program')->where('status', '!=', 'cancelled')->get()
            ->sortBy(fn ($a) => $a->activity->activity_date->format('Y-m-d').$a->activity->start_time);

        $taken = $assignments->pluck('activity_id');
        $open = Activity::upcoming()->with('program')->withCount(['assignments' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->whereNotIn('id', $taken)->get()
            ->filter(fn ($a) => (int) $a->volunteer_slots === 0 || $a->assignments_count < $a->volunteer_slots);

        return view('schedule.index', [
            'upcoming' => $assignments->filter(fn ($a) => $a->status === 'assigned' && $a->activity->activity_date->gte(today())),
            'past' => $assignments->reject(fn ($a) => $a->status === 'assigned' && $a->activity->activity_date->gte(today())),
            'open' => $open,
        ]);
    }

    public function signUp(Request $request, Activity $activity, VolunteerScheduler $scheduler)
    {
        $volunteer = $request->user()->volunteer;
        abort_unless($volunteer, 403);

        $scheduler->assign($volunteer, $activity, null, $request->user());
        Audit::record('assignment.signup', "{$volunteer->fullName()} signed up for {$activity->title}", $activity);

        return back()->with('status', "You're signed up for {$activity->label()}.");
    }

    public function withdraw(Request $request, VolunteerAssignment $assignment)
    {
        abort_unless((int) $assignment->volunteer_id === $request->user()->volunteer?->id, 403);
        abort_if($assignment->activity->activity_date->lt(today()), 422, 'Past activities cannot be changed.');

        $assignment->update(['status' => 'cancelled']);
        Audit::record('assignment.withdrawn', "{$assignment->volunteer->fullName()} withdrew from {$assignment->activity->title}", $assignment->activity);

        return back()->with('status', 'You have withdrawn from this activity. Your coordinator has been notified in the activity log.');
    }
}
