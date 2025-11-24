@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3 align-items-lg-center">
    <div>
        <div class="fw-bold">ID {{ $client->id }}</div>
        <div class="text-muted">Registered {{ optional($client->created_at)->format('d/m/Y H:i') }}</div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <form method="POST" action="{{ route('clients.attention', [$client, 'call']) }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 text-{{ $client->attentions->firstWhere('type', 'call') ? 'danger' : 'secondary' }}" title="Toggle call attention">
                <i class="bi bi-telephone-fill fs-4"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('clients.attention', [$client, 'doc']) }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 text-{{ $client->attentions->firstWhere('type', 'doc') ? 'danger' : 'secondary' }}" title="Toggle document attention">
                <i class="bi bi-file-earmark-text-fill fs-4"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Delete client?');">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger">Delete client</button>
        </form>
    </div>
</div>

<div class="card mb-3 {{ $client->is_active ? 'border-success' : 'border-secondary' }}">
    <div class="card-body">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Activated</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="activeYes" value="1" {{ $client->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="activeYes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="activeNo" value="0" {{ ! $client->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="activeNo">No</label>
                        </div>
                    </div>
                    @error('is_active')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email*</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $client->email) }}" required>
                    @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">First name*</label>
                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $client->clientProfile?->first_name) }}" required>
                    @error('first_name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last name*</label>
                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $client->clientProfile?->last_name) }}" required>
                    @error('last_name')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $client->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address 1</label>
                    <input type="text" name="address1" class="form-control" value="{{ old('address1', $client->address1) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address 2</label>
                    <input type="text" name="address2" class="form-control" value="{{ old('address2', $client->address2) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Set pwd</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                    @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Headline</label>
                    <input type="text" name="headline" class="form-control" value="{{ old('headline', $client->headline) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $client->notes) }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mb-3">
    <a class="text-decoration-none" data-bs-toggle="collapse" href="#letterCollapse" role="button" aria-expanded="false">User's letter</a>
    <div class="collapse mt-2" id="letterCollapse">
        <div class="card card-body">
            @if($client->clientProfile?->letter)
                {!! nl2br(e($client->clientProfile->letter)) !!}
            @else
                <span class="text-muted">No letter provided.</span>
            @endif
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Related Cases</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Postal code</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Headline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                        <tr class="{{ $case->status === 'progress' ? 'table-success' : 'table-secondary' }}" role="button" onclick="window.location='{{ route('cases.edit', $case) }}'">
                            <td>{{ $case->id }}</td>
                            <td>{{ $case->postal_code }}</td>
                            <td>{{ ucfirst($case->status) }}</td>
                            <td>{{ optional($case->deadline)->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $case->headline ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No cases found for this client.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $cases->links('pagination::bootstrap-5') }}
    </div>
</div>

<div class="card">
    <div class="card-body">
        <a class="text-decoration-none" data-bs-toggle="collapse" href="#logsCollapse" role="button" aria-expanded="false">Logs</a>
        <div class="collapse mt-3" id="logsCollapse">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ optional($log->created_at)->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->description ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">No log entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
