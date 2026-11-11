<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Volunteer extends Model
{
    protected $fillable = ['user_id', 'first_name', 'last_name', 'email', 'phone', 'skills', 'availability', 'status'];

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }
}
