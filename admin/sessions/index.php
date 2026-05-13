<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$flash = flash_message();
$selectedBatchId = (int) ($_GET['batch_id'] ?? 0);
$selectedCourseId = (int) ($_GET['course_id'] ?? 0);
$selectedStageId = (int) ($_GET['stage_id'] ?? 0);
$selectedUnitId = (int) ($_GET['unit_id'] ?? 0);

$countStatement = $pdo->query(
    "SELECT COUNT(*) AS total_sessions,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_sessions,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_sessions,
        SUM(CASE WHEN is_unlocked = 1 THEN 1 ELSE 0 END) AS unlocked_sessions
     FROM sessions
     WHERE batch_id IS NOT NULL"
);
$sessionCounts = $countStatement->fetch() ?: [];

$batches = $pdo->query(
    "SELECT batches.id, batches.batch_name, batches.batch_year, batches.status,
            COUNT(batch_courses.course_id) AS course_count
     FROM batches
     LEFT JOIN batch_courses ON batch_courses.batch_id = batches.id
     GROUP BY batches.id, batches.batch_name, batches.batch_year, batches.status
     ORDER BY batches.batch_year DESC, batches.batch_number ASC"
)->fetchAll();

$validBatchIds = array_map(static fn(array $batch): int => (int) $batch['id'], $batches);
if ($selectedBatchId > 0 && !in_array($selectedBatchId, $validBatchIds, true)) {
    $selectedBatchId = 0;
}

$courses = [];
if ($selectedBatchId > 0) {
    $coursesStatement = $pdo->prepare(
        "SELECT courses.id, courses.title, courses.course_code, courses.total_stages
         FROM batch_courses
         INNER JOIN courses ON courses.id = batch_courses.course_id
         WHERE batch_courses.batch_id = :batch_id
         ORDER BY courses.title ASC"
    );
    $coursesStatement->execute(['batch_id' => $selectedBatchId]);
    $courses = $coursesStatement->fetchAll();
}

$validCourseIds = array_map(static fn(array $course): int => (int) $course['id'], $courses);
if ($selectedCourseId > 0 && !in_array($selectedCourseId, $validCourseIds, true)) {
    $selectedCourseId = 0;
}

$stages = [];
if ($selectedCourseId > 0) {
    $stagesStatement = $pdo->prepare(
        "SELECT stages.id, stages.stage_number, stages.title, stages.week_start, stages.week_end,
                batch_stages.start_date AS batch_stage_start_date,
                batch_stages.end_date AS batch_stage_end_date
         FROM stages
         LEFT JOIN batch_stages ON batch_stages.stage_number = stages.stage_number
             AND batch_stages.batch_id = :batch_id
         WHERE stages.course_id = :course_id AND stages.status = 'active'
         ORDER BY stages.stage_number ASC"
    );
    $stagesStatement->execute(['batch_id' => $selectedBatchId, 'course_id' => $selectedCourseId]);
    $stages = $stagesStatement->fetchAll();
}

$validStageIds = array_map(static fn(array $stage): int => (int) $stage['id'], $stages);
if ($selectedStageId > 0 && !in_array($selectedStageId, $validStageIds, true)) {
    $selectedStageId = 0;
}

$units = [];
if ($selectedStageId > 0) {
    $unitsStatement = $pdo->prepare(
        "SELECT units.id, units.unit_title, units.unit_code, units.description, units.sort_order, units.status,
                COUNT(topics.id) AS topic_count
         FROM units
         LEFT JOIN topics ON topics.unit_id = units.id
         WHERE units.course_id = :course_id AND units.stage_id = :stage_id
         GROUP BY units.id, units.unit_title, units.unit_code, units.description, units.sort_order, units.status
         ORDER BY units.sort_order ASC, units.unit_title ASC"
    );
    $unitsStatement->execute(['course_id' => $selectedCourseId, 'stage_id' => $selectedStageId]);
    $units = $unitsStatement->fetchAll();
}

