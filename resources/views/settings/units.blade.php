@extends('layouts.app')
@section('title', 'Units of Measure')

@section('content')
    <div class="page-head">
        <div>
            <h1>Settings · Units of Measure</h1>
            <p>The only units staff can choose when recording in-kind goods. Keeping one list prevents box-versus-item mismatches.</p>
        </div>
    </div>

    <div class="grid-3-1">
        <div class="card">
            <div class="table-wrap"><table>
                <thead><tr><th>Unit</th><th>Abbreviation</th><th></th></tr></thead>
                <tbody>
                @foreach ($units as $unit)
                    <tr>
                        <td><strong>{{ $unit->name }}</strong></td>
                        <td class="mono">{{ $unit->abbreviation }}</td>
                        <td class="num">
                            <form method="POST" action="{{ route('units.destroy', $unit) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Remove the unit {{ $unit->name }}?">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <form class="card card-pad" method="POST" action="{{ route('units.store') }}" novalidate>
            @csrf
            <h2 style="margin-bottom:14px">Add Unit</h2>
            <x-field name="name" label="Unit name" required style="margin-bottom:14px">
                <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="50" placeholder="e.g. Sacks" @class(['is-invalid' => $errors->has('name')])>
            </x-field>
            <x-field name="abbreviation" label="Abbreviation" required style="margin-bottom:16px">
                <input id="abbreviation" type="text" name="abbreviation" value="{{ old('abbreviation') }}" maxlength="15" placeholder="e.g. sk" @class(['is-invalid' => $errors->has('abbreviation')])>
            </x-field>
            <button class="btn btn-block" type="submit">Add Unit</button>
        </form>
    </div>
@endsection
