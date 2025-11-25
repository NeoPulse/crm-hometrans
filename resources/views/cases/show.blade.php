@extends('layouts.app')

@section('content')
    <!-- Case overview header with participants and key dates. -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="fw-bold text-primary fs-5">HomeTrans CRM</div>
                    <div>
                        <h1 class="h5 mb-1">Case {{ $case->postal_code }}</h1>
                        <p class="mb-0 text-muted">Deadline {{ optional($case->deadline)->format('d/M') ?? 'Not set' }}</p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    @foreach ($participants as $participant)
                        @php
                            $user = $participant['user'];
                            $avatar = $user && $user->avatar_path ? asset($user->avatar_path) : asset('images/avatar-placeholder.svg');
                            $tooltipLines = [];
                            if ($user) {
                                $tooltipLines[] = e($user->display_name ?? $user->name ?? 'User');
                                if ($participant['office']) {
                                    $tooltipLines[] = 'Office: ' . e($participant['office']);
                                }
                                if ($user->phone) {
                                    $tooltipLines[] = '<a href="tel:' . e($user->phone) . '">' . e($user->phone) . '</a>';
                                }
                                if ($user->email) {
                                    $tooltipLines[] = '<a href="mailto:' . e($user->email) . '">' . e($user->email) . '</a>';
                                }
                            }
                        @endphp
                        <div class="d-flex align-items-center gap-2 team-member" data-bs-toggle="tooltip"
                             data-bs-html="true" title="{!! $user ? implode('<br>', $tooltipLines) : 'Not assigned' !!}">
                            <div class="text-muted small text-uppercase">{{ $participant['label'] }}</div>
                            <img src="{{ $avatar }}" alt="Avatar" class="rounded-circle avatar-50">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <!-- Stage list column showing all stages with progress. -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Stages:</h2>
                @if($isAdmin)
                    <div class="text-muted small">Admin controls enabled</div>
                @endif
            </div>
            <div id="stage-list" class="d-flex flex-column gap-2"></div>
            <div id="no-stages-alert" class="alert alert-info d-none">No stages have been added yet.</div>

            @if($isAdmin)
                <form id="stage-add-form" class="d-flex gap-2 mt-3" novalidate>
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Stage name" required>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            @endif
        </div>
        <div class="col-lg-8">
            <!-- Task panel for the currently selected stage. -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h2 class="h5 mb-1" id="stage-title">1. Client Onboarding &amp; File Opening</h2>
                    <p class="text-muted mb-0" id="stage-subtitle">Purpose: Confirm who each party is and make both legal files active.</p>
                </div>
            </div>
            <div id="tasks-container" class="card shadow-sm">
                <div class="card-body">
                    <div id="tasks-content">No stage selected.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Bootstrap tooltip initialisation for participant hover cards.
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        // Stage and task data provided by the controller for dynamic rendering.
        let stagesData = @json($stages);
        let activeStageId = stagesData.length ? stagesData[0].id : null;
        const isAdmin = @json($isAdmin);
        const stageListEl = document.getElementById('stage-list');
        const noStagesAlert = document.getElementById('no-stages-alert');
        const tasksContent = document.getElementById('tasks-content');
        const stageTitle = document.getElementById('stage-title');
        const csrfToken = '{{ csrf_token() }}';

        // Encode user-controlled text to prevent HTML injection in templates.
        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        // Helper to display toast-like feedback after saving.
        const flashRow = (element) => {
            element.classList.add('bg-success-subtle');
            setTimeout(() => element.classList.remove('bg-success-subtle'), 800);
        };

        // Provide consistent iconography and colour coding per status.
        const statusMeta = {
            new: { icon: 'bi-plus-circle', classes: 'text-primary' },
            progress: { icon: 'bi-arrow-repeat', classes: 'text-warning' },
            done: { icon: 'bi-check-circle', classes: 'text-success' },
        };

        // Render the list of stages on the left column.
        function renderStages() {
            stageListEl.innerHTML = '';
            if (!stagesData.length) {
                noStagesAlert.classList.remove('d-none');
                return;
            }
            noStagesAlert.classList.add('d-none');

            stagesData.forEach((stage, index) => {
                const card = document.createElement('div');
                card.className = `card stage-card shadow-sm ${stage.id === activeStageId ? 'border-primary' : ''}`;
                card.dataset.stageId = stage.id;

                card.innerHTML = `
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">${index + 1}. ${escapeHtml(stage.name)}</div>
                            <div class="d-flex align-items-center gap-2">
                                ${stage.is_new ? '<span class="badge bg-danger">NEW</span>' : ''}
                                ${isAdmin ? '<button type="button" class="btn btn-sm btn-link text-secondary stage-edit" title="Rename stage"><i class="bi bi-pencil-square"></i></button>' : ''}
                                ${isAdmin ? '<button type="button" class="btn btn-sm btn-link text-danger stage-delete" title="Delete stage"><i class="bi bi-trash"></i></button>' : ''}
                            </div>
                        </div>
                        <div class="progress mt-2" role="progressbar" aria-label="Stage progress">
                            <div class="progress-bar" style="width: ${stage.progress}%">${stage.progress}%</div>
                        </div>
                    </div>
                `;

                card.addEventListener('click', () => {
                    activeStageId = stage.id;
                    renderStages();
                    renderTasks();
                });

                // Attach edit/delete handlers without triggering stage selection.
                card.querySelectorAll('.stage-edit').forEach(btn => btn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    promptStageRename(stage.id, stage.name);
                }));
                card.querySelectorAll('.stage-delete').forEach(btn => btn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    confirmStageDeletion(stage.id);
                }));

                stageListEl.appendChild(card);
            });
        }

        // Render tasks for the currently active stage.
        function renderTasks() {
            const stage = stagesData.find(item => item.id === activeStageId);
            if (!stage) {
                tasksContent.innerHTML = '<div class="alert alert-info mb-0">No stages available to show.</div>';
                stageTitle.textContent = 'Stages';
                return;
            }
            const stageIndex = stagesData.findIndex(item => item.id === stage.id);
            stageTitle.textContent = `${stageIndex + 1}. ${stage.name}`;

            const sellerTasks = stage.tasks.filter(task => task.side === 'seller');
            const buyerTasks = stage.tasks.filter(task => task.side === 'buyer');

            tasksContent.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="h6 mb-0">Seller side</h3>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task" data-side="seller" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(sellerTasks)}
                <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                    <h3 class="h6 mb-0">Buyer side</h3>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task" data-side="buyer" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(buyerTasks)}
            `;

            // Bind add task links for admin actions.
            document.querySelectorAll('.add-task').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    createTask(stage.id, link.dataset.side);
                });
            });

            // Bind inline editing for task names and deadlines.
            document.querySelectorAll('.task-name-input').forEach(input => {
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        updateTask(input.dataset.taskId, {name: input.value}, input.closest('.task-row'));
                    }
                });
            });
            document.querySelectorAll('.task-deadline-input').forEach(input => {
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        updateTask(input.dataset.taskId, {deadline: input.value || null}, input.closest('.task-row'));
                    }
                });
            });

            // Bind status dropdown actions.
            document.querySelectorAll('.status-option').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    const taskId = item.dataset.taskId;
                    const status = item.dataset.status;
                    updateTask(taskId, {status: status}, item.closest('.task-row'));
                });
            });

            // Bind delete options for tasks.
            document.querySelectorAll('.delete-task').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    const taskId = item.dataset.taskId;
                    if (confirm('Delete this task?')) {
                        deleteTask(taskId);
                    }
                });
            });
        }

        // Build HTML for a list of tasks per side.
        function renderTaskList(tasks) {
            if (!tasks.length) {
                return '<div class="alert alert-light border">No tasks for this side yet.</div>';
            }

            return tasks.map((task, index) => {
                const meta = statusMeta[task.status];
                const statusIcon = `<i class="bi ${meta.icon} ${meta.classes}"></i>`;
                const deadlineClass = task.overdue ? 'bg-danger-subtle text-danger' : 'bg-light';

                return `
                    <div class="d-flex align-items-center gap-3 border rounded p-2 mb-2 task-row">
                        <div class="fw-semibold text-muted" style="min-width: 24px;">${index + 1}.</div>
                        <div class="flex-grow-1 text-truncate">
                            ${isAdmin ? `<input type="text" class="form-control form-control-sm border-0 bg-transparent px-0 task-name-input" value="${escapeHtml(task.name)}" data-task-id="${task.id}">` : `<span title="${escapeHtml(task.name)}" class="text-truncate d-inline-block" style="max-width: 320px;">${escapeHtml(task.name)}</span>`}
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${task.is_new ? '<span class="badge bg-danger">new</span>' : ''}
                            <div class="badge ${deadlineClass} text-dark p-2 rounded task-deadline">
                                ${isAdmin ? `<input type="date" class="form-control form-control-sm border-0 bg-transparent task-deadline-input" value="${task.deadline ?? ''}" data-task-id="${task.id}">` : task.deadline_display}
                            </div>
                            ${isAdmin ? dropdownStatus(task.id, statusIcon) : statusIcon}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Render a dropdown with status options and delete action for admins.
        function dropdownStatus(taskId, iconHtml) {
            return `
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        ${iconHtml}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item status-option" href="#" data-task-id="${taskId}" data-status="new">Mark as new</a></li>
                        <li><a class="dropdown-item status-option" href="#" data-task-id="${taskId}" data-status="progress">Mark in progress</a></li>
                        <li><a class="dropdown-item status-option" href="#" data-task-id="${taskId}" data-status="done">Mark as done</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger delete-task" href="#" data-task-id="${taskId}">Delete task</a></li>
                    </ul>
                </div>
            `;
        }

        // Trigger a stage rename workflow via prompt.
        function promptStageRename(stageId, currentName) {
            const newName = prompt('Enter a new stage name', currentName);
            if (!newName) {
                return;
            }
            fetch(`{{ url('/stages') }}/${stageId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({name: newName})
            }).then(handleResponse);
        }

        // Confirm and execute stage deletion.
        function confirmStageDeletion(stageId) {
            if (!confirm('Delete this stage and all tasks?')) {
                return;
            }
            fetch(`{{ url('/stages') }}/${stageId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            }).then(handleResponse);
        }

        // Create a new task for a side without reloading.
        function createTask(stageId, side) {
            fetch(`{{ url('/stages') }}/${stageId}/tasks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({side})
            }).then(handleResponse);
        }

        // Update a task field and refresh the UI.
        function updateTask(taskId, payload, rowElement) {
            fetch(`{{ url('/tasks') }}/${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload)
            }).then(response => handleResponse(response, rowElement));
        }

        // Delete a task and refresh state.
        function deleteTask(taskId) {
            fetch(`{{ url('/tasks') }}/${taskId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            }).then(handleResponse);
        }

        // Submit a new stage via AJAX.
        @if($isAdmin)
        document.getElementById('stage-add-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            fetch(`{{ url('/case') }}/{{ $case->id }}/stages`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData
            }).then(handleResponse);
        });
        @endif

        // Handle API responses, update state, and optionally flash rows.
        function handleResponse(response, rowElement = null) {
            response.json().then(data => {
                if (!response.ok) {
                    alert(data.message || 'An error occurred.');
                    return;
                }
                if (Array.isArray(data.stages)) {
                    stagesData = data.stages;
                    if (!stagesData.find(stage => stage.id === activeStageId) && stagesData.length) {
                        activeStageId = stagesData[0].id;
                    }
                    renderStages();
                    renderTasks();
                }
                if (rowElement) {
                    flashRow(rowElement);
                }
            }).catch(() => alert('Unexpected server response.'));
        }

        // Initial render on page load.
        renderStages();
        renderTasks();
    </script>
@endpush
