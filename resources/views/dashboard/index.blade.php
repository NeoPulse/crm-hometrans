@extends('layouts.app')

@section('content')
    <!-- Top heading describing the admin dashboard purpose. -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Dashboard</h1>
        <span class="text-muted">Administrative overview</span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">New clients in the last month</div>
                <div class="card-body">
                    <!-- Chart canvas for daily client registrations. -->
                    <canvas id="clientsChart" height="240" aria-label="Client registrations column chart"></canvas>
                    <p class="text-muted small mb-0 mt-3">Daily registrations over the past 30 days.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">New cases this year</div>
                <div class="card-body">
                    <!-- Chart canvas for monthly case creation counts. -->
                    <canvas id="casesChart" height="240" aria-label="Case creation column chart"></canvas>
                    <p class="text-muted small mb-0 mt-3">Monthly case openings for the current year.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">In progress</h2>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">P/code</th>
                        <th scope="col">Actions</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col">Deadline</th>
                        <th scope="col">Headline</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($progressCases as $case)
                        @php
                            // Mark the row and deadline styling to mirror the case manager view.
                            $rowClass = $case->status === 'progress' ? 'table-success' : 'table-secondary';
                            $attentionTypes = ['attention', 'mail', 'doc'];
                            $deadlineClass = $case->deadline && $case->deadline->isPast() ? 'text-danger fw-bold' : '';
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('casemanager.edit', $case) }}">
                            <td><a href="{{ route('casemanager.edit', $case) }}" class="text-decoration-none">{{ $case->id }}</a></td>
                            <td>{{ $case->postal_code }}</td>
                            <td>
                                <!-- Display attention markers for quick scanning. -->
                                <div class="d-flex gap-2">
                                    @foreach($attentionTypes as $type)
                                        @php $exists = $case->attentions->firstWhere('type', $type); @endphp
                                        <span class="text-{{ $exists ? 'danger' : 'secondary' }}"><i class="bi bi-{{ $type === 'attention' ? 'exclamation-circle' : ($type === 'mail' ? 'envelope' : 'file-earmark-text') }}"></i></span>
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
                            <td colspan="7" class="text-center text-muted py-4">No active cases in progress.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate the progress list to keep the table compact. -->
            {{ $progressCases->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Render column charts after the DOM is ready to ensure canvases exist.
        document.addEventListener('DOMContentLoaded', () => {
            const clientCtx = document.getElementById('clientsChart');
            const caseCtx = document.getElementById('casesChart');

            // Configure the daily client registrations chart.
            if (clientCtx) {
                new Chart(clientCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($clientLabels),
                        datasets: [{
                            label: 'Registrations',
                            data: @json($clientValues),
                            backgroundColor: '#0d6efd'
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }

            // Configure the monthly case creation chart.
            if (caseCtx) {
                new Chart(caseCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($caseLabels),
                        datasets: [{
                            label: 'New cases',
                            data: @json($caseValues),
                            backgroundColor: '#198754'
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }

            // Enable row click behaviour for the active cases table.
            document.querySelectorAll('.table-row-link').forEach((row) => {
                row.addEventListener('click', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endpush
