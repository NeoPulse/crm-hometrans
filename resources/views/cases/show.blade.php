@extends('layouts.app')

@push('styles')
    <style>
        /* Floating chat toggle styling anchored to the bottom of the viewport. */
        .case-chat-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1080;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        /* Chat panel that slides over the current page without reloads. */
        .case-chat-panel {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 420px;
            max-width: calc(100vw - 30px);
            max-height: calc(100vh - 120px);
            z-index: 1070;
            display: none;
        }

        /* Full-screen presentation on small screens. */
        @media (max-width: 991.98px) {
            .case-chat-panel {
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 100%;
                max-height: none;
            }
        }

        /* Ensure the chat message list stays scrollable within the card. */
        .chat-messages {
            max-height: 60vh;
            overflow-y: auto;
        }

        /* Visual treatment for chat bubbles depending on ownership. */
        .chat-bubble {
            display: inline-block;
            padding: 12px 14px;
            border-radius: 14px;
            max-width: 100%;
            word-break: break-word;
        }

        /* Dropzone styling to hint drag-and-drop support. */
        .chat-dropzone {
            border: 2px dashed #6c757d;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
        }

        /* Light background for new message labels. */
        .chat-new-badge {
            font-size: 0.75rem;
        }

        /* Subtle highlight for the currently expanded mobile stage. */
        .stage-card.active-mobile {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.1rem rgba(13, 110, 253, 0.25);
        }
    </style>
@endpush

