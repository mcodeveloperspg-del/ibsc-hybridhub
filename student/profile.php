<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['student']);

$user = current_user();
$studentId = (int) ($user['id'] ?? 0);
$flash = flash_message();

$profileStatement = $pdo->prepare(
    "SELECT users.id, users.student_number, users.first_name, users.last_name, users.email,
            users.phone, users.gender, users.date_of_birth, users.status, users.last_login_at,
            users.created_at, roles.name AS role_name
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE users.id = :id AND roles.name = 'student'
     LIMIT 1"
);
$profileStatement->execute(['id' => $studentId]);
$profile = $profileStatement->fetch();

if (!$profile) {
    flash_message('Your student profile could not be found.', 'warning');
    redirect(base_url('student/dashboard.php'));
}

$formState = consume_form_state('student_profile_form_state');
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$profileData = [
    'first_name' => $oldData['first_name'] ?? ($profile['first_name'] ?? ''),
    'last_name' => $oldData['last_name'] ?? ($profile['last_name'] ?? ''),
    'email' => $oldData['email'] ?? ($profile['email'] ?? ''),
    'phone' => $oldData['phone'] ?? ($profile['phone'] ?? ''),
    'gender' => $oldData['gender'] ?? ($profile['gender'] ?? ''),
    'date_of_birth' => $oldData['date_of_birth'] ?? ($profile['date_of_birth'] ?? ''),
];

$pageTitle = 'My Profile - ' . APP_NAME;
$pageHeading = 'My Profile';
$pageDescription = 'View your student account details and update your contact information.';

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

<div class="row g-4">
    <div class="col-lg-4">
        <div class="surface-card p-4 h-100">
            <div class="metric-icon text-primary bg-primary-subtle mb-3"><i class="bi bi-person-circle"></i></div>
            <h2 class="h4 mb-1"><?php echo e(full_name($profile)); ?></h2>
            <p class="text-muted mb-3"><?php echo e($profile['email']); ?></p>

            <div class="d-grid gap-3">
                <div>
                    <div class="small text-muted">Student number</div>
                    <div class="fw-semibold"><?php echo e($profile['student_number'] ?: 'Not assigned'); ?></div>
                </div>
                <div>
                    <div class="small text-muted">Account status</div>
                    <span class="status-chip success"><i class="bi bi-check-circle-fill"></i><?php echo e(ucfirst((string) $profile['status'])); ?></span>
                </div>
                <div>
                    <div class="small text-muted">Last login</div>
                    <div class="fw-semibold"><?php echo e(format_datetime($profile['last_login_at'])); ?></div>
                </div>
                <div>
                    <div class="small text-muted">Joined</div>
                    <div class="fw-semibold"><?php echo e(format_datetime($profile['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="surface-card p-4">
            <form action="<?php echo e(base_url('actions/student/save_profile.php')); ?>" method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e((string) $profileData['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e((string) $profileData['last_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo e((string) $profileData['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo e((string) $profileData['phone']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label): ?>
                            <option value="<?php echo e($value); ?>" <?php echo (string) $profileData['gender'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label">Date Of Birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo e((string) $profileData['date_of_birth']); ?>">
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-6">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                    <div class="form-text">Leave blank to keep your current password.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                </div>

                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                    <a href="<?php echo e(base_url('student/dashboard.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layouts/role_footer.php'; ?>
