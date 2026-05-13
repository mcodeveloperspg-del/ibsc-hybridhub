<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$deleteBlockState = consume_form_state('course_delete_blocked', [
    'course_title' => '',
    'enrollments' => [],
]);
$blockedCourseTitle = (string) ($deleteBlockState['course_title'] ?? '');
$blockedEnrollments = is_array($deleteBlockState['enrollments'] ?? null) ? $deleteBlockState['enrollments'] : [];
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowedStatuses = ['all', 'draft', 'active', 'archived'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$countStatement = $pdo->query("SELECT
    COUNT(*) AS total_courses,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_courses,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_courses,
    SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_courses
    FROM courses");
$courseCounts = $countStatement->fetch() ?: [];

$coursesSql = "SELECT courses.id, courses.course_code, courses.title, courses.duration_months, courses.total_stages,
                      courses.weeks_per_stage, courses.status, courses.updated_at,
                      COUNT(DISTINCT enrollments.id) AS enrollment_count,
                      CONCAT(users.first_name, ' ', users.last_name) AS created_by_name
               FROM courses
               LEFT JOIN users ON users.id = courses.created_by
               LEFT JOIN enrollments ON enrollments.course_id = courses.id
               WHERE 1 = 1";
$params = [];

if ($searchTerm !== '') {
    $coursesSql .= " AND (
        courses.title LIKE :search
        OR courses.course_code LIKE :search
        OR CONCAT(users.first_name, ' ', users.last_name) LIKE :search
    )";
    $params['search'] = '%' . $searchTerm . '%';
}

if ($statusFilter !== 'all') {
    $coursesSql .= ' AND courses.status = :status';
    $params['status'] = $statusFilter;
}

$coursesSql .= " GROUP BY courses.id, courses.course_code, courses.title, courses.duration_months, courses.total_stages,
                          courses.weeks_per_stage, courses.status, courses.updated_at, users.first_name, users.last_name
                 ORDER BY courses.id DESC";

$coursesStatement = $pdo->prepare($coursesSql);
$coursesStatement->execute($params);
$courses = $coursesStatement->fetchAll();

$pageTitle = 'Course Management - ' . APP_NAME;
$pageHeading = 'Course Management';
$pageDescription = 'Review, search, filter, create, edit, and delete course records from one management view.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Courses</div><div class="metric-value"><?php echo e((string) ($courseCounts['total_courses'] ?? 0)); ?></div><div class="metric-trend text-muted">All course records in the platform</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active Courses</div><div class="metric-value"><?php echo e((string) ($courseCounts['active_courses'] ?? 0)); ?></div><div class="metric-trend text-muted">Available for operational use</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Draft Courses</div><div class="metric-value"><?php echo e((string) ($courseCounts['draft_courses'] ?? 0)); ?></div><div class="metric-trend text-muted">Still being prepared</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Archived Courses</div><div class="metric-value"><?php echo e((string) ($courseCounts['archived_courses'] ?? 0)); ?></div><div class="metric-trend text-muted">No longer active for current delivery</div></div></div>
</div>

<?php if ($blockedEnrollments !== []): ?>
    <div class="alert alert-warning border-0 shadow-sm">
        <div class="fw-semibold mb-2">The course &quot;<?php echo e($blockedCourseTitle); ?>&quot; still has enrollments.</div>
        <p class="mb-2">Delete these enrollment records first before removing the course:</p>
        <ul class="mb-3">
            <?php foreach ($blockedEnrollments as $enrollment): ?>
                <li><?php echo e((string) ($enrollment['student_name'] ?? 'Unknown student')); ?> (<?php echo e((string) ($enrollment['student_email'] ?? 'No email')); ?>) - Batch: <?php echo e((string) ($enrollment['batch_name'] ?? 'Unknown batch')); ?> - Status: <?php echo e(ucfirst((string) ($enrollment['status'] ?? 'unknown'))); ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="<?php echo e(base_url('admin/enrollments/index.php')); ?>" class="btn btn-sm btn-outline-dark">Open Enrollments</a>
    </div>
<?php endif; ?>

<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Manage Courses</h2>
            <p class="text-muted mb-0">Search existing courses, filter the list, and open a dedicated page to create a new course.</p>
        </div>
        <a href="<?php echo e(base_url('admin/courses/form.php')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add New Course</a>
    </div>

    <form method="GET" action="<?php echo e(base_url('admin/courses/index.php')); ?>">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label for="search" class="form-label">Search Courses</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo e($searchTerm); ?>" placeholder="Search by course title, code, or creator">
            </div>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Go</button>
            </div>
        </div>
    </form>
</div>

<div class="surface-card table-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Created Courses</h2><p class="text-muted mb-0"><?php echo e((string) count($courses)); ?> course<?php echo count($courses) === 1 ? '' : 's'; ?> shown<?php echo $searchTerm !== '' || $statusFilter !== 'all' ? ' for the current filter' : ''; ?>.</p></div></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Course</th><th>Structure</th><th>Status</th><th>Updated</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($courses === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No courses matched your current search or filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><div class="fw-semibold"><?php echo e($course['title']); ?></div><div class="small text-muted"><?php echo e($course['course_code']); ?> | Created by: <?php echo e($course['created_by_name'] ?: 'Unknown'); ?></div><div class="small text-muted"><?php echo e((string) $course['enrollment_count']); ?> enrollment<?php echo (int) $course['enrollment_count'] === 1 ? '' : 's'; ?></div></td>
                        <td><div class="small"><?php echo e((string) $course['duration_months']); ?> months</div><div class="small text-muted"><?php echo e((string) $course['total_stages']); ?> stages, <?php echo e((string) $course['weeks_per_stage']); ?> weeks each</div></td>
                        <td><span class="status-chip <?php echo $course['status'] === 'active' ? 'success' : ($course['status'] === 'draft' ? 'warning' : 'danger'); ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($course['status'])); ?></span></td>
                        <td><?php echo e(format_datetime($course['updated_at'])); ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?php echo e(base_url('admin/courses/form.php?edit=' . (string) $course['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCourseModal<?php echo e((string) $course['id']); ?>">Delete</button>
                            </div>
                            <div class="modal fade" id="deleteCourseModal<?php echo e((string) $course['id']); ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h3 class="modal-title h5 mb-0">Delete Course</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-2">Are you sure you want to delete <strong><?php echo e($course['title']); ?></strong>?</p><p class="text-muted mb-0">If the course has no enrollments, its stages, units, topics, sessions, batch links, and related assignments will also be removed.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><form action="<?php echo e(base_url('actions/admin/delete_course.php')); ?>" method="POST"><input type="hidden" name="course_id" value="<?php echo e((string) $course['id']); ?>"><button type="submit" class="btn btn-danger">Confirm Delete</button></form></div></div></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>

