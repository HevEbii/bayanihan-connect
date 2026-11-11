@extends('layouts.app')
@section('title', 'Donations')

@section('content')
    <div class="page-head">
        <div>
            <h1>Donations</h1>
            <p>Every cash and in-kind donation, linked to the program it supports and how much is left to distribute.</p>
        </div>
        @can('donations.manage')
            <a class="btn" href="{{ route('donations.create') }}"><x-icon name="plus"/> Record Donation</a>
        @endcan
    </div>

    <form class="card filters" method="GET">
        <div class="field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Reference, donor, or item">
        </div>
        <div class="field">
            <label for="program">Program</label>
            <select id="program" name="program">
                <option value="">All programs</option>
                @foreach ($programs as $p)
                    <option value="{{ $p->id }}" @selected(request('program') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">Cash and in-kind</option>
                @foreach (\App\Models\Donation::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline" type="submit"><x-icon name="search"/> Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr><th>Reference</th><th>Date</th><th>Donor</th><th>Donation</th><th>Program</th><th class="num">Amount</th><th class="num">Remaining</th></tr></thead>
            <tbody>
            @forelse ($donations as $d)
                <tr>
                    <td><a class="row-link mono" href="{{ route('donations.show', $d) }}">{{ $d->reference_no }}</a></td>
                    <td>{{ $d->received_at->format('M j, Y') }}</td>
                    <td><strong>{{ $d->donor->name }}</strong></td>
                    <td>{{ $d->description() }} <span @class(['tag', 'tag-blue' => $d->isCash(), 'tag-green' => ! $d->isCash()])>{{ $d->isCash() ? 'Cash' : 'In-kind' }}</span></td>
                    <td>{{ $d->program->name }}</td>
                    <td class="num"><strong>{{ $d->formatAmount() }}</strong></td>
                    <td class="num">
                        @if ($d->remaining() <= 0)
                            <span class="tag">Fully used</span>
                        @else
                            {{ $d->formatAmount($d->remaining()) }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No donations found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $donations->links() }}
    </div>
@endsection
