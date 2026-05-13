<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$counts = $pdo->query(
    "SELECT
        COUNT(*) AS total_students,
        SUM(CASE WHEN users.status = 'active' THEN 1 ELSE 0 END) AS active_students,
        SUM(CASE WHEN users.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_students,
        SUM(CASE WHEN users.status = 'suspended' THEN 1 ELSE 0 END) AS suspended_students
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     WHERE roles.name = 'student'"
)->fetch() ?: [];

$students = $pdo->query(
    "SELECT users.id, users.student_number, users.first_name, users.last_name, users.email, users.phone, users.gender, users.avatar, users.status, users.password_must_reset, users.created_at,
            COUNT(enrollments.id) AS enrollment_count,
            GROUP_CONCAT(
                CONCAT(courses.title, ' - ', batches.batch_name, ' (', batches.batch_year, ') [', enrollments.status, ']')
                ORDER BY enrollments.enrollment_date DESC, enrollments.id DESC
                SEPARATOR '||'
            ) AS enrollment_summary
     FROM users
     INNER JOIN roles ON roles.id = users.role_id
     LEFT JOIN enrollments ON enrollments.student_id = users.id
     LEFT JOIN courses ON courses.id = enrollments.course_id
     LEFT JOIN batches ON batches.id = enrollments.batch_id
     WHERE roles.name = 'student'
     GROUP BY users.id, users.student_number, users.first_name, users.last_name, users.email, users.phone, users.gender, users.avatar, users.status, users.password_must_reset, users.created_at
     ORDER BY users.id DESC"
)->fetchAll();

$pageTitle = 'Student Accounts - ' . APP_NAME;
$pageHeading = 'Student Accounts';
$pageDescription = 'View and filter student login accounts. Actual enrollment happens separately by course and batch.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Students</div><div class="metric-value"><?php echo e((string) ($counts['total_students'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($counts['active_students'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Inactive</div><div class="metric-value"><?php echo e((string) ($counts['inactive_students'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Suspended</div><div class="metric-value"><?php echo e((string) ($counts['suspended_students'] ?? 0)); ?></div></div></div>
</div>

<div class="surface-card table-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Student Accounts</h2>
            <p class="text-muted mb-0">Use the filter to search students instantly by name, ID, email, phone, status, or enrollment.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo e(base_url('admin/students/create.php')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Student Account</a>
            <a href="<?php echo e(base_url('admin/enrollments/create.php')); ?>" target="_blank" rel="noopener" class="btn btn-outline-success">Open Enrollment Page</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <label for="studentFilter" class="form-label">Filter Students</label>
            <input type="search" class="form-control" id="studentFilter" placeholder="Type a name, student ID, email, phone, status, or course">
        </div>
        <div class="col-lg-4 d-flex align-items-end">
            <div class="text-muted small" id="studentFilterCount"><?php echo e((string) count($students)); ?> student<?php echo count($students) === 1 ? '' : 's'; ?> shown</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle" id="studentsTable">
            <thead>
                <tr>
                    <th>ID Photo</th>
                    <th>Student</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Current Enrollment Summary</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <?php
                    $studentName = trim($student['first_name'] . ' ' . $student['last_name']);
                    $studentPhoto = trim((string) ($student['avatar'] ?? ''));
                    $enrollmentSummary = array_filter(explode('||', (string) ($student['enrollment_summary'] ?? '')));
                    $filterText = strtolower($studentName . ' ' . (string) ($student['student_number'] ?? '') . ' ' . (string) $student['email'] . ' ' . (string) $student['phone'] . ' ' . (string) $student['status'] . ' ' . implode(' ', $enrollmentSummary));
                    ?>
                    <tr data-filter-text="<?php echo e($filterText); ?>">
                        <td>
                            <?php if ($studentPhoto !== ''): ?>
                                <img src="<?php echo e(base_url($studentPhoto)); ?>" alt="<?php echo e($studentName); ?> ID photo" class="student-id-photo">
                            <?php else: ?>
                                <img src="<?php echo e(base_url('assets/img/student-profile-placeholder.svg')); ?>" alt="Blank student ID photo" class="student-id-photo">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo e($studentName); ?></div>
                            <div class="small text-muted">Student ID: <?php echo e((string) ($student['student_number'] ?: $student['id'])); ?></div>
                            <div class="small text-muted">Created: <?php echo e(format_datetime($student['created_at'])); ?></div>
                        </td>
                        <td>
                            <div><?php echo e($student['email']); ?></div>
                            <div class="small text-muted"><?php echo e($student['phone'] ?: 'No phone added'); ?></div>
                        </td>
                        <td>
                            <span class="status-chip <?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($student['status'])); ?>
                            </span>
                            <?php if ((int) ($student['password_must_reset'] ?? 0) === 1): ?>
                                <div class="small text-muted mt-2">Password reset required</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($enrollmentSummary === []): ?>
                                <span class="text-muted small">No course/batch enrollment yet.</span>
                            <?php else: ?>
                                <?php foreach ($enrollmentSummary as $summaryLine): ?>
                                    <div class="small mb-1"><?php echo e($summaryLine); ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                <a href="<?php echo e(base_url('admin/students/form.php?edit=' . (string) $student['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit Details</a>
                                <a href="<?php echo e(base_url('admin/enrollments/create.php?student_id=' . (string) $student['id'])); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">Enroll to Course</a>
                                <form action="<?php echo e(base_url('actions/admin/reset_student_password.php')); ?>" method="POST">
                                    <input type="hidden" name="student_id" value="<?php echo e((string) $student['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning w-100">Reset Password</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(() => {
    const filterInput = document.getElementById('studentFilter');
    const table = document.getElementById('studentsTable');
    const count = document.getElementById('studentFilterCount');
    if (!filterInput || !table || !count) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const render = () => {
        const query = filterInput.value.trim().toLowerCase();
        let shown = 0;

        rows.forEach((row) => {
            const matches = query === '' || (row.dataset.filterText || '').includes(query);
            row.hidden = !matches;
            if (matches) {
                shown++;
            }
        });

        count.textContent = shown + ' student' + (shown === 1 ? '' : 's') + ' shown';
    };

    filterInput.addEventListener('input', render);
    render();
})();
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
