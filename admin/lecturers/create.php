<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$formState = consume_form_state('lecturer_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$pageTitle = 'Add Lecturer - ' . APP_NAME;
$pageHeading = 'Add Lecturer';
$pageDescription = 'Create a lecturer account on a dedicated page, then assign the lecturer to the current-stage units for a batch.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">Add Lecturer Into System</h2>
                    <p class="text-muted mb-0">Create the lecturer account here first, then use Lecturer Assignments to attach the lecturer to active batch-stage units.</p>
                </div>
                <a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Lecturers</a>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo e(base_url('actions/admin/save_lecturer.php')); ?>" method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo e((string) ($oldData['first_name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo e((string) ($oldData['last_name'] ?? '')); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?php echo e((string) ($oldData['email'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo e((string) ($oldData['phone'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select gender</option>
                        <?php foreach (['male', 'female', 'other'] as $gender): ?>
                            <option value="<?php echo e($gender); ?>" <?php echo (string) ($oldData['gender'] ?? '') === $gender ? 'selected' : ''; ?>><?php echo e(ucfirst($gender)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'inactive', 'suspended'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) ($oldData['status'] ?? 'active') === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-text">After creating the account, use the Lecturer Assignments page to link the lecturer to units for the batch stage currently in progress.</div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Lecturer Account</button>
                    <a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
