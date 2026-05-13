<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('batch_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$formData = [
    'id' => $oldData['id'] ?? '',
    'batch_number' => $oldData['batch_number'] ?? '',
    'total_stages' => $oldData['total_stages'] ?? 4,
    'start_date' => $oldData['start_date'] ?? '',
    'end_date' => $oldData['end_date'] ?? '',
    'status' => $oldData['status'] ?? 'planned',
    'notes' => $oldData['notes'] ?? '',
];
$stageStartDates = is_array($oldData['stage_start_dates'] ?? null) ? $oldData['stage_start_dates'] : [];
$stageEndDates = is_array($oldData['stage_end_dates'] ?? null) ? $oldData['stage_end_dates'] : [];
$stageFormData = [];
$stageCount = max(1, (int) $formData['total_stages']);
for ($stageNumber = 1; $stageNumber <= $stageCount; $stageNumber++) {
    $stageFormData[$stageNumber] = [
        'start_date' => $stageStartDates[$stageNumber] ?? '',
        'end_date' => $stageEndDates[$stageNumber] ?? '',
    ];
}
$stageFormJson = json_encode($stageFormData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$previewYear = batch_year_from_date((string) $formData['start_date']);
$previewName = ((int) $formData['batch_number']) > 0 ? batch_name_from_number((int) $formData['batch_number']) : 'Batch number pending';
$previewCode = ($previewYear !== null && (int) $formData['batch_number'] > 0)
    ? batch_intake_code((int) $formData['batch_number'], $previewYear)
    : 'Intake code will be generated after you enter the batch number and start date';
$showCreateModal = $errors !== [];

$counts = $pdo->query("SELECT COUNT(*) AS total_batches, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_batches, SUM(CASE WHEN status='planned' THEN 1 ELSE 0 END) AS planned_batches, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_batches FROM batches")->fetch() ?: [];
$batches = $pdo->query(
    "SELECT batches.*, COUNT(batch_courses.course_id) AS course_count,
            GROUP_CONCAT(CONCAT(courses.title, ' (', courses.course_code, ')') ORDER BY courses.title ASC SEPARATOR ', ') AS course_list
     FROM batches
     LEFT JOIN batch_courses ON batch_courses.batch_id = batches.id
     LEFT JOIN courses ON courses.id = batch_courses.course_id
     GROUP BY batches.id, batches.batch_number, batches.batch_year, batches.batch_name, batches.intake_code,
              batches.total_stages, batches.start_date, batches.end_date, batches.status, batches.notes, batches.created_at, batches.updated_at
     ORDER BY batches.batch_year DESC, batches.batch_number ASC, batches.start_date DESC"
)->fetchAll();
$batchStageSchedules = [];
foreach ($pdo->query('SELECT batch_id, stage_number, start_date, end_date FROM batch_stages ORDER BY batch_id ASC, stage_number ASC')->fetchAll() as $stageSchedule) {
    $batchStageSchedules[(int) $stageSchedule['batch_id']][] = $stageSchedule;
}

$pageTitle = 'Batch Management - ' . APP_NAME;
$pageHeading = 'Batch Management';
$pageDescription = 'Create numbered yearly batches once, then let the system attach all current and future courses automatically while the batch remains open.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Batches</div><div class="metric-value"><?php echo e((string) ($counts['total_batches'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active</div><div class="metric-value"><?php echo e((string) ($counts['active_batches'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Planned</div><div class="metric-value"><?php echo e((string) ($counts['planned_batches'] ?? 0)); ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Completed</div><div class="metric-value"><?php echo e((string) ($counts['completed_batches'] ?? 0)); ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="surface-card table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <h2 class="h5 mb-0">Batch List</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                    <i class="bi bi-plus-circle"></i> Create Batch
                </button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Year</th>
                            <th>Dates</th>
                            <th>Stages</th>
                            <th>Courses</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><div class="fw-semibold"><?php echo e($batch['batch_name']); ?></div><div class="small text-muted"><?php echo e($batch['intake_code']); ?></div></td>
                                <td><?php echo e((string) $batch['batch_year']); ?></td>
                                <td><div class="small"><?php echo e($batch['start_date']); ?></div><div class="small text-muted"><?php echo e($batch['end_date'] ?: 'No end date'); ?></div></td>
                                <td>
                                    <div class="small fw-semibold"><?php echo e((string) $batch['total_stages']); ?> stage<?php echo (int) $batch['total_stages'] === 1 ? '' : 's'; ?></div>
                                    <div class="small text-muted">
                                        <?php $stageSchedule = $batchStageSchedules[(int) $batch['id']] ?? []; ?>
                                        <?php echo e($stageSchedule !== [] ? 'Stage 1: ' . $stageSchedule[0]['start_date'] . ' to ' . $stageSchedule[0]['end_date'] : 'Schedule not set'); ?>
                                    </div>
                                </td>
                                <td><div class="small fw-semibold"><?php echo e((string) ($batch['course_count'] ?? 0)); ?> linked course<?php echo (int) ($batch['course_count'] ?? 0) === 1 ? '' : 's'; ?></div><div class="small text-muted"><?php echo e($batch['course_list'] ?: 'Courses will appear here after creation.'); ?></div></td>
                                <td><span class="status-chip <?php echo $batch['status'] === 'active' ? 'success' : ($batch['status'] === 'planned' ? 'info' : 'warning'); ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($batch['status'])); ?></span></td>
                                <td><a href="<?php echo e(base_url('admin/batches/edit.php?id=' . (string) $batch['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="createBatchModal" tabindex="-1" aria-labelledby="createBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="createBatchModalLabel">Create Batch</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo e(base_url('actions/admin/save_batch.php')); ?>" method="POST">
                <div class="modal-body">
                    <?php if ($showCreateModal): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="border rounded-4 bg-light p-3 mb-3">
                        <div class="small text-muted">Generated batch details</div>
                        <div class="fw-semibold"><?php echo e($previewName); ?></div>
                        <div class="small text-muted"><?php echo e($previewYear !== null ? 'Year ' . (string) $previewYear : 'Year will be taken from the start date'); ?></div>
                        <div class="small text-muted"><?php echo e($previewCode); ?></div>
                    </div>

                    <input type="hidden" name="id" value="">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Batch Number</label><input type="number" min="1" class="form-control" name="batch_number" value="<?php echo e((string) $formData['batch_number']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Total Stages</label><input type="number" min="1" class="form-control batch-total-stages" name="total_stages" value="<?php echo e((string) $formData['total_stages']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" value="<?php echo e((string) $formData['start_date']); ?>" required></div>
                        <div class="col-md-6"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" value="<?php echo e((string) $formData['end_date']); ?>"></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status" required><?php foreach (['planned', 'active', 'completed', 'archived'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $formData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12">
                            <div class="border rounded-4 p-3">
                                <div class="fw-semibold mb-2">Stage Schedule</div>
                                <div class="row g-3 batch-stage-schedule" data-stage-values="<?php echo e($stageFormJson ?: '{}'); ?>"></div>
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?php echo e((string) $formData['notes']); ?></textarea></div>
                        <div class="col-12"><div class="small text-muted">All existing courses are linked automatically when the batch is saved. New courses will also join this batch automatically until the batch end date has passed.</div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        var totalStagesInput = form.querySelector('.batch-total-stages');
        var scheduleContainer = form.querySelector('.batch-stage-schedule');

        if (!totalStagesInput || !scheduleContainer) {
            return;
        }

        var stageValues = {};
        try {
            stageValues = JSON.parse(scheduleContainer.dataset.stageValues || '{}');
        } catch (error) {
            stageValues = {};
        }

        function currentValues() {
            scheduleContainer.querySelectorAll('[data-stage-number]').forEach(function (row) {
                var stageNumber = row.dataset.stageNumber;
                var startInput = row.querySelector('[name^="stage_start_dates"]');
                var endInput = row.querySelector('[name^="stage_end_dates"]');
                stageValues[stageNumber] = {
                    start_date: startInput ? startInput.value : '',
                    end_date: endInput ? endInput.value : ''
                };
            });
        }

        function renderSchedule() {
            currentValues();
            var totalStages = Math.max(1, parseInt(totalStagesInput.value || '1', 10));
            scheduleContainer.innerHTML = '';

            for (var stageNumber = 1; stageNumber <= totalStages; stageNumber += 1) {
                var values = stageValues[String(stageNumber)] || {};
                var startDate = String(values.start_date || '').replace(/[^0-9-]/g, '');
                var endDate = String(values.end_date || '').replace(/[^0-9-]/g, '');
                var row = document.createElement('div');
                row.className = 'col-12';
                row.dataset.stageNumber = String(stageNumber);
                row.innerHTML =
                    '<div class="row g-2 align-items-end">' +
                        '<div class="col-12"><div class="small fw-semibold">Stage ' + stageNumber + '</div></div>' +
                        '<div class="col-md-6">' +
                            '<label class="form-label small">Start Date</label>' +
                            '<input type="date" class="form-control" name="stage_start_dates[' + stageNumber + ']" value="' + startDate + '" required>' +
                        '</div>' +
                        '<div class="col-md-6">' +
                            '<label class="form-label small">End Date</label>' +
                            '<input type="date" class="form-control" name="stage_end_dates[' + stageNumber + ']" value="' + endDate + '" required>' +
                        '</div>' +
                    '</div>';
                scheduleContainer.appendChild(row);
            }
        }

        totalStagesInput.addEventListener('input', renderSchedule);
        renderSchedule();
    });
});
</script>

<?php if ($showCreateModal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalElement = document.getElementById('createBatchModal');
            if (modalElement && window.bootstrap) {
                new bootstrap.Modal(modalElement).show();
            }
        });
    </script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
