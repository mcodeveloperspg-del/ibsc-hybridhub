<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function db_table_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function db_table_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        'table_name' => $table,
        'index_name' => $indexName,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function db_table_constraint_exists(PDO $pdo, string $table, string $constraintName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name'
    );
    $statement->execute([
        'table_name' => $table,
        'constraint_name' => $constraintName,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function ensure_batch_scoped_session_schema(PDO $pdo): void
{
    if (!db_table_column_exists($pdo, 'sessions', 'batch_id')) {
        $pdo->exec('ALTER TABLE sessions ADD COLUMN batch_id INT UNSIGNED DEFAULT NULL AFTER topic_id');
    }

    if (!db_table_index_exists($pdo, 'sessions', 'idx_sessions_batch_id')) {
        $pdo->exec('ALTER TABLE sessions ADD INDEX idx_sessions_batch_id (batch_id)');
    }

    if (!db_table_constraint_exists($pdo, 'sessions', 'fk_sessions_batch')) {
        $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT fk_sessions_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE');
    }

    if (!db_table_column_exists($pdo, 'sessions', 'fallback_source_session_id')) {
        $pdo->exec('ALTER TABLE sessions ADD COLUMN fallback_source_session_id INT UNSIGNED DEFAULT NULL AFTER batch_id');
    }

    if (!db_table_column_exists($pdo, 'sessions', 'recording_source')) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN recording_source ENUM('current_batch','previous_session') NOT NULL DEFAULT 'current_batch' AFTER fallback_source_session_id");
    }

    if (!db_table_index_exists($pdo, 'sessions', 'idx_sessions_fallback_source_id')) {
        $pdo->exec('ALTER TABLE sessions ADD INDEX idx_sessions_fallback_source_id (fallback_source_session_id)');
    }

    if (!db_table_constraint_exists($pdo, 'sessions', 'fk_sessions_fallback_source')) {
        $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT fk_sessions_fallback_source FOREIGN KEY (fallback_source_session_id) REFERENCES sessions(id) ON DELETE SET NULL');
    }

    if (!db_table_constraint_exists($pdo, 'sessions', 'uq_sessions_batch_topic_title')) {
        try {
            $pdo->exec('ALTER TABLE sessions ADD CONSTRAINT uq_sessions_batch_topic_title UNIQUE (batch_id, topic_id, session_title)');
        } catch (PDOException) {
            // Keep the application running even if legacy data prevents the unique key from being added immediately.
        }
    }
}

function database_env_value(string $key, string $default, bool $allowEmpty = false): string
{
    $configuredValue = app_env($key);
    if (APP_ENV === 'production' && ($configuredValue === null || (!$allowEmpty && trim($configuredValue) === ''))) {
        throw new RuntimeException($key . ' must be configured for production.');
    }

    return (string) ($configuredValue ?? $default);
}

$dbHost = database_env_value('DB_HOST', '127.0.0.1');
$dbPort = database_env_value('DB_PORT', '3306');
$dbName = database_env_value('DB_DATABASE', 'hybrid_learning_hub');
$dbUser = database_env_value('DB_USERNAME', 'root');
$dbPass = database_env_value('DB_PASSWORD', '');
$dbCharset = database_env_value('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());

    if (defined('APP_DEBUG') && APP_DEBUG) {
        exit('Database connection failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    http_response_code(500);
    exit('The application is temporarily unavailable. Please try again later.');
}
