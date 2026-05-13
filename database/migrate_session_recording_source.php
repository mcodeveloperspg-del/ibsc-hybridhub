<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

try {
    if (!db_table_column_exists($pdo, 'sessions', 'recording_source')) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN recording_source ENUM('current_batch','previous_session') NOT NULL DEFAULT 'current_batch' AFTER fallback_source_session_id");
    }

    $pdo->exec(
        "UPDATE sessions
         SET recording_source = 'previous_session'
         WHERE fallback_source_session_id IS NOT NULL"
    );

    echo "Session recording source migration completed successfully." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Session recording source migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
