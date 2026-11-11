<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerAssignment extends Model
{
    public const STATUSES = ['assigned', 'completed', 'no_show', 'cancelled'];

    protected $fillable = ['volunteer_id', 'activity_id', 'task', 'status', 'assigned_by'];

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
