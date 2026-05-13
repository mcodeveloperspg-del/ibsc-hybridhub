<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/enrollments/create.php'));
}

$enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);
$transferDate = trim((string) ($_POST['transfer_date'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));

$errors = [];

if ($enrollmentId < 1) {
    $errors[] = 'Current enrollment is required.';
}

if ($batchId < 1) {
    $errors[] = 'Destination batch is required.';
}

if ($transferDate === '') {
    $errors[] = 'Transfer date is required.';
} elseif (!is_valid_date_string($transferDate)) {
    $errors[] = 'Transfer date is invalid.';
}

if (!in_array($status, ['active', 'completed', 'suspended', 'withdrawn'], true)) {
    $errors[] = 'Invalid enrollment status.';
}

$enrollment = null;

if ($enrollmentId > 0) {
    $enrollmentStatement = $pdo->prepare(
        'SELECT enrollments.id, enrollments.student_id, enrollments.course_id, enrollments.batch_id,
                courses.title AS course_title, courses.course_code,
                batches.batch_name, batches.batch_year,
                users.first_name, users.last_name
         FROM enrollments
         INNER JOIN courses ON courses.id = enrollments.course_id
         INNER JOIN batches ON batches.id = enrollments.batch_id
         INNER JOIN users ON users.id = enrollments.student_id
         WHERE enrollments.id = :id
         LIMIT 1'
    );
    $enrollmentStatement->execute(['id' => $enrollmentId]);
    $enrollment = $enrollmentStatement->fetch() ?: null;

    if ($enrollment === null) {
        $errors[] = 'The selected enrollment could not be found.';
    }
}

if ($enrollment !== null && $batchId > 0) {
    if ((int) $enrollment['batch_id'] === $batchId) {
        $errors[] = 'Choose a different batch for the transfer.';
    }

    $batchStatement = $pdo->prepare(
        'SELECT batches.id
         FROM batches
         INNER JOIN batch_courses ON batch_courses.batch_id = batches.id
         WHERE batches.id = :batch_id AND batch_courses.course_id = :course_id
         LIMIT 1'
    );
    $batchStatement->execute([
        'batch_id' => $batchId,
        'course_id' => (int) $enrollment['course_id'],
    ]);

    if (!$batchStatement->fetch()) {
        $errors[] = 'Destination batch does not include the enrolled course.';
    }
}

if ($errors !== []) {
    $_SESSION['transfer_enrollment_form_state'] = [
        'errors' => $errors,
        'data' => [
            'enrollment_id' => $enrollmentId,
            'batch_id' => $batchId,
            'transfer_date' => $transferDate,
            'status' => $status,
        ],
    ];

    redirect(base_url('admin/enrollments/create.php'));
}

$currentUser = current_user();

try {
    $statement = $pdo->prepare(
        'UPDATE enrollments
         SET batch_id = :batch_id,
             enrollment_date = :enrollment_date,
             status = :status,
             created_by = :created_by
         WHERE id = :id'
    );
    $statement->execute([
        'batch_id' => $batchId,
        'enrollment_date' => $transferDate,
        'status' => $status,
        'created_by' => $currentUser['id'] ?? null,
        'id' => $enrollmentId,
    ]);
} catch (Throwable $throwable) {
    flash_message('Enrollment could not be transferred: ' . $throwable->getMessage(), 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

flash_message('Student transferred successfully.', 'success');
redirect(base_url('admin/enrollments/create.php'));
