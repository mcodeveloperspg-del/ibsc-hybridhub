<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/units/index.php'));
}

$unitId = isset($_POST['unit_id']) ? (int) $_POST['unit_id'] : 0;

if ($unitId < 1) {
    flash_message('The selected unit is invalid.', 'warning');
    redirect(base_url('admin/units/index.php'));
}

$deleteStatement = $pdo->prepare('DELETE FROM units WHERE id = :id');
$deleteStatement->execute(['id' => $unitId]);

if ($deleteStatement->rowCount() < 1) {
    flash_message('The selected unit could not be found.', 'warning');
    redirect(base_url('admin/units/index.php'));
}

flash_message('Unit deleted successfully.', 'success');
redirect(base_url('admin/units/index.php'));
