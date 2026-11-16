<?php

namespace App\Support;

use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Goods names offered in the "Relief item" dropdowns of the donation and distribution forms: common relief goods
 * plus everything already recorded, so the same goods keep the same spelling everywhere.
 */
class ItemSuggestions
{
    public const COMMON = ['Rice', 'Canned Goods', 'Bottled Water', 'Noodles', 'Cooking Oil', 'Family Food Packs', 'Hygiene Kits', 'School Supply Kits', 'Blankets'];

    /** The unit normally used for each common good, by unit name (used when there is no history for it yet). */
    public const DEFAULT_UNITS = [
        'Rice' => 'Sack', 'Canned Goods' => 'Can', 'Bottled Water' => 'Bottle', 'Noodles' => 'Pack', 'Cooking Oil' => 'Bottle',
        'Family Food Packs' => 'Pack', 'Hygiene Kits' => 'Kit', 'School Supply Kits' => 'Kit', 'Blankets' => 'Piece',
    ];

    /**
     * The unit that goes with each item, keyed by the lower-case item name => unit id. Learned from how the item was
     * recorded before (most-used unit wins, the standard default breaks a tie), with defaults for the common goods.
     *
     * @return array<string, int>
     */
    public static function units(): array
    {
        $unitIds = Unit::pluck('id', 'name');

        $defaults = [];
        foreach (self::DEFAULT_UNITS as $item => $unitName) {
            if ($unitIds->has($unitName)) {
                $defaults[mb_strtolower($item)] = (int) $unitIds[$unitName];
            }
        }

        $learned = Donation::whereNotNull('item_name')->whereNotNull('unit_id')->get(['item_name', 'unit_id'])
            ->concat(Distribution::whereNotNull('unit_id')->get(['item_name', 'unit_id']))
            ->groupBy(fn ($row) => mb_strtolower(trim($row->item_name)))
            ->map(function (Collection $rows, string $key) use ($defaults) {
                return $rows->countBy('unit_id')
                    ->map(fn ($count, $unitId) => ['unit' => (int) $unitId, 'count' => $count, 'default' => ($defaults[$key] ?? null) === (int) $unitId])
                    ->sortBy([['count', 'desc'], ['default', 'desc'], ['unit', 'asc']])
                    ->first()['unit'];
            });

        return array_replace($defaults, $learned->all());
    }

    /** @return Collection<int, string> */
    public static function all(): Collection
    {
        // Common names come first, so when two spellings differ only by case the standard one is kept.
        return collect(self::COMMON)
            ->merge(Donation::whereNotNull('item_name')->distinct()->pluck('item_name'))
            ->merge(Distribution::distinct()->pluck('item_name'))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
