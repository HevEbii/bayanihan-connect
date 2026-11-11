<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Donation extends Model
{
    use HasReferenceNumber;

    protected static string $referencePrefix = 'DN';

    public const TYPES = ['cash' => 'Cash', 'in_kind' => 'In-kind (goods)'];

    protected $fillable = ['donor_id', 'type', 'item_name', 'quantity', 'unit_id', 'program_id', 'activity_id',
        'received_at', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'quantity' => 'decimal:2'];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function fundings(): HasMany
    {
        return $this->hasMany(DistributionFunding::class);
    }

    public function distributions(): BelongsToMany
    {
        return $this->belongsToMany(Distribution::class, 'distribution_funding')->withPivot('amount')->withTimestamps();
    }

    public function scopeWithUsed(Builder $query): Builder
    {
        return $query->withSum('fundings as used_amount', 'amount');
    }

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    public function usedAmount(): float
    {
        return (float) ($this->used_amount ?? $this->fundings()->sum('amount'));
    }

    public function remaining(): float
    {
        return round((float) $this->quantity - $this->usedAmount(), 2);
    }

    /** Human-readable amount, e.g. "PHP 25,000.00" or "120 Boxes". */
    public function formatAmount(?float $value = null): string
    {
        $value ??= (float) $this->quantity;

        return $this->isCash()
            ? "\u{20B1}".number_format($value, 2)
            : self::trimNumber($value).' '.Str::plural($this->unit?->name ?? '', $value == 1 ? 1 : 2);
    }

    public function description(): string
    {
        return $this->isCash() ? 'Cash donation' : $this->item_name;
    }

    public static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
