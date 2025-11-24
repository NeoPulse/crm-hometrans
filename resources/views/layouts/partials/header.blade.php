@php
    $user = auth()->user();
    $caseHeaderData = $caseHeaderData ?? null;
@endphp
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('dashboard') }}">HomeTrans</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navBar" aria-controls="navBar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navBar">
            @if($caseHeaderData)
                <div class="navbar-text text-white d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                    <span>Case {{ $caseHeaderData['postal_code'] }}, deadline {{ $caseHeaderData['deadline'] }}</span>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($caseHeaderData['people'] as $person)
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-nowrap">{{ $person['label'] }}</span>
                                <img src="{{ $person['avatar'] }}" alt="avatar" class="rounded-circle" width="50" height="50" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="{!! $person['tooltip'] !!}">
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @if($user?->role === 'admin')
                        <li class="nav-item"><a class="nav-link" href="{{ route('cases.index') }}">Cases</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('clients.index') }}">Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('legals.index') }}">Legals</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Profile</a></li>
                        <li class="nav-item"><a class="nav-link" href="#">Logs</a></li>
                    @endif
                </ul>
            @endif
            <ul class="navbar-nav ms-auto">
                @auth
                    <li class="nav-item"><span class="nav-link text-white">{{ $user?->name }}</span></li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">@csrf<button class="btn btn-outline-light btn-sm">Exit</button></form>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
