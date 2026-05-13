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

$studentId = (int) ($_POST['student_id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);
$enrollmentDate = trim((string) ($_POST['enrollment_date'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));

$errors = [];

if ($studentId < 1) {
    $errors[] = 'Student is required.';
}

if ($courseId < 1) {
    $errors[] = 'Course is required.';
}

if ($batchId < 1) {
    $errors[] = 'Batch is required.';
}

if ($enrollmentDate === '') {
    $errors[] = 'Enrollment date is required.';
} elseif (!is_valid_date_string($enrollmentDate)) {
    $errors[] = 'Enrollment date is invalid.';
}

if (!in_array($status, ['active', 'completed', 'suspended', 'withdrawn'], true)) {
    $errors[] = 'Invalid enrollment status.';
}

$batchStatement = $pdo->prepare(
    'SELECT batches.id
     FROM batches
     INNER JOIN batch_courses ON batch_courses.batch_id = batches.id
     WHERE batches.id = :batch_id AND batch_courses.course_id = :course_id
     LIMIT 1'
);
$batchStatement->execute(['batch_id' => $batchId, 'course_id' => $courseId]);

if (!$batchStatement->fetch()) {
    $errors[] = 'Selected batch does not include the selected course.';
}

$existingEnrollmentStatement = $pdo->prepare(
    'SELECT enrollments.id, enrollments.course_id, enrollments.batch_id, courses.title AS course_title, courses.course_code, batches.batch_name, batches.batch_year
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     WHERE enrollments.student_id = :student_id
     LIMIT 1'
);
$existingEnrollmentStatement->execute(['student_id' => $studentId]);
$existingEnrollment = $existingEnrollmentStatement->fetch();

if ($existingEnrollment) {
    $currentEnrollmentLabel = (string) $existingEnrollment['course_title'] . ' (' . (string) $existingEnrollment['course_code'] . ') / '
        . (string) $existingEnrollment['batch_name'] . ' (' . (string) $existingEnrollment['batch_year'] . ')';
    $errors[] = 'That student is already enrolled in ' . $currentEnrollmentLabel . '. Use the transfer option to move the student to another batch.';
}

if ($errors !== []) {
    $_SESSION['enrollment_form_state'] = [
        'errors' => $errors,
        'data' => [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'batch_id' => $batchId,
            'enrollment_date' => $enrollmentDate,
            'status' => $status,
        ],
    ];

    redirect(base_url('admin/enrollments/create.php'));
}

$currentUser = current_user();

$pdo->beginTransaction();

try {
    $statement = $pdo->prepare(
        'INSERT INTO enrollments (student_id, course_id, batch_id, enrollment_date, status, created_by)
         VALUES (:student_id, :course_id, :batch_id, :enrollment_date, :status, :created_by)'
    );
    $statement->execute([
        'student_id' => $studentId,
        'course_id' => $courseId,
        'batch_id' => $batchId,
        'enrollment_date' => $enrollmentDate,
        'status' => $status,
        'created_by' => $currentUser['id'] ?? null,
    ]);

    $pdo->commit();
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash_message('Enrollment could not be saved: ' . $throwable->getMessage(), 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

flash_message('Enrollment saved successfully.', 'success');
redirect(base_url('admin/enrollments/create.php'));

