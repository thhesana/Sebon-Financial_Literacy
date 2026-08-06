@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="page-header no-print">
    <div>
        <h2 class="page-title">Survey Reports</h2>
        <p class="page-subtitle mb-0">Filter by fiscal year, program, demographics, and questionnaire answers</p>
    </div>
    <div class="page-header-actions">
        @if($searched)
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
        @endif
    </div>
</div>

<form method="POST"
      action="{{ route('reports.search') }}"
      id="report-filter-form"
      class="no-print">
    @csrf

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Filters</span>
            <button type="submit"
                    formaction="{{ route('reports.clear') }}"
                    formmethod="post"
                    class="btn btn-sm btn-light">
                <i class="bi bi-arrow-counterclockwise"></i> Reset / Clear
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fiscal Year (FY)</label>
                    <select name="fy_id" class="form-select">
                        <option value="">— All fiscal years —</option>
                        @foreach($fiscalYears as $fy)
                            <option value="{{ $fy->fiscal_year_master_id }}"
                                {{ (string) ($filters['fy_id'] ?? '') === (string) $fy->fiscal_year_master_id ? 'selected' : '' }}>
                                {{ $fy->fy }}
                                @if($fy->fy_startdate && $fy->fy_enddate)
                                    ({{ $fy->fy_startdate->format('Y-m-d') }} → {{ $fy->fy_enddate->format('Y-m-d') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Matches programs dated in that FY; undated programs use submitted date.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">— All programs —</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->CapitalMarketProgramID }}"
                                {{ (string) ($filters['program_id'] ?? '') === (string) $program->CapitalMarketProgramID ? 'selected' : '' }}>
                                {{ $program->displayLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Participant Name</label>
                    <input type="text"
                           name="participant_name"
                           class="form-control"
                           value="{{ $filters['participant_name'] ?? '' }}"
                           placeholder="Search name">
                </div>
            </div>

            @foreach($filterSections as $section)
                <div class="border rounded p-3 mb-3 bg-light-subtle">
                    <div class="fw-semibold text-primary mb-2">{{ $section->SurveySectionName }}</div>
                    <div class="row g-3">
                        @foreach($section->questions as $question)
                            @php
                                $qid = $question->QuestionId;
                                $selected = (array) ($filters['answers'][$qid] ?? []);
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label small fw-semibold mb-1">
                                    {{ $question->QuestionCode ? $question->QuestionCode.'. ' : '' }}{{ $question->QuestionText }}
                                </label>
                                <select name="answers[{{ $qid }}][]"
                                        class="form-select form-select-sm"
                                        multiple
                                        size="{{ min(5, max(3, $question->options->count())) }}">
                                    @foreach($question->options as $option)
                                        <option value="{{ $option->OptionId }}"
                                            {{ in_array((string) $option->OptionId, array_map('strval', $selected), true) ? 'selected' : '' }}>
                                            {{ $option->OptionText }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd to select multiple</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-end gap-2">
                <button type="submit"
                        formaction="{{ route('reports.clear') }}"
                        formmethod="post"
                        class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear
                </button>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>
    </div>
</form>

@if(! $searched)
    <div class="alert alert-info no-print mb-0">
        Select any filters above and click <strong>Search</strong> to load results.
        Results are not loaded automatically.
    </div>
@else
    <div class="report-print-header d-none d-print-block mb-3">
        <h2 class="mb-1">Capital Market Awareness Survey — Report</h2>
        <div>Generated: {{ now()->format('Y-m-d H:i') }} | Total: {{ $summaryRows->count() }}</div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2 no-print">
        <div class="fw-semibold">
            Results: <span class="badge text-bg-primary">{{ $summaryRows->count() }}</span>
        </div>
    </div>

    <div class="table-responsive bg-white border rounded shadow-sm report-results">
        <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-primary">
                <tr>
                    <th style="width: 55px;">SN</th>
                    <th>Participant</th>
                    <th style="width: 100px;">FY</th>
                    <th>Program</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    @foreach(($summaryRows->first()['key_answers'] ?? []) as $code => $cell)
                        <th>{{ $cell['label'] }}</th>
                    @endforeach
                    <th class="text-end no-print" style="width: 70px;">View</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryRows as $row)
                    <tr>
                        <td>{{ $row['sn'] }}</td>
                        <td>{{ $row['participant'] }}</td>
                        <td>
                            @if(empty($row['fy']))
                                <span class="text-muted">—</span>
                            @else
                                @foreach($row['fy'] as $fyLabel)
                                    <span class="badge text-bg-primary me-1">{{ $fyLabel }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $row['program'] }}</td>
                        <td>{{ $row['submitted'] }}</td>
                        <td>
                            @if($row['status'] === 'Complete')
                                <span class="badge text-bg-success">Complete</span>
                            @else
                                <span class="badge text-bg-secondary">Incomplete</span>
                            @endif
                        </td>
                        @foreach($row['key_answers'] as $cell)
                            <td>{{ $cell['value'] }}</td>
                        @endforeach
                        <td class="text-end no-print">
                            <form action="{{ route('questionnaire.show') }}"
                                  method="POST"
                                  class="d-inline"
                                  target="_blank">
                                @csrf
                                <input type="hidden" name="token" value="{{ \App\Support\SecureId::encode($row['respondent_id']) }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="View document">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">
                            No records matched your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
