@extends('layouts.app')

@section('title', 'Edit Program')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Edit Program</h2>
        <p class="page-subtitle mb-0">Update — {{ $program->CapitalMarketProgramName }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('program-master') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('program-master.update') }}" id="program-form">
            @csrf
            <input type="hidden" name="token" value="{{ $secureToken }}">
            @include('program._form_fields', [
                'name' => old('CapitalMarketProgramName', $program->CapitalMarketProgramName),
                'detail' => old('CapitalMarketProgramDetail', $program->CapitalMarketProgramDetail),
                'status' => old('Status', $program->isActive() ? 'ACTIVE' : 'INACTIVE'),
                'dateRanges' => $dateRanges,
            ])
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('program-master') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check2-circle"></i> Update Program
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
@include('program._dates_script')
@endsection
