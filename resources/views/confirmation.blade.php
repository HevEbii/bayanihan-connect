@extends('layouts.app')
@section('title', $confirmation['title'])

@section('content')
    <div class="card confirm">
        <div class="confirm-icon"><x-icon name="check" :size="28"/></div>
        <h1>{{ $confirmation['title'] }}</h1>
        <p>The entry has been saved to the Bayanihan Connect database and is now visible to everyone with access.</p>

        <div class="snapshot">
            <div class="card">
                <h3 style="margin-bottom:12px">Entry Log Snapshot:</h3>
                <dl class="dl">
                    @foreach ($confirmation['snapshot'] as $label => $value)
                        <dt>{{ $label }}:</dt><dd>{{ $value }}</dd>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="confirm-actions">
            <a class="btn btn-outline" href="{{ $confirmation['again_url'] }}">Record Another Entry</a>
            <a class="btn btn-outline" href="{{ $confirmation['view_url'] }}">View Record</a>
            <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
        </div>
    </div>
@endsection
