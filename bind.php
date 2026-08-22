<?php
/**
 * Standalone Script to Bind Bitrix24 Task Placement via Webhook or REST
 */
require_once __DIR__ . '/crest.php';

// Calculate current public URL or default handler URL
$domainUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$handlerUrl = $domainUrl . '/index.php';

echo "=== Bitrix24 Placement Registration ===\n";
echo "Handler URL: " . $handlerUrl . "\n\n";

// Bind TASK_VIEW_TAB
$resTab = CRest::call('placement.bind', [
    'PLACEMENT' => 'TASK_VIEW_TAB',
    'HANDLER' => $handlerUrl,
    'TITLE' => 'Task Secure Chat',
    'DESCRIPTION' => 'Custom Task Chat Widget with Message Visibility Selector'
]);

echo "1. TASK_VIEW_TAB Result:\n";
print_r($resTab);
echo "\n";

// Bind TASK_VIEW_SIDEBAR
$resSidebar = CRest::call('placement.bind', [
    'PLACEMENT' => 'TASK_VIEW_SIDEBAR',
    'HANDLER' => $handlerUrl,
    'TITLE' => 'Task Secure Chat',
    'DESCRIPTION' => 'Custom Task Chat Widget with Message Visibility Selector'
]);

echo "2. TASK_VIEW_SIDEBAR Result:\n";
print_r($resSidebar);
echo "\n";
