<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Program extends Model
{
    public const CATEGORIES = [
        'food' => 'Food Distribution',
        'education' => 'Educational Assistance',
        'workshop' => 'Community Workshop',
        'relief' => 'Emergency Relief',
        'community' => 'Community Project',
    ];

    public const STATUSES = ['active', 'completed', 'on_hold'];

    protected $fillable = ['name', 'category', 'description', 'coordinator_id', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function distributions(): HasManyThrough
    {
        return $this->hasManyThrough(Distribution::class, Activity::class);
    }
}
