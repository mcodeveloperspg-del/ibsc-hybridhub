<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$batchId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($batchId < 1) {
    flash_message('Select a batch to edit.', 'warning');
    redirect(base_url('admin/batches/index.php'));
}

$statement = $pdo->prepare('SELECT * FROM batches WHERE id = :id LIMIT 1');
$statement->execute(['id' => $batchId]);
$batch = $statement->fetch();

if (!$batch) {
    flash_message('Batch not found.', 'warning');
    redirect(base_url('admin/batches/index.php'));
}

$formState = consume_form_state('batch_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$stageSchedule = [];

$scheduleStatement = $pdo->prepare('SELECT stage_number, start_date, end_date FROM batch_stages WHERE batch_id = :batch_id ORDER BY stage_number ASC');
$scheduleStatement->execute(['batch_id' => $batchId]);
foreach ($scheduleStatement->fetchAll() as $stage) {
    $stageSchedule[(int) $stage['stage_number']] = $stage;
}

$formData = [
    'id' => $oldData['id'] ?? $batch['id'],
    'batch_number' => $oldData['batch_number'] ?? $batch['batch_number'],
    'total_stages' => $oldData['total_stages'] ?? $batch['total_stages'],
    'start_date' => $oldData['start_date'] ?? $batch['start_date'],
    'end_date' => $oldData['end_date'] ?? $batch['end_date'],
    'status' => $oldData['status'] ?? $batch['status'],
    'notes' => $oldData['notes'] ?? $batch['notes'],
];
$stageStartDates = is_array($oldData['stage_start_dates'] ?? null) ? $oldData['stage_start_dates'] : [];
$stageEndDates = is_array($oldData['stage_end_dates'] ?? null) ? $oldData['stage_end_dates'] : [];
$stageFormData = [];
$stageCount = max(1, (int) $formData['total_stages']);

for ($stageNumber = 1; $stageNumber <= $stageCount; $stageNumber++) {
    $stageFormData[$stageNumber] = [
        'start_date' => $stageStartDates[$stageNumber] ?? ($stageSchedule[$stageNumber]['start_date'] ?? ''),
        'end_date' => $stageEndDates[$stageNumber] ?? ($stageSchedule[$stageNumber]['end_date'] ?? ''),
    ];
}

$stageFormJson = json_encode($stageFormData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$previewYear = batch_year_from_date((string) $formData['start_date']);
$previewName = ((int) $formData['batch_number']) > 0 ? batch_name_from_number((int) $formData['batch_number']) : 'Batch number pending';
$previewCode = ($previewYear !== null && (int) $formData['batch_number'] > 0)
    ? batch_intake_code((int) $formData['batch_number'], $previewYear)
    : 'Intake code will be generated after you enter the batch number and start date';

$pageTitle = 'Edit Batch - ' . APP_NAME;
$pageHeading = 'Edit Batch';
$pageDescription = 'Update the batch details and stage date schedule from a dedicated page.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></h2>
                    <p class="text-muted mb-0">Edit the batch and keep each stage schedule accurate.</p>
                </div>
                <a href="<?php echo e(base_url('admin/batches/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Batches</a>
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

            <div class="border rounded-4 bg-light p-3 mb-3">
                <div class="small text-muted">Generated batch details</div>
                <div class="fw-semibold"><?php echo e($previewName); ?></div>
                <div class="small text-muted"><?php echo e($previewYear !== null ? 'Year ' . (string) $previewYear : 'Year will be taken from the start date'); ?></div>
                <div class="small text-muted"><?php echo e($previewCode); ?></div>
            </div>

            <form action="<?php echo e(base_url('actions/admin/save_batch.php')); ?>" method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?php echo e((string) $formData['id']); ?>">
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
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Batch</button>
                    <a href="<?php echo e(base_url('admin/batches/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
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
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
