<div id="case-chat-offcanvas" class="offcanvas offcanvas-end case-chat-offcanvas" tabindex="-1" aria-labelledby="case-chat-offcanvas-label">
    <div class="offcanvas-header border-bottom">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-chat-square-text"></i>
            <span class="fw-semibold" id="case-chat-offcanvas-label">Case chat</span>
            <span id="case-chat-header-new" class="badge bg-danger d-none">NEW</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column h-100">
        <div class="case-chat-body bg-light p-3">
            <div id="case-chat-messages" class="d-flex flex-column gap-3"></div>
            <div id="case-chat-empty" class="alert alert-info py-2 mb-0 d-none">No messages yet.</div>
        </div>
        @if($chatProfile['can_post'])
            <div class="border-top p-3">
                <form id="case-chat-form" class="d-flex flex-column gap-2" novalidate>
                    @csrf
                    <div class="d-flex align-items-center gap-2">
                        @if($isAdmin)
                            <div class="flex-shrink-0" style="min-width: 160px;">
                                <label for="chat-send-as" class="form-label mb-1 small">Send as</label>
                                <select id="chat-send-as" name="send_as" class="form-select form-select-sm">
                                    @foreach($chatProfile['labels'] as $label)
                                        <option value="{{ $label['value'] }}" @if($chatProfile['default_label'] === $label['value']) selected @endif>{{ $label['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" id="chat-send-as" name="send_as" value="{{ $chatProfile['default_label'] }}">
                        @endif
                        <div class="flex-grow-1">
                            <label for="chat-body" class="form-label mb-1 small">Message</label>
                            <textarea id="chat-body" name="body" class="form-control" rows="2" placeholder="Type your update"></textarea>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label mb-1 small">Attachment (optional, max 20MB)</label>
                            <div id="chat-dropzone" class="border border-dashed rounded p-3 bg-white text-center">
                                <i class="bi bi-paperclip me-1"></i>
                                <span id="chat-file-label">Drop a file here or click to browse.</span>
                                <input type="file" name="file" id="chat-file" class="d-none" aria-label="Upload file">
                            </div>
                        </div>
                        <div class="d-flex align-items-end gap-2">
                            <div id="chat-file-chip" class="badge bg-secondary d-none"></div>
                            <button type="submit" class="btn btn-success">Send</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        // Chat configuration derived from backend data for API calls.
        const chatConfig = {
            fetchUrl: '{{ route('cases.chat.index', $case->id) }}',
            storeUrl: '{{ route('cases.chat.store', $case->id) }}',
            unreadUrl: '{{ route('cases.chat.unread', $case->id) }}',
            deleteTemplate: '{{ route('cases.chat.destroy', [$case->id, '__ID__']) }}',
            canPost: @json($chatProfile['can_post']),
            isAdmin: @json($isAdmin),
            csrfToken: '{{ csrf_token() }}',
        };

        // Cache DOM references for the chat interface.
        const chatOffcanvasEl = document.getElementById('case-chat-offcanvas');
        const chatOffcanvas = chatOffcanvasEl ? new bootstrap.Offcanvas(chatOffcanvasEl) : null;
        const chatToggleBtn = document.getElementById('case-chat-toggle');
        const chatMessagesWrap = document.getElementById('case-chat-messages');
        const chatEmptyAlert = document.getElementById('case-chat-empty');
        const chatHeaderNew = document.getElementById('case-chat-header-new');
        const chatUnreadBadge = document.getElementById('case-chat-unread');
        const chatForm = document.getElementById('case-chat-form');
        const chatBodyInput = document.getElementById('chat-body');
        const chatDropzone = document.getElementById('chat-dropzone');
        const chatFileInput = document.getElementById('chat-file');
        const chatFileLabel = document.getElementById('chat-file-label');
        const chatFileChip = document.getElementById('chat-file-chip');

        let chatOpen = false;
        let chatPoll = null;
        let unreadPoll = null;
        let lastMessageId = null;

        // Smoothly scroll the viewport to the newest chat message when content changes.
        const scrollToLatestMessage = () => {
            if (!chatMessagesWrap) {
                return;
            }
            requestAnimationFrame(() => {
                chatMessagesWrap.scrollTo({
                    top: chatMessagesWrap.scrollHeight,
                    behavior: 'smooth',
                });
            });
        };

        // Safely parse JSON responses to avoid hard failures on HTML error pages.
        const parseJsonSafely = async (response) => {
            try {
                return await response.clone().json();
            } catch (error) {
                const fallbackMessage = await response.text();
                return { message: fallbackMessage };
            }
        };

        // Toggle the offcanvas visibility using the Bootstrap API.
        const setChatVisibility = (visible) => {
            if (!chatOffcanvas) {
                return;
            }
            chatOpen = visible;
            if (visible) {
                chatOffcanvas.show();
            } else {
                chatOffcanvas.hide();
            }
        };

        // Render a single message bubble respecting ownership and unread flags.
        const buildMessageElement = (message) => {
            const wrapper = document.createElement('div');
            wrapper.className = `d-flex ${message.is_mine ? 'justify-content-end' : 'justify-content-start'}`;

            const bubble = document.createElement('div');
            bubble.className = `case-chat-bubble ${message.is_mine ? 'mine' : 'theirs'} shadow-sm`;
            bubble.dataset.new = message.is_new ? '1' : '0';

            const header = document.createElement('div');
            header.className = 'd-flex align-items-center justify-content-between mb-1';
            header.innerHTML = `<span class="fw-semibold">${message.label}</span><small class="text-muted">${formatTimestamp(message.created_at)}</small>`;

            if (message.is_new) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger ms-2';
                badge.textContent = 'NEW';
                header.querySelector('.fw-semibold').appendChild(badge);
            }

            if (chatConfig.isAdmin) {
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-sm btn-outline-light text-nowrap ms-2';
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.addEventListener('click', () => deleteMessage(message.id, wrapper));
                header.appendChild(deleteBtn);
            }

            bubble.appendChild(header);

            if (message.body) {
                const body = document.createElement('div');
                body.innerHTML = escapeHtml(message.body).replace(/\n/g, '<br>');
                bubble.appendChild(body);
            }

            if (message.attachment) {
                const attachment = document.createElement('a');
                attachment.href = message.attachment.url;
                attachment.className = 'case-chat-attachment mt-2 text-decoration-none text-reset d-inline-flex align-items-center';
                attachment.innerHTML = `<i class="bi bi-paperclip"></i><span>${escapeHtml(message.attachment.name || 'File')}</span>`;
                bubble.appendChild(attachment);
            }

            wrapper.appendChild(bubble);
            return wrapper;
        };

        // Re-render the message list with new content.
        const renderMessages = (messages, append = false) => {
            if (!append) {
                chatMessagesWrap.innerHTML = '';
            }

            messages.forEach((msg) => {
                chatMessagesWrap.appendChild(buildMessageElement(msg));
                lastMessageId = msg.id;
            });

            if (!chatMessagesWrap.children.length) {
                chatEmptyAlert.classList.remove('d-none');
            } else {
                chatEmptyAlert.classList.add('d-none');
                scrollToLatestMessage();
            }

            const hasNew = chatMessagesWrap.querySelector('[data-new="1"]');
            chatHeaderNew.classList.toggle('d-none', !hasNew);
        };

        // Retrieve messages from the server, optionally only those after the last ID.
        const fetchMessages = () => {
            const url = lastMessageId ? `${chatConfig.fetchUrl}?after_id=${lastMessageId}` : chatConfig.fetchUrl;
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        alert(data.message || 'Unable to load chat messages.');
                        return;
                    }

                    const append = Boolean(lastMessageId);
                    renderMessages(data.messages, append);
                    updateUnreadBadge(data.unread_count);
                })
                .catch(() => alert('Chat messages could not be loaded.'));
        };

        // Persist a new message using the API.
        const sendMessage = (event) => {
            event.preventDefault();
            const formData = new FormData(chatForm);

            fetch(chatConfig.storeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chatConfig.csrfToken, Accept: 'application/json' },
                body: formData,
                credentials: 'same-origin',
            })
                .then(async (response) => {
                    const data = await parseJsonSafely(response);
                    return { ok: response.ok, data };
                })
                .then(({ ok, data }) => {
                    if (!ok) {
                        alert(data.message || 'Failed to send message.');
                        return;
                    }

                    renderMessages([data.message], true);
                    chatBodyInput.value = '';
                    resetFileSelection();
                    updateUnreadBadge(data.unread_count);
                })
                .catch(() => alert('Unable to send message.'));
        };

        // Delete a message when the admin requests removal.
        const deleteMessage = (messageId, element) => {
            const endpoint = chatConfig.deleteTemplate.replace('__ID__', messageId);
            fetch(endpoint, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': chatConfig.csrfToken, 'Accept': 'application/json' },
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        alert(data.message || 'Failed to remove message.');
                        return;
                    }

                    element.remove();
                    if (!chatMessagesWrap.children.length) {
                        chatEmptyAlert.classList.remove('d-none');
                    }
                    updateUnreadBadge(data.unread_count);
                })
                .catch(() => alert('Unable to delete message.'));
        };

        // Helper to format timestamps into a readable label.
        const formatTimestamp = (value) => {
            if (!value) {
                return '';
            }
            const date = new Date(value.replace(' ', 'T'));
            return date.toLocaleString('en-GB', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' });
        };

        // Update the floating unread badge visibility and text.
        const updateUnreadBadge = (count) => {
            if (!chatUnreadBadge) {
                return;
            }
            if (count > 0) {
                chatUnreadBadge.textContent = count;
                chatUnreadBadge.classList.remove('d-none');
            } else {
                chatUnreadBadge.classList.add('d-none');
            }
        };

        // Poll unread counts while the chat is closed to keep the badge fresh.
        const pollUnread = () => {
            fetch(chatConfig.unreadUrl, { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && typeof data.unread_count !== 'undefined') {
                        updateUnreadBadge(data.unread_count);
                    }
                })
                .catch(() => {});
        };

        // Start and stop polling helpers for the chat window.
        const startChatPolling = () => {
            stopChatPolling();
            chatPoll = setInterval(fetchMessages, 5000);
        };

        const stopChatPolling = () => {
            if (chatPoll) {
                clearInterval(chatPoll);
                chatPoll = null;
            }
        };

        const startUnreadPolling = () => {
            unreadPoll = setInterval(pollUnread, 5000);
            pollUnread();
        };

        const stopUnreadPolling = () => {
            if (unreadPoll) {
                clearInterval(unreadPoll);
                unreadPoll = null;
            }
        };

        // Manage drag-and-drop uploads for the attachment field.
        const resetFileSelection = () => {
            if (chatFileInput) {
                chatFileInput.value = '';
            }
            if (chatFileChip) {
                chatFileChip.classList.add('d-none');
            }
            if (chatFileLabel) {
                chatFileLabel.textContent = 'Drop a file here or click to browse.';
            }
        };

        if (chatDropzone && chatFileInput) {
            chatDropzone.addEventListener('click', () => chatFileInput.click());
            ['dragenter', 'dragover'].forEach((eventName) => {
                chatDropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    chatDropzone.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach((eventName) => {
                chatDropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    chatDropzone.classList.remove('dragover');
                });
            });
            chatDropzone.addEventListener('drop', (event) => {
                const file = event.dataTransfer.files[0];
                if (file) {
                    if (file.size > 20 * 1024 * 1024) {
                        alert('Files must be 20MB or smaller.');
                        return;
                    }
                    chatFileInput.files = event.dataTransfer.files;
                    chatFileLabel.textContent = file.name;
                    chatFileChip.textContent = file.name;
                    chatFileChip.classList.remove('d-none');
                }
            });
            chatFileInput.addEventListener('change', () => {
                const file = chatFileInput.files[0];
                if (file && file.size > 20 * 1024 * 1024) {
                    alert('Files must be 20MB or smaller.');
                    resetFileSelection();
                    return;
                }
                if (file) {
                    chatFileLabel.textContent = file.name;
                    chatFileChip.textContent = file.name;
                    chatFileChip.classList.remove('d-none');
                } else {
                    resetFileSelection();
                }
            });
        }

        // Register event handlers for opening and closing the chat window.
        if (chatToggleBtn) {
            chatToggleBtn.addEventListener('click', () => setChatVisibility(true));
        }

        if (chatOffcanvasEl) {
            chatOffcanvasEl.addEventListener('shown.bs.offcanvas', () => {
                chatOpen = true;
                scrollToLatestMessage();
                fetchMessages();
                startChatPolling();
                stopUnreadPolling();
            });

            chatOffcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
                chatOpen = false;
                stopChatPolling();
                startUnreadPolling();
            });
        }

        // Attach submit handler when posting is permitted.
        if (chatConfig.canPost && chatForm) {
            chatForm.addEventListener('submit', sendMessage);
        }

        // Kick off unread polling when the chat is closed initially.
        startUnreadPolling();
    </script>
@endpush
