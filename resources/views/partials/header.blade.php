{{-- Header partial displays the shared navigation bar for staff pages. --}}
@php
    // Determine whether the user is authenticated and resolve logout routing accordingly.
    $isAuthenticated = auth()->check();
    $logoutRoute = $isAuthenticated ? route('logout') : null;

    // Resolve user role flags for conditional navigation rendering.
    $user = auth()->user();
    $isAdmin = $user && $user->role === 'admin';
    $isLegal = $user && $user->role === 'legal';
    $isClient = $user && $user->role === 'client';
    $casesRoute = $isLegal ? route('casemanager.legal') : route('casemanager.index');
    $brandTarget = $isAdmin ? route('dashboard') : ($isAuthenticated ? $casesRoute : null);

    // Build a role-aware navigation definition to keep visibility rules concise.
    $navLinks = [];
    if ($isAdmin) {
        $navLinks = [
            ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Cases', 'route' => $casesRoute, 'active' => request()->routeIs('casemanager.*')],
            ['label' => 'Clients', 'route' => route('clients.index'), 'active' => request()->routeIs('clients.*')],
            ['label' => 'Legals', 'route' => route('legals.index'), 'active' => request()->routeIs('legals.*')],
            ['label' => 'Profile', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.*')],
            ['label' => 'Logs', 'route' => route('logs.index'), 'active' => request()->routeIs('logs.*')],
        ];
    } elseif ($isLegal || $isClient) {
        $navLinks = [
            ['label' => 'Cases', 'route' => $casesRoute, 'active' => request()->routeIs('casemanager.*')],
            ['label' => 'Profile', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.*')],
        ];
    }
@endphp

<div class="bg-white border-bottom shadow-sm">
    <div class="container py-3">
        <!-- Responsive navigation that collapses to a burger on md screens and below. -->
        <nav class="navbar navbar-expand-lg navbar-light" aria-label="Primary navigation">
            <div class="d-flex align-items-center">
                @if($brandTarget)
                    <a href="{{ $brandTarget }}" class="text-decoration-none">
                        <img src="{{ asset('images/logo.svg') }}" alt="Logo" height="40">
                    </a>
                @else
                    {{-- Static logo --}}
                    <img src="{{ asset('images/logo.svg') }}" alt="Logo" height="40">
                @endif
            </div>
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavbar" aria-controls="primaryNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primaryNavbar">
                <!-- Render the navigation items appropriate for the current user role. -->
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
                    @foreach($navLinks as $link)
                        <li class="nav-item">
                            <a class="nav-link {{ $link['active'] ? 'active' : '' }}" href="{{ $link['route'] }}">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                    @if($isAuthenticated)
                        <li class="nav-item mt-2 mt-lg-0">
                            <form method="POST" action="{{ $logoutRoute }}" class="d-flex">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Exit</button>
                            </form>
                        </li>
                    @endif
                </ul>
            </div>
        </nav>
    </div>
</div>
