<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$formState = consume_form_state('lecturer_assignment_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingAssignment = null;

if ($editingId > 0) {
    $editingStatement = $pdo->prepare(
        "SELECT lecturer_unit_assignments.id, lecturer_unit_assignments.lecturer_id, lecturer_unit_assignments.batch_id,
                lecturer_unit_assignments.unit_id, lecturer_unit_assignments.status, units.course_id
         FROM lecturer_unit_assignments
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         WHERE lecturer_unit_assignments.id = :id
         LIMIT 1"
    );
    $editingStatement->execute(['id' => $editingId]);
    $editingAssignment = $editingStatement->fetch();

    if (!$editingAssignment) {
        flash_message('The selected assignment could not be found.', 'warning');
        redirect(base_url('admin/lecturer_assignments/index.php'));
    }
}

$lecturerRoleId = role_id_by_name($pdo, 'teacher');
$lecturersStatement = $pdo->prepare('SELECT id, first_name, last_name, email, status FROM users WHERE role_id = :role_id ORDER BY first_name ASC, last_name ASC');
$lecturersStatement->execute(['role_id' => $lecturerRoleId]);
$lecturers = $lecturersStatement->fetchAll();

$batches = $pdo->query('SELECT id, batch_name, batch_year, status FROM batches ORDER BY batch_year DESC, batch_number ASC')->fetchAll();
$courses = $pdo->query('SELECT id, title, course_code FROM courses ORDER BY title ASC')->fetchAll();
$contexts = lecturer_assignment_contexts($pdo);
$contextJson = json_encode(array_values($contexts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$assignmentFormData = [
    'id' => $oldData['id'] ?? ($editingAssignment['id'] ?? ''),
    'lecturer_id' => $oldData['lecturer_id'] ?? ($editingAssignment['lecturer_id'] ?? ''),
    'batch_id' => $oldData['batch_id'] ?? ($editingAssignment['batch_id'] ?? ''),
    'course_id' => $oldData['course_id'] ?? ($editingAssignment['course_id'] ?? ''),
    'unit_id' => $oldData['unit_id'] ?? ($editingAssignment['unit_id'] ?? ''),
    'status' => $oldData['status'] ?? ($editingAssignment['status'] ?? 'active'),
];

$pageTitle = ($editingAssignment ? 'Edit Unit Lecturer Assignment' : 'Assign Lecturer to Unit') . ' - ' . APP_NAME;
$pageHeading = $editingAssignment ? 'Edit Unit Lecturer Assignment' : 'Assign Lecturer to Unit';
$pageDescription = 'Assign a lecturer to a batch unit from a dedicated page. A lecturer can be assigned to multiple course units across different batches.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo $editingAssignment ? 'Edit Unit Lecturer Assignment' : 'Assign Lecturer to Unit'; ?></h2>
                    <p class="text-muted mb-0">Pick the batch and course first. The system then shows the stage currently in progress and only the units available in that stage.</p>
                </div>
                <a href="<?php echo e(base_url('admin/lecturer_assignments/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Back to Unit Lecturer Assignment</a>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div id="stageSummary" class="border rounded-4 bg-light p-3 mb-3">
                <div class="small text-muted">Stage in progress</div>
                <div class="fw-semibold">Choose a batch and course</div>
                <div class="small text-muted">The matching current stage and available units will appear here.</div>
            </div>

            <form action="<?php echo e(base_url('actions/admin/save_lecturer_assignment.php')); ?>" method="POST" class="row g-3" id="assignment-form">
                <input type="hidden" name="id" value="<?php echo e((string) $assignmentFormData['id']); ?>">

                <div class="col-12">
                    <label class="form-label">Lecturer</label>
                    <select class="form-select" name="lecturer_id" required>
                        <option value="">Select lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo e((string) $lecturer['id']); ?>" <?php echo (string) $assignmentFormData['lecturer_id'] === (string) $lecturer['id'] ? 'selected' : ''; ?>><?php echo e(trim($lecturer['first_name'] . ' ' . $lecturer['last_name']) . ' - ' . $lecturer['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">The same lecturer can be assigned to multiple courses and units.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Batch</label>
                    <select class="form-select" id="batch_id" name="batch_id" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo e((string) $batch['id']); ?>" <?php echo (string) $assignmentFormData['batch_id'] === (string) $batch['id'] ? 'selected' : ''; ?>><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select class="form-select" id="course_id" name="course_id" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo e((string) $course['id']); ?>" <?php echo (string) $assignmentFormData['course_id'] === (string) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Unit</label>
                    <select class="form-select" id="unit_id" name="unit_id" required>
                        <option value="">Select batch and course first</option>
                    </select>
                    <div class="form-text">Only units inside the current stage for the selected batch are shown.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['active', 'inactive'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo (string) $assignmentFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo $editingAssignment ? 'Update Assignment' : 'Save Assignment'; ?></button>
                    <a href="<?php echo e(base_url('admin/lecturer_assignments/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const contexts = <?php echo $contextJson ?: '[]'; ?>;
    const contextMap = new Map(contexts.map((context) => [String(context.batch_id) + '-' + String(context.course_id), context]));
    const batchSelect = document.getElementById('batch_id');
    const courseSelect = document.getElementById('course_id');
    const unitSelect = document.getElementById('unit_id');
    const stageSummary = document.getElementById('stageSummary');
    const selectedUnitId = '<?php echo e((string) $assignmentFormData['unit_id']); ?>';

    if (!batchSelect || !courseSelect || !unitSelect || !stageSummary) {
        return;
    }

    function renderSummary(context) {
        if (!context) {
            stageSummary.innerHTML = '<div class="small text-muted">Stage in progress</div><div class="fw-semibold">Choose a batch and course</div><div class="small text-muted">The matching current stage and available units will appear here.</div>';
            return;
        }

        const unitCount = Array.isArray(context.units) ? context.units.length : 0;
        stageSummary.innerHTML = '<div class="small text-muted">Stage in progress</div>' +
            '<div class="fw-semibold">' + context.course_label + ' | Stage ' + context.stage_number + ' (' + context.stage_title + ')</div>' +
            '<div class="small text-muted">' + (context.progress && context.progress.label ? context.progress.label : '') + '</div>' +
            '<div class="small text-muted">Available units in this stage: ' + unitCount + '</div>';
    }

    function renderUnits(context) {
        unitSelect.innerHTML = '';

        if (!context) {
            unitSelect.appendChild(new Option('Select batch and course first', ''));
            return;
        }

        if (!Array.isArray(context.units) || context.units.length === 0) {
            unitSelect.appendChild(new Option('No units found for the current stage', ''));
            return;
        }

        unitSelect.appendChild(new Option('Select unit', ''));
        context.units.forEach((unit) => {
            const option = new Option(unit.label, String(unit.id));
            if (String(unit.id) === selectedUnitId) {
                option.selected = true;
            }
            unitSelect.appendChild(option);
        });
    }

    function syncContext() {
        const key = String(batchSelect.value) + '-' + String(courseSelect.value);
        const context = contextMap.get(key) || null;
        renderSummary(context);
        renderUnits(context);
    }

    batchSelect.addEventListener('change', syncContext);
    courseSelect.addEventListener('change', syncContext);
    syncContext();
})();
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
