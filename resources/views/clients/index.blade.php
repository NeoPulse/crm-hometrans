@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <h1 class="h4 mb-0">Clients</h1>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('clients.store') }}">
            @csrf
            <button class="btn btn-success">Add client</button>
        </form>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-8">
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search clients" aria-label="Search clients">
    </div>
    <div class="col-md-4 d-grid d-md-flex gap-2">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-{{ $status==='active' ? 'primary' : 'outline-primary' }}" href="{{ route('clients.index', array_merge(request()->query(), ['status' => 'active'])) }}">Active</a>
    <a class="btn btn-{{ $status==='nonactive' ? 'primary' : 'outline-primary' }}" href="{{ route('clients.index', array_merge(request()->query(), ['status' => 'nonactive'])) }}">Non active</a>
    <a class="btn btn-{{ $status==='all' ? 'primary' : 'outline-primary' }}" href="{{ route('clients.index', array_merge(request()->query(), ['status' => 'all'])) }}">All</a>
    <a class="btn btn-{{ $status==='attention' ? 'primary' : 'outline-primary' }}" href="{{ route('clients.index', array_merge(request()->query(), ['status' => 'attention'])) }}">Attention</a>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Actions</th>
                <th><a href="{{ route('clients.index', array_merge(request()->query(), ['sort' => 'cases'])) }}" class="text-decoration-none">Cases</a></th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th><a href="{{ route('clients.index', array_merge(request()->query(), ['sort' => 'registered'])) }}" class="text-decoration-none">Registered</a></th>
                <th>Headline</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clients as $client)
                @php
                    $rowClass = $client->is_active ? 'table-success' : 'table-secondary';
                    $caseList = $client->sellCases->concat($client->buyCases)->pluck('postal_code')->filter()->unique();
                    $caseCell = $caseList->isEmpty() ? '—' : ($caseList->count() > 1 ? $caseList->take(1)->first() . ', ...' : $caseList->first());
                    $callActive = $client->attentions->firstWhere('type', 'call');
                    $docActive = $client->attentions->firstWhere('type', 'doc');
                    $profile = $client->clientProfile;
                @endphp
                <tr class="{{ $rowClass }}" role="button" onclick="window.location='{{ route('clients.show', $client) }}'">
                    <td>{{ $client->id }}</td>
                    <td>
                        <span class="me-2 text-{{ $callActive ? 'danger' : 'secondary' }}" title="Call attention"><i class="bi bi-telephone-fill"></i></span>
                        <span class="text-{{ $docActive ? 'danger' : 'secondary' }}" title="Document attention"><i class="bi bi-file-earmark-text-fill"></i></span>
                    </td>
                    <td>{{ $caseCell }}</td>
                    <td>{{ $profile?->first_name }} {{ $profile?->last_name }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->phone ?? '—' }}</td>
                    <td>{{ optional($client->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $client->headline ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">No clients found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $clients->links('pagination::bootstrap-5') }}
@endsection
