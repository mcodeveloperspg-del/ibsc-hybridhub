<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('unit_form_state', [
    'errors' => [],
    'data' => [],
]);

$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$editingUnitId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$returnUnitId = isset($_GET['return_unit_id']) ? (int) $_GET['return_unit_id'] : (int) ($oldData['return_unit_id'] ?? 0);
$editingUnit = null;

if ($editingUnitId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
    $editStatement->execute(['id' => $editingUnitId]);
    $editingUnit = $editStatement->fetch();

    if (!$editingUnit) {
        flash_message('The selected unit could not be found.', 'warning');
        redirect(base_url('admin/units/index.php'));
    }
}

$coursesStatement = $pdo->query('SELECT id, title, course_code FROM courses ORDER BY title ASC');
$courses = $coursesStatement->fetchAll();

$stagesStatement = $pdo->query(
    "SELECT stages.id, stages.course_id, stages.stage_number, stages.title, courses.title AS course_title
     FROM stages
     INNER JOIN courses ON courses.id = stages.course_id
     ORDER BY courses.title ASC, stages.stage_number ASC"
);
$stages = $stagesStatement->fetchAll();

$unitFormData = [
    'id' => $oldData['id'] ?? ($editingUnit['id'] ?? ''),
    'course_id' => $oldData['course_id'] ?? ($editingUnit['course_id'] ?? ''),
    'stage_id' => $oldData['stage_id'] ?? ($editingUnit['stage_id'] ?? ''),
    'unit_title' => $oldData['unit_title'] ?? ($editingUnit['unit_title'] ?? ''),
    'unit_code' => $oldData['unit_code'] ?? ($editingUnit['unit_code'] ?? ''),
    'description' => $oldData['description'] ?? ($editingUnit['description'] ?? ''),
    'sort_order' => $oldData['sort_order'] ?? ($editingUnit['sort_order'] ?? 1),
    'status' => $oldData['status'] ?? ($editingUnit['status'] ?? 'active'),
];

$courseSearchValue = '';
foreach ($courses as $course) {
    if ((string) $unitFormData['course_id'] === (string) $course['id']) {
        $courseSearchValue = $course['title'] . ' (' . $course['course_code'] . ')';
        break;
    }
}

$stageSearchValue = '';
foreach ($stages as $stage) {
    if ((string) $unitFormData['stage_id'] === (string) $stage['id']) {
        $stageSearchValue = $stage['course_title'] . ' - Stage ' . $stage['stage_number'] . ' (' . $stage['title'] . ')';
        break;
    }
}

