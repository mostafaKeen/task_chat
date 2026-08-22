<?php
/**
 * Standalone Script to Unbind Duplicates & Register Single Bitrix24 Task Placement
 */
require_once __DIR__ . '/crest.php';

$handlerUrl = 'https://keenenter.com/task_chat/index.php';

echo "=== Cleaning Duplicates & Registering Single Placement ===\n";

// 1. Unbind all previous placements
echo "1. Unbinding previous placements...\n";
$unTab = CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_TAB']);
$unSidebar = CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_SIDEBAR']);
$unTop = CRest::call('placement.unbind', ['PLACEMENT' => 'TASK_VIEW_TOP_PANEL']);

print_r(['unbind_tab' => $unTab, 'unbind_sidebar' => $unSidebar, 'unbind_top' => $unTop]);

// 2. Bind ONLY ONE placement (TASK_VIEW_TAB)
echo "\n2. Binding single placement (TASK_VIEW_TAB)...\n";
$resTab = CRest::call('placement.bind', [
    'PLACEMENT' => 'TASK_VIEW_TAB',
    'HANDLER' => $handlerUrl,
    'TITLE' => 'Task Secure Chat',
    'DESCRIPTION' => 'Custom Task Chat Widget with Message Visibility Selector',
    'LANG_ALL' => [
        'en' => ['TITLE' => 'Task Secure Chat'],
        'ar' => ['TITLE' => 'محادثة المهام الخاصة']
    ]
]);

print_r($resTab);
echo "\nDone!\n";
