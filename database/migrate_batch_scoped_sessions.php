<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$columns = $pdo->query('SHOW COLUMNS FROM sessions')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('batch_id', $columns, true)) {
    $pdo->exec('ALTER TABLE sessions ADD COLUMN batch_id INT UNSIGNED DEFAULT NULL AFTER topic_id');
    $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT fk_sessions_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE');
    $pdo->exec('ALTER TABLE sessions ADD INDEX idx_sessions_batch_id (batch_id)');
}

if (!in_array('fallback_source_session_id', $columns, true)) {
    $pdo->exec('ALTER TABLE sessions ADD COLUMN fallback_source_session_id INT UNSIGNED DEFAULT NULL AFTER batch_id');
    $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT fk_sessions_fallback_source FOREIGN KEY (fallback_source_session_id) REFERENCES sessions(id) ON DELETE SET NULL');
    $pdo->exec('ALTER TABLE sessions ADD INDEX idx_sessions_fallback_source_id (fallback_source_session_id)');
}

if (!in_array('recording_source', $columns, true)) {
    $pdo->exec("ALTER TABLE sessions ADD COLUMN recording_source ENUM('current_batch','previous_session') NOT NULL DEFAULT 'current_batch' AFTER fallback_source_session_id");
}

$indexes = $pdo->query("SHOW INDEX FROM sessions WHERE Key_name = 'uq_sessions_batch_topic_title'")->fetchAll();
if ($indexes === []) {
    $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT uq_sessions_batch_topic_title UNIQUE (batch_id, topic_id, session_title)');
}

echo "Batch-scoped session fields are ready.";
