@extends('layouts.app')

@section('title', 'Dashboard — Analytics Hub')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Analytics Hub</h2>
        <p class="page-subtitle mb-0">Program-level insights from survey demographics (Q2, Q3, Q6, Q7, Q8)</p>
    </div>

    <form method="POST" action="{{ route('dashboard.program') }}" class="page-header-actions">
        @csrf
        <div>
            <label for="program_token" class="form-label fw-semibold mb-1">Capital Market Program</label>
            <select id="program_token" name="token" class="form-select program-select">
                <option value="all" {{ (int) ($selectedProgramId ?? 0) === 0 ? 'selected' : '' }}>All</option>
                @foreach($programs as $program)
                    <option value="{{ \App\Support\SecureId::encode($program->CapitalMarketProgramID) }}"
                        {{ (int) ($selectedProgramId ?? 0) === (int) $program->CapitalMarketProgramID ? 'selected' : '' }}>
                        {{ $program->displayLabel() }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-graph-up-arrow"></i> Load
        </button>
    </form>
</div>

@if($programs->isEmpty())
    <div class="alert alert-warning">
        No Capital Market Program found. Add one under <strong>Program Master</strong> first.
    </div>
@else
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 analytic-kpi">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold">Selected Program</div>
                    <div class="fs-5 fw-bold text-primary mt-1">
                        {{ $selectedProgram?->CapitalMarketProgramName ?? 'All' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 analytic-kpi">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-semibold">Total Respondents</div>
                    <div class="display-6 fw-bold text-dark mt-1">{{ number_format($analytics['total_respondents']) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($analytics['total_respondents'] === 0)
        <div class="alert alert-info">
            No survey responses found
            @if($selectedProgram)
                for <strong>{{ $selectedProgram->CapitalMarketProgramName }}</strong>
            @else
                across all programs
            @endif
            yet.
        </div>
    @else
        <div class="row g-4">
            @foreach($analytics['charts'] as $chart)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100 analytic-card">
                        <div class="card-header bg-white border-0 pt-3 pb-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge text-bg-primary">{{ $chart['code'] }}</span>
                                    <h5 class="mt-2 mb-0">{{ $chart['title'] }}</h5>
                                </div>
                                <span class="text-muted small">n={{ $chart['total'] }}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            @if(empty($chart['labels']))
                                <div class="text-muted py-5 text-center">No data for this indicator.</div>
                            @else
                                <div class="chart-wrap">
                                    <canvas id="chart-{{ $chart['code'] }}"
                                            data-type="{{ $chart['type'] }}"
                                            data-labels='@json($chart['labels'])'
                                            data-values='@json($chart['values'])'
                                            data-colors='@json($chart['colors'])'></canvas>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm mb-0 analytic-table">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Count</th>
                                                <th class="text-end">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($chart['labels'] as $i => $label)
                                                @php
                                                    $share = $chart['total'] > 0
                                                        ? round(($chart['values'][$i] / $chart['total']) * 100, 1)
                                                        : 0;
                                                @endphp
                                                <tr>
                                                    <td>{{ $label }}</td>
                                                    <td class="text-end">{{ $chart['values'][$i] }}</td>
                                                    <td class="text-end">{{ $share }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('canvas[id^="chart-"]').forEach(function (canvas) {
        var type = canvas.getAttribute('data-type') || 'bar';
        var labels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
        var values = JSON.parse(canvas.getAttribute('data-values') || '[]');
        var colors = JSON.parse(canvas.getAttribute('data-colors') || '[]');

        var isBar = type === 'bar';
        new Chart(canvas.getContext('2d'), {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Respondents',
                    data: values,
                    backgroundColor: colors,
                    borderColor: isBar ? colors : '#ffffff',
                    borderWidth: isBar ? 0 : 2,
                    borderRadius: isBar ? 6 : 0,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: !isBar,
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 14 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = values.reduce(function (a, b) { return a + b; }, 0);
                                var val = ctx.parsed.y ?? ctx.parsed;
                                var pct = total ? ((val / total) * 100).toFixed(1) : 0;
                                return ' ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                scales: isBar ? {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 40, minRotation: 0, autoSkip: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(15, 23, 42, 0.06)' }
                    }
                } : {}
            }
        });
    });
});
</script>
@endsection
