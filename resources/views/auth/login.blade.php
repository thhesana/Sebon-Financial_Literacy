<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Financial Literacy Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0b3a66;
            --primary-soft: #13508a;
            --mist: #e8f0f8;
            --font: "Manrope", system-ui, sans-serif;
            --fs-body: 0.9375rem;
            --fs-title: 1.375rem;
            --fs-sm: 0.8125rem;
            --fs-label: 0.875rem;
        }
        body {
            font-family: var(--font);
            font-size: var(--fs-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            background:
                radial-gradient(900px 420px at 15% 10%, rgba(255,255,255,0.18), transparent 50%),
                linear-gradient(145deg, #0b3a66 0%, #0a2744 55%, #071d33 100%);
            letter-spacing: -0.01em;
            color: #1a2436;
        }
        .login-card {
            background: rgba(255,255,255,0.97);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.28);
            width: 100%;
            max-width: 420px;
            padding: 2.2rem 2.1rem 2rem;
            border: 1px solid rgba(255,255,255,0.35);
        }
        .logo {
            max-width: 150px;
            display: block;
            margin: 0 auto 1rem;
        }
        .login-card h1 {
            color: var(--primary);
            font-size: var(--fs-title);
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.2rem;
            letter-spacing: -0.03em;
        }
        .login-card .subtitle {
            text-align: center;
            color: #64748b;
            font-size: var(--fs-sm);
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 650;
            color: #334155;
            font-size: var(--fs-label);
        }
        .form-control, .input-group-text {
            font-family: var(--font);
            font-size: var(--fs-body);
            border-radius: 10px;
            border-color: #d7e0ea;
            min-height: 42px;
        }
        .input-group-text {
            background: var(--mist);
            color: var(--primary);
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            font-family: var(--font);
            font-weight: 700;
            font-size: var(--fs-label);
            border-radius: 10px;
            padding: 0.7rem;
            box-shadow: 0 8px 18px rgba(11,58,102,0.22);
        }
        .btn-primary:hover {
            background: var(--primary-soft);
            border-color: var(--primary-soft);
        }
        .alert { border-radius: 10px; font-size: var(--fs-body); }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="{{ asset('images/sebon_logo.png') }}" alt="SEBON Logo" class="logo">
        <h1>Financial Literacy</h1>
        <div class="subtitle">SEBON Admin Login</div>

        @if (session('error'))
            <div class="alert alert-danger py-2">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" id="login-form" autocomplete="on">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" placeholder="For e.g. literacy_napit" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
    <script>
        // Reload if browser restores a cached login page (stale CSRF token → 419)
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>