$pageTitle = ($editingUnit ? 'Edit Unit' : 'Create Unit') . ' - ' . APP_NAME;
$pageHeading = $editingUnit ? 'Edit Unit' : 'Create Unit';
$pageDescription = 'Use this page to assign a unit to the right course and stage before topics and sessions are added.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="surface-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo $editingUnit ? 'Edit Unit Details' : 'Create a New Unit'; ?></h2>
                    <p class="text-muted mb-0">Each unit must belong to a course and stage so later topics and sessions attach correctly.</p>
                </div>
                <a href="<?php echo e($returnUnitId > 0 ? base_url('admin/units/view.php?id=' . (string) $returnUnitId) : base_url('admin/units/index.php')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $returnUnitId > 0 ? 'Back to Unit' : 'Back to Units'; ?></a>
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

            <form action="<?php echo e(base_url('actions/admin/save_unit.php')); ?>" method="POST" class="row g-3" id="unit-form">
                <input type="hidden" name="id" value="<?php echo e((string) $unitFormData['id']); ?>">
                <input type="hidden" name="return_unit_id" value="<?php echo e((string) $returnUnitId); ?>">

                <div class="col-md-6">
                    <label for="course_search" class="form-label">Course</label>
                    <input type="text" class="form-control" id="course_search" list="course-options" value="<?php echo e($courseSearchValue); ?>" placeholder="Search for a course" autocomplete="off" required>
                    <input type="hidden" id="course_id" name="course_id" value="<?php echo e((string) $unitFormData['course_id']); ?>">
                    <datalist id="course-options">
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?>" data-id="<?php echo e((string) $course['id']); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Start typing the course title or code.</div>
                </div>

                <div class="col-md-6">
                    <label for="stage_search" class="form-label">Stage</label>
                    <input type="text" class="form-control" id="stage_search" list="stage-options" value="<?php echo e($stageSearchValue); ?>" placeholder="Search for a stage" autocomplete="off" required>
                    <input type="hidden" id="stage_id" name="stage_id" value="<?php echo e((string) $unitFormData['stage_id']); ?>">
                    <datalist id="stage-options">
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo e($stage['course_title'] . ' - Stage ' . $stage['stage_number'] . ' (' . $stage['title'] . ')'); ?>" data-id="<?php echo e((string) $stage['id']); ?>" data-course-id="<?php echo e((string) $stage['course_id']); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Pick a stage after choosing the course.</div>
                </div>

                <div class="col-md-6">
                    <label for="unit_code" class="form-label">Unit Code</label>
                    <input type="text" class="form-control" id="unit_code" name="unit_code" value="<?php echo e((string) $unitFormData['unit_code']); ?>">
                </div>

                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach (['active', 'inactive'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $unitFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label for="unit_title" class="form-label">Unit Title</label>
                    <input type="text" class="form-control" id="unit_title" name="unit_title" value="<?php echo e((string) $unitFormData['unit_title']); ?>" required>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo e((string) $unitFormData['description']); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort Order</label>
                    <input type="number" min="1" class="form-control" id="sort_order" name="sort_order" value="<?php echo e((string) $unitFormData['sort_order']); ?>" required>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo $editingUnit ? 'Update Unit' : 'Create Unit'; ?></button>
                    <a href="<?php echo e($returnUnitId > 0 ? base_url('admin/units/view.php?id=' . (string) $returnUnitId) : base_url('admin/units/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const courseSearch = document.getElementById('course_search');
        const courseIdInput = document.getElementById('course_id');
        const courseOptions = Array.from(document.querySelectorAll('#course-options option'));
        const stageSearch = document.getElementById('stage_search');
        const stageIdInput = document.getElementById('stage_id');
        const stageOptions = Array.from(document.querySelectorAll('#stage-options option'));
        const stageOptionsList = document.getElementById('stage-options');
        const form = document.getElementById('unit-form');

        function syncHiddenId(searchInput, hiddenInput, options) {
            const match = options.find((option) => option.value === searchInput.value);
            hiddenInput.value = match ? (match.dataset.id || '') : '';
            return match || null;
        }

        function renderStageOptions(courseId) {
            stageOptionsList.innerHTML = '';
            const filtered = stageOptions.filter((option) => !courseId || option.dataset.courseId === courseId);

            filtered.forEach((option) => {
                stageOptionsList.appendChild(option.cloneNode(true));
            });
        }

        function availableStageOptions() {
            return stageOptions.filter((option) => !courseIdInput.value || option.dataset.courseId === courseIdInput.value);
        }

        function handleCourseChange() {
            const previousCourseId = courseIdInput.value;
            const match = syncHiddenId(courseSearch, courseIdInput, courseOptions);
            const selectedCourseId = match ? (match.dataset.id || '') : '';

            renderStageOptions(selectedCourseId);

            if (!selectedCourseId || selectedCourseId !== previousCourseId) {
                stageSearch.value = '';
                stageIdInput.value = '';
            }
        }

        courseSearch.addEventListener('input', handleCourseChange);
        courseSearch.addEventListener('change', handleCourseChange);
        courseSearch.addEventListener('input', function () {
            courseSearch.setCustomValidity('');
        });

        stageSearch.addEventListener('input', function () {
            syncHiddenId(stageSearch, stageIdInput, availableStageOptions());
            stageSearch.setCustomValidity('');
        });

        stageSearch.addEventListener('change', function () {
            syncHiddenId(stageSearch, stageIdInput, availableStageOptions());
        });

        form.addEventListener('submit', function (event) {
            const selectedCourse = syncHiddenId(courseSearch, courseIdInput, courseOptions);
            const selectedStage = syncHiddenId(stageSearch, stageIdInput, availableStageOptions());

            if (!selectedCourse) {
                event.preventDefault();
                courseSearch.focus();
                courseSearch.setCustomValidity('Please choose a course from the search results.');
                courseSearch.reportValidity();
                return;
            }

            courseSearch.setCustomValidity('');

            if (!selectedStage) {
                event.preventDefault();
                stageSearch.focus();
                stageSearch.setCustomValidity('Please choose a stage from the search results.');
                stageSearch.reportValidity();
                return;
            }

            stageSearch.setCustomValidity('');
        });

        renderStageOptions(courseIdInput.value);
    }());
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
