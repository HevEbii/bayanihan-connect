@extends('layouts.app')
@section('title', $beneficiary->fullName())

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('beneficiaries.index') }}">Beneficiaries</a> / {{ $beneficiary->code }}</div>
            <h1>{{ $beneficiary->fullName() }}</h1>
            <p>{{ $beneficiary->categoryLabel() }} · {{ $beneficiary->barangay }}</p>
        </div>
        @can('beneficiaries.manage')
            <div class="actions"><a class="btn btn-outline" href="{{ route('beneficiaries.edit', $beneficiary) }}">Update Record</a></div>
        @endcan
    </div>

    <div class="grid-3-1">
        <div class="card">
            <div class="card-head"><h2>Assistance Received</h2><p>{{ $beneficiary->distributions->count() }} distribution(s)</p></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Ledger ID</th><th>Activity</th><th>Item</th><th class="num">Batch quantity</th></tr></thead>
                <tbody>
                @forelse ($beneficiary->distributions as $d)
                    <tr>
                        <td>{{ $d->distributed_at->format('M j, Y') }}</td>
                        <td><a class="row-link mono" href="{{ route('distributions.show', $d) }}">{{ $d->reference_no }}</a></td>
                        <td>{{ $d->activity->title }}<br><small style="color:var(--muted)">{{ $d->activity->program->name }}</small></td>
                        <td>{{ $d->item_name }}</td>
                        <td class="num">{{ $d->formatQuantity() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No distributions recorded for this beneficiary yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card card-pad">
            <h2 style="margin-bottom:14px">Profile</h2>
            <dl class="dl">
                <dt>Beneficiary ID</dt><dd class="mono">{{ $beneficiary->code }}</dd>
                <dt>Program</dt><dd>{{ $beneficiary->program?->name ?? 'Not yet enrolled' }}</dd>
                <dt>Household</dt><dd>{{ $beneficiary->household_size }} member(s)</dd>
                <dt>Address</dt><dd>{{ collect([$beneficiary->address, $beneficiary->barangay])->filter()->join(', ') }}</dd>
                <dt>Birth date</dt><dd>{{ $beneficiary->birth_date?->format('M j, Y') ?? '—' }}</dd>
                <dt>Sex</dt><dd>{{ $beneficiary->sex ? ucfirst($beneficiary->sex) : '—' }}</dd>
                <dt>Contact</dt><dd>{{ $beneficiary->contact_number ?: '—' }}</dd>
                <dt>Notes</dt><dd>{{ $beneficiary->notes ?: '—' }}</dd>
                <dt>Last updated</dt><dd>{{ $beneficiary->updated_at->format('M j, Y g:i A') }}<br><small style="color:var(--muted)">by {{ $beneficiary->updater?->name ?? '—' }}</small></dd>
            </dl>
        </div>
    </div>
@endsection
