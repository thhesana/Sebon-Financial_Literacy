@extends('layouts.app')

@section('title', 'FY Master')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">FY Master</h2>
        <p class="page-subtitle mb-0">Fiscal Year Master</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('fy-master.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add New FY
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
                <th>FY</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th style="width: 120px;">Status</th>
                <th>Created Date</th>
                <th style="width: 110px;" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($years as $row)
                @php $token = \App\Support\SecureId::encode($row->fiscal_year_master_id); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->fy ?: '—' }}</td>
                    <td>{{ $row->fy_startdate ? $row->fy_startdate->format('Y-m-d') : '—' }}</td>
                    <td>{{ $row->fy_enddate ? $row->fy_enddate->format('Y-m-d') : '—' }}</td>
                    <td>
                        @if($row->isActive())
                            <span class="badge text-bg-success">Active</span>
                        @elseif(trim((string) $row->fy_status) === '')
                            <span class="badge text-bg-secondary">—</span>
                        @else
                            <span class="badge text-bg-secondary">{{ $row->fy_status }}</span>
                        @endif
                    </td>
                    <td>{{ $row->created_date ? $row->created_date->format('Y-m-d H:i') : '—' }}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('fy-master.edit') }}" class="d-inline">
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
                        No fiscal years found. Click <strong>Add New FY</strong> to begin.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
