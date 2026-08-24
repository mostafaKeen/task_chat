<?php
header('Content-Type: application/json');
require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/db.php';

$action = $_REQUEST['action'] ?? '';
$taskId = intval($_REQUEST['task_id'] ?? 0);

if (!$taskId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing task_id']);
    exit;
}

$pdo = getDbConnection();

// Get Current User Info from Bitrix24
$currentUserRes = CRest::call('user.current');
if (isset($currentUserRes['error'])) {
    echo json_encode(['status' => 'error', 'message' => 'Bitrix24 Auth Error: ' . ($currentUserRes['error_description'] ?? $currentUserRes['error'])]);
    exit;
}

$currentUser = $currentUserRes['result'] ?? [];
$currentUserId = intval($currentUser['ID'] ?? 0);
$currentUserName = trim(($currentUser['NAME'] ?? '') . ' ' . ($currentUser['LAST_NAME'] ?? ''));
$currentUserAvatar = $currentUser['PERSONAL_PHOTO'] ?? '';

// Get Task Details to Check User Roles & Get Participants
$taskRes = CRest::call('tasks.task.get', ['taskId' => $taskId]);
$taskData = $taskRes['result']['task'] ?? [];

$creatorId = intval($taskData['createdBy'] ?? 0);
$responsibleId = intval($taskData['responsibleId'] ?? 0);

$accomplices = [];
if (!empty($taskData['accomplices'])) {
    foreach ((array)$taskData['accomplices'] as $acc) {
        $accomplices[] = intval(is_array($acc) ? ($acc['id'] ?? $acc['ID'] ?? 0) : $acc);
    }
}

$auditors = [];
if (!empty($taskData['auditors'])) {
    foreach ((array)$taskData['auditors'] as $aud) {
        $auditors[] = intval(is_array($aud) ? ($aud['id'] ?? $aud['ID'] ?? 0) : $aud);
    }
}

$isCreator = ($currentUserId === $creatorId);
$isResponsible = ($currentUserId === $responsibleId);
$isAccomplice = in_array($currentUserId, $accomplices);
$isAuditor = in_array($currentUserId, $auditors);

$isTeamMember = $isCreator || $isResponsible || $isAccomplice || $isAuditor;

// Gather all participant User IDs
$participantIds = array_unique(array_filter(array_merge([$creatorId, $responsibleId], $accomplices, $auditors)));

// Fetch participant details from Bitrix24 user.get
$participants = [];
if (!empty($participantIds)) {
    $userGetRes = CRest::call('user.get', ['FILTER' => ['ID' => array_values($participantIds)]]);
    $usersData = $userGetRes['result'] ?? [];

    foreach ($usersData as $u) {
        $uId = intval($u['ID']);
        $uName = trim(($u['NAME'] ?? '') . ' ' . ($u['LAST_NAME'] ?? ''));
        if (empty($uName)) {
            $uName = 'User #' . $uId;
        }

        $roles = [];
        if ($uId === $creatorId) $roles[] = 'Creator';
        if ($uId === $responsibleId) $roles[] = 'Assignee';
        if (in_array($uId, $accomplices)) $roles[] = 'Accomplice';
        if (in_array($uId, $auditors)) $roles[] = 'Auditor';

        $participants[] = [
            'id' => $uId,
            'name' => htmlspecialchars($uName),
            'avatar' => $u['PERSONAL_PHOTO'] ?? '',
            'role' => implode(', ', $roles)
        ];
    }
}

if ($action === 'get_messages') {
    // Retrieve all messages for this task
    $stmt = $pdo->prepare("SELECT * FROM task_chat_messages WHERE task_id = :task_id ORDER BY created_at ASC");
    $stmt->execute([':task_id' => $taskId]);
    $allMessages = $stmt->fetchAll();

    $filteredMessages = [];

    foreach ($allMessages as $msg) {
        $vis = $msg['visibility'];
        $senderId = intval($msg['sender_id']);
        $allowedUserIds = json_decode($msg['allowed_user_ids'] ?? '[]', true);
        if (!is_array($allowedUserIds)) {
            $allowedUserIds = [];
        }
        $allowedUserIds = array_map('intval', $allowedUserIds);

        $canView = false;

        if ($senderId === $currentUserId) {
            // Sender can always view their own message
            $canView = true;
        } elseif ($vis === 'public') {
            // Public messages are visible to everyone
            $canView = true;
        } elseif ($vis === 'internal') {
            // Internal messages visible to team members
            if ($isTeamMember) {
                $canView = true;
            }
        } elseif ($vis === 'creator_assignee') {
            // Visible only to creator and assignee
            if ($isCreator || $isResponsible) {
                $canView = true;
            }
        } elseif ($vis === 'specific_users') {
            // Visible only to specified user IDs
            if (in_array($currentUserId, $allowedUserIds)) {
                $canView = true;
            }
        }

        if ($canView) {
            $filteredMessages[] = [
                'id' => intval($msg['id']),
                'sender_id' => $senderId,
                'sender_name' => htmlspecialchars($msg['sender_name']),
                'sender_avatar' => $msg['sender_avatar'],
                'message' => htmlspecialchars($msg['message']),
                'visibility' => $msg['visibility'],
                'allowed_user_ids' => $allowedUserIds,
                'created_at' => $msg['created_at'],
                'is_self' => ($senderId === $currentUserId)
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'current_user' => [
            'id' => $currentUserId,
            'name' => $currentUserName,
            'avatar' => $currentUserAvatar,
            'is_creator' => $isCreator,
            'is_responsible' => $isResponsible,
            'is_team_member' => $isTeamMember
        ],
        'participants' => $participants,
        'messages' => $filteredMessages
    ]);
    exit;
}

if ($action === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';

    $allowedVisibilities = ['public', 'internal', 'creator_assignee', 'specific_users'];
    if (!in_array($visibility, $allowedVisibilities)) {
        $visibility = 'public';
    }

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message content cannot be empty']);
        exit;
    }

    $rawAllowed = $_POST['allowed_user_ids'] ?? '[]';
    if (is_array($rawAllowed)) {
        $allowedUserIds = array_map('intval', $rawAllowed);
    } else {
        $decoded = json_decode($rawAllowed, true);
        $allowedUserIds = is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    if ($visibility === 'specific_users' && empty($allowedUserIds)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select at least one specific user for this message visibility.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO task_chat_messages (task_id, sender_id, sender_name, sender_avatar, message, visibility, allowed_user_ids)
        VALUES (:task_id, :sender_id, :sender_name, :sender_avatar, :message, :visibility, :allowed_user_ids)
    ");

    $stmt->execute([
        ':task_id' => $taskId,
        ':sender_id' => $currentUserId,
        ':sender_name' => $currentUserName,
        ':sender_avatar' => $currentUserAvatar,
        ':message' => $message,
        ':visibility' => $visibility,
        ':allowed_user_ids' => json_encode(array_values($allowedUserIds))
    ]);

    echo json_encode([
        'status' => 'success',
        'message_id' => $pdo->lastInsertId()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
