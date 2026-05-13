<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['student']);

if (!is_post_request()) {
    redirect(base_url('student/reset_password.php'));
}

$user = current_user();
$studentId = (int) ($user['id'] ?? 0);
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$errors = [];

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
}

if ($password === 'Password') {
    $errors[] = 'Choose a password different from the temporary password.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Password confirmation does not match.';
}

if ($errors !== []) {
    $_SESSION['student_password_reset_form_state'] = ['errors' => $errors];
    redirect(base_url('student/reset_password.php'));
}

$statement = $pdo->prepare(
    "UPDATE users
     INNER JOIN roles ON roles.id = users.role_id
     SET users.password_hash = :password_hash,
         users.password_must_reset = 0
     WHERE users.id = :id AND roles.name = 'student'"
);
$statement->execute([
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'id' => $studentId,
]);

if ($statement->rowCount() < 1) {
    flash_message('Your password could not be reset. Please try again.', 'danger');
    redirect(base_url('student/reset_password.php'));
}

$_SESSION['user']['password_must_reset'] = 0;
flash_message('Password reset successfully.', 'success');
redirect(base_url('student/dashboard.php'));
