<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['teacher']);

$user = current_user();
$flash = flash_message();
$teacherId = (int) ($user['id'] ?? 0);
$assignedUnits = teacher_assigned_units($pdo, $teacherId);

$batches = [];
foreach ($assignedUnits as $assignedUnit) {
    $batchKey = (string) $assignedUnit['batch_id'];
    if (!isset($batches[$batchKey])) {
        $batches[$batchKey] = [
            'batch_name' => $assignedUnit['batch_name'],
            'batch_year' => $assignedUnit['batch_year'],
            'batch_status' => $assignedUnit['batch_status'],
            'units' => [],
        ];
    }

    $batches[$batchKey]['units'][] = $assignedUnit;
}

$courseIds = [];
foreach ($assignedUnits as $assignedUnit) {
    $courseIds[] = (int) $assignedUnit['course_id'];
}

$pageTitle = 'My Units - ' . APP_NAME;
$pageHeading = 'My Units';
$pageDescription = 'Browse the batch cards for the units currently assigned to this lecturer, including the related course and stage summary.';

require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Assigned Batches</div>
            <div class="metric-value"><?php echo e((string) count($batches)); ?></div>
            <div class="metric-trend text-muted">Batches currently linked to this lecturer</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Assigned Units</div>
            <div class="metric-value"><?php echo e((string) count($assignedUnits)); ?></div>
            <div class="metric-trend text-muted">Active units available for teaching work</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Courses Covered</div>
            <div class="metric-value"><?php echo e((string) count(array_unique($courseIds))); ?></div>
            <div class="metric-trend text-muted">Distinct courses represented in current unit assignments</div>
        </div>
    </div>
</div>

<?php if ($assignedUnits === []): ?>
    <div class="surface-card p-4">
        <h2 class="h5 mb-2">No assigned units yet</h2>
        <p class="text-muted mb-0">This lecturer does not have any active batch-unit assignments at the moment.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($batches as $batch): ?>
            <div class="col-12 col-xl-6">
                <div class="surface-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1"><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></h2>
                            <p class="text-muted mb-0">Assigned units for this batch.</p>
                        </div>
                        <span class="status-chip <?php echo $batch['batch_status'] === 'active' ? 'success' : 'warning'; ?>">
                            <i class="bi bi-layers-fill"></i><?php echo e(ucfirst((string) $batch['batch_status'])); ?>
                        </span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($batch['units'] as $unit): ?>
                            <div class="col-12">
                                <a href="<?php echo e(base_url('teacher/units/view.php?batch_id=' . (string) $unit['batch_id'] . '&unit_id=' . (string) $unit['unit_id'])); ?>" class="text-decoration-none text-reset d-block">
                                    <div class="border rounded-4 p-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="fw-semibold mb-1">
                                                    <?php echo e($unit['unit_title']); ?><?php echo $unit['unit_code'] ? ' (' . e($unit['unit_code']) . ')' : ''; ?>
                                                </div>
                                                <div class="small text-muted mb-1">
                                                    Course: <?php echo e($unit['course_title'] . ' (' . $unit['course_code'] . ')'); ?>
                                                </div>
                                                <div class="small text-muted mb-1">
                                                    Stage: <?php echo e('Stage ' . $unit['stage_number'] . ' - ' . $unit['stage_title']); ?>
                                                </div>
                                                <div class="small text-muted mb-2">
                                                    Summary: Assigned to teach this unit for <?php echo e($batch['batch_name']); ?>.
                                                </div>
                                                <div class="small fw-semibold text-primary">Open unit topics</div>
                                            </div>
                                            <span class="status-chip <?php echo $unit['unit_status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <i class="bi bi-journal-check"></i><?php echo e(ucfirst((string) $unit['unit_status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>


