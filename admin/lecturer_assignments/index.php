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
        COUNT(*) AS total_assignments,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_assignments,
        COUNT(DISTINCT lecturer_id) AS active_lecturers,
        COUNT(DISTINCT batch_id) AS active_batches
     FROM lecturer_unit_assignments"
)->fetch() ?: [];

$assignments = $pdo->query(
    "SELECT lecturer_unit_assignments.id, lecturer_unit_assignments.status, lecturer_unit_assignments.assigned_at,
            users.first_name, users.last_name,
            batches.batch_name, batches.batch_year,
            courses.title AS course_title, courses.course_code,
            stages.stage_number, stages.title AS stage_title,
            units.unit_title, units.unit_code
     FROM lecturer_unit_assignments
     INNER JOIN users ON users.id = lecturer_unit_assignments.lecturer_id
     INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
     INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
     INNER JOIN courses ON courses.id = units.course_id
     INNER JOIN stages ON stages.id = units.stage_id
     ORDER BY lecturer_unit_assignments.assigned_at DESC, lecturer_unit_assignments.id DESC"
)->fetchAll();

$pageTitle = 'Unit Lecturer Assignment - ' . APP_NAME;
$pageHeading = 'Unit Lecturer Assignment';
$pageDescription = 'Review unit lecturer assignments by batch and open a dedicated page to assign lecturers to additional course units. A lecturer can hold multiple course assignments.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Assignments</div><div class="metric-value"><?php echo e((string) ($counts['total_assignments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($counts['active_assignments'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Lecturers in Use</div><div class="metric-value"><?php echo e((string) ($counts['active_lecturers'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Batches Covered</div><div class="metric-value"><?php echo e((string) ($counts['active_batches'] ?? 0)); ?></div></div></div>
</div>

<div class="surface-card table-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Current Unit Lecturer Assignments</h2>
            <p class="text-muted mb-0">Each batch and unit pair holds one lecturer assignment, and the same lecturer can still be assigned across multiple courses and batches.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="btn btn-outline-secondary">Manage Lecturers</a>
            <a href="<?php echo e(base_url('admin/lecturer_assignments/create.php')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Assign Lecturer to Unit</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Lecturer</th>
                    <th>Batch / Course</th>
                    <th>Stage / Unit</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No unit lecturer assignments have been created yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e(trim($assignment['first_name'] . ' ' . $assignment['last_name'])); ?></div>
                            <div class="small text-muted">Assigned: <?php echo e(format_datetime($assignment['assigned_at'])); ?></div>
                        </td>
                        <td>
                            <div class="small"><?php echo e($assignment['batch_name'] . ' (' . $assignment['batch_year'] . ')'); ?></div>
                            <div class="small text-muted"><?php echo e($assignment['course_title'] . ' (' . $assignment['course_code'] . ')'); ?></div>
                        </td>
                        <td>
                            <div class="small">Stage <?php echo e((string) $assignment['stage_number']); ?> - <?php echo e($assignment['stage_title']); ?></div>
                            <div class="small text-muted"><?php echo e($assignment['unit_title'] . ($assignment['unit_code'] ? ' (' . $assignment['unit_code'] . ')' : '')); ?></div>
                        </td>
                        <td><span class="status-chip <?php echo $assignment['status'] === 'active' ? 'success' : 'warning'; ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($assignment['status'])); ?></span></td>
                        <td><a href="<?php echo e(base_url('admin/lecturer_assignments/create.php?edit=' . (string) $assignment['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
