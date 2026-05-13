<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function enrollmentIndexExists(PDO $pdo, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        'table_name' => 'enrollments',
        'index_name' => $index,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

try {
    $pdo->exec(
        'DELETE older
         FROM enrollments older
         INNER JOIN enrollments newer
            ON newer.student_id = older.student_id
           AND (
                newer.updated_at > older.updated_at
                OR (newer.updated_at = older.updated_at AND newer.id > older.id)
           )'
    );

    if (enrollmentIndexExists($pdo, 'uq_enrollments_student_batch')) {
        $pdo->exec('ALTER TABLE enrollments DROP INDEX uq_enrollments_student_batch');
    }

    if (enrollmentIndexExists($pdo, 'uq_enrollments_student_batch_course')) {
        $pdo->exec('ALTER TABLE enrollments DROP INDEX uq_enrollments_student_batch_course');
    }

    if (!enrollmentIndexExists($pdo, 'uq_enrollments_student')) {
        $pdo->exec('ALTER TABLE enrollments ADD CONSTRAINT uq_enrollments_student UNIQUE (student_id)');
    }

    echo "Single student enrollment migration completed successfully." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Single student enrollment migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
