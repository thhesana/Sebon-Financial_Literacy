@extends('layouts.app')

@section('title', 'Program Master')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Program Master</h2>
        <p class="page-subtitle mb-0">Capital Market Programs</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('program-master.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add New Program
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="table-responsive bg-white border rounded shadow-sm">
    <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-primary">
            <tr>
                <th style="width: 70px;">SN</th>
                <th>Program Name</th>
                <th style="width: 120px;">FY</th>
                <th>Program Date</th>
                <th>Program Detail</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 110px;" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($programs as $row)
                @php $token = \App\Support\SecureId::encode($row->CapitalMarketProgramID); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->CapitalMarketProgramName ?: '—' }}</td>
                    <td>
                        @php $fyLabels = $row->matchingFiscalYears($fiscalYears); @endphp
                        @if($fyLabels === [])
                            <span class="text-muted">—</span>
                        @else
                            @foreach($fyLabels as $fyLabel)
                                <span class="badge text-bg-primary me-1">{{ $fyLabel }}</span>
                            @endforeach
                        @endif
                    </td>
                    <td>{{ $row->dateRangesLabel() }}</td>
                    <td>{{ $row->CapitalMarketProgramDetail ?: '—' }}</td>
                    <td>
                        @if($row->isActive())
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">{{ $row->Status ?: '—' }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('program-master.edit') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No programs found. Click <strong>Add New Program</strong> to begin.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
