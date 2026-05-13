<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$migrationFiles = [
    '20260326_password_reset_flag' => __DIR__ . '/migrate_password_reset_flag.php',
    '20260326_batch_structure' => __DIR__ . '/migrate_batch_structure.php',
    '20260326_batch_stage_schedule' => __DIR__ . '/migrate_batch_stage_schedule.php',
    '20260326_single_student_enrollment' => __DIR__ . '/migrate_single_student_enrollment.php',
    '20260326_student_import_fields' => __DIR__ . '/migrate_student_import_fields.php',
    '20260326_batch_scoped_sessions' => __DIR__ . '/migrate_batch_scoped_sessions.php',
    '20260326_session_recording_source' => __DIR__ . '/migrate_session_recording_source.php',
    '20260326_lecturer_unit_assignments' => __DIR__ . '/migrate_lecturer_unit_assignments.php',
    '20260326_module_access' => __DIR__ . '/migrate_module_access.php',
];

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(150) NOT NULL PRIMARY KEY,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$alreadyRun = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$alreadyRun = array_flip(array_map('strval', $alreadyRun));

$phpBinary = PHP_BINARY;
if ($phpBinary === '') {
    fwrite(STDERR, 'Unable to locate the PHP CLI binary.' . PHP_EOL);
    exit(1);
}

$insertStatement = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');

foreach ($migrationFiles as $migration => $file) {
    if (isset($alreadyRun[$migration])) {
        echo '[skip] ' . $migration . PHP_EOL;
        continue;
    }

    if (!is_file($file)) {
        fwrite(STDERR, '[fail] Missing migration file: ' . $file . PHP_EOL);
        exit(1);
    }

    echo '[run] ' . $migration . PHP_EOL;
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($file);
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    foreach ($output as $line) {
        echo '  ' . $line . PHP_EOL;
    }

    if ($exitCode !== 0) {
        fwrite(STDERR, '[fail] ' . $migration . ' exited with code ' . $exitCode . PHP_EOL);
        exit($exitCode);
    }

    $insertStatement->execute(['migration' => $migration]);
    echo '[done] ' . $migration . PHP_EOL;
}

echo 'All migrations are complete.' . PHP_EOL;
