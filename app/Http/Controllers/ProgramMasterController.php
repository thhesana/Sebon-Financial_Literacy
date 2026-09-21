<?php

namespace App\Http\Controllers;

use App\Models\CapitalMarketProgram;
use App\Models\FiscalYearMaster;
use App\Support\SecureId;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramMasterController extends Controller
{
    public function index()
    {
        $programs = CapitalMarketProgram::orderByDesc('CapitalMarketProgramID')->get();
        $fiscalYears = FiscalYearMaster::query()
            ->orderByDesc('fy_startdate')
            ->orderByDesc('fiscal_year_master_id')
            ->get();

        return view('program.index', compact('programs', 'fiscalYears'));
    }

    public function create()
    {
        return view('program.create', [
            'dateRanges' => old('dates', [['StartDate' => '', 'EndDate' => '']]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        CapitalMarketProgram::create([
            'CapitalMarketProgramName' => $data['CapitalMarketProgramName'],
            'CapitalMarketProgramDetail' => $data['CapitalMarketProgramDetail'] ?? null,
            'CapitalMarketProgramDate' => $this->encodeDates($data['dates'] ?? []),
            'Status' => $data['Status'],
        ]);

        return redirect()
            ->route('program-master')
            ->with('success', 'Program added successfully.');
    }

    public function edit(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $program = CapitalMarketProgram::findOrFail($id);

        $ranges = old('dates');
        if (! is_array($ranges) || $ranges === []) {
            $ranges = $program->dateRanges();
            if ($ranges === []) {
                $ranges = [['StartDate' => '', 'EndDate' => '']];
            }
        }

        return view('program.edit', [
            'program' => $program,
            'secureToken' => SecureId::encode($program->CapitalMarketProgramID),
            'dateRanges' => $ranges,
        ]);
    }

    public function update(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $program = CapitalMarketProgram::findOrFail($id);

        try {
            $data = $this->validated($request, $program);
        } catch (ValidationException $e) {
            $request->flash();
            $ranges = $request->input('dates');
            if (! is_array($ranges) || $ranges === []) {
                $ranges = $program->dateRanges();
                if ($ranges === []) {
                    $ranges = [['StartDate' => '', 'EndDate' => '']];
                }
            }

            return view('program.edit', [
                'program' => $program,
                'secureToken' => SecureId::encode($program->CapitalMarketProgramID),
                'dateRanges' => $ranges,
            ])->withErrors($e->validator);
        }

        $program->update([
            'CapitalMarketProgramName' => $data['CapitalMarketProgramName'],
            'CapitalMarketProgramDetail' => $data['CapitalMarketProgramDetail'] ?? null,
            'CapitalMarketProgramDate' => $this->encodeDates($data['dates'] ?? []),
            'Status' => $data['Status'],
        ]);

        return redirect()
            ->route('program-master')
            ->with('success', 'Program updated successfully.');
    }

    private function validated(Request $request, ?CapitalMarketProgram $ignore = null): array
    {
        $unique = Rule::unique(CapitalMarketProgram::class, 'CapitalMarketProgramName');
        if ($ignore) {
            $unique->ignoreModel($ignore);
        }

        return $request->validate([
            'CapitalMarketProgramName' => ['required', 'string', 'max:255', $unique],
            'CapitalMarketProgramDetail' => 'nullable|string|max:2000',
            'Status' => 'required|in:ACTIVE,INACTIVE',
            'dates' => 'nullable|array',
            'dates.*.StartDate' => 'nullable|date',
            'dates.*.EndDate' => 'nullable|date|after_or_equal:dates.*.StartDate',
        ], [
            'CapitalMarketProgramName.required' => 'Program name is required.',
            'CapitalMarketProgramName.unique' => 'This program name already exists.',
            'dates.*.EndDate.after_or_equal' => 'Each end date must be on or after its start date.',
        ]);
    }

    /**
     * Build JSON for CapitalMarketProgramDate, or null when no dates provided.
     *
     * @param  array<int, array{StartDate?: string|null, EndDate?: string|null}>  $dates
     */
    private function encodeDates(array $dates): ?string
    {
        $clean = [];
        foreach ($dates as $row) {
            if (! is_array($row)) {
                continue;
            }
            $start = trim((string) ($row['StartDate'] ?? ''));
            $end = trim((string) ($row['EndDate'] ?? ''));
            if ($start === '' && $end === '') {
                continue;
            }
            if ($start === '' || $end === '') {
                continue;
            }
            $clean[] = [
                'StartDate' => $start,
                'EndDate' => $end,
            ];
        }

        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_UNESCAPED_SLASHES);
    }
}
