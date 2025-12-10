<div id="case-chat-offcanvas" class="offcanvas offcanvas-end case-chat-offcanvas" tabindex="-1" aria-labelledby="case-chat-offcanvas-label">
    <div class="offcanvas-header border-bottom">
        <div class="d-flex align-items-center gap-2 d-none">
            <i class="bi bi-chat-square-text"></i>
            <span class="fw-semibold" id="case-chat-offcanvas-label">Case chat</span>
            <span id="case-chat-header-new" class="badge bg-danger d-none">NEW</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column h-100">
        <div class="case-chat-body @if($isAdmin || ($chatProfile['default_label'] === 'legal')) case-chat-body-margin @endif bg-light p-3">
            <div id="case-chat-messages" class="d-flex flex-column gap-3"></div>
            <div id="case-chat-empty" class="alert alert-info py-2 mb-0 d-none">No messages yet.</div>
        </div>
        @if($chatProfile['can_post'])
            <div class="border-top p-3">
                <form id="case-chat-form" class="d-flex flex-column gap-2" novalidate>
                    @csrf
                    <div class="row g-2 align-items-center">
                        @if($isAdmin)
                            <div class="col-12 col-md-4">
                                <div class="d-flex align-items-center gap-2 text-nowrap">
                                    <select id="chat-send-as" name="send_as" class="form-select form-select-sm">
                                        @foreach($chatProfile['labels'] as $label)
                                            <option value="{{ $label['value'] }}" @if($chatProfile['default_label'] === $label['value']) selected @endif>{{ $label['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
                            <input type="hidden" id="chat-send-as" name="send_as" value="{{ $chatProfile['default_label'] }}">
                        @endif

                        <div class="col-12 {{ $isAdmin ? 'col-md-8' : '' }}">
                            <div id="chat-dropzone" class="border border-dashed rounded px-3 py-1 bg-white text-center">
                                <i class="bi bi-paperclip me-1"></i>
                                <span id="chat-file-label">Drop a file here or click to browse.</span>
                                <input type="file" name="file" id="chat-file" class="d-none" aria-label="Upload file">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-end gap-2">
                        <div class="flex-grow-1">
                            <textarea id="chat-body" name="body" class="form-control" rows="2" placeholder="Message (Enter to send)"></textarea>
                        </div>
                        <div id="chat-file-chip" class="badge bg-secondary d-none"></div>
                        <button type="submit"
                                class="chatSendBtn btn btn-success rounded-circle d-flex align-items-center justify-content-center"
                                aria-label="Send message">
                            <i class="bi bi-send-fill fs-5"></i>
                        </button>
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
        let isSending = false;

        // Smoothly scroll the viewport to the newest chat message when content changes.
        const scrollToLatestMessage = () => {
            if (!chatMessagesWrap) {
                return;
            }

            // Find the scrollable container (case-chat-body) or fall back to the messages wrapper
            const scrollContainer = chatMessagesWrap.closest('.case-chat-body') || chatMessagesWrap;

            requestAnimationFrame(() => {
                scrollContainer.scrollTo({
                    top: scrollContainer.scrollHeight,
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

        // Decide alignment based on message role/label.
        const getMessageAlignment = (message) => {
            const label = (message.label || '').toLowerCase();

            // sell-side -> left, buy-side -> right, manager -> center
            if (label.includes('buy')) {
                return 'justify-content-end message--buy';
            }
            if (label.includes('sell')) {
                return 'justify-content-start message--sell';
            }
            if (label.includes('manager')) {
                return 'justify-content-center message--manager';
            }

            // Fallback to old behaviour (own messages right, others left)
            return message.is_mine ? 'justify-content-end' : 'justify-content-start';
        };

        // Render a single message bubble respecting ownership and unread flags.
        const buildMessageElement = (message) => {
            const wrapper = document.createElement('div');
            const alignmentClass = getMessageAlignment(message);
            wrapper.className = `d-flex ${alignmentClass}`;

            const bubble = document.createElement('div');
            bubble.className = `case-chat-bubble shadow-sm`;
            bubble.dataset.new = message.is_new ? '1' : '0';

            // Start new header block
            const header = document.createElement('div');
            header.className = 'd-flex align-items-center mb-1';

            // Label on the left
            const labelSpan = document.createElement('span');
            labelSpan.className = 'fw-semibold';
            labelSpan.textContent = message.label;
            header.appendChild(labelSpan);

            // NEW badge next to the label
            if (message.is_new) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger ms-2';
                badge.textContent = 'NEW';
                labelSpan.appendChild(badge);
            }

            // Right side container for delete button and timestamp
            const rightSide = document.createElement('div');
            rightSide.className = 'd-flex align-items-center ms-auto';

            // Delete icon link (for admins only), placed next to the timestamp
            if (chatConfig.isAdmin) {
                const deleteLink = document.createElement('a');
                deleteLink.href = 'javascript:void(0)';
                deleteLink.className = 'text-muted me-2'; // simple icon, no border
                deleteLink.innerHTML = '<i class="bi bi-trash"></i>';
                deleteLink.addEventListener('click', () => deleteMessage(message.id, wrapper));
                rightSide.appendChild(deleteLink);
            }

            // Timestamp stays at the far right within the header
            const timeEl = document.createElement('small');
            timeEl.className = 'text-muted';
            timeEl.textContent = formatTimestamp(message.created_at);
            rightSide.appendChild(timeEl);

            header.appendChild(rightSide);

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

                if (!append || messages.length > 0) {
                    scrollToLatestMessage();
                }
            }

            const hasNew = chatMessagesWrap.querySelector('[data-new="1"]');
            chatHeaderNew.classList.toggle('d-none', !hasNew);
        };

        // Retrieve messages from the server, optionally only those after the last ID, and only refresh when new items exist.
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
                    const hasNewMessages = Array.isArray(data.messages) && data.messages.length > 0;
                    const shouldRender = !lastMessageId || hasNewMessages;

                    if (shouldRender) {
                        renderMessages(data.messages, append);
                    }

                    updateUnreadBadge(data.unread_count);
                })
                .catch(() => alert('Chat messages could not be loaded.'));
        };

        // Persist a new message using the API.
        const sendMessage = (event) => {
            if (event) {
                event.preventDefault();
            }

            // Prevent duplicate sends while a request is in progress
            if (isSending) {
                return;
            }

            if (!chatForm) {
                return;
            }

            // Do not send completely empty messages (no text and no file)
            const bodyValue = chatBodyInput ? chatBodyInput.value.trim() : '';
            const hasFile = chatFileInput && chatFileInput.files && chatFileInput.files.length > 0;
            if (!bodyValue && !hasFile) {
                return;
            }

            const formData = new FormData(chatForm);

            // Lock UI while sending
            isSending = true;
            if (chatBodyInput) {
                chatBodyInput.disabled = true;
            }
            if (chatDropzone) {
                chatDropzone.classList.add('disabled');
            }

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
                    if (chatBodyInput) {
                        chatBodyInput.value = '';
                    }
                    resetFileSelection();
                    updateUnreadBadge(data.unread_count);
                })
                .catch(() => {
                    alert('Unable to send message.');
                })
                .finally(() => {
                    // Unlock UI after request finishes
                    isSending = false;
                    if (chatBodyInput) {
                        chatBodyInput.disabled = false;
                        chatBodyInput.focus();
                    }
                    if (chatDropzone) {
                        chatDropzone.classList.remove('disabled');
                    }
                });
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

        // Submit message on Enter key (Shift+Enter = new line)
        if (chatBodyInput) {
            chatBodyInput.addEventListener('keydown', (e) => {
                // If Enter pressed without Shift
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault(); // Prevent newline

                    // Skip if we're already sending a message
                    if (isSending) {
                        return;
                    }

                    if (chatConfig.canPost && chatForm) {
                        sendMessage(new Event('submit')); // Trigger submit
                    }
                }
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
