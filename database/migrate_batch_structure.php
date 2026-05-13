<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function batch_name_from_number_local(int $batchNumber): string
{
    return 'Batch ' . $batchNumber;
}

function batch_intake_code_local(int $batchNumber, int $batchYear): string
{
    return 'BATCH-' . $batchNumber . '-' . $batchYear;
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $statement->execute(['table_name' => $table]);
    return (int) $statement->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $statement->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $statement->execute(['table_name' => $table, 'index_name' => $index]);
    return (int) $statement->fetchColumn() > 0;
}

function constraintExists(PDO $pdo, string $table, string $constraint): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name');
    $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
    return (int) $statement->fetchColumn() > 0;
}

try {
    if (!tableExists($pdo, 'batch_courses')) {
        $pdo->exec(
            'CREATE TABLE batch_courses (
                batch_id INT UNSIGNED NOT NULL,
                course_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (batch_id, course_id),
                CONSTRAINT fk_batch_courses_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
                CONSTRAINT fk_batch_courses_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                INDEX idx_batch_courses_course_id (course_id)
            ) ENGINE=InnoDB'
        );
    }

    if (!columnExists($pdo, 'batches', 'batch_number')) {
        $pdo->exec('ALTER TABLE batches ADD COLUMN batch_number TINYINT UNSIGNED NULL AFTER id');
    }

    if (!columnExists($pdo, 'batches', 'batch_year')) {
        $pdo->exec('ALTER TABLE batches ADD COLUMN batch_year SMALLINT UNSIGNED NULL AFTER batch_number');
    }

    if (columnExists($pdo, 'batches', 'course_id')) {
        $legacyBatches = $pdo->query('SELECT id, course_id, batch_name, start_date FROM batches ORDER BY id ASC')->fetchAll();
        $seenKeys = [];
        $updateStatement = $pdo->prepare(
            'UPDATE batches
             SET batch_number = :batch_number,
                 batch_year = :batch_year,
                 batch_name = :batch_name,
                 intake_code = :intake_code
             WHERE id = :id'
        );
        $linkStatement = $pdo->prepare('INSERT IGNORE INTO batch_courses (batch_id, course_id) VALUES (:batch_id, :course_id)');

        foreach ($legacyBatches as $batch) {
            $batchNumber = 1;
            if (preg_match('/(\d+)/', (string) $batch['batch_name'], $matches)) {
                $batchNumber = max(1, (int) $matches[1]);
            }

            $batchYear = (int) date('Y', strtotime((string) $batch['start_date']));
            $key = $batchYear . '-' . $batchNumber;

            if (isset($seenKeys[$key])) {
                throw new RuntimeException('Migration stopped because duplicate yearly batch numbers already exist for batch IDs ' . $seenKeys[$key] . ' and ' . $batch['id'] . '.');
            }

            $seenKeys[$key] = (string) $batch['id'];

            $updateStatement->execute([
                'batch_number' => $batchNumber,
                'batch_year' => $batchYear,
                'batch_name' => batch_name_from_number_local($batchNumber),
                'intake_code' => batch_intake_code_local($batchNumber, $batchYear),
                'id' => (int) $batch['id'],
            ]);

            $linkStatement->execute([
                'batch_id' => (int) $batch['id'],
                'course_id' => (int) $batch['course_id'],
            ]);
        }

        if (constraintExists($pdo, 'batches', 'fk_batches_course')) {
            $pdo->exec('ALTER TABLE batches DROP FOREIGN KEY fk_batches_course');
        }

        if (indexExists($pdo, 'batches', 'uq_batches_course_name')) {
            $pdo->exec('ALTER TABLE batches DROP INDEX uq_batches_course_name');
        }

        if (indexExists($pdo, 'batches', 'idx_batches_course_id')) {
            $pdo->exec('ALTER TABLE batches DROP INDEX idx_batches_course_id');
        }

        $pdo->exec('ALTER TABLE batches DROP COLUMN course_id');
    }

    $pdo->exec('ALTER TABLE batches MODIFY COLUMN batch_number TINYINT UNSIGNED NOT NULL');
    $pdo->exec('ALTER TABLE batches MODIFY COLUMN batch_year SMALLINT UNSIGNED NOT NULL');

    if (!indexExists($pdo, 'batches', 'uq_batches_year_number')) {
        $pdo->exec('ALTER TABLE batches ADD CONSTRAINT uq_batches_year_number UNIQUE (batch_year, batch_number)');
    }

    if (!indexExists($pdo, 'batches', 'idx_batches_year')) {
        $pdo->exec('ALTER TABLE batches ADD INDEX idx_batches_year (batch_year)');
    }

    if (indexExists($pdo, 'enrollments', 'uq_enrollments_student_batch')) {
        $pdo->exec('ALTER TABLE enrollments DROP INDEX uq_enrollments_student_batch');
    }

    if (indexExists($pdo, 'enrollments', 'uq_enrollments_student_batch_course')) {
        $pdo->exec('ALTER TABLE enrollments DROP INDEX uq_enrollments_student_batch_course');
    }

    if (!indexExists($pdo, 'enrollments', 'uq_enrollments_student')) {
        $pdo->exec('ALTER TABLE enrollments ADD CONSTRAINT uq_enrollments_student UNIQUE (student_id)');
    }

    echo "Batch migration completed successfully." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Batch migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
