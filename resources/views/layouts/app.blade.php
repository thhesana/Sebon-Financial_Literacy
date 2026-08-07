<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Capital Market Awareness Survey System')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --sebon-blue: #0b3a66;
            --sebon-blue-soft: #13508a;
            --sebon-blue-mist: #e8f0f8;
            --ink: #1a2436;
            --muted: #64748b;
            --line: #e2e8f0;
            --surface: #ffffff;
            --page: #f3f6fa;
            --shadow-sm: 0 1px 2px rgba(15, 35, 60, 0.04), 0 4px 16px rgba(15, 35, 60, 0.04);
            --radius: 12px;
            --font: "Manrope", system-ui, sans-serif;
            --fs-body: 0.9375rem;   /* 15px */
            --fs-sm: 0.8125rem;     /* 13px */
            --fs-xs: 0.75rem;       /* 12px */
            --fs-label: 0.875rem;   /* 14px */
            --fs-title: 1.375rem;   /* 22px */
            --fs-section: 1rem;     /* 16px */
            --fs-nav: 0.875rem;     /* 14px */
        }

        * { box-sizing: border-box; }

        html, body {
            font-family: var(--font);
            font-size: var(--fs-body);
            line-height: 1.5;
            color: var(--ink);
            letter-spacing: -0.01em;
        }

        body {
            background:
                radial-gradient(1200px 500px at 10% -10%, #dce9f6 0%, transparent 55%),
                radial-gradient(900px 420px at 100% 0%, #eaf2f8 0%, transparent 50%),
                var(--page);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6,
        .h1, .h2, .h3, .h4, .h5, .h6 {
            font-family: var(--font);
            color: var(--ink);
            letter-spacing: -0.02em;
            font-weight: 750;
            line-height: 1.3;
        }

        .page-title,
        h2:not(.doc-section-title) {
            font-size: var(--fs-title) !important;
            font-weight: 800 !important;
            margin: 0;
        }
        h5, .h5 { font-size: var(--fs-section) !important; font-weight: 750 !important; }
        .page-subtitle,
        .page-header small,
        .page-header .text-muted,
        small.text-muted,
        .text-muted { font-size: var(--fs-sm) !important; color: var(--muted) !important; font-weight: 500; }
        .display-6 { font-size: 1.75rem !important; font-weight: 800 !important; letter-spacing: -0.03em; }
        .fs-5 { font-size: var(--fs-section) !important; font-weight: 750 !important; }
        .small, small { font-size: var(--fs-sm) !important; }

        .app-shell { min-height: calc(100vh - 130px); }
        .page-wrap {
            max-width: 1140px;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .page-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 0.6rem;
        }

        /* Header */
        .app-navbar {
            background: rgba(255, 255, 255, 0.94) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
            padding: 0.55rem 0;
        }
        .navbar-logo {
            height: 40px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
            display: block;
        }
        .navbar-toggler .nav-toggle-icon { font-size: 1.35rem; }
        .app-nav-links { display: flex; gap: 0.2rem; flex-wrap: wrap; }
        .app-nav-links .nav-link {
            color: var(--muted) !important;
            font-weight: 650;
            font-size: var(--fs-nav) !important;
            padding: 0.4rem 0.8rem !important;
            border-radius: 999px;
            transition: all .15s ease;
        }
        .app-nav-links .nav-link:hover {
            color: var(--sebon-blue) !important;
            background: var(--sebon-blue-mist);
        }
        .app-nav-links .nav-link.active {
            color: #fff !important;
            background: var(--sebon-blue);
            font-weight: 750;
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--sebon-blue-mist);
            color: var(--sebon-blue);
            border-radius: 999px;
            padding: 0.25rem 0.3rem 0.25rem 0.75rem;
            font-weight: 650;
            font-size: var(--fs-sm);
        }
        .btn-logout {
            border: 0;
            background: #fff;
            color: var(--sebon-blue);
            border-radius: 999px;
            padding: 0.25rem 0.7rem;
            font-weight: 750;
            font-size: var(--fs-xs);
            box-shadow: inset 0 0 0 1px #c9daf0;
        }
        .btn-logout:hover { background: var(--sebon-blue); color: #fff; }

        /* Cards */
        .card {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            background: var(--surface);
            overflow: hidden;
        }
        .card.shadow-sm, .card.border-0.shadow-sm { box-shadow: var(--shadow-sm) !important; }
        .card-header {
            border-bottom: 1px solid var(--line);
            background: #fbfcfe;
            font-size: var(--fs-label);
            font-weight: 750;
            padding: 0.85rem 1rem;
        }
        .card-header.bg-primary,
        .card-header.bg-primary.text-white {
            background: linear-gradient(135deg, var(--sebon-blue) 0%, var(--sebon-blue-soft) 100%) !important;
            border-bottom: 0;
            color: #fff !important;
            font-size: var(--fs-label);
        }
        .card-body { padding: 1rem; font-size: var(--fs-body); }

        /* Buttons */
        .btn {
            font-family: var(--font);
            font-weight: 700;
            font-size: var(--fs-label);
            border-radius: 10px;
            letter-spacing: -0.01em;
            line-height: 1.25;
        }
        .btn-sm { font-size: var(--fs-sm); border-radius: 8px; }
        .btn-primary {
            background: var(--sebon-blue);
            border-color: var(--sebon-blue);
            box-shadow: 0 6px 16px rgba(11, 58, 102, 0.16);
        }
        .btn-primary:hover, .btn-primary:focus {
            background: var(--sebon-blue-soft);
            border-color: var(--sebon-blue-soft);
        }
        .btn-success { background: #147a4f; border-color: #147a4f; }
        .btn-outline-primary { color: var(--sebon-blue); border-color: #b7cce2; border-width: 1.5px; }
        .btn-outline-primary:hover { background: var(--sebon-blue); border-color: var(--sebon-blue); color: #fff; }
        .btn-outline-secondary, .btn-outline-danger { border-width: 1.5px; }

        /* Forms */
        .form-label {
            font-family: var(--font);
            font-weight: 650;
            color: #334155;
            font-size: var(--fs-label) !important;
            margin-bottom: 0.35rem;
        }
        .form-control,
        .form-select,
        .form-control-lg,
        .form-select-lg,
        .form-control-sm,
        .form-select-sm {
            font-family: var(--font);
            font-size: var(--fs-body) !important;
            border: 1.5px solid #d7e0ea;
            border-radius: 10px;
            padding: 0.55rem 0.8rem;
            background: #fff;
            box-shadow: none;
            min-height: 42px;
        }
        .form-control-lg, .form-select-lg {
            padding: 0.65rem 0.9rem;
            min-height: 46px;
            border-radius: 10px;
        }
        .form-control-sm, .form-select-sm {
            min-height: 38px;
            padding: 0.4rem 0.65rem;
            font-size: var(--fs-sm) !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--sebon-blue);
            box-shadow: 0 0 0 3px rgba(11, 58, 102, 0.12);
        }
        .form-text { font-size: var(--fs-xs) !important; color: var(--muted); }
        .form-select[multiple] { min-height: 110px; }

        /* Tables */
        .table-responsive.bg-white,
        .table-responsive.border,
        .report-results,
        .table-panel {
            border: 1px solid var(--line) !important;
            border-radius: var(--radius) !important;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            background: #fff;
        }
        .table {
            margin-bottom: 0;
            font-size: var(--fs-body) !important;
            font-family: var(--font);
        }
        .table > :not(caption) > * > * {
            padding: 0.75rem 0.9rem;
            vertical-align: middle;
            font-size: var(--fs-body) !important;
        }
        .table-primary,
        .table thead.table-primary {
            --bs-table-bg: #edf3f9;
            --bs-table-color: var(--sebon-blue);
            color: var(--sebon-blue);
            font-size: var(--fs-xs) !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 750;
        }
        .table thead.table-primary th {
            font-size: var(--fs-xs) !important;
            font-weight: 750;
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg-type: #f8fafc; }
        .table-hover > tbody > tr:hover > * { --bs-table-bg-state: #eef5fb; }
        .table-sm > :not(caption) > * > * { padding: 0.5rem 0.7rem; }

        /* Badges / alerts */
        .badge {
            font-family: var(--font);
            font-weight: 700;
            font-size: var(--fs-xs) !important;
            border-radius: 7px;
            padding: 0.4em 0.65em;
        }
        .alert {
            border-radius: 10px;
            font-size: var(--fs-body);
            font-weight: 500;
        }
        .alert-info { background: #eef6ff; border-color: #cfe1f5; color: #1e4b75; }
        .alert-success { background: #eef8f2; border-color: #c8e6d4; color: #1d6a45; }
        .alert-danger { background: #fef2f2; border-color: #f3d0d0; color: #9b2c2c; }
        .alert-warning { background: #fff8eb; border-color: #f3e0b5; color: #8a5b10; }

        .text-primary { color: var(--sebon-blue) !important; }
        .bg-primary { background-color: var(--sebon-blue) !important; }
        .bg-light-subtle { background: #f8fafc !important; }

        /* Shared survey option cards */
        .option-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.65rem;
        }
        .option-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin: 0;
            padding: 0.8rem 0.9rem;
            border: 1.5px solid #d0dae6;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
            user-select: none;
            min-height: 52px;
            font-family: var(--font);
        }
        .option-card:hover {
            border-color: var(--sebon-blue);
            background: #f5f9fc;
            box-shadow: 0 2px 10px rgba(11, 58, 102, 0.06);
        }
        .option-card input.answer-option {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .option-indicator {
            flex: 0 0 auto;
            width: 22px;
            height: 22px;
            border: 2px solid #7b8ca3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            margin-top: 1px;
            color: transparent;
            font-size: var(--fs-label);
            line-height: 1;
        }
        .option-check { border-radius: 6px; }
        .option-radio { border-radius: 50%; }
        .option-radio::after {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: transparent;
        }
        .option-body { flex: 1 1 auto; min-width: 0; }
        .option-text {
            display: block;
            font-size: var(--fs-body);
            font-weight: 650;
            color: var(--ink);
            line-height: 1.4;
            padding-top: 1px;
        }
        .option-card.is-selected {
            border-color: var(--sebon-blue);
            background: linear-gradient(180deg, #e8f1fa 0%, #f7fafc 100%);
            box-shadow: 0 0 0 3px rgba(11, 58, 102, 0.12);
        }
        .option-card.is-selected .option-indicator {
            border-color: var(--sebon-blue);
            background: var(--sebon-blue);
            color: #fff;
        }
        .option-card.is-selected .option-radio::after { background: #fff; }
        .option-card.is-selected .option-text { color: var(--sebon-blue); font-weight: 750; }
        .option-card:focus-within {
            outline: 3px solid rgba(11, 58, 102, 0.22);
            outline-offset: 2px;
        }
        .question-block .form-label {
            font-size: var(--fs-body) !important;
            font-weight: 700 !important;
            margin-bottom: 0.55rem;
        }
        .question-block.is-invalid-question {
            background: #fff5f5;
            border: 1px solid #f1aeb5 !important;
            border-radius: 10px;
            padding: 12px !important;
        }
        .answer-text {
            border-width: 1.5px !important;
            border-color: #d0dae6 !important;
            border-radius: 10px !important;
        }

        /* Document preview (same font family as app) */
        .survey-doc {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.75rem 1.75rem 1.5rem;
            color: var(--ink);
            font-family: var(--font);
            font-size: var(--fs-body);
            box-shadow: var(--shadow-sm);
        }
        .survey-doc-header {
            text-align: center;
            border-bottom: 2px solid var(--sebon-blue);
            padding-bottom: 0.85rem;
            margin-bottom: 1.15rem;
        }
        .doc-org {
            color: var(--sebon-blue);
            font-weight: 750;
            font-size: var(--fs-xs);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .survey-doc-header h1 {
            font-size: var(--fs-title) !important;
            font-weight: 800;
            margin: 0.35rem 0 0.2rem;
            color: var(--sebon-blue);
        }
        .doc-sub {
            font-size: var(--fs-body);
            color: #334155;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .doc-instructions {
            text-align: left;
            margin: 0.7rem 0 0;
            font-size: var(--fs-sm);
        }
        .doc-section { margin-bottom: 1.1rem; }
        .doc-section-title {
            font-size: var(--fs-label) !important;
            font-weight: 750;
            color: var(--sebon-blue);
            background: var(--sebon-blue-mist);
            border-left: 4px solid var(--sebon-blue);
            padding: 0.45rem 0.7rem;
            margin: 0 0 0.75rem;
            border-radius: 0 8px 8px 0;
        }
        .doc-question { margin-bottom: 0.85rem; }
        .doc-q-title {
            font-size: var(--fs-body);
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        .doc-hint {
            font-weight: 500;
            font-style: italic;
            color: var(--muted);
            font-size: var(--fs-sm);
        }
        .doc-options {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem 1rem;
        }
        .doc-option {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: var(--fs-body);
            color: #334155;
        }
        .doc-option.is-checked { color: var(--sebon-blue); font-weight: 750; }
        .doc-mark { font-size: 1rem; line-height: 1; color: #64748b; }
        .doc-option.is-checked .doc-mark { color: var(--sebon-blue); }
        .doc-filled {
            display: inline-block;
            min-width: 55%;
            border-bottom: 1.5px solid var(--sebon-blue);
            padding: 0 0.25rem 0.1rem;
            font-weight: 700;
            color: var(--sebon-blue);
            font-size: var(--fs-body);
        }
        .doc-underline { color: #94a3b8; }
        .survey-doc-footer {
            margin-top: 1.35rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--line);
            text-align: center;
            font-style: italic;
            color: var(--muted);
            font-size: var(--fs-sm);
        }

        /* Analytics */
        .analytic-kpi {
            border-left: 4px solid var(--sebon-blue) !important;
            border-radius: var(--radius) !important;
        }
        .analytic-card { border-radius: var(--radius) !important; }
        .chart-wrap { position: relative; height: 260px; }
        .program-select { min-width: 260px; }
        .analytic-table th { font-size: var(--fs-xs) !important; color: var(--muted); text-transform: none; letter-spacing: 0; }
        .analytic-table td { font-size: var(--fs-body) !important; }

        .app-footer {
            border-top: 1px solid var(--line);
            background: rgba(255,255,255,0.75);
            color: var(--muted);
            padding: 1rem;
            text-align: center;
            font-size: var(--fs-sm);
            font-weight: 500;
        }

        @media (max-width: 991px) {
            .app-nav-links { margin-top: 0.65rem; width: 100%; }
            .user-chip { margin-top: 0.65rem; }
            .program-select { min-width: 100%; }
        }
        @media (max-width: 576px) {
            .option-grid { grid-template-columns: 1fr; }
            .survey-doc { padding: 1.15rem; }
        }

        @media print {
            .no-print, .navbar, .app-navbar, .app-footer, #report-filter-form { display: none !important; }
            body { background: #fff !important; }
            .page-wrap { max-width: 100%; padding: 0; }
            .survey-doc, .report-results { box-shadow: none !important; }
        }
    </style>
</head>
<body>

@include('layouts.header')

<main class="app-shell">
    <div class="page-wrap">
        @if (session('error'))
            <div class="alert alert-danger py-2">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>
</main>

@include('layouts.footer')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
