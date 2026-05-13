<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('topic_form_state', [
    'errors' => [],
    'data' => [],
]);

$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$editingTopicId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$requestedUnitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : 0;
$returnUnitId = isset($_GET['return_unit_id']) ? (int) $_GET['return_unit_id'] : (int) ($oldData['return_unit_id'] ?? 0);
$editingTopic = null;

if ($editingTopicId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM topics WHERE id = :id LIMIT 1');
    $editStatement->execute(['id' => $editingTopicId]);
    $editingTopic = $editStatement->fetch();

    if (!$editingTopic) {
        flash_message('The selected topic could not be found.', 'warning');
        redirect(base_url('admin/topics/index.php'));
    }
}

$unitsStatement = $pdo->query(
    "SELECT units.id, units.unit_title, units.unit_code, courses.title AS course_title, stages.title AS stage_title, stages.stage_number
     FROM units
     INNER JOIN courses ON courses.id = units.course_id
     INNER JOIN stages ON stages.id = units.stage_id
     ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC"
);
$units = $unitsStatement->fetchAll();

$topicFormData = [
    'id' => $oldData['id'] ?? ($editingTopic['id'] ?? ''),
    'unit_id' => $oldData['unit_id'] ?? ($editingTopic['unit_id'] ?? ($requestedUnitId > 0 ? $requestedUnitId : '')),
    'topic_title' => $oldData['topic_title'] ?? ($editingTopic['topic_title'] ?? ''),
    'description' => $oldData['description'] ?? ($editingTopic['description'] ?? ''),
    'sort_order' => $oldData['sort_order'] ?? ($editingTopic['sort_order'] ?? 1),
    'is_unlocked' => $oldData['is_unlocked'] ?? ($editingTopic['is_unlocked'] ?? 0),
    'status' => $oldData['status'] ?? ($editingTopic['status'] ?? 'active'),
];

if ($returnUnitId < 1 && (int) $topicFormData['unit_id'] > 0 && $requestedUnitId > 0) {
    $returnUnitId = (int) $topicFormData['unit_id'];
}

$topicRows = $oldData['topic_rows'] ?? [];
if (!is_array($topicRows) || $topicRows === []) {
    $topicRows = [
        ['topic_title' => '', 'description' => '', 'sort_order' => 1],
    ];
}

