<?php
require_once __DIR__ . '/crest.php';

$auth = CRest::getAuthData();
$handlerUrl = 'https://keenenter.com/task_chat/index.php';

$results = [];
$hasAuth = !empty($auth['auth_token']) && !empty($auth['domain']);

if ($hasAuth) {
    // 1. Unbind all existing task card placements to remove any duplicates
    CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_TAB']);
    CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_SIDEBAR']);
    CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_TOP_PANEL']);

    // 2. Bind ONLY ONE placement (TASK_VIEW_TAB)
    $bindTab = CRest::call('placement.bind', [
        'PLACEMENT' => 'TASK_VIEW_TAB',
        'HANDLER' => $handlerUrl,
        'TITLE' => 'Task Secure Chat',
        'DESCRIPTION' => 'Custom Chat with Message Visibility Selector',
        'LANG_ALL' => [
            'en' => ['TITLE' => 'Task Secure Chat'],
            'ar' => ['TITLE' => 'محادثة المهام الخاصة']
        ]
    ]);
    $results['TASK_VIEW_TAB'] = $bindTab;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bitrix24 Task Chat Widget Installation</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f7fb; color: #333; padding: 40px; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .card { background: white; border-radius: 12px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); max-width: 600px; width: 100%; text-align: center; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin-bottom: 12px; color: #202b3c; }
        p { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 24px; }
        .status { text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-family: monospace; font-size: 13px; margin-bottom: 24px; word-break: break-all; }
        .success { color: #16a34a; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        .btn { background: #2fc6f6; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }
        .btn:hover { background: #1ab0e0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">💬</div>
        <h1>Bitrix24 Task Chat Installation</h1>

        <?php if ($hasAuth): ?>
            <p>Duplicates removed and single placement bound successfully!</p>
            <div class="status">
                <div><strong>Placement Handler:</strong> <?= htmlspecialchars($handlerUrl) ?></div>
                <div><strong>TASK_VIEW_TAB Registration:</strong> 
                    <span class="<?= isset($results['TASK_VIEW_TAB']['result']) && $results['TASK_VIEW_TAB']['result'] ? 'success' : 'error' ?>">
                        <?= json_encode($results['TASK_VIEW_TAB']) ?>
                    </span>
                </div>
            </div>

            <button class="btn" onclick="finishInstall()">Finish & Save</button>
        <?php else: ?>
            <p class="error">⚠️ Warning: Missing Application Token (AUTH_ID)</p>
            <p>Please open or re-install the application inside Bitrix24 Local Application settings.</p>
        <?php endif; ?>
    </div>

    <script>
        function finishInstall() {
            if (typeof BX24 !== 'undefined') {
                BX24.installFinish();
            } else {
                alert('Installation complete!');
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof BX24 !== 'undefined') {
                BX24.init(function() {
                    console.log('Bitrix24 installation frame initialized');
                });
            }
        });
    </script>
</body>
</html>
