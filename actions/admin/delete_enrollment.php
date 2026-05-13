<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/enrollments/index.php'));
}

$enrollmentId = isset($_POST['enrollment_id']) ? (int) $_POST['enrollment_id'] : 0;

if ($enrollmentId < 1) {
    flash_message('The selected enrollment is invalid.', 'warning');
    redirect(base_url('admin/enrollments/index.php'));
}

$statement = $pdo->prepare('DELETE FROM enrollments WHERE id = :id');
$statement->execute(['id' => $enrollmentId]);

if ($statement->rowCount() < 1) {
    flash_message('The selected enrollment could not be found.', 'warning');
    redirect(base_url('admin/enrollments/index.php'));
}

flash_message('Enrollment deleted successfully.', 'success');
redirect(base_url('admin/enrollments/index.php'));