$normalizedRows = [];
foreach ($topicRows as $index => $row) {
    if (!is_array($row)) {
        continue;
    }

    $normalizedRows[] = [
        'topic_title' => (string) ($row['topic_title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'sort_order' => (string) ($row['sort_order'] ?? ($index + 1)),
    ];
}
if ($normalizedRows === []) {
    $normalizedRows[] = ['topic_title' => '', 'description' => '', 'sort_order' => '1'];
}

$unitSearchValue = '';
foreach ($units as $unit) {
    if ((string) $topicFormData['unit_id'] === (string) $unit['id']) {
        $unitSearchValue = $unit['course_title'] . ' - Stage ' . $unit['stage_number'] . ' - ' . $unit['unit_title'];
        if (!empty($unit['unit_code'])) {
            $unitSearchValue .= ' (' . $unit['unit_code'] . ')';
        }
        break;
    }
}

$pageTitle = ($editingTopic ? 'Edit Topic' : 'Create Topic') . ' - ' . APP_NAME;
$pageHeading = $editingTopic ? 'Edit Topic' : 'Create Topic';
$pageDescription = 'Use this page to attach topics to the right unit and define their access state.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo $editingTopic ? 'Edit Topic Details' : 'Create Topics for One Unit'; ?></h2>
                    <p class="text-muted mb-0"><?php echo $editingTopic ? 'Update one topic and adjust its access state.' : 'Select a unit once, then add multiple topics under it in a single save.'; ?></p>
                </div>
                <a href="<?php echo e($returnUnitId > 0 ? base_url('admin/units/view.php?id=' . (string) $returnUnitId) : base_url('admin/topics/index.php')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $returnUnitId > 0 ? 'Back to Unit' : 'Back to Topics'; ?></a>
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

            <form action="<?php echo e(base_url('actions/admin/save_topic.php')); ?>" method="POST" class="row g-3" id="topic-form">
                <input type="hidden" name="id" value="<?php echo e((string) $topicFormData['id']); ?>">
                <input type="hidden" name="return_unit_id" value="<?php echo e((string) $returnUnitId); ?>">

                <div class="col-12">
                    <label for="unit_search" class="form-label">Unit</label>
                    <input type="text" class="form-control" id="unit_search" list="unit-options" value="<?php echo e($unitSearchValue); ?>" placeholder="Search for a unit" autocomplete="off" required>
                    <input type="hidden" id="unit_id" name="unit_id" value="<?php echo e((string) $topicFormData['unit_id']); ?>">
                    <datalist id="unit-options">
                        <?php foreach ($units as $unit): ?>
                            <option value="<?php echo e($unit['course_title'] . ' - Stage ' . $unit['stage_number'] . ' - ' . $unit['unit_title'] . (!empty($unit['unit_code']) ? ' (' . $unit['unit_code'] . ')' : '')); ?>" data-id="<?php echo e((string) $unit['id']); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Start typing the course, stage, unit name, or unit code.</div>
                </div>

                <?php if ($editingTopic): ?>
                    <div class="col-12"><label for="topic_title" class="form-label">Topic Title</label><input type="text" class="form-control" id="topic_title" name="topic_title" value="<?php echo e((string) $topicFormData['topic_title']); ?>" required></div>
                    <div class="col-12"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?php echo e((string) $topicFormData['description']); ?></textarea></div>
                    <div class="col-md-4"><label for="sort_order" class="form-label">Sort Order</label><input type="number" min="1" class="form-control" id="sort_order" name="sort_order" value="<?php echo e((string) $topicFormData['sort_order']); ?>" required></div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <label class="form-label mb-0">Topic Entries</label>
                                <div class="form-text">Add as many topics as you need for the selected unit.</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-topic-row">Add Another Topic</button>
                        </div>
                        <div id="topic-rows" class="d-flex flex-column gap-3">
                            <?php foreach ($normalizedRows as $index => $row): ?>
                                <div class="border rounded p-3 topic-row" data-row-index="<?php echo e((string) $index); ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h3 class="h6 mb-0">Topic <?php echo e((string) ($index + 1)); ?></h3>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-topic-row" <?php echo count($normalizedRows) === 1 ? 'disabled' : ''; ?>>Remove</button>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-12"><label class="form-label">Topic Title</label><input type="text" class="form-control" name="topic_titles[]" value="<?php echo e($row['topic_title']); ?>" required></div>
                                        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="descriptions[]" rows="3"><?php echo e($row['description']); ?></textarea></div>
                                        <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" min="1" class="form-control" name="sort_orders[]" value="<?php echo e((string) $row['sort_order']); ?>" required></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-md-4"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" required><?php foreach (['active', 'inactive'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $topicFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label for="is_unlocked" class="form-label">Access State</label><select class="form-select" id="is_unlocked" name="is_unlocked" required><option value="0" <?php echo (string) $topicFormData['is_unlocked'] === '0' || (int) $topicFormData['is_unlocked'] === 0 ? 'selected' : ''; ?>>Locked</option><option value="1" <?php echo (int) $topicFormData['is_unlocked'] === 1 ? 'selected' : ''; ?>>Unlocked</option></select></div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo $editingTopic ? 'Update Topic' : 'Create Topics'; ?></button>
                    <a href="<?php echo e($returnUnitId > 0 ? base_url('admin/units/view.php?id=' . (string) $returnUnitId) : base_url('admin/topics/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if (!$editingTopic): ?>
    <template id="topic-row-template">
        <div class="border rounded p-3 topic-row">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Topic</h3>
                <button type="button" class="btn btn-sm btn-outline-danger remove-topic-row">Remove</button>
            </div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Topic Title</label><input type="text" class="form-control" name="topic_titles[]" required></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="descriptions[]" rows="3"></textarea></div>
                <div class="col-md-4"><label class="form-label">Sort Order</label><input type="number" min="1" class="form-control" name="sort_orders[]" value="1" required></div>
            </div>
        </div>
    </template>
<?php endif; ?>
<script>
    (function () {
        const unitSearch = document.getElementById('unit_search');
        const unitIdInput = document.getElementById('unit_id');
        const unitOptions = Array.from(document.querySelectorAll('#unit-options option'));
        const form = document.getElementById('topic-form');
        const rowsContainer = document.getElementById('topic-rows');
        const addRowButton = document.getElementById('add-topic-row');
        const rowTemplate = document.getElementById('topic-row-template');

        function syncUnitId() {
            const match = unitOptions.find((option) => option.value === unitSearch.value);
            unitIdInput.value = match ? (match.dataset.id || '') : '';
            return match || null;
        }

        function updateRowLabels() {
            if (!rowsContainer) {
                return;
            }

            const rows = Array.from(rowsContainer.querySelectorAll('.topic-row'));
            rows.forEach((row, index) => {
                const heading = row.querySelector('h3');
                if (heading) {
                    heading.textContent = 'Topic ' + (index + 1);
                }

                const removeButton = row.querySelector('.remove-topic-row');
                if (removeButton) {
                    removeButton.disabled = rows.length === 1;
                }
            });
        }

        function bindRowActions(scope) {
            const removeButtons = scope.querySelectorAll('.remove-topic-row');
            removeButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    if (!rowsContainer) {
                        return;
                    }
                    const rows = rowsContainer.querySelectorAll('.topic-row');
                    if (rows.length <= 1) {
                        return;
                    }
                    button.closest('.topic-row').remove();
                    updateRowLabels();
                });
            });
        }

        unitSearch.addEventListener('input', function () {
            syncUnitId();
            unitSearch.setCustomValidity('');
        });

        unitSearch.addEventListener('change', function () {
            syncUnitId();
        });

        if (addRowButton && rowTemplate && rowsContainer) {
            addRowButton.addEventListener('click', function () {
                const fragment = rowTemplate.content.cloneNode(true);
                const newRow = fragment.querySelector('.topic-row');
                const sortInput = fragment.querySelector('input[name="sort_orders[]"]');
                if (sortInput) {
                    sortInput.value = String(rowsContainer.querySelectorAll('.topic-row').length + 1);
                }
                rowsContainer.appendChild(fragment);
                bindRowActions(newRow.parentElement ? newRow.parentElement : rowsContainer.lastElementChild);
                updateRowLabels();
            });

            bindRowActions(rowsContainer);
            updateRowLabels();
        }

        form.addEventListener('submit', function (event) {
            if (!syncUnitId()) {
                event.preventDefault();
                unitSearch.focus();
                unitSearch.setCustomValidity('Please choose a unit from the search results.');
                unitSearch.reportValidity();
                return;
            }

            if (rowsContainer) {
                const titleInputs = Array.from(rowsContainer.querySelectorAll('input[name="topic_titles[]"]'));
                const hasAnyTopic = titleInputs.some((input) => input.value.trim() !== '');
                if (!hasAnyTopic) {
                    event.preventDefault();
                    titleInputs[0]?.focus();
                    return;
                }
            }
        });
    }());
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
