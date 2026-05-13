<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/enrollment_import.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/enrollments/create.php'));
}

$preview = $_SESSION['enrollment_import_preview'] ?? null;

if (!is_array($preview) || !isset($preview['rows']) || !is_array($preview['rows'])) {
    flash_message('No import preview is available to confirm.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$summary = $preview['summary'] ?? [];

if ((int) ($summary['blocking_rows'] ?? 0) > 0) {
    flash_message('Please fix the blocking rows in the CSV preview before importing.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

ensure_student_import_schema($pdo);

$previewCourseId = (int) ($preview['course_id'] ?? 0);
$previewBatchId = (int) ($preview['batch_id'] ?? 0);

$targetStatement = $pdo->prepare(
    'SELECT batches.id
     FROM batches
     INNER JOIN batch_courses ON batch_courses.batch_id = batches.id
     WHERE batches.id = :batch_id AND batch_courses.course_id = :course_id
     LIMIT 1'
);
$targetStatement->execute(['batch_id' => $previewBatchId, 'course_id' => $previewCourseId]);

if (!$targetStatement->fetch()) {
    flash_message('The selected import batch and course are no longer linked. Please preview the CSV again.', 'danger');
    unset($_SESSION['enrollment_import_preview']);
    redirect(base_url('admin/enrollments/create.php'));
}

$roleStatement = $pdo->prepare("SELECT id FROM roles WHERE name = 'student' LIMIT 1");
$roleStatement->execute();
$studentRoleId = (int) $roleStatement->fetchColumn();

if ($studentRoleId < 1) {
    flash_message('Student role is not configured in the system.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$currentUser = current_user();
$createdStudents = 0;
$existingStudents = 0;
$createdEnrollments = 0;
$reusedEnrollments = 0;
$transferredEnrollments = 0;

$selectUserById = $pdo->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.student_number, users.date_of_birth,
            roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id
     LIMIT 1"
);
$selectUserByEmail = $pdo->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.student_number, users.date_of_birth,
            roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.email = :email
     LIMIT 1"
);
$selectUserByStudentNumber = $pdo->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.student_number, users.date_of_birth,
            roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.student_number = :student_number
     LIMIT 1"
);
$insertUser = $pdo->prepare(
    'INSERT INTO users (role_id, student_number, first_name, last_name, email, password_hash, password_must_reset, phone, gender, date_of_birth, status)
     VALUES (:role_id, :student_number, :first_name, :last_name, :email, :password_hash, :password_must_reset, :phone, :gender, :date_of_birth, :status)'
);
$selectEnrollment = $pdo->prepare(
    'SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id AND batch_id = :batch_id LIMIT 1'
);
$selectAnyEnrollment = $pdo->prepare(
    'SELECT id FROM enrollments WHERE student_id = :student_id LIMIT 1'
);
$insertEnrollment = $pdo->prepare(
    'INSERT INTO enrollments (student_id, course_id, batch_id, enrollment_date, status, created_by)
     VALUES (:student_id, :course_id, :batch_id, :enrollment_date, :status, :created_by)'
);
$transferEnrollment = $pdo->prepare(
    'UPDATE enrollments
     SET course_id = :course_id,
         batch_id = :batch_id,
         enrollment_date = :enrollment_date,
         status = :status,
         created_by = :created_by
     WHERE id = :id'
);

$resolvedUsers = [];

$pdo->beginTransaction();

try {
    foreach ($preview['rows'] as $row) {
        if (!empty($row['is_blocking'])) {
            continue;
        }

        $studentLookupKey = (string) ($row['student_lookup_key'] ?? '');
        $studentIdValue = normalize_import_text((string) ($row['student_id'] ?? ''));
        $email = normalize_import_email((string) ($row['email'] ?? ''));
        $existingUser = null;

        if ($studentLookupKey !== '' && isset($resolvedUsers[$studentLookupKey])) {
            $existingUser = $resolvedUsers[$studentLookupKey];
        } elseif (!empty($row['existing_user_id'])) {
            $selectUserById->execute(['id' => (int) $row['existing_user_id']]);
            $existingUser = $selectUserById->fetch() ?: null;
        }

        if ($existingUser === null && $email !== '') {
            $selectUserByEmail->execute(['email' => $email]);
            $existingUser = $selectUserByEmail->fetch() ?: null;
        }

        if ($existingUser === null && $studentIdValue !== '') {
            $selectUserByStudentNumber->execute(['student_number' => $studentIdValue]);
            $existingUser = $selectUserByStudentNumber->fetch() ?: null;
        }

        if ($existingUser !== null && (string) ($existingUser['role_name'] ?? '') !== 'student') {
            throw new RuntimeException('Import stopped because a matched account is not a student record.');
        }

        if ($existingUser === null) {
            $insertUser->execute([
                'role_id' => $studentRoleId,
                'student_number' => $studentIdValue !== '' ? $studentIdValue : null,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => $email,
                'password_hash' => password_hash('Password', PASSWORD_DEFAULT),
                'password_must_reset' => 1,
                'phone' => (($row['mobile'] ?? '') !== '' ? (string) $row['mobile'] : null),
                'gender' => null,
                'date_of_birth' => (($row['date_of_birth'] ?? '') !== '' ? (string) $row['date_of_birth'] : null),
                'status' => 'active',
            ]);

            $existingUser = [
                'id' => (int) $pdo->lastInsertId(),
                'role_name' => 'student',
            ];
            $createdStudents++;
        } else {
            $existingStudents++;
        }

        if ($studentLookupKey !== '') {
            $resolvedUsers[$studentLookupKey] = $existingUser;
        }

        $selectEnrollment->execute([
            'student_id' => (int) $existingUser['id'],
            'course_id' => $previewCourseId,
            'batch_id' => $previewBatchId,
        ]);

        if ($selectEnrollment->fetch()) {
            $reusedEnrollments++;
            continue;
        }

        $selectAnyEnrollment->execute(['student_id' => (int) $existingUser['id']]);
        $existingEnrollment = $selectAnyEnrollment->fetch() ?: null;

        if ($existingEnrollment !== null) {
            $transferEnrollment->execute([
                'course_id' => $previewCourseId,
                'batch_id' => $previewBatchId,
                'enrollment_date' => date('Y-m-d'),
                'status' => 'active',
                'created_by' => $currentUser['id'] ?? null,
                'id' => (int) $existingEnrollment['id'],
            ]);
            $transferredEnrollments++;
            continue;
        }

        $insertEnrollment->execute([
            'student_id' => (int) $existingUser['id'],
            'course_id' => $previewCourseId,
            'batch_id' => $previewBatchId,
            'enrollment_date' => date('Y-m-d'),
            'status' => 'active',
            'created_by' => $currentUser['id'] ?? null,
        ]);
        $createdEnrollments++;
    }

    $pdo->commit();
    unset($_SESSION['enrollment_import_preview']);

    flash_message(
        'CSV import completed. Created ' . $createdStudents . ' students, reused ' . $existingStudents . ' existing student accounts without changing them, transferred ' . $transferredEnrollments . ' students, added ' . $createdEnrollments . ' enrollments, and skipped ' . $reusedEnrollments . ' existing enrollments.',
        'success'
    );
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash_message('The enrollment import could not be completed: ' . $throwable->getMessage(), 'danger');
}

redirect(base_url('admin/enrollments/create.php'));

