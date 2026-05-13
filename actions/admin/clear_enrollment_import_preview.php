<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (is_post_request()) {
    unset($_SESSION['enrollment_import_preview']);
    flash_message('Import preview cleared.', 'success');
}

redirect(base_url('admin/enrollments/create.php'));

