<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

function require_login(): void
{
    $pdo = app_pdo();
    if ($pdo instanceof PDO) {
        bootstrap_module_access($pdo);
    }

    if (!is_logged_in()) {
        $_SESSION['intended_url'] = current_path();
        flash_message('Please log in to continue.', 'warning');
        redirect(base_url('auth/login.php'));
    }

    $fingerprint = $_SESSION['user']['fingerprint'] ?? '';

    if ($fingerprint === '' || !hash_equals($fingerprint, session_fingerprint())) {
        logout_user();
        flash_message('Your session expired for security reasons. Please log in again.', 'warning');
        redirect(base_url('auth/login.php'));
    }

    $currentPath = current_path();
    if (
        user_role() === 'student'
        && (int) ($_SESSION['user']['password_must_reset'] ?? 0) === 1
        && !str_ends_with($currentPath, '/student/reset_password.php')
        && !str_ends_with($currentPath, '/auth/logout.php')
    ) {
        flash_message('Please reset your temporary password before continuing.', 'warning');
        redirect(base_url('student/reset_password.php'));
    }
}

function has_role(array $allowedRoles): bool
{
    $roleName = user_role();

    return $roleName !== null && in_array($roleName, $allowedRoles, true);
}

function deny_access(array $allowedRoles = []): void
{
    http_response_code(403);

    $_SESSION['forbidden'] = [
        'allowed_roles' => $allowedRoles,
        'attempted_path' => current_path(),
    ];

    redirect(base_url('forbidden.php'));
}

function require_role(array $allowedRoles): void
{
    require_login();

    $pdo = app_pdo();
    $entries = route_permission_entries_for_path();

    if ($pdo instanceof PDO && $entries !== []) {
        if (!current_user_can_access_route($pdo)) {
            deny_access(route_permission_modules());
        }
        return;
    }

    if (!has_role($allowedRoles)) {
        deny_access($allowedRoles);
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect(role_dashboard_path((string) user_role()));
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'role_id' => (int) $user['role_id'],
        'role_name' => $user['role_name'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'status' => $user['status'],
        'password_must_reset' => (int) ($user['password_must_reset'] ?? 0),
        'fingerprint' => session_fingerprint(),
        'login_time' => date('Y-m-d H:i:s'),
    ];
}

function redirect_after_login(): void
{
    $intendedUrl = $_SESSION['intended_url'] ?? null;
    unset($_SESSION['intended_url']);

    if (is_string($intendedUrl) && $intendedUrl !== '' && $intendedUrl !== '/forbidden.php') {
        $pdo = app_pdo();
        if (!$pdo instanceof PDO) {
            redirect($intendedUrl);
        }

        $entries = route_permission_entries_for_path($intendedUrl);
        if ($entries === [] || current_user_can_access_route($pdo, $intendedUrl)) {
            redirect($intendedUrl);
        }
    }

    redirect(role_dashboard_path((string) user_role()));
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
