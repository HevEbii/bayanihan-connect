<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Distribution extends Model
{
    use HasReferenceNumber;

    protected static string $referencePrefix = 'RC';

    protected $fillable = ['client_uuid', 'activity_id', 'item_name', 'quantity', 'unit_id', 'distributed_at', 'notes',
        'recorded_by'];

    protected function casts(): array
    {
        return ['distributed_at' => 'date', 'quantity' => 'decimal:2'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function fundings(): HasMany
    {
        return $this->hasMany(DistributionFunding::class);
    }

    public function donations(): BelongsToMany
    {
        return $this->belongsToMany(Donation::class, 'distribution_funding')->withPivot('amount')->withTimestamps();
    }

    public function beneficiaries(): BelongsToMany
    {
        return $this->belongsToMany(Beneficiary::class);
    }

    public function formatQuantity(): string
    {
        return Donation::trimNumber((float) $this->quantity).' '.Str::plural($this->unit?->name ?? '', (float) $this->quantity == 1 ? 1 : 2);
    }
}
