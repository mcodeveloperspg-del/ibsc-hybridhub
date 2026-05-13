<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$lecturerId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($lecturerId < 1) {
    flash_message('The selected lecturer account could not be found.', 'warning');
    redirect(base_url('admin/lecturers/index.php'));
}

$formState = consume_form_state('lecturer_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$lecturerRoleId = role_id_by_name($pdo, 'teacher');

$lecturerStatement = $pdo->prepare(
    'SELECT id, first_name, last_name, email, phone, gender, status
     FROM users
     WHERE id = :id AND role_id = :role_id
     LIMIT 1'
);
$lecturerStatement->execute(['id' => $lecturerId, 'role_id' => $lecturerRoleId]);
$lecturer = $lecturerStatement->fetch();

if (!$lecturer) {
    flash_message('The selected lecturer account could not be found.', 'warning');
    redirect(base_url('admin/lecturers/index.php'));
}

$lecturerFormData = [
    'id' => $oldData['id'] ?? $lecturer['id'],
    'first_name' => $oldData['first_name'] ?? $lecturer['first_name'],
    'last_name' => $oldData['last_name'] ?? $lecturer['last_name'],
    'email' => $oldData['email'] ?? $lecturer['email'],
    'phone' => $oldData['phone'] ?? $lecturer['phone'],
    'gender' => $oldData['gender'] ?? $lecturer['gender'],
    'status' => $oldData['status'] ?? $lecturer['status'],
];

$pageTitle = 'Edit Lecturer Account - ' . APP_NAME;
$pageHeading = 'Edit Lecturer Account';
$pageDescription = 'Update lecturer profile details here. Password changes are optional when editing.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">Edit Lecturer Account Details</h2>
                    <p class="text-muted mb-0">Update this lecturer profile without changing the separate unit assignment records.</p>
                </div>
                <a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Lecturers</a>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please fix the following issues:</div>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo e(base_url('actions/admin/save_lecturer.php')); ?>" method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?php echo e((string) $lecturerFormData['id']); ?>">

                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo e((string) $lecturerFormData['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo e((string) $lecturerFormData['last_name']); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?php echo e((string) $lecturerFormData['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo e((string) $lecturerFormData['phone']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select gender</option>
                        <?php foreach (['male', 'female', 'other'] as $gender): ?>
                            <option value="<?php echo e($gender); ?>" <?php echo (string) $lecturerFormData['gender'] === $gender ? 'selected' : ''; ?>><?php echo e(ucfirst($gender)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="password">
                    <div class="form-text">Leave blank to keep the current password.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'inactive', 'suspended'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) $lecturerFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Lecturer Account</button>
                    <a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
