@extends('layouts.app')

@section('content')
    <!-- Heading row with client identifiers, registration date, delete control, and attention toggles. -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h4 mb-0">Client #{{ $client->id }}</h1>
            <span class="text-muted">Registered {{ optional($client->created_at)->format('d/m/Y') }}</span>
            <form method="POST" action="{{ route('clients.destroy', $client) }}" id="delete-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-danger p-0">Delete client</button>
            </form>
        </div>
        <div class="d-flex align-items-center gap-3">
            @php
                $callAttention = $client->attentions->firstWhere('type', 'call');
                $docAttention = $client->attentions->firstWhere('type', 'doc');
            @endphp
            <!-- Attention icons toggle call/doc flags for this client. -->
            <form method="POST" action="{{ route('clients.attention', [$client, 'call']) }}">
                @csrf
                <button type="submit" class="btn btn-link p-0 text-{{ $callAttention ? 'danger' : 'secondary' }}" title="Toggle call attention">
                    <i class="bi bi-telephone fs-4"></i>
                </button>
            </form>
            <form method="POST" action="{{ route('clients.attention', [$client, 'doc']) }}">
                @csrf
                <button type="submit" class="btn btn-link p-0 text-{{ $docAttention ? 'danger' : 'secondary' }}" title="Toggle document attention">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Status alerts for validation errors or successful saves. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <!-- Warning to prevent deletion when the client participates in cases. -->
    @if (($relatedCases->total() ?? $relatedCases->count()) > 0)
        <div class="alert alert-warning">Client cannot be removed while assigned to cases.</div>
    @endif

    <!-- Client form split into two columns for activation, contact, and notes. -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('clients.update', $client) }}" id="client-form" novalidate>
                @csrf
                <div class="row g-3 align-items-start">
                    <div class="col-12 col-lg-6">
                        <div class="p-3 rounded" id="activation-block">
                            <!-- Activation radio set updates row background dynamically. -->
                            <label class="form-label d-block">Activated *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_active" id="activeYes" value="1" {{ $client->is_active ? 'checked' : '' }} required>
                                <label class="form-check-label" for="activeYes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_active" id="activeNo" value="0" {{ ! $client->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="activeNo">No</label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <!-- First and last name fields map to the client profile. -->
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="{{ optional($client->clientProfile)->first_name }}">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="{{ optional($client->clientProfile)->last_name }}">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ $client->email }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="{{ $client->phone }}">
                            </div>
                            <div class="mb-3">
                                <label for="address1" class="form-label">Address 1</label>
                                <input type="text" class="form-control" id="address1" name="address1" value="{{ $client->address1 }}">
                            </div>
                            <div class="mb-3">
                                <label for="address2" class="form-label">Address 2</label>
                                <input type="text" class="form-control" id="address2" name="address2" value="{{ $client->address2 }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <!-- Secondary details for the client card without credential changes. -->
                        <div class="mb-3">
                            <label for="headline" class="form-label">Headline</label>
                            <input type="text" class="form-control" id="headline" name="headline" value="{{ $client->headline }}">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="6">{{ $client->notes }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    <button type="submit" class="btn btn-primary px-4" id="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User's letter spoiler to toggle the stored letter text. -->
    <div class="mb-4">
        <a href="#" id="toggle-letter" class="text-decoration-none">User's letter</a>
        <div id="letter-block" class="mt-3 d-none">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{ optional($client->clientProfile)->letter ?? 'No letter saved for this client.' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Related cases table mirrors the case manager layout for consistency. -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                    @forelse($relatedCases as $case)
                        @php
                            $rowClass = $case->status === 'progress' ? 'table-success' : 'table-secondary';
                            $attentionTypes = ['attention', 'mail', 'doc'];
                            $deadlineClass = $case->deadline && $case->deadline->isPast() ? 'text-danger fw-bold' : '';
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('casemanager.edit', $case) }}">
                            <td><a href="{{ route('casemanager.edit', $case) }}" class="text-decoration-none">{{ $case->id }}</a></td>
                            <td>{{ $case->postal_code }}</td>
                            <td>
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
                            <td colspan="7" class="text-center text-muted py-4">No related cases found for this client.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate related cases independently to keep the table concise. -->
            {{ $relatedCases->links() }}
        </div>
    </div>

    <!-- Activity log table hidden by default and toggled via spoiler link. -->
    <div class="card shadow-sm">
        <div class="card-body">
            <a href="#" id="toggle-logs" class="text-decoration-none">Show logs</a>
            <div id="logs-panel" class="mt-3 d-none">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">User</th>
                            <th scope="col">Action</th>
                            <th scope="col">Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/y H:i') }}</td>
                                <td>
                                    @if($log->user_id)
                                        <a href="/users/{{ $log->user_id }}/edit" class="text-decoration-none">{{ $log->user_name ?? 'User' }}</a>
                                    @else
                                        System
                                    @endif
                                </td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->details ?? 'No details provided' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No activity recorded for this client yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Confirm deletion to avoid accidental removals.
        document.getElementById('delete-form')?.addEventListener('submit', (event) => {
            if (!confirm('Are you sure you want to delete this client?')) {
                event.preventDefault();
            }
        });

        // Apply clickable rows for related cases navigation.
        document.querySelectorAll('.table-row-link').forEach((row) => {
            row.addEventListener('click', () => {
                window.location.href = row.dataset.href;
            });
        });

        // Toggle the letter spoiler block visibility.
        const letterToggle = document.getElementById('toggle-letter');
        const letterBlock = document.getElementById('letter-block');
        if (letterToggle && letterBlock) {
            letterToggle.addEventListener('click', (event) => {
                event.preventDefault();
                letterBlock.classList.toggle('d-none');
                letterToggle.textContent = letterBlock.classList.contains('d-none') ? "User's letter" : 'Hide letter';
            });
        }

        // Toggle the activity log visibility.
        const logToggle = document.getElementById('toggle-logs');
        const logPanel = document.getElementById('logs-panel');
        if (logToggle && logPanel) {
            logToggle.addEventListener('click', (event) => {
                event.preventDefault();
                logPanel.classList.toggle('d-none');
                logToggle.textContent = logPanel.classList.contains('d-none') ? 'Show logs' : 'Hide logs';
            });
        }

        // Update activation block background based on selected radio.
        const activationBlock = document.getElementById('activation-block');
        const activeYes = document.getElementById('activeYes');
        const activeNo = document.getElementById('activeNo');
        const updateActivationColor = () => {
            if (!activationBlock) return;
            activationBlock.className = 'p-3 rounded ' + (activeYes?.checked ? 'bg-success-subtle' : 'bg-secondary-subtle');
        };
        updateActivationColor();
        activeYes?.addEventListener('change', updateActivationColor);
        activeNo?.addEventListener('change', updateActivationColor);

        // Highlight the save button when any form input changes.
        document.getElementById('client-form')?.addEventListener('input', () => {
            const saveBtn = document.getElementById('save-btn');
            if (saveBtn) {
                saveBtn.classList.remove('btn-primary');
                saveBtn.classList.add('btn-warning');
                saveBtn.textContent = 'Save changes';
            }
        });
    </script>
@endpush
