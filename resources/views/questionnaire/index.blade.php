@extends('layouts.app')

@section('title', 'Questionnaire')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Submitted Surveys</h2>
        <p class="page-subtitle mb-0">Capital Market Awareness Questionnaire</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('questionnaire.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add New Survey
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
                <th>Participant Name</th>
                <th>Submitted Date</th>
                <th>Status</th>
                <th style="width: 170px;" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($respondents as $row)
                @php $token = \App\Support\SecureId::encode($row->RespondentId); @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->ParticipantName ?: '—' }}</td>
                    <td>
                        {{ $row->SubmittedDate ? $row->SubmittedDate->format('Y-m-d H:i') : '—' }}
                    </td>
                    <td>
                        @if($row->IsComplete)
                            <span class="badge text-bg-success">Complete</span>
                        @else
                            <span class="badge text-bg-secondary">Incomplete</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <form action="{{ route('questionnaire.show') }}"
                              method="POST"
                              class="d-inline"
                              target="_blank"
                              rel="noopener">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary me-1" title="View survey (document format)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </form>
                        <form action="{{ route('questionnaire.edit') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </form>
                        <form action="{{ route('questionnaire.destroy') }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Delete this survey response?');">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No surveys submitted yet. Click <strong>Add New Survey</strong> to begin.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
