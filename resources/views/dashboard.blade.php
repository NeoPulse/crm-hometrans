@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">New clients last 30 days</h2>
                <canvas id="clientsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Cases this year</h2>
                <canvas id="casesChart"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <h2 class="h5">Cases</h2>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>P/Code</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Headline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                        <tr class="{{ $case->status === 'progress' ? 'table-success' : 'table-secondary' }}" onclick="window.location='{{ route('cases.edit', $case) }}'" role="button">
                            <td>{{ $case->id }}</td>
                            <td>{{ $case->postal_code }}</td>
                            <td>{{ ucfirst($case->status) }}</td>
                            <td>{{ optional($case->deadline)->format('d/m/Y') }}</td>
                            <td>{{ $case->headline }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No cases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $cases->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
const clientCtx = document.getElementById('clientsChart');
new Chart(clientCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($clientRegistrations->keys()) !!},
        datasets: [{ label: 'Clients', data: {!! json_encode($clientRegistrations->values()) !!}, backgroundColor: '#0d6efd' }]
    }
});

const casesCtx = document.getElementById('casesChart');
new Chart(casesCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($caseByMonth->keys()) !!},
        datasets: [{ label: 'Cases', data: {!! json_encode($caseByMonth->values()) !!}, backgroundColor: '#198754' }]
    }
});
</script>
@endpush
