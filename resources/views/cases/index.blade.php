@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Cases</h1>
    <form class="d-flex" method="POST" action="{{ route('cases.store') }}">
        @csrf
        <input type="text" name="postal_code" class="form-control me-2" placeholder="Postal code" required>
        <button class="btn btn-primary">Add case</button>
    </form>
</div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6">
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search cases">
    </div>
    <div class="col-md-4">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="all">All</option>
            <option value="new" @selected(request('status')==='new')>New</option>
            <option value="progress" @selected(request('status')==='progress')>Progress</option>
            <option value="completed" @selected(request('status')==='completed')>Completed</option>
            <option value="cancelled" @selected(request('status')==='cancelled')>Cancelled</option>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>
<div class="table-responsive">
    <table class="table table-hover align-middle">
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
                <tr><td colspan="5" class="text-center">No cases found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $cases->links() }}
@endsection
