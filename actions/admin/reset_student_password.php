<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/students/index.php'));
}

$studentId = (int) ($_POST['student_id'] ?? 0);

if ($studentId < 1) {
    flash_message('The selected student is invalid.', 'warning');
    redirect(base_url('admin/students/index.php'));
}

$statement = $pdo->prepare(
    "UPDATE users
     INNER JOIN roles ON roles.id = users.role_id
     SET users.password_hash = :password_hash,
         users.password_must_reset = 1
     WHERE users.id = :id AND roles.name = 'student'"
);
$statement->execute([
    'password_hash' => password_hash('Password', PASSWORD_DEFAULT),
    'id' => $studentId,
]);

if ($statement->rowCount() < 1) {
    flash_message('The selected student could not be found.', 'warning');
    redirect(base_url('admin/students/index.php'));
}

flash_message('Student password reset to the default password. The student must choose a new password at next login.', 'success');
redirect(base_url('admin/students/index.php'));
