@extends('layouts.app')

@section('content')
    <!-- Page heading with case reference and navigation placeholder. -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Case #{{ $case->id }} {{ $case->postal_code }}</h1>
            <p class="text-muted mb-0">Review and maintain participants, status, and notifications.</p>
        </div>
        <button class="btn btn-outline-secondary" type="button">Show case</button>
    </div>

    <!-- Status and validation feedback for administrator actions. -->
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <!-- Participant assignment form covering both sell and buy sides. -->
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Participants</h2>
                    <form method="POST" action="{{ route('casemanager.participants', $case) }}" id="participants-form" novalidate>
                        @csrf
                        <div class="mb-3">
                            <h3 class="h6 text-uppercase text-muted">Sell-side</h3>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="sell_legal_display" class="form-label">Sell legal</label>
                            <input type="hidden" name="sell_legal_id" id="sell_legal_id" value="{{ $case->sell_legal_id }}">
                            <input type="text" class="form-control user-search" data-role="legal" data-target="sell-legal-results" data-hidden="sell_legal_id" id="sell_legal_display" value="{{ $case->sellLegal ? $case->sellLegal->name . ' (#' . $case->sellLegal->id . ')' : '' }}" placeholder="Type ID or name">
                            <div class="form-text">{{ $case->sellLegal ? $case->sellLegal->name : 'No sell legal assigned' }}</div>
                            <div class="list-group position-absolute w-100 z-1 shadow-sm" id="sell-legal-results"></div>
                        </div>
                        <div class="mb-4 position-relative">
                            <label for="sell_client_display" class="form-label">Sell client</label>
                            <input type="hidden" name="sell_client_id" id="sell_client_id" value="{{ $case->sell_client_id }}">
                            <input type="text" class="form-control user-search" data-role="client" data-target="sell-client-results" data-hidden="sell_client_id" id="sell_client_display" value="{{ $case->sellClient ? $case->sellClient->name . ' (#' . $case->sellClient->id . ')' : '' }}" placeholder="Type ID or name">
                            <div class="form-text">{{ $case->sellClient ? $case->sellClient->name : 'No sell client assigned' }}</div>
                            <div class="list-group position-absolute w-100 z-1 shadow-sm" id="sell-client-results"></div>
                        </div>
                        <div class="mb-3">
                            <h3 class="h6 text-uppercase text-muted">Buy-side</h3>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="buy_legal_display" class="form-label">Buy legal</label>
                            <input type="hidden" name="buy_legal_id" id="buy_legal_id" value="{{ $case->buy_legal_id }}">
                            <input type="text" class="form-control user-search" data-role="legal" data-target="buy-legal-results" data-hidden="buy_legal_id" id="buy_legal_display" value="{{ $case->buyLegal ? $case->buyLegal->name . ' (#' . $case->buyLegal->id . ')' : '' }}" placeholder="Type ID or name">
                            <div class="form-text">{{ $case->buyLegal ? $case->buyLegal->name : 'No buy legal assigned' }}</div>
                            <div class="list-group position-absolute w-100 z-1 shadow-sm" id="buy-legal-results"></div>
                        </div>
                        <div class="mb-4 position-relative">
                            <label for="buy_client_display" class="form-label">Buy client</label>
                            <input type="hidden" name="buy_client_id" id="buy_client_id" value="{{ $case->buy_client_id }}">
                            <input type="text" class="form-control user-search" data-role="client" data-target="buy-client-results" data-hidden="buy_client_id" id="buy_client_display" value="{{ $case->buyClient ? $case->buyClient->name . ' (#' . $case->buyClient->id . ')' : '' }}" placeholder="Type ID or name">
                            <div class="form-text">{{ $case->buyClient ? $case->buyClient->name : 'No buy client assigned' }}</div>
                            <div class="list-group position-absolute w-100 z-1 shadow-sm" id="buy-client-results"></div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="participants-save">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <!-- Attention toggle tools and main case details form. -->
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-1">Attentions</h2>
                        <p class="mb-0 text-muted">Toggle alerts for this case.</p>
                    </div>
                    <div class="d-flex gap-3">
                        @foreach(['attention' => 'exclamation-circle', 'mail' => 'envelope', 'doc' => 'file-earmark-text'] as $type => $icon)
                            @php $active = $case->attentions->firstWhere('type', $type); @endphp
                            <form method="POST" action="{{ route('casemanager.attention', [$case, $type]) }}">
                                @csrf
                                <button type="submit" class="btn btn-link p-0 text-{{ $active ? 'danger' : 'secondary' }}" title="Toggle {{ $type }}">
                                    <i class="bi bi-{{ $icon }} fs-4"></i>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Case details</h2>
                    <form method="POST" action="{{ route('casemanager.details', $case) }}" id="details-form" novalidate>
                        @csrf
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="postal_code_detail" class="form-label">P/Code *</label>
                                <input type="text" class="form-control" id="postal_code_detail" name="postal_code" value="{{ $case->postal_code }}" required pattern="^\\S+$">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="property" class="form-label">Property</label>
                                <input type="text" class="form-control" id="property" name="property" value="{{ $case->property }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    @foreach(['new' => 'New', 'progress' => 'Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                                        <option value="{{ $key }}" {{ $case->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="deadline" class="form-label">Deadline</label>
                                <input type="date" class="form-control" id="deadline" name="deadline" value="{{ optional($case->deadline)->toDateString() }}">
                            </div>
                            <div class="col-12">
                                <label for="headline" class="form-label">Headline</label>
                                <input type="text" class="form-control" id="headline" name="headline" value="{{ $case->headline }}">
                            </div>
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4">{{ $case->notes }}</textarea>
                            </div>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary" id="details-save">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Public link placeholder and activity log viewer. -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                <div>
                    <h2 class="h6 mb-1">Public link</h2>
                    <p class="text-muted mb-0">Shareable placeholder URL for progress-stage guests.</p>
                </div>
                <a href="https://domain.com/#" target="_blank" rel="noopener" class="btn btn-outline-primary">https://domain.com/#</a>
            </div>
            <div>
                <a href="#" id="toggle-logs" class="text-decoration-none">Show case logs</a>
            </div>
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
                                    @if($log->user_id && $log->user_name)
                                        <a href="/users/{{ $log->user_id }}/edit" class="text-decoration-none">{{ $log->user_name }}</a>
                                    @elseif($log->user_id)
                                        <span>{{ $log->user_id }}</span>
                                    @else
                                        <span>System</span>
                                    @endif
                                </td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->details ?? 'No details provided' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No activity recorded for this case yet.</td>
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
        // Shared helper to mark save buttons when form data changes.
        const markDirty = (formId, buttonId) => {
            const form = document.getElementById(formId);
            const button = document.getElementById(buttonId);
            if (!form || !button) return;

            form.addEventListener('input', () => {
                button.classList.add('btn-warning');
                button.classList.remove('btn-primary');
                button.textContent = 'Save changes';
            });
        };

        // Initialize dirty tracking for both forms.
        markDirty('participants-form', 'participants-save');
        markDirty('details-form', 'details-save');

        // Toggle the visibility of the logs panel via spoiler link.
        const toggleLogs = document.getElementById('toggle-logs');
        const logsPanel = document.getElementById('logs-panel');
        if (toggleLogs && logsPanel) {
            toggleLogs.addEventListener('click', (event) => {
                event.preventDefault();
                logsPanel.classList.toggle('d-none');
                toggleLogs.textContent = logsPanel.classList.contains('d-none') ? 'Show case logs' : 'Hide case logs';
            });
        }

        // Attach live user search to participant inputs with dropdown selection.
        document.querySelectorAll('.user-search').forEach((input) => {
            const resultsId = input.dataset.target;
            const hiddenId = input.dataset.hidden;
            const resultsContainer = document.getElementById(resultsId);

            input.addEventListener('input', () => {
                const query = input.value.trim();
                if (hiddenId) {
                    const hiddenField = document.getElementById(hiddenId);
                    if (hiddenField) hiddenField.value = '';
                }

                if (!query) {
                    resultsContainer.innerHTML = '';
                    return;
                }

                fetch(`{{ route('casemanager.usersearch') }}?q=${encodeURIComponent(query)}&role=${input.dataset.role}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsContainer.innerHTML = '';
                        data.forEach(user => {
                            const option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'list-group-item list-group-item-action';
                            option.textContent = `${user.name} (#${user.id}) - ${user.role}`;
                            option.addEventListener('click', () => {
                                input.value = `${user.name} (#${user.id})`;
                                const hiddenField = hiddenId ? document.getElementById(hiddenId) : null;
                                if (hiddenField) hiddenField.value = user.id;
                                resultsContainer.innerHTML = '';
                            });
                            resultsContainer.appendChild(option);
                        });
                    })
                    .catch(() => {
                        resultsContainer.innerHTML = '<div class="list-group-item text-danger">Search failed</div>';
                    });
            });
        });

        // Apply contextual styling to the status selector to reflect the chosen state.
        const statusSelect = document.getElementById('status');
        const statusClasses = {
            new: 'text-primary border-primary',
            progress: 'text-warning border-warning',
            completed: 'text-success border-success',
            cancelled: 'text-danger border-danger',
        };

        const applyStatusColor = () => {
            if (!statusSelect) return;
            statusSelect.className = 'form-select';
            const statusClass = statusClasses[statusSelect.value];
            if (statusClass) {
                statusClass.split(' ').forEach(cls => statusSelect.classList.add(cls));
            }
        };

        applyStatusColor();
        statusSelect?.addEventListener('change', applyStatusColor);
    </script>
@endpush
