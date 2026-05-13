<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['student']);

$formState = consume_form_state('student_password_reset_form_state');
$errors = $formState['errors'] ?? [];

$pageTitle = 'Reset Password - ' . APP_NAME;
$pageHeading = 'Reset Password';
$pageDescription = 'Set a new password before continuing to your learning workspace.';

require_once __DIR__ . '/../includes/layouts/role_header.php';
?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4">
        <div class="fw-semibold mb-2">Please fix the following:</div>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo e((string) $error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="surface-card p-4">
            <h2 class="h5 mb-2">Choose a New Password</h2>
            <p class="text-muted">Your account is using a temporary password. Create a private password to continue.</p>
            <form action="<?php echo e(base_url('actions/student/reset_password.php')); ?>" method="POST" class="row g-3">
                <div class="col-12">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                </div>
                <div class="col-12">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layouts/role_footer.php'; ?>
