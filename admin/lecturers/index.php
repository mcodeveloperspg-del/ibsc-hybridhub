<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$lecturerRoleId = role_id_by_name($pdo, 'teacher');

$counts = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_lecturers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_lecturers,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_lecturers,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) AS suspended_lecturers
     FROM users
     WHERE role_id = :role_id"
);
$counts->execute(['role_id' => $lecturerRoleId]);
$lecturerCounts = $counts->fetch() ?: [];

$lecturersStatement = $pdo->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.created_at,
            COUNT(DISTINCT lecturer_unit_assignments.id) AS assignment_count
     FROM users
     LEFT JOIN lecturer_unit_assignments
        ON lecturer_unit_assignments.lecturer_id = users.id
       AND lecturer_unit_assignments.status = 'active'
     WHERE users.role_id = :role_id
     GROUP BY users.id, users.first_name, users.last_name, users.email, users.phone, users.status, users.created_at
     ORDER BY users.id DESC"
);
$lecturersStatement->execute(['role_id' => $lecturerRoleId]);
$lecturers = $lecturersStatement->fetchAll();

$pageTitle = 'Lecturer Management - ' . APP_NAME;
$pageHeading = 'Lecturer Management';
$pageDescription = 'Review lecturer accounts, open the dedicated page to add a new lecturer, and manage lecturer records safely.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Lecturers</div><div class="metric-value"><?php echo e((string) ($lecturerCounts['total_lecturers'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($lecturerCounts['active_lecturers'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Inactive</div><div class="metric-value"><?php echo e((string) ($lecturerCounts['inactive_lecturers'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Suspended</div><div class="metric-value"><?php echo e((string) ($lecturerCounts['suspended_lecturers'] ?? 0)); ?></div></div></div>
</div>

<div class="surface-card table-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Lecturer Accounts</h2>
            <p class="text-muted mb-0">These accounts can sign in and manage resources for their assigned units.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(base_url('admin/lecturer_assignments/index.php')); ?>" class="btn btn-outline-secondary">Manage Assignments</a>
            <a href="<?php echo e(base_url('admin/lecturers/create.php')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Lecturer</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Lecturer</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Active Assignments</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lecturers === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No lecturer accounts have been created yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($lecturers as $lecturer): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e(trim($lecturer['first_name'] . ' ' . $lecturer['last_name'])); ?></div>
                            <div class="small text-muted">Created: <?php echo e(format_datetime($lecturer['created_at'])); ?></div>
                        </td>
                        <td>
                            <div><?php echo e($lecturer['email']); ?></div>
                            <div class="small text-muted"><?php echo e($lecturer['phone'] ?: 'No phone added'); ?></div>
                        </td>
                        <td>
                            <span class="status-chip <?php echo $lecturer['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($lecturer['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo e((string) $lecturer['assignment_count']); ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?php echo e(base_url('admin/lecturers/form.php?edit=' . (string) $lecturer['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteLecturerModal<?php echo e((string) $lecturer['id']); ?>">Delete</button>
                            </div>

                            <div class="modal fade" id="deleteLecturerModal<?php echo e((string) $lecturer['id']); ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3 class="modal-title h5 mb-0">Delete Lecturer</h3>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-2">Are you sure you want to delete <strong><?php echo e(trim($lecturer['first_name'] . ' ' . $lecturer['last_name'])); ?></strong>?</p>
                                            <?php if ((int) $lecturer['assignment_count'] > 0): ?>
                                                <p class="text-muted mb-0">This lecturer is assigned to <?php echo e((string) $lecturer['assignment_count']); ?> active course unit<?php echo (int) $lecturer['assignment_count'] === 1 ? '' : 's'; ?>. Deleting the lecturer will also remove those assignment records.</p>
                                            <?php else: ?>
                                                <p class="text-muted mb-0">This lecturer is not assigned to any active course units, so the record can be deleted directly.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form action="<?php echo e(base_url('actions/admin/delete_lecturer.php')); ?>" method="POST">
                                                <input type="hidden" name="lecturer_id" value="<?php echo e((string) $lecturer['id']); ?>">
                                                <button type="submit" class="btn btn-danger">Confirm Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