$validUnitIds = array_map(static fn(array $unit): int => (int) $unit['id'], $units);
if ($selectedUnitId > 0 && !in_array($selectedUnitId, $validUnitIds, true)) {
    $selectedUnitId = 0;
}

$topics = [];
if ($selectedUnitId > 0) {
    $topicsStatement = $pdo->prepare(
        "SELECT topics.id, topics.topic_title, topics.description, topics.sort_order, topics.is_unlocked, topics.status
         FROM topics
         WHERE topics.unit_id = :unit_id
         ORDER BY topics.sort_order ASC, topics.topic_title ASC"
    );
    $topicsStatement->execute(['unit_id' => $selectedUnitId]);
    $topics = $topicsStatement->fetchAll();
}

$sessionsByTopic = [];
if ($topics !== []) {
    $topicIds = array_map(static fn(array $topic): int => (int) $topic['id'], $topics);
    $placeholders = implode(',', array_fill(0, count($topicIds), '?'));
    $sessionsStatement = $pdo->prepare(
        "SELECT sessions.id, sessions.topic_id, sessions.session_title, sessions.session_date,
                sessions.video_provider, sessions.recording_source, sessions.session_type, sessions.sort_order,
                sessions.is_unlocked, sessions.status, sessions.fallback_source_session_id
         FROM sessions
         WHERE sessions.topic_id IN ($placeholders)
           AND sessions.batch_id = ?
         ORDER BY sessions.topic_id ASC, sessions.sort_order ASC, sessions.session_date ASC, sessions.id ASC"
    );
    $topicIds[] = $selectedBatchId;
    $sessionsStatement->execute($topicIds);
    foreach ($sessionsStatement->fetchAll() as $session) {
        $sessionsByTopic[(int) $session['topic_id']][] = $session;
    }
}

$selectedBatch = null;
foreach ($batches as $batch) {
    if ((int) $batch['id'] === $selectedBatchId) {
        $selectedBatch = $batch;
        break;
    }
}

$selectedCourse = null;
foreach ($courses as $course) {
    if ((int) $course['id'] === $selectedCourseId) {
        $selectedCourse = $course;
        break;
    }
}

$selectedStage = null;
foreach ($stages as $stage) {
    if ((int) $stage['id'] === $selectedStageId) {
        $selectedStage = $stage;
        break;
    }
}

$selectedUnit = null;
foreach ($units as $unit) {
    if ((int) $unit['id'] === $selectedUnitId) {
        $selectedUnit = $unit;
        break;
    }
}

$baseSelectionParams = [
    'batch_id' => $selectedBatchId,
    'course_id' => $selectedCourseId,
    'stage_id' => $selectedStageId,
    'unit_id' => $selectedUnitId,
];

