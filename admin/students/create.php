<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$formState = consume_form_state('student_enrollment_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$pageTitle = 'Create Student Account - ' . APP_NAME;
$pageHeading = 'Create Student Account';
$pageDescription = 'Create a student login account. Course-and-batch enrollment remains separate.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">New Student Account</h2>
                    <p class="text-muted mb-0">After creating the account, enroll the student into a course and batch from the enrollment page.</p>
                </div>
                <a href="<?php echo e(base_url('admin/students/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Student Accounts</a>
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

            <form action="<?php echo e(base_url('actions/admin/save_student.php')); ?>" method="POST" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="id" value="">

                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo e((string) ($oldData['first_name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo e((string) ($oldData['last_name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Student ID Photo</label>
                    <input type="file" class="form-control" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">Upload JPG, PNG, or WEBP. The student record shows a placeholder until a photo is uploaded.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <img src="<?php echo e(base_url('assets/img/student-profile-placeholder.svg')); ?>" alt="Blank student ID photo" class="student-id-photo large">
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
                    <div class="form-text">Use <strong>Password</strong> as a temporary password when you want the student to reset it at first login.</div>
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
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Student Account</button>
                    <a href="<?php echo e(base_url('admin/students/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
