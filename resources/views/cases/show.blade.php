@extends('layouts.app')

@section('content')

    <div class="row align-items-center">
        <div class="col-6 col-lg-3">
            <h1 class="fs-4 mb-0">
                @if($isAdmin)
                    <a href="{{ route('casemanager.edit', $case) }}" target="_blank" class="text-body">Case {{ $case->postal_code }}</a>
                @else
                    Case {{ $case->postal_code }}
                @endif
            </h1>
            <p class="m-0">Deadline {{ optional($case->deadline)->format('d/m') ?? '—' }}</p>
        </div>
        <div class="col-6 col-lg-2 text-end text-lg-center">
            <button id="case-chat-toggle" class="btn btn-primary shadow-sm px-4 py-2">
                    <i class="bi bi-chat-dots-fill me-2"></i>Case chat
                    <span id="case-chat-unread" class="badge bg-danger ms-2 d-none">0</span>
            </button>
        </div>
        <div class="col-lg-7 mt-3 mt-lg-0 text-lg-end">
            <div class="d-inline-flex gap-sm-3 border border-primary rounded bg-white">

                @foreach ($participants as $participant)
                    @php
                        /** @var \App\Models\User|null $user */
                        $user = $participant['user'] ?? null;

                        if (! $user || empty($user->display_name)) {
                            continue;
                        }

                        $label = $participant['label'] ?? '';

                        $avatarFilename = $user->avatar_path ? basename($user->avatar_path) : null;
                        $avatar = $avatarFilename
                            ? asset('storage/avatars/' . $avatarFilename)
                            : asset('images/avatar-placeholder.svg');

                        $popoverLines = [];

                        $popoverLines[] = e($user->display_name ?? $user->name ?? 'User');

                        if (!empty($participant['office'])) {
                            $popoverLines[] = 'Office: ' . e($participant['office']);
                        }

                        if ($user->phone) {
                            $popoverLines[] =
                                "<a href='tel:" . e($user->phone) . "'>" . e($user->phone) . "</a>";
                        }

                        if ($user->email) {
                            $popoverLines[] =
                                "<a href='mailto:" . e($user->email) . "'>" . e($user->email) . "</a>";
                        }

                        $popover = implode('<br>', $popoverLines);
                    @endphp

                    <div class="team-member d-md-flex align-items-center text-start justify-content-between px-3 py-1 flex-shrink-0 rounded"
                         tabindex="0"
                         data-bs-toggle="popover"
                         data-bs-trigger="focus"
                         data-bs-html="true"
                         data-bs-placement="bottom"
                         data-bs-content="{!! $popover !!}">

                        <img src="{{ $avatar }}" class="rounded-circle avatar-65 me-3" alt="Avatar">

                        <div class="caseShow__participantName">
                            {{ $label }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <hr class="pb-3">

    <div class="row g-5">
        <div class="col-12 col-lg-5">
            {{-- Stage list column showing all stages with progress. --}}
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-2">Stages:</h2>
            </div>
            <div id="stage-list" class="d-flex flex-column gap-2"></div>
            <div id="no-stages-alert" class="alert alert-info d-none">No stages have been added yet.</div>

            @if($isAdmin)
                <form id="stage-add-form" class="d-flex gap-2 mt-3" novalidate>
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Stage name" required>
                    <button type="submit" class="btn btn-success">Add</button>
                </form>
            @endif
        </div>
        <div class="col-lg-7 d-none d-lg-block" id="desktop-task-column">
            {{-- Task panel for the currently selected stage on desktop screens. --}}
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

    @include('cases.partials.chat', ['case' => $case, 'chatProfile' => $chatProfile, 'isAdmin' => $isAdmin])
@endsection

@push('scripts')
    <script>

        // Popover initialization
        document.addEventListener('DOMContentLoaded', function () {
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (el) {
                return new bootstrap.Popover(el, {
                    html: true,
                    sanitize: false,
                    trigger: 'focus'
                });
            });
        });

        // Bootstrap tooltip initialisation for participant hover cards.
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        // Stage and task data provided by the controller for dynamic rendering.
        let stagesData = @json($stages);
        let activeStageId = stagesData.length ? stagesData[0].id : null;
        const isAdmin = @json($isAdmin);
        const openedMobileStages = new Set();
        const stageListEl = document.getElementById('stage-list');
        const noStagesAlert = document.getElementById('no-stages-alert');
        const tasksContent = document.getElementById('tasks-content');
        const stageTitle = document.getElementById('stage-title');
        const csrfToken = '{{ csrf_token() }}';
        const viewportMatcher = window.matchMedia('(max-width: 991.98px)');

        // Encode user-controlled text to prevent HTML injection in templates.
        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        // Toggle a subtle spinner on rows while background saves are running.
        const toggleSaving = (rowElement, isSaving) => {
            if (!rowElement) {
                return;
            }
            if (isSaving) {
                rowElement.classList.add('saving');
            } else {
                rowElement.classList.remove('saving');
            }
        };

        // Helper to display toast-like feedback after saving.
        const flashRow = (element) => {
            element.classList.add('bg-success-subtle');
            setTimeout(() => element.classList.remove('bg-success-subtle'), 800);
        };

        // Provide consistent iconography and colour coding per status.
        const statusMeta = {
            new: { icon: 'bi-circle', classes: 'text-secondary' },
            progress: { icon: 'bi-clock-fill', classes: 'text-primary' },
            done: { icon: 'bi-check-circle-fill', classes: 'text-success' },
        };

        // Determine whether the current viewport should use the mobile layout.
        const isMobileLayout = () => viewportMatcher.matches;

        // Reset responsive-specific state when the viewport crosses the lg breakpoint.
        viewportMatcher.addEventListener('change', () => {
            openedMobileStages.clear();
            renderStages();
            renderTasks();
        });

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
                const isMobile = isMobileLayout();
                const isActiveDesktop = stage.id === activeStageId && !isMobile;
                const isExpandedMobile = openedMobileStages.has(stage.id);
                card.className = `card stage-card shadow-sm mb-2 border-2 ${isActiveDesktop ? 'border-primary' : ''}`;
                card.dataset.stageId = stage.id;

                card.innerHTML = `
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">${index + 1}. ${escapeHtml(stage.name)}</div>
                            <div class="d-flex align-items-center gap-1">
                                ${stage.is_new ? '<span class="badge bg-danger">NEW</span>' : ''}
                                ${isAdmin ? '<button type="button" class="btn btn-sm btn-link text-secondary stage-edit" title="Rename stage"><i class="bi bi-pencil-square"></i></button>' : ''}
                                ${isAdmin ? '<button type="button" class="btn btn-sm btn-link text-danger stage-delete" title="Delete stage"><i class="bi bi-trash"></i></button>' : ''}
                            </div>
                        </div>
                        <div class="progress mt-2" role="progressbar" aria-label="Stage progress" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: ${stage.progress}%">${stage.progress}%</div>
                        </div>
                        ${isMobile ? `<div class="text-muted small mt-2">${isExpandedMobile ? 'Tap to hide tasks' : 'Tap to show tasks'}</div>` : ''}
                    </div>
                `;

                // Inject the task list directly inside the card for mobile users.
                if (isMobile && isExpandedMobile) {
                    const mobileTasks = document.createElement('div');
                    mobileTasks.className = 'stage-tasks-mobile mt-3';
                    mobileTasks.innerHTML = buildTaskSection(stage);
                    card.querySelector('.card-body').appendChild(mobileTasks);
                }

                card.addEventListener('click', (event) => {
                    if (event.target.closest('.stage-edit') || event.target.closest('.stage-delete') || event.target.closest('.stage-tasks-mobile')) {
                        return;
                    }
                    if (isMobileLayout()) {
                        if (openedMobileStages.has(stage.id)) {
                            openedMobileStages.delete(stage.id);
                        } else {
                            openedMobileStages.add(stage.id);
                        }
                        renderStages();
                        return;
                    }
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
                    confirmStageDeletion(stage);
                }));

                stageListEl.appendChild(card);
            });

            // Bind task-related controls for each expanded mobile stage.
            if (isMobileLayout()) {
                stageListEl.querySelectorAll('.stage-tasks-mobile').forEach(container => {
                    const stageId = Number(container.closest('.stage-card')?.dataset.stageId);
                    const targetStage = stagesData.find(item => item.id === stageId);
                    if (targetStage) {
                        bindTaskInteractions(container, targetStage);
                    }
                });
            }
        }

        // Build the full task section markup shared by mobile and desktop.
        function buildTaskSection(stage) {
            const sellerTasks = stage.tasks.filter(task => task.side === 'seller');
            const buyerTasks = stage.tasks.filter(task => task.side === 'buyer');

            return `
                <div class="d-flex align-items-center mb-2 position-relative">
                    <div class="flex-grow-1 text-center">
                        <h3 class="h6 mb-0 fw-bold">Seller side</h3>
                    </div>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task position-absolute end-0 top-50 translate-middle-y" data-side="seller" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(sellerTasks)}
                <div class="d-flex align-items-center mt-4 mb-2 position-relative">
                    <div class="flex-grow-1 text-center">
                        <h3 class="h6 mb-0 fw-bold">Buyer side</h3>
                    </div>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task position-absolute end-0 top-50 translate-middle-y" data-side="buyer" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(buyerTasks)}
            `;
        }

        // Render tasks for the currently active stage.
        function renderTasks() {
            if (isMobileLayout()) {
                tasksContent.innerHTML = '<div class="alert alert-light mb-0">Tasks are displayed inside each stage on mobile.</div>';
                return;
            }
            const stage = stagesData.find(item => item.id === activeStageId);
            if (!stage) {
                tasksContent.innerHTML = '<div class="alert alert-info mb-0">No stages available to show.</div>';
                stageTitle.textContent = 'Stages';
                return;
            }
            const stageIndex = stagesData.findIndex(item => item.id === stage.id);
            stageTitle.textContent = `${stageIndex + 1}. ${stage.name}`;

            tasksContent.innerHTML = buildTaskSection(stage);

            // Bind task actions for the desktop task container.
            bindTaskInteractions(tasksContent, stage);
        }

        // Attach all task-related event handlers inside a given DOM scope.
        function bindTaskInteractions(scopeElement, stage) {
            if (!scopeElement) {
                return;
            }

            // Bind add task links for admin actions.
            scopeElement.querySelectorAll('.add-task').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const targetStageId = Number(link.dataset.stage || stage?.id);
                    createTask(targetStageId, link.dataset.side);
                });
            });

            // Bind deadline updates so selected dates are persisted.
            scopeElement.querySelectorAll('.task-deadline-input').forEach(input => {
                input.addEventListener('click', event => event.stopPropagation());
                input.addEventListener('change', () => {
                    updateTask(input.dataset.taskId, {deadline: input.value || null}, input.closest('.task-row'));
                });
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        updateTask(input.dataset.taskId, {deadline: input.value || null}, input.closest('.task-row'));
                    }
                });
            });

            // Bind status dropdown actions and name edits for administrators.
            scopeElement.querySelectorAll('.status-option').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const taskId = item.dataset.taskId;
                    const status = item.dataset.status;
                    updateTask(taskId, {status: status}, item.closest('.task-row'));
                });
            });
            scopeElement.querySelectorAll('.edit-task-name').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const taskId = item.dataset.taskId;
                    const existingName = decodeURIComponent(item.dataset.taskName || '');
                    const newName = prompt('Enter a new task title', existingName);
                    if (newName) {
                        updateTask(taskId, {name: newName}, item.closest('.task-row'));
                    }
                });
            });

            // Bind delete options for tasks.
            scopeElement.querySelectorAll('.delete-task').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
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
                const statusIcon = `<i class="bi ${meta.icon} ${meta.classes} fs-4"></i>`;
                const deadlineClass = task.overdue ? 'bg-danger-subtle text-danger' : 'bg-light';

                return `
                    <div class="d-flex align-items-center gap-1 border-bottom pb-1 mb-1 task-row position-relative">
                        <div class="fw-semibold text-muted" style="min-width: 24px;">${index + 1}.</div>
                        <div class="flex-grow-1 task__name">
                            <div class="text-truncate" title="${escapeHtml(task.name)}">${escapeHtml(task.name)}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-auto justify-content-end text-end">
                            ${task.is_new ? '<span class="badge bg-danger">new</span>' : ''}
                            <div class="badge ${deadlineClass} text-dark rounded task-deadline">
                                ${isAdmin ? `<input type="date" class="form-control form-control-sm border-0 bg-transparent task-deadline-input" value="${task.deadline ?? ''}" data-task-id="${task.id}">` : task.deadline_display}
                            </div>
                            ${isAdmin ? dropdownStatus(task.id, statusIcon, task.name) : statusIcon}
                        </div>
                        <div class="position-absolute saving-indicator">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Saving...</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Render a dropdown with status options and delete action for admins.
        function dropdownStatus(taskId, iconHtml, taskName) {
            return `
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle text-success" data-bs-toggle="dropdown" aria-expanded="false">
                        ${iconHtml}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item text-primary edit-task-name" href="#" data-task-id="${taskId}" data-task-name="${encodeURIComponent(taskName ?? '')}">Edit name</a></li>
                        <li><hr class="dropdown-divider"></li>
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
        function confirmStageDeletion(stage) {
            if (stage.tasks && stage.tasks.length) {
                alert('You cannot delete a stage that still contains tasks. Remove the tasks first.');
                return;
            }
            if (!confirm('Delete this empty stage?')) {
                return;
            }
            fetch(`{{ url('/stages') }}/${stage.id}`, {
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
            toggleSaving(rowElement, true);
            fetch(`{{ url('/tasks') }}/${taskId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload)
            })
                .then(response => handleResponse(response, rowElement))
                .catch(() => alert('Unexpected server response.'))
                .finally(() => toggleSaving(rowElement, false));
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
            return response.json().then(data => {
                if (!response.ok) {
                    alert(data.message || 'An error occurred.');
                    return;
                }
                if (Array.isArray(data.stages)) {
                    stagesData = data.stages;
                    if (!stagesData.find(stage => stage.id === activeStageId) && stagesData.length) {
                        activeStageId = stagesData[0].id;
                    }
                    openedMobileStages.forEach((stageId) => {
                        if (!stagesData.find(stage => stage.id === stageId)) {
                            openedMobileStages.delete(stageId);
                        }
                    });
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
