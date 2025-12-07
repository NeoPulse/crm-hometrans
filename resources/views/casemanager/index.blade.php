@extends('layouts.app')

@section('content')
    <!-- Header row containing quick add control and search/filter tools. -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-2">
            <!-- Quick add form requires a postal code without spaces. -->
            <form class="d-flex gap-2" method="POST" action="{{ route('casemanager.store') }}" novalidate>
                @csrf
                <div>
                    <label for="postal_code" class="visually-hidden">Postal code</label>
                    <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="Postal code" required pattern="^\\S+$">
                </div>
                <button type="submit" class="btn btn-success">Add case</button>
            </form>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Search form triggers on enter and preserves filters. -->
            <form class="d-flex" method="GET" action="{{ route('casemanager.index') }}" role="search">
                <label for="search" class="visually-hidden">Search</label>
                <input type="text" class="form-control" id="search" name="q" placeholder="Search cases" value="{{ $searchTerm }}">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            </form>
            <!-- Status filter buttons allow quick narrowing including attention flag. -->
            <div class="btn-group" role="group" aria-label="Status filters">
                @php
                    $filters = ['all' => 'All', 'new' => 'New', 'progress' => 'Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'attention' => 'Attention'];
                @endphp
                @foreach($filters as $key => $label)
                    <a class="btn btn-outline-secondary {{ $statusFilter === $key ? 'active' : '' }}" href="{{ route('casemanager.index', ['status' => $key, 'q' => $searchTerm, 'direction' => $sortDirection]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Display validation or session feedback to the administrator. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <!-- Cases table with status-aware row styling and sortable status column. -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">P/code</th>
                        <th scope="col">Actions</th>
                        <th scope="col">
                            <a href="{{ route('casemanager.index', ['status' => $statusFilter, 'q' => $searchTerm, 'direction' => $sortDirection === 'asc' ? 'desc' : 'asc']) }}" class="text-decoration-none text-dark">Status</a>
                        </th>
                        <th scope="col">Created</th>
                        <th scope="col">Deadline</th>
                        <th scope="col">Headline</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        // Map statuses to Bootstrap 5 table row classes
                        $statusClasses = [
                            'new'       => 'table-secondary', // gray
                            'progress'  => 'table-info',      // turquoise-like
                            'completed' => 'table-success',   // green
                            'cancelled' => 'table-cancelled',      // dark
                        ];
                    @endphp
                    @forelse($cases as $case)
                        @php
                            // Select class based on case status
                            //$rowClass = $case->status === 'progress' ? 'table-success' : 'table-secondary';
                            $rowClass = $statusClasses[$case->status] ?? 'table-secondary';
                            $attentionTypes = ['attention', 'mail', 'doc'];
                            $deadlineClass = $case->deadline && $case->deadline->isPast() ? 'text-danger fw-bold' : '';
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('casemanager.edit', $case) }}">
                            <td><a href="{{ route('casemanager.edit', $case) }}" class="text-decoration-none">{{ $case->id }}</a></td>
                            <td>{{ $case->postal_code }}</td>
                            <td>
                                <!-- Render attention icons in red when active and muted when absent. -->
                                <div class="d-flex gap-2">
                                    @foreach($attentionTypes as $type)
                                        @php $exists = $case->attentions->firstWhere('type', $type); @endphp
                                        <span class="text-{{ $exists ? 'danger' : 'secondary' }}"><i class="bi bi-{{ $type === 'attention' ? 'exclamation-circle-fill' : ($type === 'mail' ? 'envelope-fill' : 'file-earmark-text-fill') }}"></i></span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-capitalize">{{ $case->status }}</td>
                            <td>{{ optional($case->created_at)->format('d/m/y') }}</td>
                            <td class="{{ $deadlineClass }}">{{ optional($case->deadline)->format('d/m/y') ?? '—' }}</td>
                            <td class="text-wrap">{{ $case->headline ?? 'No headline available' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No cases found for the selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate through results with Bootstrap renderer. -->
            {{ $cases->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Highlight the add-case button when the postal code field has content.
        document.addEventListener('DOMContentLoaded', () => {
            const postalInput = document.getElementById('postal_code');
            const addForm = postalInput?.closest('form');

            if (postalInput && addForm) {
                postalInput.addEventListener('input', () => {
                    addForm.querySelector('button[type="submit"]').classList.toggle('btn-outline-success', !postalInput.value.trim());
                });
            }

            // Make each case row clickable to open the edit screen.
            document.querySelectorAll('.table-row-link').forEach((row) => {
                row.addEventListener('click', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endpush
