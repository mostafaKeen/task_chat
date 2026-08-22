/**
 * Bitrix24 Task Chat Widget Client Logic
 */

document.addEventListener('DOMContentLoaded', function () {
    const config = window.APP_CONFIG || {};
    const taskId = config.taskId;
    const domain = config.domain;
    const authId = config.authId;

    const chatBody = document.getElementById('chatBody');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const messagesList = document.getElementById('messagesList');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const visibilityOptions = document.querySelectorAll('.visibility-option');

    let currentVisibility = 'public';
    let isSubmitting = false;
    let pollInterval = null;
    let currentUser = null;

    // Initialize Bitrix24 JS SDK
    if (typeof BX24 !== 'undefined') {
        BX24.init(function () {
            console.log('BX24 Initialized in Task Placement');
            resizeFrame();
        });
    }

    function resizeFrame() {
        if (typeof BX24 !== 'undefined' && BX24.resizeWindow) {
            const height = document.body.scrollHeight || 600;
            BX24.resizeWindow(window.innerWidth, Math.max(height, 500));
        }
    }

    // Visibility Selector Handler
    visibilityOptions.forEach(option => {
        option.addEventListener('click', function () {
            visibilityOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                currentVisibility = radio.value;
            }
        });
    });

    // Auto-expand textarea
    messageInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        resizeFrame();
    });

    // Enter to send, Shift+Enter for new line
    messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Send Button Click Handler
    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage();
    });

    // Fetch Messages Function
    function fetchMessages() {
        const url = `api.php?action=get_messages&task_id=${taskId}&DOMAIN=${encodeURIComponent(domain)}&AUTH_ID=${encodeURIComponent(authId)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (loadingSpinner) {
                    loadingSpinner.style.display = 'none';
                }

                if (data.status === 'success') {
                    currentUser = data.current_user;
                    renderMessages(data.messages || []);
                } else {
                    console.error('API Error:', data.message);
                }
            })
            .catch(err => {
                console.error('Fetch Error:', err);
            });
    }

    // Render Messages UI
    function renderMessages(messages) {
        if (messages.length === 0) {
            messagesList.innerHTML = `
                <div class="empty-chat">
                    <div class="icon">💬</div>
                    <p>No messages yet in this task discussion.</p>
                    <p style="font-size:12px;">Select visibility level below and start the conversation!</p>
                </div>
            `;
            return;
        }

        const isAtBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 50;

        let html = '';
        messages.forEach(msg => {
            const isSelf = msg.is_self;
            const senderInitial = (msg.sender_name || 'U').charAt(0).toUpperCase();

            let visBadgeHtml = '';
            if (msg.visibility === 'public') {
                visBadgeHtml = `<span class="visibility-badge public">🌐 Public</span>`;
            } else if (msg.visibility === 'internal') {
                visBadgeHtml = `<span class="visibility-badge internal">🔒 Internal Team</span>`;
            } else if (msg.visibility === 'creator_assignee') {
                visBadgeHtml = `<span class="visibility-badge creator_assignee">👥 Creator & Assignee</span>`;
            }

            const avatarContent = msg.sender_avatar 
                ? `<img src="${msg.sender_avatar}" alt="${msg.sender_name}">`
                : senderInitial;

            html += `
                <div class="message-item ${isSelf ? 'self' : ''}" data-id="${msg.id}">
                    <div class="avatar">${avatarContent}</div>
                    <div class="message-content">
                        <div class="message-header">
                            <span class="sender-name">${msg.sender_name}</span>
                            <span class="message-time">${formatTime(msg.created_at)}</span>
                        </div>
                        <div class="bubble">${escapeHtml(msg.message)}</div>
                        ${visBadgeHtml}
                    </div>
                </div>
            `;
        });

        messagesList.innerHTML = html;

        if (isAtBottom) {
            scrollToBottom();
        }
    }

    // Send Message Function
    function sendMessage() {
        const text = messageInput.value.trim();
        if (!text || isSubmitting) return;

        isSubmitting = true;
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.6';

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('task_id', taskId);
        formData.append('DOMAIN', domain);
        formData.append('AUTH_ID', authId);
        formData.append('message', text);
        formData.append('visibility', currentVisibility);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                isSubmitting = false;
                sendBtn.disabled = false;
                sendBtn.style.opacity = '1';

                if (data.status === 'success') {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    fetchMessages();
                    scrollToBottom();
                } else {
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                isSubmitting = false;
                sendBtn.disabled = false;
                sendBtn.style.opacity = '1';
                console.error('Send Error:', err);
            });
    }

    function scrollToBottom() {
        setTimeout(() => {
            chatBody.scrollTop = chatBody.scrollHeight;
            resizeFrame();
        }, 50);
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initial Load & Polling (every 3 seconds)
    fetchMessages();
    pollInterval = setInterval(fetchMessages, 3000);
});
