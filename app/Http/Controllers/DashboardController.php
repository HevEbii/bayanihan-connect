<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Distribution;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Program;
use App\Models\Volunteer;
use App\Support\Role;
use Illuminate\Http\Request;

/** Role-based home screen: each role lands on the information relevant to their work. */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->role === Role::DONOR) {
            return redirect()->route('impact');
        }

        $data = ['user' => $user];

        if ($user->hasRole(Role::ADMIN, Role::DIRECTOR)) {
            $data['stats'] = [
                ['label' => 'Active programs', 'value' => Program::where('status', 'active')->count(),
                    'hint' => Activity::upcoming()->whereDate('activity_date', '<=', today()->addDays(7))->count().' activities this week'],
                ['label' => 'Beneficiaries registered', 'value' => number_format(Beneficiary::count()),
                    'hint' => Beneficiary::where('created_at', '>=', now()->subDays(30))->count().' new in 30 days'],
                ['label' => 'Donors enrolled', 'value' => number_format(Donor::count()),
                    'hint' => Donation::whereDate('received_at', '>=', today()->startOfMonth())->count().' donations this month'],
                ['label' => 'Cash received (this month)', 'value' => "\u{20B1}".number_format(
                    Donation::where('type', 'cash')->whereDate('received_at', '>=', today()->startOfMonth())->sum('quantity'), 2),
                    'hint' => Distribution::whereDate('distributed_at', '>=', today()->startOfMonth())->count().' distributions this month'],
            ];
            $data['programs'] = Program::withCount(['beneficiaries', 'activities'])->where('status', 'active')->orderBy('name')->get();
        }

        if ($user->role === Role::COORDINATOR) {
            $programIds = $user->coordinatedPrograms()->pluck('id');
            $data['stats'] = [
                ['label' => 'My programs', 'value' => $programIds->count(), 'hint' => 'Programs you coordinate'],
                ['label' => 'Beneficiaries', 'value' => number_format(Beneficiary::whereIn('program_id', $programIds)->count()), 'hint' => 'In your programs'],
                ['label' => 'Upcoming activities', 'value' => Activity::upcoming()->whereIn('program_id', $programIds)->count(), 'hint' => 'Scheduled from today'],
                ['label' => 'Active volunteers', 'value' => Volunteer::where('status', 'active')->count(), 'hint' => 'Across the Foundation'],
            ];
            $data['activities'] = Activity::upcoming()->with('program')->withCount(['assignments' => fn ($q) => $q->where('status', '!=', 'cancelled')])
                ->whereIn('program_id', $programIds)->limit(6)->get();
        }

        if ($user->role === Role::FINANCE) {
            $donations = Donation::withUsed()->with(['donor', 'unit', 'program'])->latest('received_at')->get();
            $data['stats'] = [
                ['label' => 'Cash received (this month)', 'value' => "\u{20B1}".number_format(
                    $donations->where('type', 'cash')->filter(fn ($d) => $d->received_at->gte(today()->startOfMonth()))->sum('quantity'), 2),
                    'hint' => 'Cash donations recorded'],
                ['label' => 'In-kind donations', 'value' => $donations->where('type', 'in_kind')->count(), 'hint' => 'All time'],
                ['label' => 'Donations with balance', 'value' => $donations->filter(fn ($d) => $d->remaining() > 0)->count(), 'hint' => 'Not yet fully distributed'],
                ['label' => 'Donors enrolled', 'value' => Donor::count(), 'hint' => 'Individuals and institutions'],
            ];
            $data['openDonations'] = $donations->filter(fn ($d) => $d->remaining() > 0)->take(6);
        }

        if ($user->role === Role::VOLUNTEER) {
            $volunteer = $user->volunteer;
            $data['assignments'] = $volunteer
                ? $volunteer->assignments()->with('activity.program')->where('status', 'assigned')
                    ->whereHas('activity', fn ($q) => $q->whereDate('activity_date', '>=', today()))
                    ->get()->sortBy(fn ($a) => $a->activity->activity_date->format('Y-m-d').$a->activity->start_time)
                : collect();
            $data['stats'] = [
                ['label' => 'Upcoming assignments', 'value' => $data['assignments']->count(), 'hint' => 'Activities you are scheduled for'],
                ['label' => 'Completed activities', 'value' => $volunteer ? $volunteer->assignments()->where('status', 'completed')->count() : 0, 'hint' => 'Thank you!'],
                ['label' => 'Unread messages', 'value' => $user->unreadMessagesCount(), 'hint' => 'From your coordinators'],
            ];
        }

        $data['recent'] = AuditLog::with('user')
            ->whereNotIn('action', ['auth.login', 'auth.logout', 'access.denied'])
            ->when($user->role === Role::VOLUNTEER, fn ($q) => $q->where('user_id', $user->id))
            ->latest('created_at')->limit(6)->get();

        return view('dashboard', $data);
    }
}
