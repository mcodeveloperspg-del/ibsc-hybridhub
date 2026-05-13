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
$accessFilter = trim((string) ($_GET['access'] ?? 'all'));
$courseFilter = (int) ($_GET['course_id'] ?? 0);
$stageFilter = (int) ($_GET['stage_id'] ?? 0);
$unitFilter = (int) ($_GET['unit_id'] ?? 0);
$allowedStatuses = ['all', 'active', 'inactive'];
$allowedAccess = ['all', 'locked', 'unlocked'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

if (!in_array($accessFilter, $allowedAccess, true)) {
    $accessFilter = 'all';
}

$coursesStatement = $pdo->query('SELECT id, course_code FROM courses ORDER BY course_code ASC');
$courses = $coursesStatement->fetchAll();

$stagesStatement = $pdo->query(
    "SELECT stages.id, stages.course_id, stages.stage_number
     FROM stages
     ORDER BY stages.course_id ASC, stages.stage_number ASC"
);
$stages = $stagesStatement->fetchAll();

$unitsStatement = $pdo->query(
    "SELECT units.id, units.course_id, units.stage_id, units.unit_code
     FROM units
     ORDER BY units.course_id ASC, units.stage_id ASC, units.sort_order ASC"
);
$units = $unitsStatement->fetchAll();

$validCourseIds = array_map(static fn(array $course): int => (int) $course['id'], $courses);
$validStageIds = array_map(static fn(array $stage): int => (int) $stage['id'], $stages);
$validUnitIds = array_map(static fn(array $unit): int => (int) $unit['id'], $units);
if ($courseFilter > 0 && !in_array($courseFilter, $validCourseIds, true)) $courseFilter = 0;
if ($stageFilter > 0 && !in_array($stageFilter, $validStageIds, true)) $stageFilter = 0;
if ($unitFilter > 0 && !in_array($unitFilter, $validUnitIds, true)) $unitFilter = 0;

$countStatement = $pdo->query("SELECT COUNT(*) AS total_topics,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_topics,
    SUM(CASE WHEN is_unlocked = 1 THEN 1 ELSE 0 END) AS unlocked_topics,
    SUM(CASE WHEN is_unlocked = 0 THEN 1 ELSE 0 END) AS locked_topics
    FROM topics");
$topicCounts = $countStatement->fetch() ?: [];

$topicsSql = "SELECT topics.id, topics.topic_title, topics.sort_order, topics.is_unlocked, topics.status, topics.updated_at,
                     units.id AS unit_id, units.unit_title, units.unit_code,
                     courses.title AS course_title, courses.course_code,
                     stages.id AS stage_id, stages.title AS stage_title, stages.stage_number,
                     COUNT(sessions.id) AS session_count
              FROM topics
              INNER JOIN units ON units.id = topics.unit_id
              INNER JOIN courses ON courses.id = units.course_id
              INNER JOIN stages ON stages.id = units.stage_id
              LEFT JOIN sessions ON sessions.topic_id = topics.id
              WHERE 1 = 1";
$params = [];

if ($searchTerm !== '') {
    $topicsSql .= " AND (
        topics.topic_title LIKE :search
        OR units.unit_title LIKE :search
        OR units.unit_code LIKE :search
        OR courses.title LIKE :search
        OR courses.course_code LIKE :search
        OR stages.title LIKE :search
    )";
    $params['search'] = '%' . $searchTerm . '%';
}
if ($statusFilter !== 'all') { $topicsSql .= ' AND topics.status = :status'; $params['status'] = $statusFilter; }
if ($accessFilter === 'locked') { $topicsSql .= ' AND topics.is_unlocked = 0'; }
if ($accessFilter === 'unlocked') { $topicsSql .= ' AND topics.is_unlocked = 1'; }
if ($courseFilter > 0) { $topicsSql .= ' AND courses.id = :course_id'; $params['course_id'] = $courseFilter; }
if ($stageFilter > 0) { $topicsSql .= ' AND stages.id = :stage_id'; $params['stage_id'] = $stageFilter; }
if ($unitFilter > 0) { $topicsSql .= ' AND units.id = :unit_id'; $params['unit_id'] = $unitFilter; }

$topicsSql .= " GROUP BY topics.id, topics.topic_title, topics.sort_order, topics.is_unlocked, topics.status, topics.updated_at,
                         units.id, units.unit_title, units.unit_code, courses.title, courses.course_code, stages.id, stages.title, stages.stage_number
                ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC, topics.sort_order ASC";
$topicsStatement = $pdo->prepare($topicsSql); $topicsStatement->execute($params); $topics = $topicsStatement->fetchAll();

$pageTitle = 'Topic Management - ' . APP_NAME;
$pageHeading = 'Topic Management';
$pageDescription = 'Review, search, filter, create, edit, and delete topic records from one management view.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Topics</div><div class="metric-value"><?php echo e((string) ($topicCounts['total_topics'] ?? 0)); ?></div><div class="metric-trend text-muted">All topic records linked to units</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Active Topics</div><div class="metric-value"><?php echo e((string) ($topicCounts['active_topics'] ?? 0)); ?></div><div class="metric-trend text-muted">Topics currently enabled in the structure</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Unlocked Topics</div><div class="metric-value"><?php echo e((string) ($topicCounts['unlocked_topics'] ?? 0)); ?></div><div class="metric-trend text-muted">Topics already released for learning access</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Locked Topics</div><div class="metric-value"><?php echo e((string) ($topicCounts['locked_topics'] ?? 0)); ?></div><div class="metric-trend text-muted">Topics still waiting for official release</div></div></div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3"><div><h2 class="h5 mb-1">Manage Topics</h2><p class="text-muted mb-0">Search existing topics, filter the list, and open a dedicated page to create a new topic.</p></div><a href="<?php echo e(base_url('admin/topics/form.php')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add New Topic</a></div>
    <form method="GET" action="<?php echo e(base_url('admin/topics/index.php')); ?>">
        <div class="row g-3 mb-3"><div class="col-12"><label for="search" class="form-label">Search Topics</label><input type="text" class="form-control" id="search" name="search" value="<?php echo e($searchTerm); ?>" placeholder="Search by topic, unit, course, or stage"></div></div>
        <div class="row g-3 align-items-end">
            <div class="col-md-2"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status"><option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option><option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
            <div class="col-md-2"><label for="access" class="form-label">Access</label><select class="form-select" id="access" name="access"><option value="all" <?php echo $accessFilter === 'all' ? 'selected' : ''; ?>>All access states</option><option value="locked" <?php echo $accessFilter === 'locked' ? 'selected' : ''; ?>>Locked</option><option value="unlocked" <?php echo $accessFilter === 'unlocked' ? 'selected' : ''; ?>>Unlocked</option></select></div>
            <div class="col-md-2"><label for="course_id" class="form-label">Course</label><select class="form-select" id="course_id" name="course_id"><option value="0">All</option><?php foreach ($courses as $course): ?><option value="<?php echo e((string) $course['id']); ?>" <?php echo $courseFilter === (int) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['course_code']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label for="stage_id" class="form-label">Stage</label><select class="form-select" id="stage_id" name="stage_id"><option value="0">All</option><?php foreach ($stages as $stage): ?><option value="<?php echo e((string) $stage['id']); ?>" data-course-id="<?php echo e((string) $stage['course_id']); ?>" <?php echo $stageFilter === (int) $stage['id'] ? 'selected' : ''; ?>><?php echo e((string) $stage['stage_number']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label for="unit_id" class="form-label">Unit</label><select class="form-select" id="unit_id" name="unit_id"><option value="0">All</option><?php foreach ($units as $unit): ?><option value="<?php echo e((string) $unit['id']); ?>" data-course-id="<?php echo e((string) $unit['course_id']); ?>" data-stage-id="<?php echo e((string) $unit['stage_id']); ?>" <?php echo $unitFilter === (int) $unit['id'] ? 'selected' : ''; ?>><?php echo e($unit['unit_code'] ?: 'No code'); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-1 d-grid"><button type="submit" class="btn btn-outline-primary">Go</button></div>
            <div class="col-md-1 d-grid"><a href="<?php echo e(base_url('admin/topics/index.php')); ?>" class="btn btn-outline-secondary">Reset</a></div>
        </div>
    </form>
</div>

<div class="surface-card table-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Created Topics</h2><p class="text-muted mb-0"><?php echo e((string) count($topics)); ?> topic<?php echo count($topics) === 1 ? '' : 's'; ?> shown<?php echo $searchTerm !== '' || $statusFilter !== 'all' || $accessFilter !== 'all' || $courseFilter > 0 || $stageFilter > 0 || $unitFilter > 0 ? ' for the current filter' : ''; ?>.</p></div></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Topic</th><th>Unit / Course</th><th>Order</th><th>Access</th><th>Sessions</th><th>Action</th></tr></thead><tbody>
        <?php if ($topics === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No topics matched your current search or filter.</td></tr><?php endif; ?>
        <?php foreach ($topics as $topic): ?>
            <tr>
                <td><div class="fw-semibold"><?php echo e($topic['topic_title']); ?></div><div class="small text-muted"><?php echo e(ucfirst($topic['status'])); ?></div></td>
                <td><div class="small"><?php echo e($topic['unit_title']); ?><?php echo $topic['unit_code'] ? ' (' . e($topic['unit_code']) . ')' : ''; ?></div><div class="small text-muted"><?php echo e($topic['course_title']); ?> (<?php echo e($topic['course_code']); ?>) | Stage <?php echo e((string) $topic['stage_number']); ?></div></td>
                <td><?php echo e((string) $topic['sort_order']); ?></td>
                <td><span class="status-chip <?php echo (int) $topic['is_unlocked'] === 1 ? 'success' : 'warning'; ?>"><i class="bi <?php echo (int) $topic['is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i><?php echo (int) $topic['is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?></span></td>
                <td><?php echo e((string) $topic['session_count']); ?></td>
                <td><div class="d-flex flex-wrap gap-2"><a href="<?php echo e(base_url('admin/topics/form.php?edit=' . (string) $topic['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a><button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTopicModal<?php echo e((string) $topic['id']); ?>">Delete</button></div>
                    <div class="modal fade" id="deleteTopicModal<?php echo e((string) $topic['id']); ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h3 class="modal-title h5 mb-0">Delete Topic</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-2">Are you sure you want to delete <strong><?php echo e($topic['topic_title']); ?></strong>?</p><p class="text-muted mb-0">This will also delete <?php echo e((string) $topic['session_count']); ?> session<?php echo (int) $topic['session_count'] === 1 ? '' : 's'; ?> and any related resources because they depend on this topic.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><form action="<?php echo e(base_url('actions/admin/delete_topic.php')); ?>" method="POST"><input type="hidden" name="topic_id" value="<?php echo e((string) $topic['id']); ?>"><button type="submit" class="btn btn-danger">Confirm Delete</button></form></div></div></div></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
<script>
    (function () {
        const courseFilter = document.getElementById('course_id');
        const stageFilter = document.getElementById('stage_id');
        const unitFilter = document.getElementById('unit_id');
        const stageOptions = Array.from(stageFilter.querySelectorAll('option'));
        const unitOptions = Array.from(unitFilter.querySelectorAll('option'));
        const initialStageId = stageFilter.value;
        const initialUnitId = unitFilter.value;

        function updateStageOptions() {
            const selectedCourseId = courseFilter.value;
            const currentValue = stageFilter.value || initialStageId;
            stageFilter.innerHTML = '';
            stageOptions.forEach((option) => {
                const optionCourseId = option.dataset.courseId || '0';
                if (option.value === '0' || selectedCourseId === '0' || optionCourseId === selectedCourseId) {
                    const clone = option.cloneNode(true);
                    if (clone.value === currentValue) {
                        clone.selected = true;
                    }
                    stageFilter.appendChild(clone);
                }
            });
            if (![...stageFilter.options].some((option) => option.value === stageFilter.value)) {
                stageFilter.value = '0';
            }
        }

        function updateUnitOptions() {
            const selectedCourseId = courseFilter.value;
            const selectedStageId = stageFilter.value;
            const currentValue = unitFilter.value || initialUnitId;
            unitFilter.innerHTML = '';
            unitOptions.forEach((option) => {
                const optionCourseId = option.dataset.courseId || '0';
                const optionStageId = option.dataset.stageId || '0';
                const matchesCourse = option.value === '0' || selectedCourseId === '0' || optionCourseId === selectedCourseId;
                const matchesStage = option.value === '0' || selectedStageId === '0' || optionStageId === selectedStageId;
                if (matchesCourse && matchesStage) {
                    const clone = option.cloneNode(true);
                    if (clone.value === currentValue) {
                        clone.selected = true;
                    }
                    unitFilter.appendChild(clone);
                }
            });
            if (![...unitFilter.options].some((option) => option.value === unitFilter.value)) {
                unitFilter.value = '0';
            }
        }

        courseFilter.addEventListener('change', function () {
            updateStageOptions();
            updateUnitOptions();
        });

        stageFilter.addEventListener('change', function () {
            updateUnitOptions();
        });

        updateStageOptions();
        updateUnitOptions();
    }());
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
