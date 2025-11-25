{{-- Header partial displays the shared navigation bar for staff pages. --}}
@php
    // Determine whether the user is authenticated and resolve logout routing accordingly.
    $isAuthenticated = auth()->check();
    $logoutRoute = $isAuthenticated ? route('logout') : null;
    $user = auth()->user();
    $isAdmin = $user && $user->role === 'admin';
    $casesRoute = $user && $user->role === 'legal' ? route('casemanager.legal') : route('casemanager.index');
@endphp

<div class="bg-white border-bottom shadow-sm">
    <div class="container py-3">
        <!-- Brand with primary navigation links and exit control. -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="fw-bold fs-5 text-primary">{{ config('app.name', 'HomeTrans CRM') }}</div>
            </div>
            <nav aria-label="Primary navigation">
                <ul class="nav nav-pills align-items-center">
                    @if($isAdmin)
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a></li>
                    @endif
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('casemanager.*') ? 'active' : '' }}" href="{{ $isAuthenticated ? $casesRoute : '#' }}">Cases</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}" href="{{ $isAdmin ? route('clients.index') : '#' }}">Clients</a></li>
                    @if($isAdmin)
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('legals.*') ? 'active' : '' }}" href="{{ route('legals.index') }}">Legals</a></li>
                    @endif
                    <li class="nav-item"><a class="nav-link" href="#">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Logs</a></li>
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
