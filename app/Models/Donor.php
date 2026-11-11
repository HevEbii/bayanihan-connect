<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    public const TYPES = ['individual' => 'Individual', 'institution' => 'Institution / Partner'];

    protected $fillable = ['name', 'type', 'contact_person', 'email', 'phone', 'address', 'user_id'];

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
