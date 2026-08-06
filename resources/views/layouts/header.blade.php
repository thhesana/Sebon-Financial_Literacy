<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container-fluid page-wrap py-0" style="padding-top:0;padding-bottom:0;">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/sebon_logo.png') }}"
                 alt="SEBON"
                 class="navbar-logo">
        </a>

        <button class="navbar-toggler border-0 shadow-none"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNav"
                aria-controls="mainNav"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <i class="bi bi-list text-primary nav-toggle-icon"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav app-nav-links me-auto">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('program-master*') ? 'active' : '' }}" href="{{ route('program-master') }}">Program Master</a>
                <a class="nav-link {{ request()->routeIs('fy-master*') ? 'active' : '' }}" href="{{ route('fy-master') }}">FY Master</a>
                <a class="nav-link {{ request()->routeIs('questionnaire*') ? 'active' : '' }}" href="{{ route('questionnaire') }}">Survey Module</a>
                <a class="nav-link {{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}">Reports</a>
            </div>

            <div class="user-chip ms-lg-auto">
                <span><i class="bi bi-person-circle me-1"></i>{{ session('username') }}</span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn-logout">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>
