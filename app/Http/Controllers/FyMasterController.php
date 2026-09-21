<?php

namespace App\Http\Controllers;

use App\Models\FiscalYearMaster;
use App\Support\SecureId;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FyMasterController extends Controller
{
    public function index()
    {
        $years = FiscalYearMaster::query()
            ->orderByDesc('fy_startdate')
            ->orderByDesc('fiscal_year_master_id')
            ->get();

        return view('fy.index', compact('years'));
    }

    public function create()
    {
        return view('fy.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        FiscalYearMaster::create([
            'fy' => $data['fy'],
            'fy_startdate' => $data['fy_startdate'],
            'fy_enddate' => $data['fy_enddate'],
            'fy_status' => $data['fy_status'],
            'created_date' => now(),
        ]);

        return redirect()
            ->route('fy-master')
            ->with('success', 'Fiscal year added successfully.');
    }

    public function edit(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $year = FiscalYearMaster::findOrFail($id);

        return view('fy.edit', [
            'year' => $year,
            'secureToken' => SecureId::encode($year->fiscal_year_master_id),
        ]);
    }

    public function update(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $year = FiscalYearMaster::findOrFail($id);

        try {
            $data = $this->validated($request, $year);
        } catch (ValidationException $e) {
            $request->flash();

            return view('fy.edit', [
                'year' => $year,
                'secureToken' => SecureId::encode($year->fiscal_year_master_id),
            ])->withErrors($e->validator);
        }

        $year->update([
            'fy' => $data['fy'],
            'fy_startdate' => $data['fy_startdate'],
            'fy_enddate' => $data['fy_enddate'],
            'fy_status' => $data['fy_status'],
        ]);

        return redirect()
            ->route('fy-master')
            ->with('success', 'Fiscal year updated successfully.');
    }

    private function validated(Request $request, ?FiscalYearMaster $ignore = null): array
    {
        $unique = Rule::unique(FiscalYearMaster::class, 'fy');
        if ($ignore) {
            $unique->ignoreModel($ignore);
        }

        return $request->validate([
            'fy' => ['required', 'string', 'max:50', $unique],
            'fy_startdate' => 'required|date',
            'fy_enddate' => 'required|date|after_or_equal:fy_startdate',
            'fy_status' => 'required|in:Active,Inactive',
        ], [
            'fy.required' => 'Fiscal year is required.',
            'fy.unique' => 'This fiscal year already exists.',
            'fy_startdate.required' => 'Start date is required.',
            'fy_enddate.required' => 'End date is required.',
            'fy_enddate.after_or_equal' => 'End date must be on or after start date.',
            'fy_status.required' => 'Status is required.',
        ]);
    }
}
