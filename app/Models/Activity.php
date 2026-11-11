<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    public const STATUSES = ['scheduled', 'completed', 'cancelled'];

    protected $fillable = ['program_id', 'title', 'location', 'activity_date', 'start_time', 'end_time',
        'volunteer_slots', 'description', 'status'];

    protected function casts(): array
    {
        return ['activity_date' => 'date'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }

    public function volunteers(): BelongsToMany
    {
        return $this->belongsToMany(Volunteer::class, 'volunteer_assignments')->withPivot('task', 'status');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('activity_date', '>=', today())->where('status', 'scheduled')
            ->orderBy('activity_date')->orderBy('start_time');
    }

    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }

    public function durationHours(): float
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->start_time));
        [$eh, $em] = array_map('intval', explode(':', $this->end_time));

        return max(0, (($eh * 60 + $em) - ($sh * 60 + $sm)) / 60);
    }

    public function activeAssignmentsCount(): int
    {
        return $this->assignments()->where('status', '!=', 'cancelled')->count();
    }

    public function label(): string
    {
        return $this->title.' ('.$this->activity_date->format('M j, Y').')';
    }
}
