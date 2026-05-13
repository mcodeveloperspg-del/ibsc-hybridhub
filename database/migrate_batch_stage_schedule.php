<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function batch_stage_table_exists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    return (int) $statement->fetchColumn() > 0;
}

function batch_stage_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $statement->execute(['table_name' => $tableName, 'column_name' => $columnName]);
    return (int) $statement->fetchColumn() > 0;
}

try {
    if (!batch_stage_column_exists($pdo, 'batches', 'total_stages')) {
        $pdo->exec('ALTER TABLE batches ADD COLUMN total_stages TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER intake_code');
    }

    if (!batch_stage_table_exists($pdo, 'batch_stages')) {
        $pdo->exec(
            'CREATE TABLE batch_stages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id INT UNSIGNED NOT NULL,
                stage_number TINYINT UNSIGNED NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_batch_stages_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
                CONSTRAINT uq_batch_stages_batch_stage UNIQUE (batch_id, stage_number),
                INDEX idx_batch_stages_batch_id (batch_id),
                INDEX idx_batch_stages_dates (start_date, end_date)
            ) ENGINE=InnoDB'
        );
    }

    echo "Batch stage schedule migration completed successfully." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Batch stage schedule migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
