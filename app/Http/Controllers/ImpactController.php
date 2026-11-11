<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * View-only donor portal: shows where each of the donor's contributions went.
 * Beneficiary identities are never shown here, only counts.
 */
class ImpactController extends Controller
{
    public function __invoke(Request $request)
    {
        $donor = $request->user()->donor;
        abort_unless($donor, 403, 'Your account is not linked to a donor record yet. Please contact the Foundation.');

        $donations = $donor->donations()->withUsed()
            ->with(['unit', 'program', 'activity', 'fundings.distribution' => fn ($q) => $q->with(['activity', 'unit'])->withCount('beneficiaries')])
            ->latest('received_at')->get();

        $distributions = $donations->flatMap->fundings->pluck('distribution')->unique('id');

        return view('impact', [
            'donor' => $donor,
            'donations' => $donations,
            'totals' => [
                'cash' => $donations->where('type', 'cash')->sum('quantity'),
                'in_kind' => $donations->where('type', 'in_kind')->count(),
                'programs' => $donations->pluck('program_id')->unique()->count(),
                'beneficiaries' => $distributions->sum('beneficiaries_count'),
            ],
        ]);
    }
}
