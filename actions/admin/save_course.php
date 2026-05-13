<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

function sync_course_stages(PDO $pdo, int $courseId, int $totalStages, int $weeksPerStage): void
{
    $selectStatement = $pdo->prepare('SELECT id, stage_number FROM stages WHERE course_id = :course_id');
    $selectStatement->execute(['course_id' => $courseId]);

    $existingStages = [];

    foreach ($selectStatement->fetchAll() as $stage) {
        $existingStages[(int) $stage['stage_number']] = (int) $stage['id'];
    }

    $upsertStatement = $pdo->prepare(
        'INSERT INTO stages (course_id, stage_number, title, description, week_start, week_end, status)
         VALUES (:course_id, :stage_number, :title, :description, :week_start, :week_end, :status)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            week_start = VALUES(week_start),
            week_end = VALUES(week_end),
            status = VALUES(status)'
    );

    for ($stageNumber = 1; $stageNumber <= $totalStages; $stageNumber++) {
        $weekStart = (($stageNumber - 1) * $weeksPerStage) + 1;
        $weekEnd = $stageNumber * $weeksPerStage;

        $upsertStatement->execute([
            'course_id' => $courseId,
            'stage_number' => $stageNumber,
            'title' => 'Stage ' . $stageNumber,
            'description' => 'Weeks ' . $weekStart . ' to ' . $weekEnd . ' for this course.',
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'status' => 'active',
        ]);
    }

    $deactivateStatement = $pdo->prepare(
        'UPDATE stages
         SET status = :status
         WHERE course_id = :course_id AND stage_number > :total_stages'
    );

    $deactivateStatement->execute([
        'status' => 'inactive',
        'course_id' => $courseId,
        'total_stages' => $totalStages,
    ]);
}

if (!is_post_request()) {
    redirect(base_url('admin/courses/index.php'));
}

$courseId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$courseCode = strtoupper(trim((string) ($_POST['course_code'] ?? '')));
$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$durationMonths = (int) ($_POST['duration_months'] ?? 0);
$totalStages = (int) ($_POST['total_stages'] ?? 0);
$weeksPerStage = (int) ($_POST['weeks_per_stage'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'draft'));

$allowedStatuses = ['draft', 'active', 'archived'];
$errors = [];

if ($courseCode === '') {
    $errors[] = 'Course code is required.';
}

if ($title === '') {
    $errors[] = 'Course title is required.';
}

if ($durationMonths < 1) {
    $errors[] = 'Duration in months must be at least 1.';
}

if ($totalStages < 1) {
    $errors[] = 'Total stages must be at least 1.';
}

if ($weeksPerStage < 1) {
    $errors[] = 'Weeks per stage must be at least 1.';
}

if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected status is invalid.';
}

$duplicateSql = 'SELECT id FROM courses WHERE course_code = :course_code';
$params = ['course_code' => $courseCode];

if ($courseId > 0) {
    $duplicateSql .= ' AND id != :id';
    $params['id'] = $courseId;
}

$duplicateStatement = $pdo->prepare($duplicateSql);
$duplicateStatement->execute($params);

if ($duplicateStatement->fetch()) {
    $errors[] = 'That course code is already in use.';
}

if ($errors !== []) {
    $_SESSION['course_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $courseId,
            'course_code' => $courseCode,
            'title' => $title,
            'description' => $description,
            'duration_months' => $durationMonths,
            'total_stages' => $totalStages,
            'weeks_per_stage' => $weeksPerStage,
            'status' => $status,
        ],
    ];

    $redirectUrl = base_url('admin/courses/form.php');

    if ($courseId > 0) {
        $redirectUrl .= '?edit=' . (string) $courseId;
    }

    redirect($redirectUrl);
}

$pdo->beginTransaction();

try {
    if ($courseId > 0) {
        $updateStatement = $pdo->prepare(
            'UPDATE courses
             SET course_code = :course_code,
                 title = :title,
                 description = :description,
                 duration_months = :duration_months,
                 total_stages = :total_stages,
                 weeks_per_stage = :weeks_per_stage,
                 status = :status
             WHERE id = :id'
        );

        $updateStatement->execute([
            'course_code' => $courseCode,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'duration_months' => $durationMonths,
            'total_stages' => $totalStages,
            'weeks_per_stage' => $weeksPerStage,
            'status' => $status,
            'id' => $courseId,
        ]);

        sync_course_stages($pdo, $courseId, $totalStages, $weeksPerStage);
        $pdo->commit();

        flash_message('Course updated successfully and stages synced.', 'success');
    } else {
        $currentUser = current_user();

        $insertStatement = $pdo->prepare(
            'INSERT INTO courses (course_code, title, description, duration_months, total_stages, weeks_per_stage, status, created_by)
             VALUES (:course_code, :title, :description, :duration_months, :total_stages, :weeks_per_stage, :status, :created_by)'
        );

        $insertStatement->execute([
            'course_code' => $courseCode,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'duration_months' => $durationMonths,
            'total_stages' => $totalStages,
            'weeks_per_stage' => $weeksPerStage,
            'status' => $status,
            'created_by' => $currentUser['id'] ?? null,
        ]);

        $newCourseId = (int) $pdo->lastInsertId();
        sync_course_stages($pdo, $newCourseId, $totalStages, $weeksPerStage);
        attach_course_to_open_batches($pdo, $newCourseId);
        $pdo->commit();

        flash_message('Course created successfully, stages generated, and the course was linked to all open batches.', 'success');
    }
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash_message('The course could not be saved. Please try again.', 'danger');
    redirect(base_url('admin/courses/form.php'));
}

redirect(base_url('admin/courses/index.php'));
