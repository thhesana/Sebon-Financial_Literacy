@extends('layouts.app')

@section('title', 'Add New FY')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">Add New FY</h2>
        <p class="page-subtitle mb-0">Create a Fiscal Year</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('fy-master') }}" class="btn btn-outline-secondary">
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
        <form method="POST" action="{{ route('fy-master.store') }}">
            @csrf

            <div class="mb-3">
                <label for="fy" class="form-label fw-semibold">
                    FY <span class="text-danger">*</span>
                </label>
                <input type="text"
                       class="form-control form-control-lg @error('fy') is-invalid @enderror"
                       id="fy"
                       name="fy"
                       value="{{ old('fy') }}"
                       required
                       maxlength="50"
                       placeholder="e.g. 2082/83">
                @error('fy')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="fy_startdate" class="form-label fw-semibold">
                        Start Date <span class="text-danger">*</span>
                    </label>
                    <input type="date"
                           class="form-control form-control-lg @error('fy_startdate') is-invalid @enderror"
                           id="fy_startdate"
                           name="fy_startdate"
                           value="{{ old('fy_startdate') }}"
                           required>
                    @error('fy_startdate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="fy_enddate" class="form-label fw-semibold">
                        End Date <span class="text-danger">*</span>
                    </label>
                    <input type="date"
                           class="form-control form-control-lg @error('fy_enddate') is-invalid @enderror"
                           id="fy_enddate"
                           name="fy_enddate"
                           value="{{ old('fy_enddate') }}"
                           required>
                    @error('fy_enddate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label for="fy_status" class="form-label fw-semibold">
                    Status <span class="text-danger">*</span>
                </label>
                <select id="fy_status"
                        name="fy_status"
                        class="form-select form-select-lg @error('fy_status') is-invalid @enderror"
                        required>
                    <option value="Active" {{ old('fy_status', 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
                    <option value="Inactive" {{ old('fy_status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                @error('fy_status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('fy-master') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check2-circle"></i> Save FY
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
