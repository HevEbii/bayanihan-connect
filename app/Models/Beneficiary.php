<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Beneficiary extends Model
{
    use HasReferenceNumber;

    protected static string $referencePrefix = 'BN';

    protected static string $referenceColumn = 'code';

    public const CATEGORIES = [
        'family' => 'Family / Household',
        'learner' => 'Learner',
        'senior' => 'Senior Citizen',
        'pwd' => 'Person with Disability',
        'individual' => 'Individual',
    ];

    protected $fillable = ['client_uuid', 'first_name', 'last_name', 'birth_date', 'sex', 'contact_number', 'barangay',
        'address', 'household_size', 'category', 'program_id', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    protected function formatReference(): string
    {
        return 'BN-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function distributions(): BelongsToMany
    {
        return $this->belongsToMany(Distribution::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
