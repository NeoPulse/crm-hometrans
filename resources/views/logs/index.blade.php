@extends('layouts.app')

{{-- Logs page lists system activity with search and filtering for administrators. --}}
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Activity Logs</h1>
            <p class="text-muted mb-0">Review recorded actions with pagination, search and action filters.</p>
        </div>
    </div>

    <!-- Filter form enabling keyword search and action selection. -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('logs.index') }}" class="row gy-2 gx-3 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="{{ $search }}" class="form-control" placeholder="Search in details, location or user name">
                </div>
                <div class="col-md-4">
                    <label for="action" class="form-label">Action Type</label>
                    <select name="action" id="action" class="form-select">
                        <option value="">All actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ $actionFilter === $action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary flex-grow-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table presenting paginated activity log entries. -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">User</th>
                            <th scope="col">Action</th>
                            <th scope="col">Location</th>
                            <th scope="col">Details</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->created_at }}</td>
                                <td>
                                    @if($log->user_id)
                                        @php
                                            // Resolve the correct profile link based on the recorded role to open in a new tab.
                                            $userLink = url('users/' . $log->user_id . '/edit');
                                            if ($log->user_role === 'client') {
                                                $userLink = route('clients.edit', $log->user_id);
                                            } elseif ($log->user_role === 'legal') {
                                                $userLink = route('legals.edit', $log->user_id);
                                            }
                                        @endphp
                                        <a href="{{ $userLink }}" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">{{ $log->user_name ?? 'User' }}</a>
                                    @else
                                        <span class="fw-semibold">System</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-secondary text-uppercase">{{ $log->action }}</span></td>
                                <td>{{ $log->location ?? 'Not specified' }}</td>
                                <td>{{ $log->details ?? 'No details provided.' }}</td>
                                <td>{{ $log->ip_address ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">No activity logs available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
