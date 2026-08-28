<?php
/**
 * Database Connection & Schema Setup for Bitrix24 Task Chat Widget
 */

define('DB_FILE', __DIR__ . '/data/chat_database.sqlite');

function getDbConnection() {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Ensure table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS task_chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                sender_id INTEGER NOT NULL,
                sender_name TEXT NOT NULL,
                sender_avatar TEXT DEFAULT '',
                message TEXT NOT NULL,
                visibility TEXT NOT NULL DEFAULT 'public',
                allowed_user_ids TEXT DEFAULT '[]',
                file_attachments TEXT DEFAULT '[]',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_task_id ON task_chat_messages(task_id);
        ");

        // Migration check for existing SQLite databases missing columns
        $cols = $pdo->query("PRAGMA table_info(task_chat_messages)")->fetchAll();
        $hasAllowedUsersCol = false;
        $hasFileAttachmentsCol = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'allowed_user_ids') {
                $hasAllowedUsersCol = true;
            }
            if ($col['name'] === 'file_attachments') {
                $hasFileAttachmentsCol = true;
            }
        }
        if (!$hasAllowedUsersCol) {
            $pdo->exec("ALTER TABLE task_chat_messages ADD COLUMN allowed_user_ids TEXT DEFAULT '[]'");
        }
        if (!$hasFileAttachmentsCol) {
            $pdo->exec("ALTER TABLE task_chat_messages ADD COLUMN file_attachments TEXT DEFAULT '[]'");
        }

        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
