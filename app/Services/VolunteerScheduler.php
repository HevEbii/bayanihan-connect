<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerAssignment;
use Illuminate\Validation\ValidationException;

/**
 * Assigns volunteers to activities after checking for scheduling conflicts (FR04).
 */
class VolunteerScheduler
{
    /** Active assignments of this volunteer whose activity overlaps the given activity's time slot. */
    public function conflictsFor(Volunteer $volunteer, Activity $activity)
    {
        return VolunteerAssignment::with('activity')
            ->where('volunteer_id', $volunteer->id)
            ->where('activity_id', '!=', $activity->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('activity', fn ($q) => $q
                ->where('status', '!=', 'cancelled')
                ->whereDate('activity_date', $activity->activity_date)
                ->where('start_time', '<', $activity->end_time)
                ->where('end_time', '>', $activity->start_time))
            ->get();
    }

    public function assign(Volunteer $volunteer, Activity $activity, ?string $task, ?User $by): VolunteerAssignment
    {
        if ($activity->status !== 'scheduled') {
            throw ValidationException::withMessages(['activity_id' => 'Volunteers can only be assigned to scheduled activities.']);
        }
        if ($volunteer->status !== 'active') {
            throw ValidationException::withMessages(['volunteer_id' => "{$volunteer->fullName()} is not an active volunteer."]);
        }

        $existing = VolunteerAssignment::where('volunteer_id', $volunteer->id)->where('activity_id', $activity->id)->first();
        if ($existing && $existing->status !== 'cancelled') {
            throw ValidationException::withMessages(['volunteer_id' => "{$volunteer->fullName()} is already assigned to this activity."]);
        }

        $conflict = $this->conflictsFor($volunteer, $activity)->first();
        if ($conflict) {
            $other = $conflict->activity;
            throw ValidationException::withMessages(['volunteer_id' => "Schedule conflict: {$volunteer->fullName()} is already assigned to "
                ."\"{$other->title}\" on {$other->activity_date->format('M j, Y')} ({$other->timeRange()}). Choose an alternate slot or volunteer."]);
        }

        if ($activity->volunteer_slots > 0 && $activity->activeAssignmentsCount() >= $activity->volunteer_slots) {
            throw ValidationException::withMessages(['activity_id' => 'All volunteer slots for this activity are filled.']);
        }

        if ($existing) {
            $existing->update(['status' => 'assigned', 'task' => $task, 'assigned_by' => $by?->id]);

            return $existing;
        }

        return VolunteerAssignment::create([
            'volunteer_id' => $volunteer->id,
            'activity_id' => $activity->id,
            'task' => $task,
            'status' => 'assigned',
            'assigned_by' => $by?->id,
        ]);
    }
}
