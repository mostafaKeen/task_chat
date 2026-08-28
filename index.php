<?php
require_once __DIR__ . '/crest.php';

// Store Auth Data in Session
$auth = CRest::getAuthData();

// Extract placement options
$placement = $_POST['PLACEMENT'] ?? $_GET['PLACEMENT'] ?? 'TASK_VIEW_TAB';
$rawOptions = $_POST['PLACEMENT_OPTIONS'] ?? $_GET['PLACEMENT_OPTIONS'] ?? '{}';

$options = json_decode($rawOptions, true);
if (!is_array($options)) {
    $options = [];
}

$taskId = intval($options['taskId'] ?? $_GET['taskId'] ?? 0);
$domain = htmlspecialchars($auth['domain'] ?? '');
$authId = htmlspecialchars($auth['auth_token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Secure Chat</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bitrix24 JS SDK -->
    <script src="//api.bitrix24.com/api/v1/"></script>
    <link rel="stylesheet" href="assets/css/widget.css?v=<?= time() ?>">
</head>
<body>

<div class="chat-container">
    <!-- Chat Header -->
    <div class="chat-header">
        <div class="header-info">
            <div class="header-icon">💬</div>
            <div>
                <h3 class="header-title">Task Secure Chat</h3>
                <span class="header-subtitle">Task #<?= $taskId ?: 'General' ?> • Multi-visibility Discussion</span>
            </div>
        </div>
        <div class="header-badges">
            <span class="status-indicator">
                <span class="dot"></span> Active
            </span>
        </div>
    </div>

    <!-- Chat Messages Body -->
    <div class="chat-body" id="chatBody">
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner"></div>
            <p>Loading messages & visibility rules...</p>
        </div>
        <div class="messages-list" id="messagesList"></div>
    </div>

    <!-- Chat Input Footer -->
    <div class="chat-footer">
        <!-- Visibility Selector Toolbar -->
        <div class="visibility-toolbar">
            <span class="toolbar-label">Visibility Before Sending:</span>
            <div class="visibility-selector">
                <label class="visibility-option active" data-value="public" title="Visible to all task participants">
                    <input type="radio" name="msgVisibility" value="public" checked>
                    <span class="icon">🌐</span>
                    <span class="text">Public</span>
                </label>
                <label class="visibility-option" data-value="internal" title="Visible only to internal team members">
                    <input type="radio" name="msgVisibility" value="internal">
                    <span class="icon">🔒</span>
                    <span class="text">Internal Team</span>
                </label>
                <label class="visibility-option" data-value="creator_assignee" title="Visible only to Task Creator & Assignee">
                    <input type="radio" name="msgVisibility" value="creator_assignee">
                    <span class="icon">👥</span>
                    <span class="text">Creator & Assignee</span>
                </label>
                <label class="visibility-option" data-value="specific_users" title="Visible only to selected specific users">
                    <input type="radio" name="msgVisibility" value="specific_users">
                    <span class="icon">🎯</span>
                    <span class="text">Specific Users</span>
                </label>
            </div>
        </div>

        <!-- Specific Users Picker Box (Hidden by default) -->
        <div class="user-picker-container" id="userPickerContainer" style="display: none;">
            <div class="picker-header">
                <span>Select Target Users:</span>
                <span class="picker-hint">Check users who can view this message</span>
            </div>
            <div class="user-checkbox-list" id="userCheckboxList">
                <p class="picker-loading">Loading task participants...</p>
            </div>
        </div>

        <!-- File Attachment Preview Area (Hidden by default) -->
        <div class="attachment-preview-container" id="attachmentPreviewContainer" style="display: none;">
            <div class="attachment-preview-chip">
                <span class="file-icon">📎</span>
                <span class="file-name" id="previewFileName">filename.ext</span>
                <span class="file-size" id="previewFileSize">(0 KB)</span>
                <button type="button" class="remove-attachment-btn" id="removeAttachmentBtn" title="Remove attachment">&times;</button>
            </div>
        </div>

        <!-- Input Box -->
        <form class="chat-input-form" id="chatForm" onsubmit="return false;">
            <div class="input-wrapper">
                <button type="button" class="attach-btn" id="attachBtn" title="Attach file from computer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                </button>
                <input type="file" id="fileInput" name="attachment" style="display: none;">
                <textarea 
                    id="messageInput" 
                    class="chat-textarea" 
                    placeholder="Type your message or attach a file... (Press Shift+Enter for new line, Enter to send)" 
                    rows="1"
                ></textarea>
                <button type="submit" class="send-btn" id="sendBtn">
                    <span>Send</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.APP_CONFIG = {
        taskId: <?= $taskId ?>,
        domain: "<?= $domain ?>",
        authId: "<?= $authId ?>"
    };
</script>
<script src="assets/js/widget.js?v=<?= time() ?>"></script>
</body>
</html>
