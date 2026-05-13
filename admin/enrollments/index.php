<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$filterStudentId = trim((string) ($_GET['student_id'] ?? ''));
$filterStudentName = trim((string) ($_GET['student_name'] ?? ''));
$filterCourseId = (int) ($_GET['course_id'] ?? 0);
$filterBatchId = (int) ($_GET['batch_id'] ?? 0);
$filterStatus = trim((string) ($_GET['status'] ?? 'current'));
$allowedStatuses = ['current', 'active', 'suspended', 'completed', 'withdrawn', 'all'];

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'current';
}

$courses = $pdo->query('SELECT id, title, course_code FROM courses ORDER BY title ASC')->fetchAll();
$batches = $pdo->query('SELECT id, batch_name, batch_year FROM batches ORDER BY batch_year DESC, batch_number ASC')->fetchAll();

$where = [];
$params = [];

if ($filterStudentId !== '') {
    $where[] = '(users.student_number LIKE :student_number OR CAST(users.id AS CHAR) LIKE :student_id_like)';
    $params['student_number'] = '%' . $filterStudentId . '%';
    $params['student_id_like'] = '%' . $filterStudentId . '%';
}

if ($filterStudentName !== '') {
    $where[] = "CONCAT(users.first_name, ' ', users.last_name) LIKE :student_name";
    $params['student_name'] = '%' . $filterStudentName . '%';
}

if ($filterCourseId > 0) {
    $where[] = 'courses.id = :course_id';
    $params['course_id'] = $filterCourseId;
}

if ($filterBatchId > 0) {
    $where[] = 'batches.id = :batch_id';
    $params['batch_id'] = $filterBatchId;
}

if ($filterStatus === 'current') {
    $where[] = "enrollments.status IN ('active', 'suspended')";
} elseif ($filterStatus !== 'all') {
    $where[] = 'enrollments.status = :status';
    $params['status'] = $filterStatus;
}

$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT
    COUNT(*) AS total_enrollments,
    SUM(CASE WHEN enrollments.status = 'active' THEN 1 ELSE 0 END) AS active_enrollments,
    SUM(CASE WHEN enrollments.status = 'suspended' THEN 1 ELSE 0 END) AS suspended_enrollments,
    SUM(CASE WHEN enrollments.status = 'completed' THEN 1 ELSE 0 END) AS completed_enrollments,
    SUM(CASE WHEN enrollments.status = 'withdrawn' THEN 1 ELSE 0 END) AS withdrawn_enrollments
FROM enrollments
INNER JOIN users ON users.id = enrollments.student_id
INNER JOIN courses ON courses.id = enrollments.course_id
INNER JOIN batches ON batches.id = enrollments.batch_id
$whereSql";
$countStatement = $pdo->prepare($countSql);
$countStatement->execute($params);
$counts = $countStatement->fetch() ?: [];

$enrollmentsSql = "SELECT enrollments.id, enrollments.enrollment_date, enrollments.status,
        users.id AS user_id, users.student_number, users.first_name, users.last_name, users.email,
        courses.id AS course_id, courses.title AS course_title, courses.course_code,
        batches.id AS batch_id, batches.batch_name, batches.batch_year
    FROM enrollments
    INNER JOIN users ON users.id = enrollments.student_id
    INNER JOIN courses ON courses.id = enrollments.course_id
    INNER JOIN batches ON batches.id = enrollments.batch_id
    $whereSql
    ORDER BY enrollments.enrollment_date DESC, enrollments.id DESC";
$enrollmentsStatement = $pdo->prepare($enrollmentsSql);
$enrollmentsStatement->execute($params);
$enrollments = $enrollmentsStatement->fetchAll();

$pageTitle = 'Enrollment Management - ' . APP_NAME;
$pageHeading = 'Enrollment Management';
$pageDescription = 'View currently enrolled students, narrow the list with filters, and open a dedicated page for new enrollments and CSV imports.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Visible Enrollments</div><div class="metric-value"><?php echo e((string) ($counts['total_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($counts['active_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Suspended</div><div class="metric-value"><?php echo e((string) ($counts['suspended_enrollments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Completed or Withdrawn</div><div class="metric-value"><?php echo e((string) (((int) ($counts['completed_enrollments'] ?? 0)) + ((int) ($counts['withdrawn_enrollments'] ?? 0)))); ?></div></div></div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Current Enrolled Students</h2>
            <p class="text-muted mb-0">By default this list shows active and suspended enrollments. Use the status filter to inspect completed or withdrawn records too.</p>
        </div>
        <a href="<?php echo e(base_url('admin/enrollments/create.php')); ?>" target="_blank" rel="noopener" class="btn btn-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Enroll Students
        </a>
    </div>

    <form method="GET" action="<?php echo e(base_url('admin/enrollments/index.php')); ?>" class="row g-3 mb-4">
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Student ID</label>
            <input type="text" name="student_id" class="form-control" value="<?php echo e($filterStudentId); ?>" placeholder="Student ID">
        </div>
        <div class="col-md-4 col-xl-3">
            <label class="form-label">Student Name</label>
            <input type="text" name="student_name" class="form-control" value="<?php echo e($filterStudentName); ?>" placeholder="Student name">
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Course</label>
            <select name="course_id" class="form-select">
                <option value="0">All courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo e((string) $course['id']); ?>" <?php echo $filterCourseId === (int) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Batch</label>
            <select name="batch_id" class="form-select">
                <option value="0">All batches</option>
                <?php foreach ($batches as $batch): ?>
                    <option value="<?php echo e((string) $batch['id']); ?>" <?php echo $filterBatchId === (int) $batch['id'] ? 'selected' : ''; ?>><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-xl-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="current" <?php echo $filterStatus === 'current' ? 'selected' : ''; ?>>Current only</option>
                <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="withdrawn" <?php echo $filterStatus === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All statuses</option>
            </select>
        </div>
        <div class="col-md-4 col-xl-1 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($enrollments === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No enrolled students matched the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($enrollments as $enrollment): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e(trim($enrollment['first_name'] . ' ' . $enrollment['last_name'])); ?></div>
                            <div class="small text-muted">Student ID: <?php echo e((string) ($enrollment['student_number'] ?: $enrollment['user_id'])); ?></div>
                            <div class="small text-muted"><?php echo e($enrollment['email']); ?></div>
                            <div class="small text-muted">Enrolled: <?php echo e(format_datetime($enrollment['enrollment_date'])); ?></div>
                        </td>
                        <td><?php echo e($enrollment['course_title'] . ' (' . $enrollment['course_code'] . ')'); ?></td>
                        <td><?php echo e($enrollment['batch_name'] . ' (' . $enrollment['batch_year'] . ')'); ?></td>
                        <td>
                            <span class="status-chip <?php echo in_array($enrollment['status'], ['active'], true) ? 'success' : 'warning'; ?>">
                                <i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($enrollment['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                            <a href="<?php echo e(base_url('admin/enrollments/create.php?transfer_enrollment_id=' . (string) $enrollment['id'])); ?>" class="btn btn-sm btn-outline-primary">Transfer</a>
                            <form action="<?php echo e(base_url('actions/admin/delete_enrollment.php')); ?>" method="POST" onsubmit="return confirm('Delete this enrollment record?');">
                                <input type="hidden" name="enrollment_id" value="<?php echo e((string) $enrollment['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
