@extends('layouts.app')
@section('title', $distribution->reference_no)

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('distributions.index') }}">Distribution</a> / {{ $distribution->reference_no }}</div>
            <h1>{{ $distribution->formatQuantity() }} {{ $distribution->item_name }}</h1>
            <p>{{ $distribution->activity->title }} · {{ $distribution->activity->program->name }} · {{ $distribution->distributed_at->format('F j, Y') }}</p>
        </div>
    </div>

    <div class="grid-3-1">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>Funding Trail</h2><p>Donation → Activity → Distribution</p></div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Donation</th><th>Donor</th><th>Item</th><th class="num">Drawn for this distribution</th></tr></thead>
                    <tbody>
                    @foreach ($distribution->fundings as $f)
                        <tr>
                            <td class="mono">
                                @can('donations.view')
                                    <a class="row-link" href="{{ route('donations.show', $f->donation) }}">{{ $f->donation->reference_no }}</a>
                                @else
                                    {{ $f->donation->reference_no }}
                                @endcan
                            </td>
                            <td>{{ $f->donation->donor->name }}</td>
                            <td>{{ $f->donation->description() }}</td>
                            <td class="num"><strong>{{ $f->donation->formatAmount((float) $f->amount) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>

            @can('beneficiaries.view')
                <div class="card">
                    <div class="card-head"><h2>Beneficiaries Served</h2><p>{{ $distribution->beneficiaries->count() }} recipient(s)</p></div>
                    <div class="table-wrap"><table>
                        <thead><tr><th>ID</th><th>Name</th><th>Barangay</th><th class="num">Household</th></tr></thead>
                        <tbody>
                        @forelse ($distribution->beneficiaries as $b)
                            <tr>
                                <td class="mono">{{ $b->code }}</td>
                                <td><a class="row-link" href="{{ route('beneficiaries.show', $b) }}">{{ $b->fullName() }}</a></td>
                                <td>{{ $b->barangay }}</td>
                                <td class="num">{{ $b->household_size }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No individual beneficiaries were recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </div>
            @else
                <div class="card card-pad"><strong>{{ $distribution->beneficiaries->count() }}</strong> beneficiaries served. Names are visible to program staff only.</div>
            @endcan
        </div>
        <div class="card card-pad">
            <h2 style="margin-bottom:14px">Entry Details</h2>
            <dl class="dl">
                <dt>Ledger ID</dt><dd class="mono">{{ $distribution->reference_no }}</dd>
                <dt>Activity</dt><dd><a href="{{ route('activities.show', $distribution->activity) }}">{{ $distribution->activity->title }}</a></dd>
                <dt>Location</dt><dd>{{ $distribution->activity->location ?: '—' }}</dd>
                <dt>Quantity</dt><dd>{{ $distribution->formatQuantity() }}</dd>
                <dt>Recorded by</dt><dd>{{ $distribution->recorder?->name ?? '—' }}</dd>
                <dt>Recorded on</dt><dd>{{ $distribution->created_at->format('M j, Y g:i A') }}</dd>
                <dt>Notes</dt><dd>{{ $distribution->notes ?: '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection
