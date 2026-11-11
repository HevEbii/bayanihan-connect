<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Program;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** FR01 - shared beneficiary registry used by every program. */
class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $beneficiaries = Beneficiary::with('program')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('code', 'like', $term)->orWhere('barangay', 'like', $term));
            })
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(15)->withQueryString();

        return view('beneficiaries.index', [
            'beneficiaries' => $beneficiaries,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('beneficiaries.form', [
            'beneficiary' => new Beneficiary(['household_size' => 1, 'category' => 'family']),
            'programs' => Program::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Replayed offline entry that already reached the server: answer with the original record.
        if (! empty($data['client_uuid']) && $existing = Beneficiary::where('client_uuid', $data['client_uuid'])->first()) {
            return $this->confirm($request, $existing, 'Beneficiary already registered');
        }

        $this->guardDuplicate($data);

        $beneficiary = Beneficiary::create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        Audit::record('beneficiary.created', "Registered beneficiary {$beneficiary->code} ({$beneficiary->fullName()})", $beneficiary);

        return $this->confirm($request, $beneficiary, 'Beneficiary Registered Successfully');
    }

    public function show(Beneficiary $beneficiary)
    {
        $beneficiary->load(['program', 'updater', 'distributions' => fn ($q) => $q->with(['activity.program', 'unit'])->latest('distributed_at')]);

        return view('beneficiaries.show', compact('beneficiary'));
    }

    public function edit(Beneficiary $beneficiary)
    {
        return view('beneficiaries.form', [
            'beneficiary' => $beneficiary,
            'programs' => Program::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Beneficiary $beneficiary)
    {
        $data = $this->validated($request);
        unset($data['client_uuid']);
        $this->guardDuplicate($data, $beneficiary->id);

        $beneficiary->update([...$data, 'updated_by' => $request->user()->id]);
        Audit::record('beneficiary.updated', "Updated beneficiary {$beneficiary->code} ({$beneficiary->fullName()})", $beneficiary);

        return redirect()->route('beneficiaries.show', $beneficiary)->with('status', 'Beneficiary record updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['female', 'male'])],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'barangay' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'household_size' => ['required', 'integer', 'min:1', 'max:50'],
            'category' => ['required', Rule::in(array_keys(Beneficiary::CATEGORIES))],
            'program_id' => ['nullable', 'exists:programs,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'first_name.required' => 'Please enter the beneficiary\'s first name.',
            'last_name.required' => 'Please enter the beneficiary\'s last name.',
            'barangay.required' => 'Please enter the barangay.',
        ]);
    }

    /** Prevents the same person being registered twice by different coordinators. */
    private function guardDuplicate(array $data, ?int $ignoreId = null): void
    {
        $duplicate = Beneficiary::whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($data['last_name']))])
            ->whereRaw('LOWER(barangay) = ?', [mb_strtolower(trim($data['barangay']))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'first_name' => "This beneficiary is already registered as {$duplicate->code}. Update the existing record instead.",
            ]);
        }
    }

    private function confirm(Request $request, Beneficiary $beneficiary, string $title)
    {
        return $this->saved($request, $title, $beneficiary->code, [
            'Beneficiary' => $beneficiary->fullName(),
            'Barangay' => $beneficiary->barangay,
            'Category' => $beneficiary->categoryLabel(),
            'Program' => $beneficiary->program?->name ?? 'Not yet enrolled',
            'Beneficiary ID Assigned' => $beneficiary->code,
        ], route('beneficiaries.create'), route('beneficiaries.show', $beneficiary));
    }
}
