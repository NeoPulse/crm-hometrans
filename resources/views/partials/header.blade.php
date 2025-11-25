{{-- Header partial displays the shared navigation bar for staff pages. --}}
@php
    // Determine whether the user is authenticated and resolve logout routing accordingly.
    $isAuthenticated = auth()->check();
    $logoutRoute = $isAuthenticated ? route('logout') : null;

    // Resolve user role flags for conditional navigation rendering.
    $user = auth()->user();
    $isAdmin = $user && $user->role === 'admin';
    $isLegal = $user && $user->role === 'legal';
    $casesRoute = $user && $user->role === 'legal' ? route('casemanager.legal') : route('casemanager.index');

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
    } elseif ($isLegal) {
        $navLinks = [
            ['label' => 'Cases', 'route' => $casesRoute, 'active' => request()->routeIs('casemanager.*')],
            ['label' => 'Profile', 'route' => route('profile.show'), 'active' => request()->routeIs('profile.*')],
        ];
    } elseif ($isAuthenticated) {
        $navLinks = [
            ['label' => 'Cases', 'route' => $casesRoute, 'active' => request()->routeIs('casemanager.*')],
        ];
    }
@endphp

<div class="bg-white border-bottom shadow-sm">
    <div class="container py-3">
        <!-- Brand with primary navigation links and exit control. -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="fw-bold fs-5 text-primary">{{ config('app.name', 'HomeTrans CRM') }}</div>
            </div>
            <nav aria-label="Primary navigation">
                <!-- Render the navigation items appropriate for the current user role. -->
                <ul class="nav nav-pills align-items-center">
                    @foreach($navLinks as $link)
                        <li class="nav-item">
                            <a class="nav-link {{ $link['active'] ? 'active' : '' }}" href="{{ $link['route'] }}">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                    <li class="nav-item">
                        @if($isAuthenticated)
                            <form method="POST" action="{{ $logoutRoute }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Exit</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Exit</button>
                        @endif
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>
