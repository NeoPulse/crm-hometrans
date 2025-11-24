@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <h1 class="h4 mb-0">Legals</h1>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('legals.store') }}">
            @csrf
            <button class="btn btn-success">Add legal</button>
        </form>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-8">
        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search legals" aria-label="Search legals">
    </div>
    <div class="col-md-4 d-grid d-md-flex gap-2">
        <button class="btn btn-outline-primary" type="submit">Search</button>
        <a href="{{ route('legals.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-{{ $status==='active' ? 'primary' : 'outline-primary' }}" href="{{ route('legals.index', array_merge(request()->query(), ['status' => 'active'])) }}">Active</a>
    <a class="btn btn-{{ $status==='nonactive' ? 'primary' : 'outline-primary' }}" href="{{ route('legals.index', array_merge(request()->query(), ['status' => 'nonactive'])) }}">Non active</a>
    <a class="btn btn-{{ $status==='all' ? 'primary' : 'outline-primary' }}" href="{{ route('legals.index', array_merge(request()->query(), ['status' => 'all'])) }}">All</a>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th><a href="{{ route('legals.index', array_merge(request()->query(), ['sort' => 'cases'])) }}" class="text-decoration-none">Cases</a></th>
                <th><a href="{{ route('legals.index', array_merge(request()->query(), ['sort' => 'person'])) }}" class="text-decoration-none">Person</a></th>
                <th><a href="{{ route('legals.index', array_merge(request()->query(), ['sort' => 'company'])) }}" class="text-decoration-none">Company name</a></th>
                <th><a href="{{ route('legals.index', array_merge(request()->query(), ['sort' => 'locality'])) }}" class="text-decoration-none">Locality</a></th>
                <th>Headline</th>
            </tr>
        </thead>
        <tbody>
            @forelse($legals as $legal)
                @php
                    $rowClass = $legal->is_active ? 'table-success' : 'table-secondary';
                    $caseList = $legal->sellLegalCases->concat($legal->buyLegalCases)->pluck('postal_code')->filter()->unique();
                    $caseCell = $caseList->isEmpty() ? '—' : ($caseList->count() > 1 ? $caseList->take(1)->first() . ', ...' : $caseList->first());
                @endphp
                <tr class="{{ $rowClass }}" role="button" onclick="window.location='{{ route('legals.show', $legal) }}'">
                    <td>{{ $legal->id }}</td>
                    <td>{{ $caseCell }}</td>
                    <td>{{ $legal->legalProfile?->person ?? '—' }}</td>
                    <td>{{ $legal->legalProfile?->company ?? '—' }}</td>
                    <td>{{ $legal->legalProfile?->locality ?? '—' }}</td>
                    <td>{{ $legal->headline ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">No legals found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $legals->links('pagination::bootstrap-5') }}
@endsection
