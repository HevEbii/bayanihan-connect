<?php

namespace App\Services;

use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Program;
use App\Models\VolunteerAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compiles the consolidated, cross-program report for the Director (FR06).
 */
class ReportBuilder
{
    /**
     * @param  array<int>  $programIds  empty = all programs
     */
    public function build(CarbonInterface $from, CarbonInterface $to, array $programIds = []): array
    {
        $programs = Program::query()
            ->when($programIds, fn ($q) => $q->whereIn('id', $programIds))
            ->orderBy('name')->get();
        $ids = $programs->pluck('id')->all();

        $donations = Donation::with(['unit', 'donor'])
            ->whereIn('program_id', $ids)
            ->whereDate('received_at', '>=', $from)->whereDate('received_at', '<=', $to)
            ->get();

        $distributions = Distribution::with(['unit', 'activity'])
            ->withCount('beneficiaries')
            ->whereHas('activity', fn ($q) => $q->whereIn('program_id', $ids))
            ->whereDate('distributed_at', '>=', $from)->whereDate('distributed_at', '<=', $to)
            ->get();

        $assignments = VolunteerAssignment::with('activity')
            ->whereIn('status', ['assigned', 'completed'])
            ->whereHas('activity', fn ($q) => $q->whereIn('program_id', $ids)
                ->whereDate('activity_date', '>=', $from)->whereDate('activity_date', '<=', $to))
            ->get();

        $beneficiariesServed = DB::table('beneficiary_distribution')
            ->whereIn('distribution_id', $distributions->pluck('id'))
            ->distinct()->count('beneficiary_id');

        $completed = $assignments->where('status', 'completed');

        $byProgram = $programs->map(function (Program $program) use ($donations, $distributions, $assignments) {
            $progDistributions = $distributions->filter(fn ($d) => (int) $d->activity->program_id === $program->id);

            return [
                'program' => $program,
                'cash' => $donations->where('program_id', $program->id)->where('type', 'cash')->sum('quantity'),
                'in_kind_count' => $donations->where('program_id', $program->id)->where('type', 'in_kind')->count(),
                'distributions' => $progDistributions->count(),
                'beneficiaries' => DB::table('beneficiary_distribution')
                    ->whereIn('distribution_id', $progDistributions->pluck('id'))->distinct()->count('beneficiary_id'),
                'volunteers' => $assignments->filter(fn ($a) => (int) $a->activity->program_id === $program->id)
                    ->pluck('volunteer_id')->unique()->count(),
            ];
        });

        return [
            'from' => $from,
            'to' => $to,
            'programs' => $programs,
            'has_data' => $donations->isNotEmpty() || $distributions->isNotEmpty() || $assignments->isNotEmpty(),
            'totals' => [
                'cash_received' => (float) $donations->where('type', 'cash')->sum('quantity'),
                'donations' => $donations->count(),
                'donors' => $donations->pluck('donor_id')->unique()->count(),
                'distributions' => $distributions->count(),
                'beneficiaries_served' => $beneficiariesServed,
                'volunteers' => $assignments->pluck('volunteer_id')->unique()->count(),
                'volunteer_hours' => round($completed->sum(fn ($a) => $a->activity->durationHours()), 1),
            ],
            'goods_received' => $this->groupGoods($donations->where('type', 'in_kind')),
            'goods_distributed' => $this->groupGoods($distributions),
            'by_program' => $byProgram,
        ];
    }

    /** Sums quantities per item and standardized unit. */
    private function groupGoods(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($r) => mb_strtolower(trim($r->item_name)).'|'.$r->unit_id)
            ->map(fn (Collection $group) => [
                'item' => $group->first()->item_name,
                'unit' => $group->first()->unit?->name,
                'quantity' => (float) $group->sum('quantity'),
            ])
            ->sortBy('item')->values();
    }
}
