@extends('layouts.app')

@section('content')
    <!-- Informational header reminding legal users about their limited view. -->
    <div class="alert alert-info">You are viewing cases assigned to you that are currently in progress.</div>

    <!-- Table of legal-assigned cases with minimal columns. -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">P/code</th>
                        <th scope="col">Deadline</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($cases as $case)
                        @php
                            // Highlight overdue deadlines in red to match the admin view.
                            $deadlineClass = $case->deadline && $case->deadline->isPast() ? 'text-danger fw-bold' : '';
                        @endphp
                        <tr>
                            <td>{{ $case->id }}</td>
                            <td>{{ $case->postal_code }}</td>
                            <td class="{{ $deadlineClass }}">{{ optional($case->deadline)->format('d/m/y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">No progress cases assigned to you.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate results for legal users for consistency. -->
            {{ $cases->links() }}
        </div>
    </div>
@endsection
