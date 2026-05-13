<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/lecturer_assignments/index.php'));
}

$assignmentId = (int) ($_POST['id'] ?? 0);
$lecturerId = (int) ($_POST['lecturer_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$unitId = (int) ($_POST['unit_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'active'));

$errors = [];
$redirectPath = base_url('admin/lecturer_assignments/create.php');
$allowedStatuses = ['active', 'inactive'];

if ($lecturerId < 1) {
    $errors[] = 'A lecturer must be selected.';
}

if ($batchId < 1) {
    $errors[] = 'A batch must be selected.';
}

if ($courseId < 1) {
    $errors[] = 'A course must be selected.';
}

if ($unitId < 1) {
    $errors[] = 'A unit must be selected.';
}

if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected status is invalid.';
}

$lecturerRoleId = role_id_by_name($pdo, 'teacher');
$lecturerStatement = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role_id = :role_id LIMIT 1');
$lecturerStatement->execute(['id' => $lecturerId, 'role_id' => $lecturerRoleId]);
if (!$lecturerStatement->fetch()) {
    $errors[] = 'The selected lecturer does not exist.';
}

$batchCourseStatement = $pdo->prepare(
    'SELECT batches.id AS batch_id, batches.start_date, courses.id AS course_id, courses.weeks_per_stage, courses.total_stages
     FROM batches
     INNER JOIN batch_courses ON batch_courses.batch_id = batches.id
     INNER JOIN courses ON courses.id = batch_courses.course_id
     WHERE batches.id = :batch_id AND courses.id = :course_id
     LIMIT 1'
);
$batchCourseStatement->execute(['batch_id' => $batchId, 'course_id' => $courseId]);
$batchCourse = $batchCourseStatement->fetch();

if (!$batchCourse) {
    $errors[] = 'The selected batch does not contain the selected course.';
}

$unitStatement = $pdo->prepare(
    'SELECT units.id, units.course_id, units.stage_id, stages.stage_number
     FROM units
     INNER JOIN stages ON stages.id = units.stage_id
     WHERE units.id = :unit_id
     LIMIT 1'
);
$unitStatement->execute(['unit_id' => $unitId]);
$unit = $unitStatement->fetch();

if (!$unit) {
    $errors[] = 'The selected unit does not exist.';
}

if ($batchCourse && $unit) {
    if ((int) $unit['course_id'] !== $courseId) {
        $errors[] = 'The selected unit does not belong to the selected course.';
    }

    $stageNumber = active_stage_number_for_batch_record(
        $pdo,
        $batchId,
        (string) $batchCourse['start_date'],
        (int) $batchCourse['weeks_per_stage'],
        (int) $batchCourse['total_stages']
    );

    if ($stageNumber === null) {
        $errors[] = 'The system could not determine the current stage for this batch.';
    } elseif ((int) $unit['stage_number'] !== $stageNumber) {
        $errors[] = 'The selected unit is not part of the stage currently in progress for this batch.';
    }
}

if ($assignmentId > 0) {
    $assignmentCheck = $pdo->prepare('SELECT id FROM lecturer_unit_assignments WHERE id = :id LIMIT 1');
    $assignmentCheck->execute(['id' => $assignmentId]);
    if (!$assignmentCheck->fetch()) {
        $errors[] = 'The selected assignment could not be found.';
    }
}

if ($errors !== []) {
    $_SESSION['lecturer_assignment_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $assignmentId,
            'lecturer_id' => $lecturerId,
            'batch_id' => $batchId,
            'course_id' => $courseId,
            'unit_id' => $unitId,
            'status' => $status,
        ],
    ];
    if ($assignmentId > 0) {
        $redirectPath .= '?edit=' . (string) $assignmentId;
    }
    redirect($redirectPath);
}

$existingStatement = $pdo->prepare('SELECT id FROM lecturer_unit_assignments WHERE batch_id = :batch_id AND unit_id = :unit_id LIMIT 1');
$existingStatement->execute(['batch_id' => $batchId, 'unit_id' => $unitId]);
$existingId = (int) $existingStatement->fetchColumn();
$currentUser = current_user();
$assignedBy = (int) ($currentUser['id'] ?? 0);

if ($assignmentId > 0 || $existingId > 0) {
    $targetId = $assignmentId > 0 ? $assignmentId : $existingId;
    $statement = $pdo->prepare(
        'UPDATE lecturer_unit_assignments
         SET lecturer_id = :lecturer_id,
             batch_id = :batch_id,
             unit_id = :unit_id,
             assigned_by = :assigned_by,
             status = :status
         WHERE id = :id'
    );
    $statement->execute([
        'lecturer_id' => $lecturerId,
        'batch_id' => $batchId,
        'unit_id' => $unitId,
        'assigned_by' => $assignedBy > 0 ? $assignedBy : null,
        'status' => $status,
        'id' => $targetId,
    ]);

    flash_message('Unit lecturer assignment updated successfully.', 'success');
    redirect(base_url('admin/lecturer_assignments/index.php'));
}

$statement = $pdo->prepare(
    'INSERT INTO lecturer_unit_assignments (lecturer_id, batch_id, unit_id, assigned_by, status)
     VALUES (:lecturer_id, :batch_id, :unit_id, :assigned_by, :status)'
);
$statement->execute([
    'lecturer_id' => $lecturerId,
    'batch_id' => $batchId,
    'unit_id' => $unitId,
    'assigned_by' => $assignedBy > 0 ? $assignedBy : null,
    'status' => $status,
]);

flash_message('Unit lecturer assignment saved successfully.', 'success');
redirect(base_url('admin/lecturer_assignments/index.php'));
