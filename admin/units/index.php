<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$courseFilter = (int) ($_GET['course_id'] ?? 0);
$stageFilter = (int) ($_GET['stage_id'] ?? 0);
$allowedStatuses = ['all', 'active', 'inactive'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
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

$validCourseIds = array_map(static fn(array $course): int => (int) $course['id'], $courses);
$validStageIds = array_map(static fn(array $stage): int => (int) $stage['id'], $stages);

if ($courseFilter > 0 && !in_array($courseFilter, $validCourseIds, true)) {
    $courseFilter = 0;
}

if ($stageFilter > 0 && !in_array($stageFilter, $validStageIds, true)) {
    $stageFilter = 0;
}

$countStatement = $pdo->query("SELECT
    COUNT(*) AS total_units,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_units,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_units
    FROM units");
$unitCounts = $countStatement->fetch() ?: [];

$unitsSql = "SELECT units.id, units.unit_code, units.unit_title, units.description, units.sort_order, units.status, units.updated_at,
                    courses.title AS course_title, courses.course_code,
                    stages.title AS stage_title, stages.stage_number,
                    COUNT(topics.id) AS topic_count
             FROM units
             INNER JOIN courses ON courses.id = units.course_id
             INNER JOIN stages ON stages.id = units.stage_id
             LEFT JOIN topics ON topics.unit_id = units.id
             WHERE 1 = 1";
$unitsParams = [];

if ($searchTerm !== '') {
    $unitsSql .= " AND (
        units.unit_title LIKE :search
        OR units.unit_code LIKE :search
        OR courses.title LIKE :search
        OR courses.course_code LIKE :search
        OR stages.title LIKE :search
    )";
    $unitsParams['search'] = '%' . $searchTerm . '%';
}

if ($statusFilter !== 'all') {
    $unitsSql .= ' AND units.status = :status';
    $unitsParams['status'] = $statusFilter;
}

if ($courseFilter > 0) {
    $unitsSql .= ' AND units.course_id = :course_id';
    $unitsParams['course_id'] = $courseFilter;
}

if ($stageFilter > 0) {
    $unitsSql .= ' AND units.stage_id = :stage_id';
    $unitsParams['stage_id'] = $stageFilter;
}

$unitsSql .= " GROUP BY units.id, units.unit_code, units.unit_title, units.description, units.sort_order, units.status, units.updated_at,
                        courses.title, courses.course_code, stages.title, stages.stage_number
               ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC";

$unitsStatement = $pdo->prepare($unitsSql);
$unitsStatement->execute($unitsParams);
$units = $unitsStatement->fetchAll();

$pageTitle = 'Unit Management - ' . APP_NAME;
$pageHeading = 'Unit Management';
$pageDescription = 'Review units as summary cards, then open one unit to manage its topics and details.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Total Units</div>
            <div class="metric-value"><?php echo e((string) ($unitCounts['total_units'] ?? 0)); ?></div>
            <div class="metric-trend text-muted">All unit records linked to courses and stages</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Active Units</div>
            <div class="metric-value"><?php echo e((string) ($unitCounts['active_units'] ?? 0)); ?></div>
            <div class="metric-trend text-muted">Units currently enabled for operational use</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Inactive Units</div>
            <div class="metric-value"><?php echo e((string) ($unitCounts['inactive_units'] ?? 0)); ?></div>
            <div class="metric-trend text-muted">Units temporarily disabled from the structure</div>
        </div>
    </div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Manage Units</h2>
            <p class="text-muted mb-0">Search existing units, filter the list, and open a dedicated page to create a new unit.</p>
        </div>
        <a href="<?php echo e(base_url('admin/units/form.php')); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create New Unit
        </a>
    </div>

    <form method="GET" action="<?php echo e(base_url('admin/units/index.php')); ?>">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label for="search" class="form-label">Search Units</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo e($searchTerm); ?>" placeholder="Search by unit title, code, course, or stage">
            </div>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="course_id" class="form-label">Course</label>
                <select class="form-select" id="course_id" name="course_id">
                    <option value="0">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo e((string) $course['id']); ?>" <?php echo $courseFilter === (int) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="stage_id" class="form-label">Stage</label>
                <select class="form-select" id="stage_id" name="stage_id">
                    <option value="0">All stages</option>
                    <?php foreach ($stages as $stage): ?>
                        <option value="<?php echo e((string) $stage['id']); ?>" data-course-id="<?php echo e((string) $stage['course_id']); ?>" <?php echo $stageFilter === (int) $stage['id'] ? 'selected' : ''; ?>><?php echo e($stage['course_title'] . ' - Stage ' . $stage['stage_number'] . ' (' . $stage['title'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-outline-primary">Go</button>
            </div>
        </div>
    </form>
</div>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Created Units</h2>
            <p class="text-muted mb-0"><?php echo e((string) count($units)); ?> unit<?php echo count($units) === 1 ? '' : 's'; ?> shown<?php echo $searchTerm !== '' || $statusFilter !== 'all' || $courseFilter > 0 || $stageFilter > 0 ? ' for the current filter' : ''; ?>.</p>
        </div>
    </div>

    <?php if ($units === []): ?>
        <div class="text-center text-muted py-4">No units matched your current search or filter.</div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($units as $unit): ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?php echo e(base_url('admin/units/view.php?id=' . (string) $unit['id'])); ?>" class="surface-card d-block h-100 p-3 text-decoration-none text-body">
                    <div class="d-flex justify-content-between gap-3 mb-3">
                        <div>
                            <div class="fw-semibold"><?php echo e($unit['unit_title']); ?></div>
                            <div class="small text-muted"><?php echo e($unit['unit_code'] ?: 'No unit code'); ?></div>
                        </div>
                        <span class="status-chip <?php echo $unit['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <i class="bi bi-circle-fill small"></i>
                            <?php echo e(ucfirst($unit['status'])); ?>
                        </span>
                    </div>
                    <p class="small text-muted mb-3"><?php echo e($unit['description'] ?: 'No unit summary has been added yet.'); ?></p>
                    <div class="row g-2 small">
                        <div class="col-12">
                            <div class="text-muted">Course</div>
                            <div class="fw-semibold"><?php echo e($unit['course_title']); ?> (<?php echo e($unit['course_code']); ?>)</div>
                        </div>
                        <div class="col-7">
                            <div class="text-muted">Stage</div>
                            <div class="fw-semibold">Stage <?php echo e((string) $unit['stage_number']); ?></div>
                        </div>
                        <div class="col-5">
                            <div class="text-muted">Topics</div>
                            <div class="fw-semibold"><?php echo e((string) $unit['topic_count']); ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
    (function () {
        const courseFilter = document.getElementById('course_id');
        const stageFilter = document.getElementById('stage_id');
        const stageOptions = Array.from(stageFilter.querySelectorAll('option'));
        const selectedStageId = stageFilter.value;

        function updateStageFilterOptions() {
            const selectedCourseId = courseFilter.value;
            stageFilter.innerHTML = '';

            stageOptions.forEach((option) => {
                const optionCourseId = option.dataset.courseId || '0';
                if (option.value === '0' || selectedCourseId === '0' || optionCourseId === selectedCourseId) {
                    const clone = option.cloneNode(true);
                    if (clone.value === selectedStageId) {
                        clone.selected = true;
                    }
                    stageFilter.appendChild(clone);
                }
            });

            if (![...stageFilter.options].some((option) => option.value === stageFilter.value)) {
                stageFilter.value = '0';
            }
        }

        courseFilter.addEventListener('change', function () {
            updateStageFilterOptions();
            const currentStageOption = [...stageFilter.options].some((option) => option.value === stageFilter.value);
            if (!currentStageOption) {
                stageFilter.value = '0';
            }
        });

        updateStageFilterOptions();
    }());
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
