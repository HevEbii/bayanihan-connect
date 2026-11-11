<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/** The standardized units of measure for in-kind goods (FR05). */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Piece', 'pc'], ['Pack', 'pk'], ['Box', 'box'], ['Case', 'cs'], ['Kit', 'kit'], ['Sack', 'sk'],
            ['Kilogram', 'kg'], ['Liter', 'L'], ['Bottle', 'btl'], ['Can', 'can'], ['Set', 'set'],
        ] as [$name, $abbr]) {
            Unit::firstOrCreate(['name' => $name], ['abbreviation' => $abbr]);
        }
    }
}
