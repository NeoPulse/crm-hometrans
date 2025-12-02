@extends('layouts.app')

@section('content')
    <!-- Toolbar housing quick add, search, and filters for clients. -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <!-- Add client button posts to the store route to generate a new record. -->
            <form method="POST" action="{{ route('clients.store') }}">
                @csrf
                <button type="submit" class="btn btn-success">Add client</button>
            </form>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Search box triggers on Enter and preserves active filters. -->
            <form class="d-flex" method="GET" action="{{ route('clients.index') }}" role="search">
                <label for="search" class="visually-hidden">Search clients</label>
                <input type="text" class="form-control" id="search" name="q" placeholder="Search clients" value="{{ $searchTerm }}">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            </form>
            <!-- Status filters allow quick toggling including attention mode. -->
            <div class="btn-group" role="group" aria-label="Client filters">
                @php
                    $filters = ['active' => 'Active', 'inactive' => 'Non active', 'all' => 'All', 'attention' => 'Attention'];
                @endphp
                @foreach($filters as $key => $label)
                    <a class="btn btn-outline-secondary {{ $statusFilter === $key ? 'active' : '' }}" href="{{ route('clients.index', ['status' => $key, 'q' => $searchTerm, 'sort' => $sort, 'direction' => $direction]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Validation and success feedback for administrative actions. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <!-- Clients table with attention markers, case count sorting, and clickable rows. -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Actions</th>
                        <th scope="col">
                            <a href="{{ route('clients.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'cases', 'direction' => $sort === 'cases' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Cases</a>
                        </th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">
                            <a href="{{ route('clients.index', ['status' => $statusFilter, 'q' => $searchTerm, 'sort' => 'registered', 'direction' => $sort === 'registered' && $direction === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Registered</a>
                        </th>
                        <th scope="col">Headline</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($clients as $client)
                        @php
                            // Determine row styling based on activation and gather attention flags.
                            $rowClass = $client->is_active ? 'table-success' : 'table-secondary';
                            $callAttention = $client->attentions->firstWhere('type', 'call');
                            $docAttention = $client->attentions->firstWhere('type', 'doc');
                            $casePostalCodes = collect($client->sellCases)->merge($client->buyCases)->pluck('postal_code')->filter()->unique()->values();
                            $casePreview = $casePostalCodes->isEmpty() ? 'No cases' : ($casePostalCodes->count() > 1 ? $casePostalCodes->first() . ', ...' : $casePostalCodes->first());
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('clients.edit', $client) }}">
                            <td><a href="{{ route('clients.edit', $client) }}" class="text-decoration-none">{{ $client->id }}</a></td>
                            <td>
                                <!-- Attention icons show in red when flagged. -->
                                <div class="d-flex gap-2">
                                    <span class="text-{{ $callAttention ? 'danger' : 'secondary' }}" title="Call"><i class="bi bi-telephone"></i></span>
                                    <span class="text-{{ $docAttention ? 'danger' : 'secondary' }}" title="Documents"><i class="bi bi-file-earmark-text"></i></span>
                                </div>
                            </td>
                            <td>{{ $casePreview }}</td>
                            <td>{{ $client->display_name }}</td>
                            <td>{{ $client->email }}</td>
                            <td>{{ $client->phone ?? '—' }}</td>
                            <td>{{ optional($client->created_at)->format('d/m/y') }}</td>
                            <td class="text-wrap">{{ $client->headline ?? 'No headline available' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No clients match the selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate the client list with Bootstrap styling. -->
            {{ $clients->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Enable entire row click to open the client card.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.table-row-link').forEach((row) => {
                row.addEventListener('click', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endpush