@section('header')
    <!-- Case-specific header replacing the default navigation for the case area. -->
    @php
        $currentUser = auth()->user();
        $isLegal = $currentUser && $currentUser->role === 'legal';
        $brandTarget = $isAdmin ? route('dashboard') : ($isLegal ? route('casemanager.legal') : null);
    @endphp
    <div class="bg-white border-bottom shadow-sm">
        <div class="container py-3">
            <div class="d-flex align-items-center gap-4 flex-nowrap">
                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                    {{-- Role-aware brand link that keeps the client view static. --}}
                    @if($brandTarget)
                        <a href="{{ $brandTarget }}" class="text-decoration-none fw-bold text-primary fs-5">HomeTrans CRM</a>
                    @else
                        <div class="fw-bold text-primary fs-5">HomeTrans CRM</div>
                    @endif
                    <div class="text-nowrap">
                        <div class="text-uppercase text-muted small">Postal code</div>
                        <h1 class="h5 mb-0">Case {{ $case->postal_code }}</h1>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 flex-nowrap overflow-auto team-strip">
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
                <div class="ms-auto flex-shrink-0">
                    <form method="POST" action="{{ route('logout') }}" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Exit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')

    <div class="row g-4">
        <div class="col-lg-4">
            <!-- Stage list column showing all stages with progress. -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0">Stages:</h2>
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

            <div id="mobile-task-slot" class="d-lg-none mt-3"></div>
        </div>
        <div class="col-lg-8 d-none d-lg-block">
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

    <!-- Floating chat toggle and overlay panel. -->
    <button type="button" id="chat-toggle" class="btn btn-primary case-chat-toggle">
        Chat
        <span class="badge bg-light text-primary ms-2" id="chat-unread-count">{{ $chatUnreadCount }}</span>
    </button>

    <div id="case-chat-panel" class="case-chat-panel">
        <div class="card h-100 shadow-lg">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="fw-semibold">Case chat</div>
                    <span class="badge bg-danger chat-new-badge d-none" id="chat-new-flag">NEW</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="chat-close">Close</button>
            </div>
            <div class="card-body chat-messages bg-light" id="chat-messages">
                <div class="text-center text-muted py-4">Loading chat...</div>
            </div>
            @if($canPostChat)
                <div class="card-footer">
                    <form id="chat-form" class="d-flex flex-column gap-2" enctype="multipart/form-data" novalidate>
                        @csrf
                        @if($isAdmin)
                            <div class="d-flex gap-2 align-items-center">
                                <label for="chat-sender" class="form-label mb-0">Send as</label>
                                <select id="chat-sender" name="sender_label" class="form-select form-select-sm w-auto">
                                    <option value="manager">Manager</option>
                                    <option value="buy">Buy Side</option>
                                    <option value="sell">Sell Side</option>
                                </select>
                            </div>
                        @endif
                        <textarea class="form-control" id="chat-body" name="body" rows="3" placeholder="Write a message... (optional)"></textarea>
                        <div class="chat-dropzone text-center" id="chat-dropzone">
                            <div class="small text-muted">Drag and drop a file here or click to browse (max 20 MB).</div>
                            <input type="file" id="chat-attachment" name="attachment" class="visually-hidden">
                            <div class="mt-1" id="chat-selected-file"></div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="chat-clear-file">Clear file</button>
                            <button type="submit" class="btn btn-primary btn-sm">Send</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="card-footer">
                    <div class="alert alert-info mb-0">Only administrators and solicitors can send messages. Clients can read and download attachments.</div>
                </div>
            @endif
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
        let mobileExpandedStageId = null;
        const isAdmin = @json($isAdmin);
        const canPostChat = @json($canPostChat);
        const legalSide = @json($legalSide);
        const stageListEl = document.getElementById('stage-list');
        const noStagesAlert = document.getElementById('no-stages-alert');
        const tasksContent = document.getElementById('tasks-content');
        const stageTitle = document.getElementById('stage-title');
        const stageSubtitle = document.getElementById('stage-subtitle');
        const csrfToken = '{{ csrf_token() }}';

        // Encode user-controlled text to prevent HTML injection in templates.
        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        // Detect whether the viewport is currently in mobile mode.
        const isMobileView = () => window.matchMedia('(max-width: 991.98px)').matches;

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
            new: { icon: 'bi-plus-circle', classes: 'text-primary' },
            progress: { icon: 'bi-arrow-repeat', classes: 'text-warning' },
            done: { icon: 'bi-check-circle', classes: 'text-success' },
        };

        // Render the list of stages on the left column, enabling mobile spoilers.
        function renderStages() {
            stageListEl.innerHTML = '';
            if (!stagesData.length) {
                noStagesAlert.classList.remove('d-none');
                return;
            }
            noStagesAlert.classList.add('d-none');

            stagesData.forEach((stage, index) => {
                const card = document.createElement('div');
                const isActiveDesktop = stage.id === activeStageId && !isMobileView();
                const isExpandedMobile = stage.id === mobileExpandedStageId;
                card.className = `card stage-card shadow-sm ${isActiveDesktop ? 'border-primary' : ''} ${isExpandedMobile ? 'active-mobile' : ''}`;
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
                        <div class="progress mt-2" role="progressbar" aria-label="Stage progress" style="height: 14px;">
                            <div class="progress-bar bg-success" style="width: ${stage.progress}%">${stage.progress}%</div>
                        </div>
                    </div>
                `;

                // Toggle tasks inline for mobile and focus the desktop task area otherwise.
                card.addEventListener('click', () => {
                    if (isMobileView()) {
                        if (mobileExpandedStageId === stage.id) {
                            mobileExpandedStageId = null;
                        } else {
                            mobileExpandedStageId = stage.id;
                            activeStageId = stage.id;
                        }
                        renderStages();
                        renderTasks();
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

                // Inject tasks inline when the stage is expanded on mobile view.
                if (isMobileView() && isExpandedMobile) {
                    const mobileTasks = document.createElement('div');
                    mobileTasks.className = 'mt-2 d-lg-none';
                    mobileTasks.innerHTML = buildTasksMarkup(stage);
                    bindTaskEvents(mobileTasks);
                    card.appendChild(mobileTasks);
                }

                stageListEl.appendChild(card);
            });
        }

        // Render tasks for the currently active stage in the desktop panel.
        function renderTasks() {
            const stage = stagesData.find(item => item.id === activeStageId);
            if (!stage) {
                tasksContent.innerHTML = '<div class="alert alert-info mb-0">No stages available to show.</div>';
                stageTitle.textContent = 'Stages';
                stageSubtitle.textContent = 'Tasks will appear after you add a stage.';
                return;
            }
            const stageIndex = stagesData.findIndex(item => item.id === stage.id);
            stageTitle.textContent = `${stageIndex + 1}. ${stage.name}`;
            stageSubtitle.textContent = 'Tasks for the selected stage.';

            renderTasksForContainer(stage, tasksContent);
        }

        // Render task markup inside the provided container element and bind actions.
        function renderTasksForContainer(stage, container) {
            container.innerHTML = buildTasksMarkup(stage);
            bindTaskEvents(container);
        }

        // Compose the HTML for a stage task list across both sides.
        function buildTasksMarkup(stage) {
            const sellerTasks = stage.tasks.filter(task => task.side === 'seller');
            const buyerTasks = stage.tasks.filter(task => task.side === 'buyer');

            return `
                <div class="d-flex align-items-center mb-2 position-relative">
                    <div class="flex-grow-1 text-center">
                        <h3 class="h6 mb-0">Seller side</h3>
                    </div>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task position-absolute end-0 top-50 translate-middle-y" data-side="seller" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(sellerTasks)}
                <div class="d-flex align-items-center mt-4 mb-2 position-relative">
                    <div class="flex-grow-1 text-center">
                        <h3 class="h6 mb-0">Buyer side</h3>
                    </div>
                    ${isAdmin ? `<a href="#" class="link-primary text-decoration-none add-task position-absolute end-0 top-50 translate-middle-y" data-side="buyer" data-stage="${stage.id}">add task</a>` : ''}
                </div>
                ${renderTaskList(buyerTasks)}
            `;
        }

        // Bind actions inside a rendered task container (desktop or mobile).
        function bindTaskEvents(container) {
            container.querySelectorAll('.add-task').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    createTask(link.dataset.stage, link.dataset.side);
                });
            });

            container.querySelectorAll('.task-deadline-input').forEach(input => {
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

            container.querySelectorAll('.status-option').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    const taskId = item.dataset.taskId;
                    const status = item.dataset.status;
                    updateTask(taskId, {status: status}, item.closest('.task-row'));
                });
            });

            container.querySelectorAll('.edit-task-name').forEach(item => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    const taskId = item.dataset.taskId;
                    const existingName = decodeURIComponent(item.dataset.taskName || '');
                    const newName = prompt('Enter a new task title', existingName);
                    if (newName) {
                        updateTask(taskId, {name: newName}, item.closest('.task-row'));
                    }
                });
            });

            container.querySelectorAll('.delete-task').forEach(item => {
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
                    <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-3 task-row position-relative">
                        <div class="fw-semibold text-muted" style="min-width: 24px;">${index + 1}.</div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-truncate" title="${escapeHtml(task.name)}">${escapeHtml(task.name)}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-auto justify-content-end text-end">
                            ${task.is_new ? '<span class="badge bg-danger">NEW</span>' : ''}
                            <div class="badge ${deadlineClass} text-dark p-2 rounded task-deadline">
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
                    <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        ${iconHtml}
                    </button>
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
                    if (!stagesData.find(stage => stage.id === mobileExpandedStageId)) {
                        mobileExpandedStageId = null;
                    }
                    renderStages();
                    renderTasks();
                }
                if (rowElement) {
                    flashRow(rowElement);
                }
            }).catch(() => alert('Unexpected server response.'));
        }

        // Re-render stages on resize to honour mobile spoiler behaviour.
        window.addEventListener('resize', () => {
            renderStages();
            renderTasks();
        });

        // Initial render on page load.
        renderStages();
        renderTasks();

        // --- Case chat functionality below ---

        const chatToggle = document.getElementById('chat-toggle');
        const chatPanel = document.getElementById('case-chat-panel');
        const chatClose = document.getElementById('chat-close');
        const chatMessages = document.getElementById('chat-messages');
        const chatUnreadBadge = document.getElementById('chat-unread-count');
        const chatNewFlag = document.getElementById('chat-new-flag');
        const chatForm = document.getElementById('chat-form');
        const chatBody = document.getElementById('chat-body');
        const chatSender = document.getElementById('chat-sender');
        const chatAttachment = document.getElementById('chat-attachment');
        const chatDropzone = document.getElementById('chat-dropzone');
        const chatSelectedFile = document.getElementById('chat-selected-file');
        const chatClearFile = document.getElementById('chat-clear-file');

        const chatRoutes = {
            fetch: `{{ route('cases.chat.index', $case) }}`,
            unread: `{{ route('cases.chat.unread', $case) }}`,
            post: `{{ route('cases.chat.store', $case) }}`,
            deleteBase: `{{ url('/case/' . $case->id . '/chat') }}`,
        };

        let chatOpen = false;
        let chatPoll = null;
        let chatUnreadPoll = null;
        let chatViewLogged = false;

        // Update the unread badge on the floating toggle.
        function updateUnreadBadge(value) {
            const safeValue = Math.max(0, value ?? 0);
            chatUnreadBadge.textContent = safeValue;
        }

        // Open the chat panel and start live polling.
        function openChat() {
            chatOpen = true;
            chatPanel.style.display = 'block';
            fetchChat(!chatViewLogged);
            chatViewLogged = true;

            if (chatPoll) {
                clearInterval(chatPoll);
            }
            chatPoll = setInterval(() => {
                fetchChat(false);
            }, 5000);
        }

        // Close the chat panel and pause message polling.
        function closeChat() {
            chatOpen = false;
            chatPanel.style.display = 'none';
            chatNewFlag.classList.add('d-none');
            if (chatPoll) {
                clearInterval(chatPoll);
            }
        }

        // Poll unread count while the panel is closed.
        function startUnreadPolling() {
            if (chatUnreadPoll) {
                clearInterval(chatUnreadPoll);
            }
            chatUnreadPoll = setInterval(() => {
                if (!chatOpen) {
                    fetchUnreadCount();
                }
            }, 5000);
        }

        // Fetch chat messages from the server.
        function fetchChat(logView) {
            let url = chatRoutes.fetch;
            if (logView) {
                url += '?log_view=1';
            }
            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                }
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        alert(data.message || 'Could not load chat.');
                        return;
                    }
                    renderChatMessages(data.messages);
                    updateUnreadBadge(data.unread);
                })
                .catch(() => alert('Unexpected server response.'));
        }

        // Render chat messages with side-aware alignment.
        function renderChatMessages(messages) {
            chatMessages.innerHTML = '';
            if (!messages.length) {
                chatMessages.innerHTML = '<div class="text-center text-muted py-4">No messages yet.</div>';
                return;
            }

            let hasNew = false;

            messages.forEach((message) => {
                if (message.is_new) {
                    hasNew = true;
                }

                const wrapper = document.createElement('div');
                wrapper.className = `mb-3 d-flex ${message.is_own ? 'justify-content-end' : 'justify-content-start'}`;

                const bubble = document.createElement('div');
                bubble.className = `chat-bubble ${message.is_own ? 'bg-primary text-white' : 'bg-white border'}`;

                const attachmentHtml = message.attachment ? `
                    <div class="mt-2">
                        <a href="${message.attachment.url}" class="btn btn-sm ${message.is_own ? 'btn-light text-primary' : 'btn-outline-secondary'}" target="_blank" rel="noopener">
                            <i class="bi bi-paperclip me-1"></i>${escapeHtml(message.attachment.name || 'Download file')}
                        </a>
                    </div>
                ` : '';

                const deleteHtml = isAdmin ? `<button type="button" class="btn btn-link text-danger btn-sm p-0 ms-2 delete-chat-message" data-message-id="${message.id}" title="Delete message"><i class="bi bi-trash"></i></button>` : '';

                bubble.innerHTML = `
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge ${message.is_own ? 'bg-light text-primary' : 'bg-secondary'}">${message.label}</span>
                        ${message.is_new ? '<span class="badge bg-danger chat-new-badge">NEW</span>' : ''}
                        <small class="text-muted ms-auto">${message.created_at}</small>
                        ${deleteHtml}
                    </div>
                    ${message.body ? `<div>${escapeHtml(message.body)}</div>` : ''}
                    ${attachmentHtml}
                `;

                wrapper.appendChild(bubble);
                chatMessages.appendChild(wrapper);
            });

            chatNewFlag.classList.toggle('d-none', !hasNew);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Submit a chat message with optional file attachment.
        if (chatForm) {
            chatForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const formData = new FormData(chatForm);
                if (!isAdmin && legalSide) {
                    formData.set('sender_label', legalSide);
                }
                fetch(chatRoutes.post, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok) {
                            alert(data.message || 'Unable to send message.');
                            return;
                        }
                        chatForm.reset();
                        if (chatSelectedFile) {
                            chatSelectedFile.textContent = '';
                        }
                        fetchChat(false);
                    })
                    .catch(() => alert('Unexpected server response.'));
            });
        }

        // Delete chat messages when the admin clicks the trash icon.
        chatMessages.addEventListener('click', (event) => {
            const deleteButton = event.target.closest('.delete-chat-message');
            if (!deleteButton) {
                return;
            }
            const messageId = deleteButton.dataset.messageId;
            if (!confirm('Delete this chat message?')) {
                return;
            }
            fetch(`${chatRoutes.deleteBase}/${messageId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                }
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        alert(data.message || 'Unable to delete message.');
                        return;
                    }
                    fetchChat(false);
                })
                .catch(() => alert('Unexpected server response.'));
        });

        // Represent selected file names inside the dropzone.
        const updateSelectedFileLabel = () => {
            if (!chatAttachment || !chatSelectedFile) {
                return;
            }
            if (chatAttachment.files.length) {
                chatSelectedFile.textContent = chatAttachment.files[0].name;
            } else {
                chatSelectedFile.textContent = '';
            }
        };

        // Enable clicking the dropzone to pick a file.
        if (chatDropzone && chatAttachment) {
            chatDropzone.addEventListener('click', () => chatAttachment.click());

            chatDropzone.addEventListener('dragover', (event) => {
                event.preventDefault();
                chatDropzone.classList.add('bg-light');
            });
            chatDropzone.addEventListener('dragleave', () => chatDropzone.classList.remove('bg-light'));
            chatDropzone.addEventListener('drop', (event) => {
                event.preventDefault();
                chatDropzone.classList.remove('bg-light');
                if (event.dataTransfer.files.length) {
                    chatAttachment.files = event.dataTransfer.files;
                    updateSelectedFileLabel();
                }
            });

            chatAttachment.addEventListener('change', updateSelectedFileLabel);
        }

        // Clear the selected file without reloading the page.
        if (chatClearFile && chatAttachment) {
            chatClearFile.addEventListener('click', () => {
                chatAttachment.value = '';
                updateSelectedFileLabel();
            });
        }

        // Toggle chat panel visibility via the floating button.
        chatToggle.addEventListener('click', () => {
            if (chatOpen) {
                closeChat();
            } else {
                openChat();
            }
        });

        chatClose.addEventListener('click', () => closeChat());

        // Retrieve unread count independently while chat is closed.
        function fetchUnreadCount() {
            fetch(chatRoutes.unread, {
                headers: {
                    'Accept': 'application/json',
                }
            })
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        return;
                    }
                    updateUnreadBadge(data.unread);
                })
                .catch(() => {});
        }

        // Kick off unread polling immediately after load.
        startUnreadPolling();
    </script>
@endpush
