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

/**
 * Gets or creates a folder on Bitrix24 Drive for the specific task, using the task title and ID.
 * Sanitizes the folder name to avoid invalid characters.
 */
function getOrCreateTaskFolder($taskId, $taskTitle) {
    // Get application drive storage
    $storageRes = CRest::call('disk.storage.getForApp');
    if (isset($storageRes['error']) || empty($storageRes['result'])) {
        return null;
    }
    
    $storageId = $storageRes['result']['ID'];
    
    // Construct sanitized task folder name: "Task_Title (ID 123)"
    // Sanitize title to remove characters that are not allowed in folder names
    $sanitizedTitle = preg_replace('/[\\\/\:\*\?\"\<\>\|]/', '_', $taskTitle);
    $folderName = trim($sanitizedTitle);
    if (empty($folderName)) {
        $folderName = "Task #" . $taskId;
    } else {
        $folderName = $folderName . " (ID " . $taskId . ")";
    }

    // Check if the folder already exists in the root of the app storage
    $childrenRes = CRest::call('disk.storage.getChildren', ['id' => $storageId]);
    if (!empty($childrenRes['result'])) {
        foreach ($childrenRes['result'] as $child) {
            if ($child['TYPE'] === 'folder' && $child['NAME'] === $folderName) {
                return $child['ID'];
            }
        }
    }

    // Folder does not exist, create it
    $createRes = CRest::call('disk.storage.addFolder', [
        'id' => $storageId,
        'data' => ['NAME' => $folderName]
    ]);
    
    if (!empty($createRes['result']['ID'])) {
        return $createRes['result']['ID'];
    }

    return null;
}

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
            $attachments = json_decode($msg['file_attachments'] ?? '[]', true) ?: [];
            $formattedAttachments = [];
            foreach ($attachments as $file) {
                if (is_array($file)) {
                    // Check and fix relative URLs
                    if (isset($file['download_url']) && strpos($file['download_url'], '/') === 0) {
                        $file['download_url'] = 'https://' . $domain . $file['download_url'];
                    }
                    if (isset($file['detail_url']) && strpos($file['detail_url'], '/') === 0) {
                        $file['detail_url'] = 'https://' . $domain . $file['detail_url'];
                    }
                    $formattedAttachments[] = $file;
                }
            }

            $filteredMessages[] = [
                'id' => intval($msg['id']),
                'sender_id' => $senderId,
                'sender_name' => htmlspecialchars($msg['sender_name'] ?? ''),
                'sender_avatar' => $msg['sender_avatar'] ?? '',
                'message' => htmlspecialchars($msg['message'] ?? ''),
                'visibility' => $msg['visibility'],
                'allowed_user_ids' => $allowedUserIds,
                'file_attachments' => $formattedAttachments,
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

    if (empty($message) && (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK)) {
        echo json_encode(['status' => 'error', 'message' => 'Message content or file attachment is required']);
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

    // If a file is uploaded
    $fileAttachments = [];
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $taskTitle = $taskData['title'] ?? 'Task #' . $taskId;
        $folderId = getOrCreateTaskFolder($taskId, $taskTitle);
        
        if ($folderId) {
            $fileName = $_FILES['attachment']['name'];
            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileBase64 = base64_encode(file_get_contents($fileTmpPath));
            
            // Build permissions (rights) array
            $rights = [];
            
            // The sender should always have full access to their uploaded file
            $rights[] = [
                'TASK_ID' => 79, // disk_access_full
                'ACCESS_CODE' => 'U' . $currentUserId
            ];

            if ($visibility === 'public') {
                // Grant access to all task participants
                foreach ($participantIds as $pId) {
                    if ($pId !== $currentUserId) {
                        $rights[] = [
                            'TASK_ID' => 75, // disk_access_edit
                            'ACCESS_CODE' => 'U' . $pId
                        ];
                    }
                }
            } elseif ($visibility === 'internal') {
                // Grant access to all internal team members (Creator, Assignee, Accomplices, Auditors)
                foreach ($participantIds as $pId) {
                    if ($pId !== $currentUserId) {
                        $rights[] = [
                            'TASK_ID' => 75,
                            'ACCESS_CODE' => 'U' . $pId
                        ];
                    }
                }
            } elseif ($visibility === 'creator_assignee') {
                // Grant access only to Creator and Assignee
                $cAndA = array_unique([$creatorId, $responsibleId]);
                foreach ($cAndA as $pId) {
                    if ($pId !== $currentUserId) {
                        $rights[] = [
                            'TASK_ID' => 75,
                            'ACCESS_CODE' => 'U' . $pId
                        ];
                    }
                }
            } elseif ($visibility === 'specific_users') {
                // Grant access only to specific users
                foreach ($allowedUserIds as $pId) {
                    if ($pId !== $currentUserId) {
                        $rights[] = [
                            'TASK_ID' => 75,
                            'ACCESS_CODE' => 'U' . $pId
                        ];
                    }
                }
            }

            // Upload the file to the task folder on Bitrix24 Disk
            $uploadRes = CRest::call('disk.folder.uploadFile', [
                'id' => $folderId,
                'data' => ['NAME' => $fileName],
                'fileContent' => [$fileName, $fileBase64],
                'generateUniqueName' => true,
                'rights' => $rights
            ]);

            if (isset($uploadRes['result']) && !empty($uploadRes['result']['ID'])) {
                $fileRes = $uploadRes['result'];
                $fileAttachments[] = [
                    'id' => intval($fileRes['ID']),
                    'name' => $fileRes['NAME'],
                    'size' => intval($fileRes['SIZE']),
                    'download_url' => $fileRes['DOWNLOAD_URL'],
                    'detail_url' => $fileRes['DETAIL_URL']
                ];
            } else {
                $uploadErr = $uploadRes['error_description'] ?? ($uploadRes['error'] ?? 'Unknown Disk Error');
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload file to Bitrix24 Drive: ' . $uploadErr]);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to initialize Task folder on Bitrix24 Drive.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO task_chat_messages (task_id, sender_id, sender_name, sender_avatar, message, visibility, allowed_user_ids, file_attachments)
        VALUES (:task_id, :sender_id, :sender_name, :sender_avatar, :message, :visibility, :allowed_user_ids, :file_attachments)
    ");

    $stmt->execute([
        ':task_id' => $taskId,
        ':sender_id' => $currentUserId,
        ':sender_name' => $currentUserName,
        ':sender_avatar' => $currentUserAvatar,
        ':message' => $message,
        ':visibility' => $visibility,
        ':allowed_user_ids' => json_encode(array_values($allowedUserIds)),
        ':file_attachments' => json_encode($fileAttachments)
    ]);

    echo json_encode([
        'status' => 'success',
        'message_id' => $pdo->lastInsertId()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
