<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['student']);

if (!is_post_request()) {
    redirect(base_url('student/profile.php'));
}

$user = current_user();
$studentId = (int) ($user['id'] ?? 0);
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$dateOfBirth = trim((string) ($_POST['date_of_birth'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$errors = [];

if ($firstName === '') {
    $errors[] = 'First name is required.';
}

if ($lastName === '') {
    $errors[] = 'Last name is required.';
}

if ($email === '') {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email address is invalid.';
}

if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
    $errors[] = 'Gender selection is invalid.';
}

if ($dateOfBirth !== '' && !is_valid_date_string($dateOfBirth)) {
    $errors[] = 'Date of birth is invalid.';
}

if ($password !== '' || $confirmPassword !== '') {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }
}

$studentCheckStatement = $pdo->prepare(
    "SELECT users.id, users.role_id, users.status, roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id AND roles.name = 'student'
     LIMIT 1"
);
$studentCheckStatement->execute(['id' => $studentId]);
$student = $studentCheckStatement->fetch();

if (!$student) {
    flash_message('Your student profile could not be found.', 'warning');
    redirect(base_url('student/dashboard.php'));
}

$duplicateStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
$duplicateStatement->execute(['email' => $email, 'id' => $studentId]);

if ($duplicateStatement->fetch()) {
    $errors[] = 'That email address is already in use.';
}

if ($errors !== []) {
    $_SESSION['student_profile_form_state'] = [
        'errors' => $errors,
        'data' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'gender' => $gender,
            'date_of_birth' => $dateOfBirth,
        ],
    ];

    redirect(base_url('student/profile.php'));
}

$params = [
    'id' => $studentId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'phone' => $phone !== '' ? $phone : null,
    'gender' => $gender !== '' ? $gender : null,
    'date_of_birth' => $dateOfBirth !== '' ? $dateOfBirth : null,
];

if ($password !== '') {
    $statement = $pdo->prepare(
        'UPDATE users
         SET first_name = :first_name,
             last_name = :last_name,
             email = :email,
             phone = :phone,
             gender = :gender,
             date_of_birth = :date_of_birth,
             password_hash = :password_hash,
             password_must_reset = 0
         WHERE id = :id'
    );
    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $_SESSION['user']['password_must_reset'] = 0;
} else {
    $statement = $pdo->prepare(
        'UPDATE users
         SET first_name = :first_name,
             last_name = :last_name,
             email = :email,
             phone = :phone,
             gender = :gender,
             date_of_birth = :date_of_birth
         WHERE id = :id'
    );
}

$statement->execute($params);

$_SESSION['user']['first_name'] = $firstName;
$_SESSION['user']['last_name'] = $lastName;
$_SESSION['user']['email'] = $email;

flash_message('Profile updated successfully.', 'success');
redirect(base_url('student/profile.php'));
