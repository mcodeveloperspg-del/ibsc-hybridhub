<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$studentId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($studentId < 1) {
    flash_message('The selected student account could not be found.', 'warning');
    redirect(base_url('admin/students/index.php'));
}

$formState = consume_form_state('student_enrollment_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$studentStatement = $pdo->prepare(
    "SELECT users.id, users.student_number, users.first_name, users.last_name, users.email, users.phone, users.gender, users.avatar, users.status
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id AND roles.name = 'student'
     LIMIT 1"
);
$studentStatement->execute(['id' => $studentId]);
$student = $studentStatement->fetch();

if (!$student) {
    flash_message('The selected student account could not be found.', 'warning');
    redirect(base_url('admin/students/index.php'));
}

$studentFormData = [
    'id' => $oldData['id'] ?? $student['id'],
    'first_name' => $oldData['first_name'] ?? $student['first_name'],
    'last_name' => $oldData['last_name'] ?? $student['last_name'],
    'email' => $oldData['email'] ?? $student['email'],
    'phone' => $oldData['phone'] ?? $student['phone'],
    'gender' => $oldData['gender'] ?? $student['gender'],
    'avatar' => $student['avatar'] ?? '',
    'status' => $oldData['status'] ?? $student['status'],
];

$pageTitle = 'Edit Student Account - ' . APP_NAME;
$pageHeading = 'Edit Student Account';
$pageDescription = 'Update student account details here. Course-and-batch enrollment remains separate from account editing.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">Edit Student Account Details</h2>
                    <p class="text-muted mb-0">Update the login account here without changing the student's separate course and batch enrollment records.</p>
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
                <input type="hidden" name="id" value="<?php echo e((string) $studentFormData['id']); ?>">

                <div class="col-12">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?php if ((string) $studentFormData['avatar'] !== ''): ?>
                            <img src="<?php echo e(base_url((string) $studentFormData['avatar'])); ?>" alt="<?php echo e(trim((string) $studentFormData['first_name'] . ' ' . (string) $studentFormData['last_name'])); ?> ID photo" class="student-id-photo large">
                        <?php else: ?>
                            <img src="<?php echo e(base_url('assets/img/student-profile-placeholder.svg')); ?>" alt="Blank student ID photo" class="student-id-photo large">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <label class="form-label">Student ID Photo</label>
                            <input type="file" class="form-control" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text">Upload JPG, PNG, or WEBP to replace the current ID photo placeholder.</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo e((string) $studentFormData['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo e((string) $studentFormData['last_name']); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?php echo e((string) $studentFormData['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo e((string) $studentFormData['phone']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select gender</option>
                        <?php foreach (['male', 'female', 'other'] as $gender): ?>
                            <option value="<?php echo e($gender); ?>" <?php echo (string) $studentFormData['gender'] === $gender ? 'selected' : ''; ?>><?php echo e(ucfirst($gender)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="password">
                    <div class="form-text">Leave blank to keep the current password.</div>
                    <div class="form-text">Set to <strong>Password</strong> to require a reset at next login.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'inactive', 'suspended'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) $studentFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Student Account</button>
                    <a href="<?php echo e(base_url('admin/enrollments/create.php?student_id=' . (string) $student['id'])); ?>" target="_blank" rel="noopener" class="btn btn-outline-success">Open Enrollment Page</a>
                    <a href="<?php echo e(base_url('admin/students/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