$pageTitle = 'Session Management - ' . APP_NAME;
$pageHeading = 'Session / Video Management';
$pageDescription = 'Access sessions through batch, course, stage, unit, and topic. Each batch now keeps its own session records for each topic, with older topic sessions used only as approved fallback sources.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Total Sessions</div><div class="metric-value"><?php echo e((string) ($sessionCounts['total_sessions'] ?? 0)); ?></div><div class="metric-trend text-muted">Batch-specific topic sessions</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Published</div><div class="metric-value"><?php echo e((string) ($sessionCounts['published_sessions'] ?? 0)); ?></div><div class="metric-trend text-muted">Ready for delivery</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Draft</div><div class="metric-value"><?php echo e((string) ($sessionCounts['draft_sessions'] ?? 0)); ?></div><div class="metric-trend text-muted">Being prepared</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Unlocked</div><div class="metric-value"><?php echo e((string) ($sessionCounts['unlocked_sessions'] ?? 0)); ?></div><div class="metric-trend text-muted">Released sessions</div></div></div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Browse by Batch Structure</h2>
            <p class="text-muted mb-0">Choose a batch first, then move through its course structure to create or manage topic sessions.</p>
        </div>
        <a href="<?php echo e(base_url('admin/sessions/create.php')); ?>" class="btn btn-outline-primary">Create Session Without Filter</a>
    </div>

    <form method="GET" action="<?php echo e(base_url('admin/sessions/index.php')); ?>" class="row g-3 align-items-end">
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Batch</label>
            <select class="form-select" name="batch_id" onchange="this.form.course_id.value='0';this.form.stage_id.value='0';this.form.unit_id.value='0';this.form.submit()">
                <option value="0">Select batch</option>
                <?php foreach ($batches as $batch): ?>
                    <option value="<?php echo e((string) $batch['id']); ?>" <?php echo $selectedBatchId === (int) $batch['id'] ? 'selected' : ''; ?>><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Course</label>
            <select class="form-select" name="course_id" <?php echo $selectedBatchId < 1 ? 'disabled' : ''; ?> onchange="this.form.stage_id.value='0';this.form.unit_id.value='0';this.form.submit()">
                <option value="0">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo e((string) $course['id']); ?>" <?php echo $selectedCourseId === (int) $course['id'] ? 'selected' : ''; ?>><?php echo e($course['title'] . ' (' . $course['course_code'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Stage</label>
            <select class="form-select" name="stage_id" <?php echo $selectedCourseId < 1 ? 'disabled' : ''; ?> onchange="this.form.unit_id.value='0';this.form.submit()">
                <option value="0">Select stage</option>
                <?php foreach ($stages as $stage): ?>
                    <option value="<?php echo e((string) $stage['id']); ?>" <?php echo $selectedStageId === (int) $stage['id'] ? 'selected' : ''; ?>>Stage <?php echo e((string) $stage['stage_number']); ?> - <?php echo e($stage['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 col-xl-3">
            <label class="form-label">Unit</label>
            <select class="form-select" name="unit_id" <?php echo $selectedStageId < 1 ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="0">Select unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?php echo e((string) $unit['id']); ?>" <?php echo $selectedUnitId === (int) $unit['id'] ? 'selected' : ''; ?>><?php echo e($unit['unit_title'] . ($unit['unit_code'] ? ' (' . $unit['unit_code'] . ')' : '')); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($selectedBatchId < 1): ?>
    <div class="surface-card p-4 text-center text-muted">Select a batch to begin browsing sessions.</div>
<?php elseif ($selectedCourseId < 1): ?>
    <div class="surface-card p-4">
        <h2 class="h5 mb-3">Courses in <?php echo e($selectedBatch['batch_name'] ?? 'Selected Batch'); ?></h2>
        <div class="row g-3">
            <?php foreach ($courses as $course): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="surface-card d-block p-3 text-decoration-none text-body h-100" href="<?php echo e(base_url('admin/sessions/index.php?batch_id=' . (string) $selectedBatchId . '&course_id=' . (string) $course['id'])); ?>">
                        <div class="fw-semibold"><?php echo e($course['title']); ?></div>
                        <div class="small text-muted"><?php echo e($course['course_code']); ?> | <?php echo e((string) $course['total_stages']); ?> stages</div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($selectedStageId < 1): ?>
    <div class="surface-card p-4">
        <h2 class="h5 mb-3">Stages in <?php echo e($selectedCourse['title'] ?? 'Selected Course'); ?></h2>
        <div class="row g-3">
            <?php foreach ($stages as $stage): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="surface-card d-block p-3 text-decoration-none text-body h-100" href="<?php echo e(base_url('admin/sessions/index.php?batch_id=' . (string) $selectedBatchId . '&course_id=' . (string) $selectedCourseId . '&stage_id=' . (string) $stage['id'])); ?>">
                        <div class="fw-semibold">Stage <?php echo e((string) $stage['stage_number']); ?> - <?php echo e($stage['title']); ?></div>
                        <div class="small text-muted">
                            <?php if (!empty($stage['batch_stage_start_date']) && !empty($stage['batch_stage_end_date'])): ?>
                                <?php echo e($stage['batch_stage_start_date'] . ' to ' . $stage['batch_stage_end_date']); ?>
                            <?php else: ?>
                                Weeks <?php echo e((string) $stage['week_start']); ?> to <?php echo e((string) $stage['week_end']); ?>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($selectedUnitId < 1): ?>
    <div class="surface-card p-4">
        <h2 class="h5 mb-3">Units in Stage <?php echo e((string) ($selectedStage['stage_number'] ?? '')); ?></h2>
        <?php if ($units === []): ?><div class="text-muted">No units have been created for this stage yet.</div><?php endif; ?>
        <div class="row g-3">
            <?php foreach ($units as $unit): ?>
                <div class="col-md-6 col-xl-4">
                    <a class="surface-card d-block p-3 text-decoration-none text-body h-100" href="<?php echo e(base_url('admin/sessions/index.php?batch_id=' . (string) $selectedBatchId . '&course_id=' . (string) $selectedCourseId . '&stage_id=' . (string) $selectedStageId . '&unit_id=' . (string) $unit['id'])); ?>">
                        <div class="fw-semibold"><?php echo e($unit['unit_title']); ?></div>
                        <div class="small text-muted"><?php echo e($unit['unit_code'] ?: 'No unit code'); ?> | <?php echo e((string) $unit['topic_count']); ?> topics</div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="surface-card p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Topics in <?php echo e($selectedUnit['unit_title'] ?? 'Selected Unit'); ?></h2>
                <p class="text-muted mb-0">Sessions created here belong only to the selected batch. Older topic sessions can still be linked later as fallback delivery sources when a lecturer cannot record a new session.</p>
            </div>
            <a href="<?php echo e(base_url('admin/topics/form.php?unit_id=' . (string) $selectedUnitId . '&return_unit_id=' . (string) $selectedUnitId)); ?>" class="btn btn-outline-primary">Add Topic</a>
        </div>

        <?php if ($topics === []): ?>
            <div class="text-center text-muted py-4">No topics have been created for this unit yet.</div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($topics as $topic): ?>
                <?php $topicSessions = $sessionsByTopic[(int) $topic['id']] ?? []; ?>
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                        <div>
                            <div class="fw-semibold"><?php echo e($topic['topic_title']); ?></div>
                            <div class="small text-muted"><?php echo e($topic['description'] ?: 'No topic description.'); ?></div>
                        </div>
                        <a href="<?php echo e(base_url('admin/sessions/create.php?topic_id=' . (string) $topic['id'] . '&batch_id=' . (string) $selectedBatchId . '&course_id=' . (string) $selectedCourseId . '&stage_id=' . (string) $selectedStageId . '&unit_id=' . (string) $selectedUnitId)); ?>" class="btn btn-sm btn-primary">Create Session</a>
                    </div>

                    <?php if ($topicSessions === []): ?>
                        <div class="text-muted small">No sessions have been created under this topic yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Session</th><th>Type</th><th>Status</th><th>Access</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php foreach ($topicSessions as $session): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($session['session_title']); ?></div>
                                                <div class="small text-muted"><?php echo e($session['video_provider']); ?><?php echo $session['session_date'] ? ' | ' . e($session['session_date']) : ''; ?></div>
                                                <div class="small text-muted"><?php echo e((string) ($session['recording_source'] ?? 'current_batch') === 'previous_session' ? 'Previous session recording' : 'Current batch recording'); ?></div>
                                            </td>
                                            <td><?php echo e(ucfirst($session['session_type'])); ?><?php echo (int) ($session['fallback_source_session_id'] ?? 0) > 0 ? ' (Fallback)' : ''; ?></td>
                                            <td><?php echo e(ucfirst($session['status'])); ?></td>
                                            <td><span class="status-chip <?php echo (int) $session['is_unlocked'] === 1 ? 'success' : 'warning'; ?>"><i class="bi <?php echo (int) $session['is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i><?php echo (int) $session['is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?></span></td>
                                            <td><a href="<?php echo e(base_url('admin/sessions/create.php?edit=' . (string) $session['id'] . '&batch_id=' . (string) $selectedBatchId . '&course_id=' . (string) $selectedCourseId . '&stage_id=' . (string) $selectedStageId . '&unit_id=' . (string) $selectedUnitId)); ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
