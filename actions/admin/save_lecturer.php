<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/lecturers/index.php'));
}

$lecturerId = (int) ($_POST['id'] ?? 0);
$isEditing = $lecturerId > 0;
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$status = trim((string) ($_POST['status'] ?? 'active'));

$redirectPath = $isEditing
    ? base_url('admin/lecturers/form.php?edit=' . (string) $lecturerId)
    : base_url('admin/lecturers/create.php');

$errors = [];
$lecturerRoleId = role_id_by_name($pdo, 'teacher');

if ($lecturerRoleId < 1) {
    flash_message('Lecturer role is not configured in the system.', 'danger');
    redirect(base_url('admin/dashboard.php'));
}

if ($isEditing) {
    $lecturerCheckStatement = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role_id = :role_id LIMIT 1');
    $lecturerCheckStatement->execute(['id' => $lecturerId, 'role_id' => $lecturerRoleId]);

    if (!$lecturerCheckStatement->fetch()) {
        flash_message('The selected lecturer account could not be found.', 'warning');
        redirect(base_url('admin/lecturers/index.php'));
    }
}

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

if ($isEditing) {
    if ($password !== '' || $confirmPassword !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
    }
} else {
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }
}

if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
    $errors[] = 'Invalid lecturer status.';
}

$duplicateStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
$duplicateStatement->execute(['email' => $email, 'id' => $lecturerId]);

if ($duplicateStatement->fetch()) {
    $errors[] = 'That email address is already in use.';
}

if ($errors !== []) {
    $_SESSION['lecturer_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $lecturerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'gender' => $gender,
            'status' => $status,
        ],
    ];

    redirect($redirectPath);
}

if ($isEditing) {
    if ($password !== '') {
        $statement = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 gender = :gender,
                 status = :status,
                 password_hash = :password_hash
             WHERE id = :id AND role_id = :role_id'
        );
        $statement->execute([
            'id' => $lecturerId,
            'role_id' => $lecturerRoleId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'gender' => $gender !== '' ? $gender : null,
            'status' => $status,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } else {
        $statement = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 gender = :gender,
                 status = :status
             WHERE id = :id AND role_id = :role_id'
        );
        $statement->execute([
            'id' => $lecturerId,
            'role_id' => $lecturerRoleId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'gender' => $gender !== '' ? $gender : null,
            'status' => $status,
        ]);
    }

    flash_message('Lecturer account updated successfully.', 'success');
    redirect(base_url('admin/lecturers/form.php?edit=' . (string) $lecturerId));
}

$statement = $pdo->prepare(
    'INSERT INTO users (role_id, first_name, last_name, email, password_hash, phone, gender, status)
     VALUES (:role_id, :first_name, :last_name, :email, :password_hash, :phone, :gender, :status)'
);
$statement->execute([
    'role_id' => $lecturerRoleId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'phone' => $phone !== '' ? $phone : null,
    'gender' => $gender !== '' ? $gender : null,
    'status' => $status,
]);

flash_message('Lecturer account created successfully. You can now assign the lecturer to current-stage units.', 'success');
redirect(base_url('admin/lecturers/index.php'));
