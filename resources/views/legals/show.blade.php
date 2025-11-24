@extends('layouts.app')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3 align-items-lg-center">
    <div>
        <div class="fw-bold">Legal ID {{ $legal->id }}</div>
        <div class="text-muted">Registered {{ optional($legal->created_at)->format('d/m/Y H:i') }}</div>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('legals.password', $legal) }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Generate password</button>
        </form>
    </div>
</div>

@if($generated)
    <div class="alert alert-info">
        <div class="fw-bold mb-2">New credentials</div>
        <div class="small">Registration link: <a href="{{ $generated['registration_url'] }}" target="_blank" rel="noopener">{{ $generated['registration_url'] }}</a></div>
        <div class="small">Login: <span class="fw-semibold">{{ $generated['login'] }}</span></div>
        <div class="small">Password: <span class="fw-semibold">{{ $generated['password'] }}</span></div>
    </div>
@endif

<div class="card mb-3 {{ $legal->is_active ? 'border-success' : 'border-secondary' }}">
    <div class="card-body">
        <form method="POST" action="{{ route('legals.update', $legal) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Active</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="legalActiveYes" value="1" {{ $legal->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="legalActiveYes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active" id="legalActiveNo" value="0" {{ ! $legal->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="legalActiveNo">No</label>
                        </div>
                    </div>
                    @error('is_active')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company</label>
                    <input type="text" name="company" class="form-control" value="{{ old('company', $legal->legalProfile?->company) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Website</label>
                    <input type="text" name="website" class="form-control" value="{{ old('website', $legal->legalProfile?->website) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Headline</label>
                    <input type="text" name="headline" class="form-control" value="{{ old('headline', $legal->headline) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes', $legal->notes) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Locality</label>
                    <input type="text" name="locality" class="form-control" value="{{ old('locality', $legal->legalProfile?->locality) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address 1</label>
                    <input type="text" name="address1" class="form-control" value="{{ old('address1', $legal->address1) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address 2</label>
                    <input type="text" name="address2" class="form-control" value="{{ old('address2', $legal->address2) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Person*</label>
                    <input type="text" name="person" class="form-control" value="{{ old('person', $legal->legalProfile?->person) }}" required>
                    @error('person')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mobile</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $legal->phone) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email*</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $legal->email) }}" required>
                    @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Office</label>
                    <input type="text" name="office" class="form-control" value="{{ old('office', $legal->legalProfile?->office) }}">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
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
                        <tr><td colspan="5" class="text-center">No cases found for this legal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $cases->links('pagination::bootstrap-5') }}
    </div>
</div>

<div class="card">
    <div class="card-body">
        <button class="btn btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#legalLogs" aria-expanded="false">Show logs</button>
        <div class="collapse mt-3" id="legalLogs">
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
