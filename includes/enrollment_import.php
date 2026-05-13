<?php

declare(strict_types=1);

function schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function schema_index_exists(PDO $pdo, string $table, string $index): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function ensure_student_import_schema(PDO $pdo): void
{
    if (!schema_column_exists($pdo, 'users', 'student_number')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN student_number VARCHAR(100) DEFAULT NULL AFTER role_id");
    }

    if (!schema_column_exists($pdo, 'users', 'date_of_birth')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER gender");
    }

    if (!schema_index_exists($pdo, 'users', 'idx_users_student_number')) {
        $pdo->exec('ALTER TABLE users ADD INDEX idx_users_student_number (student_number)');
    }
}

function enrollment_import_header_aliases(): array
{
    return [
        'student_id' => ['studentid', 'student_id', 'student no', 'studentno', 'studentnumber', 'student_number'],
        'student_name' => ['studentname', 'student_name', 'name', 'full_name', 'fullname'],
        'course' => ['course', 'course_name', 'coursecode', 'course_code', 'program', 'programme'],
        'email' => ['email', 'emailid', 'email_id', 'studentemail', 'student_email'],
        'mobile' => ['mobile', 'phone', 'mobile_number', 'mobilenumber', 'contact', 'contact_number'],
        'date_of_birth' => ['dateofbirth', 'date_of_birth', 'dob', 'birthdate', 'date birth'],
    ];
}

function normalize_import_header(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = strtolower($value);

    return (string) preg_replace('/[^a-z0-9]+/', '', $value);
}

function map_import_headers(array $headerRow): array
{
    $aliases = enrollment_import_header_aliases();
    $mapped = [];

    foreach ($headerRow as $index => $header) {
        $normalizedHeader = normalize_import_header((string) $header);

        foreach ($aliases as $canonical => $options) {
            $normalizedOptions = array_map('normalize_import_header', $options);

            if (in_array($normalizedHeader, $normalizedOptions, true)) {
                $mapped[$canonical] = $index;
                break;
            }
        }
    }

    return $mapped;
}

function normalize_import_text(?string $value): string
{
    $value = trim((string) $value);
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function split_import_student_name(string $fullName): array
{
    $fullName = normalize_import_text($fullName);

    if ($fullName === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/', $fullName) ?: [];

    if (count($parts) === 1) {
        return [$parts[0], $parts[0]];
    }

    $lastName = (string) array_pop($parts);
    $firstName = trim(implode(' ', $parts));

    return [$firstName, $lastName];
}

function normalize_import_email(string $email): string
{
    return strtolower(normalize_import_text($email));
}

function normalize_import_phone(string $phone): string
{
    $phone = normalize_import_text($phone);

    if ($phone === '') {
        return '';
    }

    $phone = preg_replace('/(?!^\+)[^0-9]/', '', $phone) ?? $phone;
    $phone = preg_replace('/\s+/', '', $phone) ?? $phone;

    return $phone;
}

function normalize_import_date_value(string $value): ?string
{
    $value = normalize_import_text($value);

    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'd.m.Y',
        'j/n/Y',
        'j-n-Y',
        'j.n.Y',
        'm/d/Y',
        'm-d-Y',
        'n/j/Y',
        'n-j-Y',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);

        if ($date instanceof DateTime && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function normalize_course_match_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-z0-9]+/', '', normalize_import_text($value)));
}

function enrollment_import_course_lookup(array $courses): array
{
    $lookup = [];

    foreach ($courses as $course) {
        $keys = [
            normalize_course_match_key((string) ($course['title'] ?? '')),
            normalize_course_match_key((string) ($course['course_code'] ?? '')),
            normalize_course_match_key((string) (($course['title'] ?? '') . ' ' . ($course['course_code'] ?? ''))),
            normalize_course_match_key((string) (($course['course_code'] ?? '') . ' ' . ($course['title'] ?? ''))),
        ];

        foreach ($keys as $key) {
            if ($key === '') {
                continue;
            }

            $lookup[$key][] = $course;
        }
    }

    return $lookup;
}

function find_import_course_match(string $rawCourse, array $courseLookup): ?array
{
    $normalized = normalize_course_match_key($rawCourse);

    if ($normalized === '') {
        return null;
    }

    if (isset($courseLookup[$normalized]) && count($courseLookup[$normalized]) === 1) {
        return $courseLookup[$normalized][0];
    }

    $matchedCourses = [];

    foreach ($courseLookup as $key => $courses) {
        if (str_contains($key, $normalized) || str_contains($normalized, $key)) {
            foreach ($courses as $course) {
                $matchedCourses[(int) $course['id']] = $course;
            }
        }
    }

    return count($matchedCourses) === 1 ? array_values($matchedCourses)[0] : null;
}

function enrollment_import_batch_status_rank(string $status): int
{
    return match ($status) {
        'active' => 0,
        'planned' => 1,
        'completed' => 2,
        'archived' => 3,
        default => 9,
    };
}

function choose_import_batch(array $batches, ?string $today = null): ?array
{
    if ($batches === []) {
        return null;
    }

    $today = $today ?: date('Y-m-d');

    usort($batches, static function (array $left, array $right) use ($today): int {
        $leftOpen = (($left['end_date'] ?? null) === null || (string) $left['end_date'] === '' || (string) $left['end_date'] >= $today) ? 1 : 0;
        $rightOpen = (($right['end_date'] ?? null) === null || (string) $right['end_date'] === '' || (string) $right['end_date'] >= $today) ? 1 : 0;

        if ($leftOpen !== $rightOpen) {
            return $rightOpen <=> $leftOpen;
        }

        $statusCompare = enrollment_import_batch_status_rank((string) ($left['status'] ?? ''))
            <=> enrollment_import_batch_status_rank((string) ($right['status'] ?? ''));

        if ($statusCompare !== 0) {
            return $statusCompare;
        }

        $yearCompare = ((int) ($right['batch_year'] ?? 0)) <=> ((int) ($left['batch_year'] ?? 0));

        if ($yearCompare !== 0) {
            return $yearCompare;
        }

        $numberCompare = ((int) ($right['batch_number'] ?? 0)) <=> ((int) ($left['batch_number'] ?? 0));

        if ($numberCompare !== 0) {
            return $numberCompare;
        }

        return strcmp((string) ($right['start_date'] ?? ''), (string) ($left['start_date'] ?? ''));
    });

    return $batches[0];
}

function generate_import_temp_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}
