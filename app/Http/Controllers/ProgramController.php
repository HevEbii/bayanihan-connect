<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\User;
use App\Support\Audit;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::with('coordinator')
            ->withCount(['beneficiaries', 'activities', 'donations'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('name')->get();

        return view('programs.index', compact('programs'));
    }

    public function create()
    {
        return view('programs.form', ['program' => new Program(['status' => 'active']), 'coordinators' => $this->coordinators()]);
    }

    public function store(Request $request)
    {
        $program = Program::create($this->validated($request));
        Audit::record('program.created', "Created program {$program->name}", $program);

        return redirect()->route('programs.show', $program)->with('status', 'Program created. Add its first activity below.');
    }

    public function show(Program $program)
    {
        $program->load(['coordinator', 'activities' => fn ($q) => $q->withCount([
            'assignments' => fn ($a) => $a->where('status', '!=', 'cancelled'), 'distributions',
        ])->orderByDesc('activity_date')]);
        $program->loadCount(['beneficiaries', 'donations']);

        return view('programs.show', compact('program'));
    }

    public function edit(Program $program)
    {
        return view('programs.form', ['program' => $program, 'coordinators' => $this->coordinators()]);
    }

    public function update(Request $request, Program $program)
    {
        $program->update($this->validated($request));
        Audit::record('program.updated', "Updated program {$program->name}", $program);

        return redirect()->route('programs.show', $program)->with('status', 'Program updated.');
    }

    private function coordinators()
    {
        return User::where('role', Role::COORDINATOR)->where('is_active', true)->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Program::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'coordinator_id' => ['nullable', Rule::exists('users', 'id')->where('role', Role::COORDINATOR)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(Program::STATUSES)],
        ]);
    }
}
