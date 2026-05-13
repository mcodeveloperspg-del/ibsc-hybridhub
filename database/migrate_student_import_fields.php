<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/enrollment_import.php';

try {
    ensure_student_import_schema($pdo);
    echo "Student import fields are ready." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Student import migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
