<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function password_reset_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) $statement->fetchColumn() > 0;
}

try {
    if (!password_reset_column_exists($pdo, 'users', 'password_must_reset')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN password_must_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }

    echo "Password reset flag migration completed successfully." . PHP_EOL;
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Password reset flag migration failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
