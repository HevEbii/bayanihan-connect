<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a readable, unique reference (e.g. DN-2026-0007) derived from the primary key.
 * Models define `protected static string $referencePrefix` and `$referenceColumn` if not "reference_no".
 */
trait HasReferenceNumber
{
    protected static function bootHasReferenceNumber(): void
    {
        static::creating(function ($model) {
            $column = $model->referenceColumn();
            if (empty($model->{$column})) {
                $model->{$column} = 'TMP-'.Str::upper(Str::random(12));
            }
        });

        static::created(function ($model) {
            $column = $model->referenceColumn();
            if (str_starts_with($model->{$column}, 'TMP-')) {
                $model->{$column} = $model->formatReference();
                $model->saveQuietly();
            }
        });
    }

    public function referenceColumn(): string
    {
        return property_exists($this, 'referenceColumn') ? static::$referenceColumn : 'reference_no';
    }

    protected function formatReference(): string
    {
        $year = $this->created_at?->format('Y') ?? now()->format('Y');

        return static::$referencePrefix.'-'.$year.'-'.str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT);
    }
}
