<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('course_form_state', [
    'errors' => [],
    'data' => [],
]);

$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$editingCourseId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingCourse = null;

if ($editingCourseId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
    $editStatement->execute(['id' => $editingCourseId]);
    $editingCourse = $editStatement->fetch();

    if (!$editingCourse) {
        flash_message('The selected course could not be found.', 'warning');
        redirect(base_url('admin/courses/index.php'));
    }
}

$courseFormData = [
    'id' => $oldData['id'] ?? ($editingCourse['id'] ?? ''),
    'course_code' => $oldData['course_code'] ?? ($editingCourse['course_code'] ?? ''),
    'title' => $oldData['title'] ?? ($editingCourse['title'] ?? ''),
    'description' => $oldData['description'] ?? ($editingCourse['description'] ?? ''),
    'duration_months' => $oldData['duration_months'] ?? ($editingCourse['duration_months'] ?? 6),
    'total_stages' => $oldData['total_stages'] ?? ($editingCourse['total_stages'] ?? 4),
    'weeks_per_stage' => $oldData['weeks_per_stage'] ?? ($editingCourse['weeks_per_stage'] ?? 6),
    'status' => $oldData['status'] ?? ($editingCourse['status'] ?? 'active'),
];

$pageTitle = ($editingCourse ? 'Edit Course' : 'Create Course') . ' - ' . APP_NAME;
$pageHeading = $editingCourse ? 'Edit Course' : 'Create Course';
$pageDescription = 'Use this page to define the academic structure settings for a course.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1"><?php echo $editingCourse ? 'Edit Course Details' : 'Create a New Course'; ?></h2><p class="text-muted mb-0">Enter the main course details carefully. These settings shape the academic structure used later.</p></div><a href="<?php echo e(base_url('admin/courses/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Courses</a></div>
            <?php if ($errors !== []): ?><div class="alert alert-danger"><div class="fw-semibold mb-2">Please fix the following issues:</div><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form action="<?php echo e(base_url('actions/admin/save_course.php')); ?>" method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?php echo e((string) $courseFormData['id']); ?>">
                <div class="col-md-6"><label for="course_code" class="form-label">Course Code</label><input type="text" class="form-control" id="course_code" name="course_code" value="<?php echo e((string) $courseFormData['course_code']); ?>" required></div>
                <div class="col-md-6"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" required><?php foreach (['draft', 'active', 'archived'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $courseFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="title" class="form-label">Course Title</label><input type="text" class="form-control" id="title" name="title" value="<?php echo e((string) $courseFormData['title']); ?>" required></div>
                <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?php echo e((string) $courseFormData['description']); ?></textarea></div>
                <div class="col-md-4"><label for="duration_months" class="form-label">Duration (Months)</label><input type="number" min="1" class="form-control" id="duration_months" name="duration_months" value="<?php echo e((string) $courseFormData['duration_months']); ?>" required></div>
                <div class="col-md-4"><label for="total_stages" class="form-label">Total Stages</label><input type="number" min="1" class="form-control" id="total_stages" name="total_stages" value="<?php echo e((string) $courseFormData['total_stages']); ?>" required></div>
                <div class="col-md-4"><label for="weeks_per_stage" class="form-label">Weeks Per Stage</label><input type="number" min="1" class="form-control" id="weeks_per_stage" name="weeks_per_stage" value="<?php echo e((string) $courseFormData['weeks_per_stage']); ?>" required></div>
                <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary"><?php echo $editingCourse ? 'Update Course' : 'Create Course'; ?></button><a href="<?php echo e(base_url('admin/courses/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
