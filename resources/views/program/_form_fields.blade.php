<div class="mb-3">
    <label for="CapitalMarketProgramName" class="form-label fw-semibold">
        Program Name <span class="text-danger">*</span>
    </label>
    <input type="text"
           class="form-control form-control-lg @error('CapitalMarketProgramName') is-invalid @enderror"
           id="CapitalMarketProgramName"
           name="CapitalMarketProgramName"
           value="{{ $name }}"
           required
           maxlength="255"
           placeholder="e.g. Capital Market Awareness 2027">
    @error('CapitalMarketProgramName')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label fw-semibold mb-0">Program Date(s)</label>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-date-range">
            <i class="bi bi-plus-lg"></i> Add date range
        </button>
    </div>
    <p class="form-text mb-2">Use one range for a single-date program, or add more ranges for multi-date programs. Leave blank if no dates.</p>

    <div id="date-ranges" class="d-flex flex-column gap-2">
        @foreach($dateRanges as $i => $range)
            <div class="row g-2 align-items-end date-range-row">
                <div class="col-md-5">
                    <label class="form-label">Start Date</label>
                    <input type="date"
                           class="form-control"
                           name="dates[{{ $i }}][StartDate]"
                           value="{{ $range['StartDate'] ?? '' }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label">End Date</label>
                    <input type="date"
                           class="form-control"
                           name="dates[{{ $i }}][EndDate]"
                           value="{{ $range['EndDate'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger w-100 remove-date-range" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="mb-3">
    <label for="CapitalMarketProgramDetail" class="form-label fw-semibold">
        Program Detail
    </label>
    <textarea class="form-control @error('CapitalMarketProgramDetail') is-invalid @enderror"
              id="CapitalMarketProgramDetail"
              name="CapitalMarketProgramDetail"
              rows="4"
              maxlength="2000"
              placeholder="e.g. Training program for new investors.">{{ $detail }}</textarea>
    @error('CapitalMarketProgramDetail')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <label for="Status" class="form-label fw-semibold">
        Status <span class="text-danger">*</span>
    </label>
    <select id="Status"
            name="Status"
            class="form-select form-select-lg @error('Status') is-invalid @enderror"
            required>
        <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>Active</option>
        <option value="INACTIVE" {{ $status === 'INACTIVE' ? 'selected' : '' }}>Inactive</option>
    </select>
    @error('Status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
