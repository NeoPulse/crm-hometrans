@extends('layouts.app')

@section('content')
    <!-- Header row with legal identifier, registration date, and password tools. -->
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h4 mb-0">Legal #{{ $legal->id }}</h1>
            <span class="text-muted">Registered {{ optional($legal->created_at)->format('d/m/Y') }}</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Trigger to generate a new password for the solicitor. -->
            <form method="POST" action="{{ route('legals.password', $legal) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary">Generate password</button>
            </form>
        </div>
    </div>

    <!-- Feedback blocks for validation errors, success messages, and password generation output. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($generatedPassword)
        <div class="alert alert-info">
            <p class="mb-1">A new password has been generated for this legal. Share the credentials below:</p>
            <ul class="mb-0">
                <li><strong>Login URL:</strong> <a href="{{ route('login') }}" class="text-decoration-none">{{ route('login') }}</a></li>
                <li><strong>Email:</strong> {{ $legal->email }}</li>
                <li><strong>Password:</strong> <code>{{ $generatedPassword }}</code></li>
            </ul>
        </div>
    @endif

    <!-- Two-column form capturing activation state and solicitor details. -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('legals.update', $legal) }}" id="legal-form" novalidate enctype="multipart/form-data">
                @csrf
                <div class="row g-lg-5 align-items-start">
                    <div class="col-12 col-lg-6">
                        {{-- Avatar preview and upload control for the legal profile. --}}
                        @php
                            // Resolve a reliable avatar path, falling back to a placeholder when none exists.
                            $avatarFilename = $legal->avatar_path ? basename($legal->avatar_path) : null;
                            $avatarOnDisk = $avatarFilename && \Illuminate\Support\Facades\Storage::disk('public')->exists('avatars/' . $avatarFilename);
                            $avatarPath = $avatarOnDisk
                                ? asset('storage/avatars/' . $avatarFilename)
                                : asset('images/avatar-placeholder.svg');
                        @endphp
                        <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded">
                            <img src="{{ $avatarPath }}" alt="Legal avatar" class="rounded-circle avatar-50">
                            <div class="flex-grow-1">
                                <label for="avatar" class="form-label">Avatar</label>
                                <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">Upload an image to represent this solicitor. It will be resized to a compact JPEG.</div>
                                @enderror
                            </div>
                        </div>

                        <div class="p-3 rounded" id="activation-block">
                            <!-- Activation toggle with color feedback mirroring the clients section. -->
                            <label class="form-label d-block">Activated *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_active" id="activeYes" value="1" {{ $legal->is_active ? 'checked' : '' }} required>
                                <label class="form-check-label" for="activeYes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_active" id="activeNo" value="0" {{ ! $legal->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="activeNo">No</label>
                            </div>
                        </div>
                        <div class="mt-3">
                            <!-- Company and web presence details. -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ $legal->email }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="person" class="form-label">Person *</label>
                                <input type="text" class="form-control" id="person" name="person" value="{{ optional($legal->legalProfile)->person ?? $legal->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="company" class="form-label">Company</label>
                                <input type="text" class="form-control" id="company" name="company" value="{{ optional($legal->legalProfile)->company }}">
                            </div>
                            <div class="mb-3">
                                <label for="website" class="form-label">Website</label>
                                <input type="text" class="form-control" id="website" name="website" value="{{ optional($legal->legalProfile)->website }}">
                            </div>
                            <div class="mb-3">
                                <label for="locality" class="form-label">Locality</label>
                                <input type="text" class="form-control" id="locality" name="locality" value="{{ optional($legal->legalProfile)->locality }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <!-- Primary contact and communication fields. -->
                        <div class="mb-3">
                            <label for="address1" class="form-label">Address 1</label>
                            <input type="text" class="form-control" id="address1" name="address1" value="{{ $legal->address1 }}">
                        </div>
                        <div class="mb-3">
                            <label for="address2" class="form-label">Address 2</label>
                            <input type="text" class="form-control" id="address2" name="address2" value="{{ $legal->address2 }}">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ $legal->phone }}">
                        </div>
                        <div class="mb-3">
                            <label for="office" class="form-label">Office</label>
                            <input type="text" class="form-control" id="office" name="office" value="{{ optional($legal->legalProfile)->office }}">
                        </div>
                        <div class="mb-3">
                            <label for="headline" class="form-label">Headline</label>
                            <input type="text" class="form-control" id="headline" name="headline" value="{{ $legal->headline }}">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="8">{{ $legal->notes }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    <button type="submit" class="btn btn-primary px-4" id="save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Related cases table mirroring the layout from the Cases section. -->
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
                            // Set row colors and deadline emphasis in line with the cases module.
                            $rowClass = $case->status === 'progress' ? 'table-success' : 'table-secondary';
                            $attentionTypes = ['attention', 'mail', 'doc'];
                            $deadlineClass = $case->deadline && $case->deadline->isPast() ? 'text-danger fw-bold' : '';
                        @endphp
                        <tr class="{{ $rowClass }} table-row-link" data-href="{{ route('casemanager.edit', $case) }}">
                            <td><a href="{{ route('casemanager.edit', $case) }}" class="text-decoration-none">{{ $case->id }}</a></td>
                            <td>{{ $case->postal_code }}</td>
                            <td>
                                <!-- Attention icons highlight when corresponding records are present. -->
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
                            <td colspan="7" class="text-center text-muted py-4">No related cases found for this legal.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginate the related cases independently for clarity. -->
            {{ $relatedCases->links() }}
        </div>
    </div>

    <!-- Activity log toggle mirroring the client card behavior. -->
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
                                <td colspan="4" class="text-center text-muted">No activity recorded for this legal yet.</td>
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
        // Update activation block background to mirror the selected status.
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

        // Flag the save button when form values change.
        document.getElementById('legal-form')?.addEventListener('input', () => {
            const saveBtn = document.getElementById('save-btn');
            if (saveBtn) {
                saveBtn.classList.remove('btn-primary');
                saveBtn.classList.add('btn-warning');
                saveBtn.textContent = 'Save changes';
            }
        });

        // Enable clickable rows for quick navigation to case details.
        document.querySelectorAll('.table-row-link').forEach((row) => {
            row.addEventListener('click', () => {
                window.location.href = row.dataset.href;
            });
        });

        // Toggle visibility of the activity log panel.
        const logToggle = document.getElementById('toggle-logs');
        const logPanel = document.getElementById('logs-panel');
        if (logToggle && logPanel) {
            logToggle.addEventListener('click', (event) => {
                event.preventDefault();
                logPanel.classList.toggle('d-none');
                logToggle.textContent = logPanel.classList.contains('d-none') ? 'Show logs' : 'Hide logs';
            });
        }
    </script>
@endpush
