@extends('layouts.app')

@section('content')

    <h1 class="text-center mt-lg-5 mb-4 fs-2">Your cases</h1>

    {{-- Table of legal-assigned cases with minimal columns. --}}
    <div class="card shadow-sm col-lg-5 mx-auto">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Case</th>
                        <th scope="col" class="text-end">Deadline</th>
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
                            <td><a href="{{ route('cases.show', $case) }}" class="text-decoration-none">{{ $case->postal_code }}</a></td>
                            <td class="{{ $deadlineClass }} text-end">{{ optional($case->deadline)->format('d/m/y') ?? '—' }}</td>
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
            {{-- Paginate results for legal users for consistency. --}}
            {{ $cases->links() }}
        </div>
    </div>
@endsection
