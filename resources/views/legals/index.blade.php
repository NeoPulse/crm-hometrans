@extends('layouts.app')

@section('content')
    <!-- Toolbar for adding solicitors, searching, and toggling status filters. -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <!-- Quick add button posts to the store route and opens the new card. -->
            <form method="POST" action="{{ route('legals.store') }}">
                @csrf
                <button type="submit" class="btn btn-success">Add legal</button>
            </form>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Search field triggers on enter and keeps current filters and sorting. -->
            <form class="d-flex" method="GET" action="{{ route('legals.index') }}" role="search">
                <label for="search" class="visually-hidden">Search legals</label>
                <input type="text" class="form-control" id="search" name="q" placeholder="Search legals" value="{{ $searchTerm }}">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            </form>
            <!-- Status filter buttons to show active, inactive, or all solicitors. -->
            <div class="btn-group" role="group" aria-label="Legal filters">
                @php $filters = ['active' => 'Active', 'inactive' => 'Non active', 'all' => 'All']; @endphp
                @foreach($filters as $key => $label)
                    <a class="btn btn-outline-secondary {{ $statusFilter === $key ? 'active' : '' }}" href="{{ route('legals.index', ['status' => $key, 'q' => $searchTerm, 'sort' => $sort, 'direction' => $direction]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Display validation errors or success messages for administrative actions. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <!-- Solicitor table with sortable columns and role-based row coloring. -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">
                            <a href="{{ route('legals.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'cases', 'direction' => $sort === 'cases' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Cases</a>
                        </th>
                        <th scope="col">
                            <a href="{{ route('legals.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'person', 'direction' => $sort === 'person' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Person</a>
                        </th>
                        <th scope="col">
                            <a href="{{ route('legals.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'company', 'direction' => $sort === 'company' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Company name</a>
                        </th>
                        <th scope="col">
                            <a href="{{ route('legals.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'locality', 'direction' => $sort === 'locality' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Locality</a>
                        </th>
                        <th scope="col">Headline</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($legals as $legal)
                        @php
                            // Determine row styling based on activation and summarize case postal codes.
                            $rowClass = $legal->is_active ? 'table-success' : 'table-secondary';
                            $casePostalCodes = collect($legal->sellLegalCases)->merge($legal->buyLegalCases)->pluck('postal_code')->filter()->unique()->values();
                            $casePreview = $casePostalCodes->isEmpty() ? 'No cases' : ($casePostalCodes->count() > 1 ? $casePostalCodes->first() . ', ...' : $casePostalCodes->first());
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('legals.edit', $legal) }}">
                            <td><a href="{{ route('legals.edit', $legal) }}" class="text-decoration-none">{{ $legal->id }}</a></td>
                            <td>{{ $casePreview }}</td>
                            <td>{{ optional($legal->legalProfile)->person ?? $legal->name }}</td>
                            <td>{{ optional($legal->legalProfile)->company ?? 'No company' }}</td>
                            <td>{{ optional($legal->legalProfile)->locality ?? 'No locality' }}</td>
                            <td class="text-wrap">{{ $legal->headline ?? 'No headline available' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No legals match the selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate results with Bootstrap styling. -->
            {{ $legals->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Enable full-row navigation to the legal card.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.table-row-link').forEach((row) => {
                row.addEventListener('click', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endpush
