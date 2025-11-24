@extends('layouts.app')

@section('content')
@php($attentionMap = $attentionMap ?? ['stages' => [], 'tasks' => []])
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Case {{ $case->postal_code }} ({{ ucfirst($case->status) }})</h1>
        <p class="text-muted mb-0">Public link: {{ url('/case/'.$case->id.'?token='.$case->public_link) }}</p>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="text-end">
            <div class="small text-uppercase text-muted">Deadline</div>
            <div class="fw-bold">{{ $case->deadline ? $case->deadline->format('d/m/Y') : 'No deadline' }}</div>
        </div>
        @foreach($caseHeaderData['people'] as $person)
            <div class="d-flex align-items-center gap-2">
                <img src="{{ $person['avatar'] }}" width="36" height="36" class="rounded-circle border" data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="{{ $person['tooltip'] }}" alt="avatar">
                <div class="small">{{ $person['label'] }}</div>
            </div>
        @endforeach
    </div>
</div>
<div class="row">
    <div class="col-lg-4 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Stages:</h2>
            @if(auth()->user()?->role === 'admin')
                <form id="add-stage-form" class="d-flex gap-2" action="{{ route('cases.stages.store', $case) }}" method="POST">
                    @csrf
                    <input class="form-control form-control-sm" name="name" placeholder="Stage name" required>
                    <button class="btn btn-primary btn-sm">Save</button>
                </form>
            @endif
        </div>
        <div id="stage-list" class="list-group">
            @forelse($stages as $index => $stage)
                <button class="list-group-item list-group-item-action d-flex flex-column gap-1 stage-item {{ $index===0 ? 'active' : '' }}" data-stage-id="{{ $stage->id }}" data-stage-index="{{ $index+1 }}">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div class="fw-semibold text-start">{{ $index+1 }}. {{ $stage->name }}</div>
                        <div class="d-flex align-items-center gap-2">
                            @if(in_array($stage->id, $attentionMap['stages']))
                                <span class="badge bg-danger">NEW</span>
                            @endif
                            @if(auth()->user()?->role === 'admin')
                                <span class="text-muted small stage-actions" data-stage-id="{{ $stage->id }}"><i class="bi bi-pencil"></i> <i class="bi bi-trash ms-1"></i></span>
                            @endif
                        </div>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $stage->completedTaskRatio() }}%" aria-valuenow="{{ $stage->completedTaskRatio() }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </button>
            @empty
                <p class="text-muted">Stages not added yet.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        @forelse($stages as $index => $stage)
            <div class="card stage-panel mb-3 {{ $index===0 ? '' : 'd-none' }}" data-stage-id="{{ $stage->id }}">
                <div class="card-body">
                    <h2 class="h5">{{ $index+1 }}. {{ $stage->name }}</h2>
                    <p class="text-muted">Purpose: Confirm who each party is and make both legal files active.</p>
                    <div class="row g-3">
                        @foreach(['seller' => 'Seller side', 'buyer' => 'Buyer side'] as $sideKey => $sideLabel)
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h3 class="h6 mb-0">{{ $sideLabel }}</h3>
                                    @if(auth()->user()?->role === 'admin')
                                        <a href="#" class="small add-task-link" data-stage-id="{{ $stage->id }}" data-side="{{ $sideKey }}">add task</a>
                                    @endif
                                </div>
                                @php($sideTasks = $stage->tasks->where('side', $sideKey)->values())
                                @forelse($sideTasks as $taskIndex => $task)
                                    @php($isLate = $task->deadline && $task->deadline->isPast() && $task->status !== 'done')
                                    <div class="border rounded p-2 mb-2 d-flex align-items-center gap-2 flex-wrap task-row" data-task-id="{{ $task->id }}" data-stage-id="{{ $stage->id }}">
                                        <span class="fw-bold">{{ $taskIndex+1 }}.</span>
                                        <div class="flex-grow-1 text-truncate" title="{{ $task->name }}">
                                            @if(auth()->user()?->role === 'admin')
                                                <input type="text" class="form-control form-control-sm border-0 bg-transparent editable-name" value="{{ $task->name }}" data-task-id="{{ $task->id }}">
                                            @else
                                                {{ $task->name }}
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="deadline-box px-2 py-1 rounded {{ $isLate ? 'bg-danger text-white' : 'bg-light' }}">
                                                @if(auth()->user()?->role === 'admin')
                                                    <input type="date" class="form-control form-control-sm border-0 bg-transparent editable-deadline" value="{{ $task->deadline ? $task->deadline->format('Y-m-d') : '' }}" data-task-id="{{ $task->id }}">
                                                @else
                                                    {{ $task->deadline ? $task->deadline->format('d/m') : '00/00' }}
                                                @endif
                                            </div>
                                            @if(in_array($task->id, $attentionMap['tasks']))
                                                <span class="badge bg-danger">new</span>
                                            @endif
                                            <div class="dropdown">
                                                <button class="btn btn-sm {{ $task->status === 'done' ? 'btn-success' : ($task->status === 'progress' ? 'btn-warning' : 'btn-outline-secondary') }} status-btn" data-task-id="{{ $task->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                                    @switch($task->status)
                                                        @case('done')<i class="bi bi-check2-circle"></i>@break
                                                        @case('progress')<i class="bi bi-arrow-repeat"></i>@break
                                                        @default<i class="bi bi-circle"></i>
                                                    @endswitch
                                                </button>
                                                @if(auth()->user()?->role === 'admin')
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item status-option" data-status="new" href="#">Set New</a></li>
                                                        <li><a class="dropdown-item status-option" data-status="progress" href="#">Set In progress</a></li>
                                                        <li><a class="dropdown-item status-option" data-status="done" href="#">Set Done</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger delete-task" href="#">Delete</a></li>
                                                    </ul>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted">No tasks for this side.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted">No tasks available.</p>
        @endforelse
    </div>
</div>

<div id="chat-toggle" class="chat-toggle btn btn-primary position-fixed">Chat <span class="badge bg-light text-primary" id="chat-unread">0</span></div>
<div id="chat-panel" class="chat-panel card shadow position-fixed d-none">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Case chat</strong>
        <button class="btn btn-sm btn-outline-secondary" id="chat-close">Close</button>
    </div>
    <div class="card-body d-flex flex-column">
        <div id="chat-messages" class="flex-grow-1 overflow-auto mb-2" style="max-height: 45vh"></div>
        <form id="chat-form" class="d-flex gap-2 align-items-center" enctype="multipart/form-data">
            <input type="hidden" name="alias" id="chat-alias">
            <input type="text" name="body" class="form-control" placeholder="Type a message" autocomplete="off">
            <input type="file" name="attachment" class="form-control" style="max-width:160px">
            <button class="btn btn-primary">Send</button>
        </form>
        @if(auth()->user()?->role === 'admin')
            <div class="mt-2 small">Send as: 
                <select id="alias-select" class="form-select form-select-sm" style="max-width: 200px; display:inline-block;">
                    <option value="Manager">Manager</option>
                    <option value="Buy Side">Buy Side</option>
                    <option value="Sell Side">Sell Side</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const caseId = {{ $case->id }};
    const isAdmin = {{ auth()->user()?->role === 'admin' ? 'true' : 'false' }};

    function toggleStage(targetId) {
        document.querySelectorAll('.stage-panel').forEach(panel => {
            panel.classList.toggle('d-none', panel.dataset.stageId !== targetId);
        });
        document.querySelectorAll('.stage-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.stageId === targetId);
        });
    }

    document.querySelectorAll('.stage-item').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleStage(btn.dataset.stageId);
        });
    });

    document.querySelectorAll('.stage-actions').forEach(iconWrap => {
        iconWrap.style.cursor = 'pointer';
        iconWrap.addEventListener('click', async (e) => {
            const stageId = iconWrap.dataset.stageId;
            if (e.target.classList.contains('bi-trash')) {
                if (!confirm('Delete this stage?')) return;
                await fetch(`/cases/${caseId}/stages/${stageId}`, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                });
                location.reload();
            }
            if (e.target.classList.contains('bi-pencil')) {
                const newName = prompt('Stage name');
                if (!newName) return;
                await fetch(`/cases/${caseId}/stages/${stageId}`, {
                    method: 'PATCH',
                    headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                    body: JSON.stringify({name: newName}),
                });
                location.reload();
            }
        });
    });

    const stageForm = document.getElementById('add-stage-form');
    if (stageForm) {
        stageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(stageForm);
            const response = await fetch(stageForm.action, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: formData,
            });
            if (response.ok) {
                location.reload();
            }
        });
    }

    document.querySelectorAll('.editable-name').forEach(input => {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
        });
        input.addEventListener('blur', () => saveTask(input.dataset.taskId, {name: input.value}));
    });

    document.querySelectorAll('.editable-deadline').forEach(input => {
        input.addEventListener('change', () => saveTask(input.dataset.taskId, {deadline: input.value}));
    });

    document.querySelectorAll('.status-option').forEach(option => {
        option.addEventListener('click', (e) => {
            e.preventDefault();
            const taskId = option.closest('.task-row').dataset.taskId;
            saveTask(taskId, {status: option.dataset.status});
        });
    });

    document.querySelectorAll('.delete-task').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const taskRow = btn.closest('.task-row');
            if (!confirm('Delete this task?')) return;
            const response = await fetch(`/cases/${caseId}/tasks/${taskRow.dataset.taskId}`, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            });
            taskRow.remove();
            if (response.ok) {
                const data = await response.json();
                if (data.stage_progress !== undefined) {
                    document.querySelectorAll(`.stage-item[data-stage-id='${taskRow.dataset.stageId}'] .progress-bar`).forEach(bar => {
                        bar.style.width = `${data.stage_progress}%`;
                        bar.setAttribute('aria-valuenow', data.stage_progress);
                    });
                }
            }
        });
    });

    document.querySelectorAll('.add-task-link').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const stageId = link.dataset.stageId;
            const side = link.dataset.side;
            const response = await fetch(`{{ url('/cases') }}/${caseId}/tasks/quick`, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                body: JSON.stringify({stage_id: stageId, side}),
            });
            if (response.ok) {
                location.reload();
            }
        });
    });

    async function saveTask(taskId, payload) {
        const response = await fetch(`/cases/${caseId}/tasks/${taskId}`, {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify(payload),
        });
        if (response.ok) {
            const data = await response.json();
            const row = document.querySelector(`.task-row[data-task-id='${taskId}']`);
            if (row && data.stage_progress !== undefined) {
                const stageId = row.dataset.stageId;
                document.querySelectorAll(`.stage-item[data-stage-id='${stageId}'] .progress-bar`).forEach(bar => {
                    bar.style.width = `${data.stage_progress}%`;
                    bar.setAttribute('aria-valuenow', data.stage_progress);
                });
            }
        }
    }

    // Chat logic
    const chatToggle = document.getElementById('chat-toggle');
    const chatPanel = document.getElementById('chat-panel');
    const chatClose = document.getElementById('chat-close');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const unreadBadge = document.getElementById('chat-unread');
    const aliasSelect = document.getElementById('alias-select');
    const chatAliasInput = document.getElementById('chat-alias');
    let lastMessageId = Number(localStorage.getItem(`case-${caseId}-lastSeen`)) || 0;

    function renderMessages(list) {
        chatMessages.innerHTML = '';
        list.forEach(msg => {
            const bubble = document.createElement('div');
            bubble.className = `p-2 mb-2 rounded ${msg.is_own ? 'bg-primary text-white ms-auto' : 'bg-light me-auto'} chat-bubble`;
            bubble.style.maxWidth = '80%';
            bubble.innerHTML = `<div class="small fw-bold">${msg.alias}${msg.id > lastMessageId ? ' <span class=\"badge bg-danger\">NEW</span>' : ''}</div>` +
                `<div>${msg.body ?? ''}</div>` +
                (msg.attachment_url ? `<div class="mt-1"><a href="${msg.attachment_url}" target="_blank" class="text-decoration-underline ${msg.is_own ? 'text-white' : ''}">${msg.attachment_name}</a></div>` : '') +
                `<div class="text-muted small mt-1">${msg.created_at}${isAdmin ? ` <button class='btn btn-sm btn-link text-danger p-0 ms-2 delete-message' data-id='${msg.id}'>delete</button>` : ''}</div>`;
            chatMessages.appendChild(bubble);
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function fetchMessages() {
        const response = await fetch(`/case/${caseId}/chat`);
        if (!response.ok) return;
        const data = await response.json();
        renderMessages(data.messages);
        const latest = data.messages.length ? data.messages[data.messages.length - 1].id : 0;
        const unread = data.messages.filter(m => m.id > lastMessageId).length;
        unreadBadge.textContent = unread;
        if (!chatPanel.classList.contains('d-none') && latest) {
            lastMessageId = latest;
            localStorage.setItem(`case-${caseId}-lastSeen`, lastMessageId);
            unreadBadge.textContent = 0;
        }
        document.querySelectorAll('.delete-message').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                await fetch(`/case/${caseId}/chat/${btn.dataset.id}`, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': csrfToken},
                });
                fetchMessages();
            });
        });
        chatForm.classList.toggle('d-none', !data.can_post);
    }

    chatToggle.addEventListener('click', () => {
        chatPanel.classList.toggle('d-none');
        if (!chatPanel.classList.contains('d-none')) {
            lastMessageId = Number(localStorage.getItem(`case-${caseId}-lastSeen`)) || 0;
            localStorage.setItem(`case-${caseId}-lastSeen`, lastMessageId);
            unreadBadge.textContent = 0;
        }
    });
    chatClose.addEventListener('click', () => chatPanel.classList.add('d-none'));

    chatForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(chatForm);
        if (aliasSelect) {
            chatAliasInput.value = aliasSelect.value;
            formData.set('alias', aliasSelect.value);
        }
        const response = await fetch(`/case/${caseId}/chat`, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrfToken},
            body: formData,
        });
        if (response.ok) {
            chatForm.reset();
            fetchMessages();
        }
    });

    setInterval(fetchMessages, 5000);
    fetchMessages();
</script>
<style>
    .chat-toggle {
        bottom: 16px;
        right: 16px;
        z-index: 1050;
    }
    .chat-panel {
        bottom: 80px;
        right: 16px;
        width: min(420px, 100%);
        max-height: 80vh;
        z-index: 1050;
    }
    @media (max-width: 767px) {
        .stage-panel { display: block; }
        .chat-panel { right: 0; left: 0; bottom: 0; width: 100%; height: 80vh; }
        .chat-toggle { left: 50%; transform: translateX(-50%); }
        .stage-item { text-align: left; }
    }
</style>
@endpush
