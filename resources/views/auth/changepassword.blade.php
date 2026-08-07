<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Change Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b3a66;
            --primary-soft: #13508a;
            --font: "Manrope", system-ui, sans-serif;
            --fs-body: 0.9375rem;
            --fs-title: 1.375rem;
            --fs-sm: 0.8125rem;
            --fs-label: 0.875rem;
        }
        body {
            font-family: var(--font);
            font-size: var(--fs-body);
            background:
                radial-gradient(1200px 500px at 10% -10%, #dce9f6 0%, transparent 55%),
                radial-gradient(900px 420px at 100% 0%, #eaf2f8 0%, transparent 50%),
                #f3f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            letter-spacing: -0.01em;
            color: #1a2436;
        }
        .card-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 35, 60, 0.04), 0 4px 16px rgba(15, 35, 60, 0.04);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }
        h1 {
            color: var(--primary);
            font-size: var(--fs-title);
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }
        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: var(--fs-sm);
            font-weight: 500;
            margin-bottom: 1.35rem;
        }
        .form-label {
            font-weight: 650;
            color: #334155;
            font-size: var(--fs-label);
        }
        .form-control {
            font-family: var(--font);
            font-size: var(--fs-body);
            border: 1.5px solid #d7e0ea;
            border-radius: 10px;
            min-height: 42px;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 58, 102, 0.12);
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            font-family: var(--font);
            font-weight: 700;
            font-size: var(--fs-label);
            border-radius: 10px;
            min-height: 42px;
        }
        .btn-primary:hover { background: var(--primary-soft); border-color: var(--primary-soft); }
        .alert { border-radius: 10px; font-size: var(--fs-body); }
        .logo { max-width: 140px; display: block; margin: 0 auto 1rem; }
    </style>
</head>
<body>
    <div class="card-box">
        <img src="{{ asset('images/sebon_logo.png') }}" alt="SEBON Logo" class="logo">
        <h1>Change Password</h1>
        <p class="subtitle">New password will be securely encrypted.</p>

        @if (session('error'))
            <div class="alert alert-danger py-2">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('changepassword.post') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Old Password</label>
                <input type="password" class="form-control" name="old_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required minlength="8">
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" name="confirm_password" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
</body>
</html>
