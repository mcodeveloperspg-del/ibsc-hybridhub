<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/topics/index.php'));
}

$topicId = isset($_POST['topic_id']) ? (int) $_POST['topic_id'] : 0;
$returnUnitId = (int) ($_POST['return_unit_id'] ?? 0);
$redirectUrl = $returnUnitId > 0 ? base_url('admin/units/view.php?id=' . (string) $returnUnitId) : base_url('admin/topics/index.php');

if ($topicId < 1) {
    flash_message('The selected topic is invalid.', 'warning');
    redirect($redirectUrl);
}

$deleteStatement = $pdo->prepare('DELETE FROM topics WHERE id = :id');
$deleteStatement->execute(['id' => $topicId]);

if ($deleteStatement->rowCount() < 1) {
    flash_message('The selected topic could not be found.', 'warning');
    redirect($redirectUrl);
}

flash_message('Topic deleted successfully.', 'success');
redirect($redirectUrl);
