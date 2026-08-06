<script>
document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('date-ranges');
    var addBtn = document.getElementById('add-date-range');
    if (!container || !addBtn) return;

    function reindex() {
        container.querySelectorAll('.date-range-row').forEach(function (row, i) {
            var start = row.querySelector('input[name*="[StartDate]"]');
            var end = row.querySelector('input[name*="[EndDate]"]');
            if (start) start.name = 'dates[' + i + '][StartDate]';
            if (end) end.name = 'dates[' + i + '][EndDate]';
        });
    }

    function syncRemoveButtons() {
        var rows = container.querySelectorAll('.date-range-row');
        rows.forEach(function (row) {
            var btn = row.querySelector('.remove-date-range');
            if (btn) btn.disabled = rows.length <= 1;
        });
    }

    function bindRemove(btn) {
        btn.addEventListener('click', function () {
            var rows = container.querySelectorAll('.date-range-row');
            if (rows.length <= 1) {
                rows[0].querySelectorAll('input').forEach(function (input) { input.value = ''; });
                return;
            }
            btn.closest('.date-range-row').remove();
            reindex();
            syncRemoveButtons();
        });
    }

    container.querySelectorAll('.remove-date-range').forEach(bindRemove);

    addBtn.addEventListener('click', function () {
        var index = container.querySelectorAll('.date-range-row').length;
        var wrap = document.createElement('div');
        wrap.className = 'row g-2 align-items-end date-range-row';
        wrap.innerHTML =
            '<div class="col-md-5">' +
                '<label class="form-label">Start Date</label>' +
                '<input type="date" class="form-control" name="dates[' + index + '][StartDate]" value="">' +
            '</div>' +
            '<div class="col-md-5">' +
                '<label class="form-label">End Date</label>' +
                '<input type="date" class="form-control" name="dates[' + index + '][EndDate]" value="">' +
            '</div>' +
            '<div class="col-md-2">' +
                '<button type="button" class="btn btn-outline-danger w-100 remove-date-range" title="Remove">' +
                    '<i class="bi bi-trash"></i>' +
                '</button>' +
            '</div>';
        container.appendChild(wrap);
        bindRemove(wrap.querySelector('.remove-date-range'));
        syncRemoveButtons();
    });

    syncRemoveButtons();
});
</script>
