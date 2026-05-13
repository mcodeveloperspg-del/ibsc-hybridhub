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

ensure_student_import_schema($pdo);

$courseId = (int) ($_POST['course_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);

if ($courseId < 1 || $batchId < 1) {
    flash_message('Please choose both a course and a batch before uploading the CSV.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$targetStatement = $pdo->prepare(
    "SELECT courses.id AS course_id, courses.title AS course_title, courses.course_code,
            batches.id AS batch_id, batches.batch_name, batches.batch_year
     FROM batch_courses
     INNER JOIN courses ON courses.id = batch_courses.course_id
     INNER JOIN batches ON batches.id = batch_courses.batch_id
     WHERE courses.id = :course_id AND batches.id = :batch_id
     LIMIT 1"
);
$targetStatement->execute(['course_id' => $courseId, 'batch_id' => $batchId]);
$target = $targetStatement->fetch();

if (!$target) {
    flash_message('The selected batch does not include the selected course.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

if (!isset($_FILES['enrollment_csv']) || !is_array($_FILES['enrollment_csv'])) {
    flash_message('Please choose a CSV file to import.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$upload = $_FILES['enrollment_csv'];

if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash_message('The CSV upload failed. Please try again.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$tmpPath = (string) ($upload['tmp_name'] ?? '');
$originalName = (string) ($upload['name'] ?? 'import.csv');

if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    flash_message('The uploaded file could not be read.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$handle = fopen($tmpPath, 'rb');

if ($handle === false) {
    flash_message('The uploaded CSV could not be opened.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$headerRow = fgetcsv($handle);

if ($headerRow === false) {
    fclose($handle);
    flash_message('The uploaded CSV is empty.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$headerMap = map_import_headers($headerRow);
$requiredHeaders = ['student_id', 'student_name', 'email', 'mobile', 'date_of_birth'];
$missingHeaders = [];

foreach ($requiredHeaders as $header) {
    if (!array_key_exists($header, $headerMap)) {
        $missingHeaders[] = $header;
    }
}

$recognizedHeaders = array_keys($headerMap);
$unexpectedHeaders = [];
$nonEmptyHeaderCount = 0;
foreach ($headerRow as $header) {
    $normalizedHeader = normalize_import_header((string) $header);
    if ($normalizedHeader === '') {
        continue;
    }
    $nonEmptyHeaderCount++;

    $matched = false;
    foreach (enrollment_import_header_aliases() as $aliases) {
        $normalizedAliases = array_map('normalize_import_header', $aliases);
        if (in_array($normalizedHeader, $normalizedAliases, true)) {
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        $unexpectedHeaders[] = (string) $header;
    }
}

if ($unexpectedHeaders !== [] || count($recognizedHeaders) !== count($requiredHeaders) || $nonEmptyHeaderCount !== count($requiredHeaders)) {
    fclose($handle);
    flash_message('The CSV must only contain these columns: Student ID, Student Name, Email ID, Mobile, Date of Birth.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

if ($missingHeaders !== []) {
    fclose($handle);
    flash_message('The CSV is missing required columns: ' . implode(', ', $missingHeaders) . '.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$users = $pdo->query(
    "SELECT users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.student_number, users.date_of_birth,
            roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id"
)->fetchAll();

$usersByEmail = [];
$usersByStudentNumber = [];

foreach ($users as $user) {
    $emailKey = normalize_import_email((string) ($user['email'] ?? ''));
    $studentNumberKey = normalize_import_text((string) ($user['student_number'] ?? ''));

    if ($emailKey !== '') {
        $usersByEmail[$emailKey] = $user;
    }

    if ($studentNumberKey !== '') {
        $usersByStudentNumber[$studentNumberKey] = $user;
    }
}

$enrollments = $pdo->query(
    'SELECT enrollments.student_id, enrollments.course_id, enrollments.batch_id,
            courses.title AS course_title, courses.course_code, batches.batch_name, batches.batch_year
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id'
)->fetchAll();
$existingEnrollmentKeys = [];
$enrollmentsByStudent = [];

foreach ($enrollments as $enrollment) {
    $studentEnrollmentId = (int) $enrollment['student_id'];
    $existingEnrollmentKeys[$studentEnrollmentId . '|' . (int) $enrollment['course_id'] . '|' . (int) $enrollment['batch_id']] = true;
    $enrollmentsByStudent[$studentEnrollmentId][] = $enrollment;
}

$rows = [];
$plannedStudents = [];
$plannedEnrollmentKeys = [];
$summary = [
    'total_rows' => 0,
    'valid_rows' => 0,
    'blocking_rows' => 0,
    'new_students' => 0,
    'existing_students' => 0,
    'new_enrollments' => 0,
    'existing_enrollments' => 0,
    'transfers' => 0,
];

$rowNumber = 1;

while (($csvRow = fgetcsv($handle)) !== false) {
    $rowNumber++;
    $rawValues = array_map(static fn ($value): string => normalize_import_text((string) $value), $csvRow);

    if ($rawValues === [] || count(array_filter($rawValues, static fn (string $value): bool => $value !== '')) === 0) {
        continue;
    }

    $summary['total_rows']++;
    $extraValues = array_slice($rawValues, count($headerRow));

    $sourceStudentId = normalize_import_text((string) ($csvRow[$headerMap['student_id']] ?? ''));
    $sourceStudentName = normalize_import_text((string) ($csvRow[$headerMap['student_name']] ?? ''));
    $sourceEmail = normalize_import_email((string) ($csvRow[$headerMap['email']] ?? ''));
    $sourceMobile = normalize_import_phone((string) ($csvRow[$headerMap['mobile']] ?? ''));
    $sourceDateOfBirth = normalize_import_text((string) ($csvRow[$headerMap['date_of_birth']] ?? ''));

    [$firstName, $lastName] = split_import_student_name($sourceStudentName);
    $dateOfBirth = normalize_import_date_value($sourceDateOfBirth);

    $issues = [];

    if (count(array_filter($extraValues, static fn (string $value): bool => $value !== '')) > 0) {
        $issues[] = 'This row contains extra values beyond the five allowed CSV columns.';
    }

    if ($sourceStudentId === '') {
        $issues[] = 'Student ID is missing.';
    }

    if ($firstName === '' || $lastName === '') {
        $issues[] = 'Student name could not be split into first and last names.';
    }

    if ($sourceEmail === '') {
        $issues[] = 'Email address is missing.';
    } elseif (!filter_var($sourceEmail, FILTER_VALIDATE_EMAIL)) {
        $issues[] = 'Email address is invalid.';
    }

    if ($sourceMobile === '') {
        $issues[] = 'Mobile number is missing.';
    }

    if ($dateOfBirth === null) {
        $issues[] = 'Date of birth could not be normalized.';
    }

    $emailMatch = $sourceEmail !== '' ? ($usersByEmail[$sourceEmail] ?? null) : null;
    $studentNumberMatch = $sourceStudentId !== '' ? ($usersByStudentNumber[$sourceStudentId] ?? null) : null;

    if ($emailMatch !== null && $studentNumberMatch !== null && (int) $emailMatch['id'] !== (int) $studentNumberMatch['id']) {
        $issues[] = 'Email and Student ID point to two different existing users.';
    }

    $existingUser = $emailMatch ?? $studentNumberMatch;

    if ($existingUser !== null && (string) ($existingUser['role_name'] ?? '') !== 'student') {
        $issues[] = 'The matched existing account is not a student account.';
    }

    $studentLookupKey = $existingUser !== null
        ? 'existing:' . (int) $existingUser['id']
        : 'new:' . ($sourceStudentId !== '' ? $sourceStudentId : $sourceEmail);

    $studentAction = 'Create student';
    $tempPassword = '';
    $notices = [];

    if ($existingUser !== null) {
        $studentAction = 'Existing student - no account changes';
        $notices[] = 'Student account already exists. The import will not create or update this student record.';

        if (!isset($plannedStudents[$studentLookupKey])) {
            $plannedStudents[$studentLookupKey] = ['student_action' => $studentAction];
            $summary['existing_students']++;
        }
    } else {
        if (isset($plannedStudents[$studentLookupKey])) {
            $studentAction = 'Reuse imported student';
            $tempPassword = (string) $plannedStudents[$studentLookupKey]['temp_password'];
        } else {
            $tempPassword = 'Password';
            $plannedStudents[$studentLookupKey] = [
                'temp_password' => $tempPassword,
                'student_action' => $studentAction,
            ];
            $summary['new_students']++;
        }
    }

    $enrollmentAction = 'Create enrollment';

    if ($courseId > 0 && $batchId > 0) {
        $enrollmentKey = $studentLookupKey . '|' . $courseId . '|' . $batchId;
        $existingEnrollmentKey = $existingUser !== null
            ? (int) $existingUser['id'] . '|' . $courseId . '|' . $batchId
            : null;

        $studentEnrollments = $existingUser !== null ? ($enrollmentsByStudent[(int) $existingUser['id']] ?? []) : [];

        if ($existingEnrollmentKey !== null && isset($existingEnrollmentKeys[$existingEnrollmentKey])) {
            $enrollmentAction = 'Already enrolled';
            $summary['existing_enrollments']++;
        } elseif (isset($plannedEnrollmentKeys[$enrollmentKey])) {
            $enrollmentAction = 'Duplicate enrollment in CSV';
            $issues[] = 'This CSV repeats the same student-course enrollment more than once.';
        } elseif ($studentEnrollments !== []) {
            $currentEnrollment = $studentEnrollments[0];
            $currentCourseLabel = (string) $currentEnrollment['course_title'] . ' (' . (string) $currentEnrollment['course_code'] . ')';
            $currentBatchLabel = (string) $currentEnrollment['batch_name'] . ' (' . (string) $currentEnrollment['batch_year'] . ')';
            $enrollmentAction = 'Transfer to selected batch';
            $notices[] = 'Student is currently enrolled in ' . $currentCourseLabel . ' / ' . $currentBatchLabel . '. Confirming will transfer the student to the selected batch.';
            $plannedEnrollmentKeys[$enrollmentKey] = true;
            $summary['transfers']++;
        } else {
            $plannedEnrollmentKeys[$enrollmentKey] = true;
            $summary['new_enrollments']++;
        }
    }

    $isBlocking = $issues !== [];

    if ($isBlocking) {
        $summary['blocking_rows']++;
    } else {
        $summary['valid_rows']++;
    }

    $rows[] = [
        'row_number' => $rowNumber,
        'student_id' => $sourceStudentId,
        'student_name' => $sourceStudentName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $sourceEmail,
        'mobile' => $sourceMobile,
        'date_of_birth' => $dateOfBirth,
        'student_lookup_key' => $studentLookupKey,
        'existing_user_id' => $existingUser !== null ? (int) $existingUser['id'] : null,
        'matched_course_id' => $courseId,
        'matched_course_label' => (string) $target['course_title'] . ' (' . (string) $target['course_code'] . ')',
        'matched_batch_id' => $batchId,
        'matched_batch_label' => (string) $target['batch_name'] . ' (' . (string) $target['batch_year'] . ')',
        'student_action' => $studentAction,
        'enrollment_action' => $enrollmentAction,
        'temp_password' => $tempPassword,
        'issues' => $issues,
        'notices' => $notices,
        'is_blocking' => $isBlocking,
    ];
}

fclose($handle);

if ($rows === []) {
    flash_message('The CSV did not contain any student rows to preview.', 'danger');
    redirect(base_url('admin/enrollments/create.php'));
}

$_SESSION['enrollment_import_preview'] = [
    'file_name' => $originalName,
    'created_at' => date('Y-m-d H:i:s'),
    'course_id' => $courseId,
    'batch_id' => $batchId,
    'course_label' => (string) $target['course_title'] . ' (' . (string) $target['course_code'] . ')',
    'batch_label' => (string) $target['batch_name'] . ' (' . (string) $target['batch_year'] . ')',
    'summary' => $summary,
    'rows' => $rows,
];

if ($summary['blocking_rows'] > 0) {
    flash_message('Import preview generated. Fix the flagged rows in the CSV before confirming the import.', 'warning');
} else {
    flash_message('Import preview generated. Review the polished records below and confirm when ready.', 'success');
}

redirect(base_url('admin/enrollments/create.php'));

