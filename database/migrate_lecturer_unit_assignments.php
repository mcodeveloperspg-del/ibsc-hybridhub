<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function table_exists(PDO $pdo, string $tableName): bool
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

function is_valid_date_string_local(string $date): bool
{
    $value = DateTime::createFromFormat('Y-m-d', $date);
    return $value instanceof DateTime && $value->format('Y-m-d') === $date;
}

function active_stage_number_for_batch_local(string $batchStartDate, int $weeksPerStage, int $totalStages, ?string $today = null): ?int
{
    if (!is_valid_date_string_local($batchStartDate) || $weeksPerStage < 1 || $totalStages < 1) {
        return null;
    }
    $today = $today ?: date('Y-m-d');
    if (!is_valid_date_string_local($today)) {
        $today = date('Y-m-d');
    }
    $start = new DateTimeImmutable($batchStartDate);
    $current = new DateTimeImmutable($today);
    $elapsedDays = (int) $start->diff($current)->format('%r%a');
    if ($elapsedDays < 0) {
        return 1;
    }
    $elapsedWeeks = intdiv($elapsedDays, 7);
    $stageNumber = intdiv($elapsedWeeks, $weeksPerStage) + 1;
    return $stageNumber > $totalStages ? $totalStages : $stageNumber;
}

if (!table_exists($pdo, 'lecturer_unit_assignments')) {
    $pdo->exec(
        "CREATE TABLE lecturer_unit_assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lecturer_id INT UNSIGNED NOT NULL,
            batch_id INT UNSIGNED NOT NULL,
            unit_id INT UNSIGNED NOT NULL,
            assigned_by INT UNSIGNED DEFAULT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_lecturer_unit_assignment_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_lecturer_unit_assignment_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
            CONSTRAINT fk_lecturer_unit_assignment_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
            CONSTRAINT fk_lecturer_unit_assignment_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT uq_lecturer_unit_batch UNIQUE (batch_id, unit_id),
            INDEX idx_lecturer_unit_assignment_lecturer_id (lecturer_id),
            INDEX idx_lecturer_unit_assignment_batch_id (batch_id),
            INDEX idx_lecturer_unit_assignment_unit_id (unit_id),
            INDEX idx_lecturer_unit_assignment_status (status)
        ) ENGINE=InnoDB"
    );
    echo "Created lecturer_unit_assignments table." . PHP_EOL;
} else {
    echo "lecturer_unit_assignments table already exists." . PHP_EOL;
}

$assignmentRows = $pdo->query(
    "SELECT teacher_id, course_id, batch_id, assigned_by, status
     FROM teacher_course_assignments
     WHERE status = 'active'"
)->fetchAll();

$insertStatement = $pdo->prepare(
    'INSERT IGNORE INTO lecturer_unit_assignments (lecturer_id, batch_id, unit_id, assigned_by, status)
     VALUES (:lecturer_id, :batch_id, :unit_id, :assigned_by, :status)'
);

$insertedCount = 0;
foreach ($assignmentRows as $assignmentRow) {
    $batchId = (int) ($assignmentRow['batch_id'] ?? 0);
    if ($batchId < 1) {
        continue;
    }

    $batchCourseStatement = $pdo->prepare(
        'SELECT batches.start_date, courses.weeks_per_stage, courses.total_stages
         FROM batches
         INNER JOIN batch_courses ON batch_courses.batch_id = batches.id
         INNER JOIN courses ON courses.id = batch_courses.course_id
         WHERE batches.id = :batch_id AND courses.id = :course_id
         LIMIT 1'
    );
    $batchCourseStatement->execute([
        'batch_id' => $batchId,
        'course_id' => (int) $assignmentRow['course_id'],
    ]);
    $batchCourse = $batchCourseStatement->fetch();

    if (!$batchCourse) {
        continue;
    }

    $stageNumber = active_stage_number_for_batch_local(
        (string) $batchCourse['start_date'],
        (int) $batchCourse['weeks_per_stage'],
        (int) $batchCourse['total_stages']
    );

    if ($stageNumber === null) {
        continue;
    }

    $unitsStatement = $pdo->prepare(
        'SELECT units.id
         FROM units
         INNER JOIN stages ON stages.id = units.stage_id
         WHERE units.course_id = :course_id
           AND stages.stage_number = :stage_number'
    );
    $unitsStatement->execute([
        'course_id' => (int) $assignmentRow['course_id'],
        'stage_number' => $stageNumber,
    ]);
    $unitIds = $unitsStatement->fetchAll(PDO::FETCH_COLUMN);

    foreach ($unitIds as $unitId) {
        $insertStatement->execute([
            'lecturer_id' => (int) $assignmentRow['teacher_id'],
            'batch_id' => $batchId,
            'unit_id' => (int) $unitId,
            'assigned_by' => !empty($assignmentRow['assigned_by']) ? (int) $assignmentRow['assigned_by'] : null,
            'status' => $assignmentRow['status'],
        ]);
        $insertedCount += $insertStatement->rowCount();
    }
}

echo 'Migration complete. Lecturer unit assignments created: ' . $insertedCount . PHP_EOL;
