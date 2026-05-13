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

$lecturerId = (int) ($_POST['lecturer_id'] ?? 0);

if ($lecturerId < 1) {
    flash_message('The selected lecturer is invalid.', 'warning');
    redirect(base_url('admin/lecturers/index.php'));
}

$lecturerRoleId = role_id_by_name($pdo, 'teacher');
$lecturerStatement = $pdo->prepare(
    'SELECT id FROM users WHERE id = :id AND role_id = :role_id LIMIT 1'
);
$lecturerStatement->execute([
    'id' => $lecturerId,
    'role_id' => $lecturerRoleId,
]);

if (!$lecturerStatement->fetch()) {
    flash_message('The selected lecturer could not be found.', 'warning');
    redirect(base_url('admin/lecturers/index.php'));
}

$assignmentCountStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM lecturer_unit_assignments
     WHERE lecturer_id = :lecturer_id
       AND status = 'active'"
);
$assignmentCountStatement->execute(['lecturer_id' => $lecturerId]);
$assignmentCount = (int) $assignmentCountStatement->fetchColumn();

$deleteStatement = $pdo->prepare('DELETE FROM users WHERE id = :id AND role_id = :role_id');
$deleteStatement->execute([
    'id' => $lecturerId,
    'role_id' => $lecturerRoleId,
]);

if ($deleteStatement->rowCount() < 1) {
    flash_message('The selected lecturer could not be deleted.', 'warning');
    redirect(base_url('admin/lecturers/index.php'));
}

if ($assignmentCount > 0) {
    flash_message('Lecturer deleted successfully. Assigned course unit records were removed as part of the deletion.', 'success');
} else {
    flash_message('Lecturer deleted successfully.', 'success');
}

redirect(base_url('admin/lecturers/index.php'));
