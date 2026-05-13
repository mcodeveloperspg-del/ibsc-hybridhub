<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_post_request()) {
    redirect(base_url('auth/login.php'));
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$_SESSION['old_email'] = $email;

if ($email === '' || $password === '') {
    flash_message('Email and password are required.', 'danger');
    redirect(base_url('auth/login.php'));
}

$sql = 'SELECT users.id, users.role_id, users.first_name, users.last_name, users.email, users.password_hash, users.password_must_reset, users.status, roles.name AS role_name
        FROM users
        INNER JOIN roles ON roles.id = users.role_id
        WHERE users.email = :email
        LIMIT 1';

$statement = $pdo->prepare($sql);
$statement->execute(['email' => $email]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_message('Invalid email or password.', 'danger');
    redirect(base_url('auth/login.php'));
}

if ($user['status'] !== 'active') {
    flash_message('Your account is not active. Please contact the system administrator.', 'warning');
    redirect(base_url('auth/login.php'));
}

login_user($user);
unset($_SESSION['old_email']);

$updateLoginStatement = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
$updateLoginStatement->execute(['id' => $user['id']]);

flash_message('Welcome back, ' . full_name($user) . '.', 'success');
redirect_after_login();
