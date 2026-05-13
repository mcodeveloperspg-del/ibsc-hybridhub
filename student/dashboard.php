<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['student']);

$user = current_user();
$flash = flash_message();
$studentId = (int) ($user['id'] ?? 0);
$today = date('Y-m-d');

function fetch_student_total(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

$totalEnrolledUnits = fetch_student_total(
    $pdo,
    'SELECT COUNT(DISTINCT units.id)
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND units.status = :unit_status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date',
    [
        'student_id' => $studentId,
        'status' => 'active',
        'unit_status' => 'active',
        'today' => $today,
    ]
);

$totalUnlockedSessions = fetch_student_total(
    $pdo,
    'SELECT COUNT(DISTINCT sessions.id)
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = \'published\'
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
       AND sessions.is_unlocked = 1',
    [
        'student_id' => $studentId,
        'status' => 'active',
        'today' => $today,
    ]
);

$totalLockedSessions = fetch_student_total(
    $pdo,
    'SELECT COUNT(DISTINCT sessions.id)
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = \'published\'
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
       AND sessions.is_unlocked = 0',
    [
        'student_id' => $studentId,
        'status' => 'active',
        'today' => $today,
    ]
);

$totalWatchedSessions = fetch_student_total(
    $pdo,
    'SELECT COUNT(*) FROM watched_sessions WHERE student_id = :student_id',
    ['student_id' => $studentId]
);

$averageProgressStatement = $pdo->prepare('SELECT COALESCE(AVG(progress_percent), 0) FROM watched_sessions WHERE student_id = :student_id');
$averageProgressStatement->execute(['student_id' => $studentId]);
$averageProgress = (int) round((float) $averageProgressStatement->fetchColumn());

$enrolledUnitsStatement = $pdo->prepare(
    'SELECT DISTINCT units.id AS unit_id, units.unit_code, units.unit_title,
            courses.id AS course_id, courses.course_code, courses.title AS course_title,
            batches.batch_name, stages.stage_number, stages.title AS stage_title,
            batch_stages.start_date AS stage_start_date, batch_stages.end_date AS stage_end_date
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND units.status = :unit_status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
     ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC'
);
$enrolledUnitsStatement->execute([
    'student_id' => $studentId,
    'status' => 'active',
    'unit_status' => 'active',
    'today' => $today,
]);
$enrolledUnits = $enrolledUnitsStatement->fetchAll();

$enrolledUnitStageGroups = [];
foreach ($enrolledUnits as $unit) {
    $groupKey = (string) $unit['course_id'] . ':stage:' . (string) $unit['stage_number'];
    if (!isset($enrolledUnitStageGroups[$groupKey])) {
        $enrolledUnitStageGroups[$groupKey] = [
            'course_code' => (string) $unit['course_code'],
            'course_title' => (string) $unit['course_title'],
            'stage_number' => (int) $unit['stage_number'],
            'stage_title' => (string) $unit['stage_title'],
            'stage_start_date' => (string) $unit['stage_start_date'],
            'stage_end_date' => (string) $unit['stage_end_date'],
            'units' => [],
        ];
    }

    $enrolledUnitStageGroups[$groupKey]['units'][] = $unit;
}

$availableSessionsStatement = $pdo->prepare(
    'SELECT DISTINCT sessions.id, sessions.session_title, sessions.session_date, topics.topic_title,
            courses.id AS course_id, courses.title AS course_title,
            COALESCE(watched_sessions.progress_percent, 0) AS progress_percent,
            watched_sessions.watched_at
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = \'published\'
     LEFT JOIN watched_sessions ON watched_sessions.session_id = sessions.id AND watched_sessions.student_id = enrollments.student_id
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
       AND sessions.is_unlocked = 1
     ORDER BY sessions.session_date DESC, sessions.id DESC
     LIMIT 5'
);
$availableSessionsStatement->execute([
    'student_id' => $studentId,
    'status' => 'active',
    'today' => $today,
]);
$availableSessions = $availableSessionsStatement->fetchAll();

$lockedSessionsStatement = $pdo->prepare(
    'SELECT DISTINCT sessions.session_title, sessions.session_type, topics.topic_title, courses.title AS course_title
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = \'published\'
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = :status
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
       AND sessions.is_unlocked = 0
     ORDER BY sessions.id DESC
     LIMIT 5'
);
$lockedSessionsStatement->execute([
    'student_id' => $studentId,
    'status' => 'active',
    'today' => $today,
]);
$lockedSessions = $lockedSessionsStatement->fetchAll();

$pageTitle = 'Student Dashboard - ' . APP_NAME;
$pageHeading = 'My Learning Hub';
$pageDescription = 'Your units, available sessions, and current stage progress.';

require_once __DIR__ . '/../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">My units</div><div class="metric-value"><?php echo e((string) $totalEnrolledUnits); ?></div><div class="metric-trend text-muted">Current stage only</div></div><div class="metric-icon text-primary bg-primary-subtle"><i class="bi bi-journal-richtext"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Unlocked sessions</div><div class="metric-value"><?php echo e((string) $totalUnlockedSessions); ?></div><div class="metric-trend text-muted">Ready to watch</div></div><div class="metric-icon text-success bg-success-subtle"><i class="bi bi-play-circle-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Locked sessions</div><div class="metric-value"><?php echo e((string) $totalLockedSessions); ?></div><div class="metric-trend text-muted">Waiting for release</div></div><div class="metric-icon text-warning bg-warning-subtle"><i class="bi bi-lock-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Average progress</div><div class="metric-value"><?php echo e((string) $averageProgress); ?>%</div><div class="metric-trend text-muted"><?php echo e((string) $totalWatchedSessions); ?> watched sessions</div></div><div class="metric-icon text-info bg-info-subtle"><i class="bi bi-graph-up-arrow"></i></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><a href="<?php echo e(base_url('student/courses/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-grid"></i></span><span>My Units</span></div><div class="small text-muted">Open your unit list.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('student/courses/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-play-btn"></i></span><span>Continue Learning</span></div><div class="small text-muted">Go to available sessions.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('student/courses/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-bar-chart"></i></span><span>Track Progress</span></div><div class="small text-muted">Check what is completed.</div></a></div>
</div>

<div class="row g-4">
    <div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">My units</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Unit</th><th>Batch</th><th>Open</th></tr></thead><tbody><?php if ($enrolledUnits === []): ?><tr><td colspan="3" class="text-muted text-center py-4">No unit is currently in progress for this student.</td></tr><?php endif; ?><?php foreach ($enrolledUnitStageGroups as $stageGroup): ?><tr class="table-light"><td colspan="3"><div class="fw-semibold">Stage <?php echo e((string) $stageGroup['stage_number']); ?> - <?php echo e($stageGroup['stage_title']); ?></div><div class="small text-muted"><?php echo e($stageGroup['course_title']); ?> (<?php echo e($stageGroup['course_code']); ?>) | <?php echo e($stageGroup['stage_start_date']); ?> to <?php echo e($stageGroup['stage_end_date']); ?></div></td></tr><?php foreach ($stageGroup['units'] as $unit): ?><tr><td><div class="fw-semibold"><?php echo e($unit['unit_title']); ?></div><div class="small text-muted"><?php echo e($unit['unit_code'] ?: 'No code'); ?></div></td><td><?php echo e($unit['batch_name']); ?></td><td><a href="<?php echo e(base_url('student/courses/view.php?course_id=' . (string) $unit['course_id'] . '&unit_id=' . (string) $unit['unit_id'])); ?>" class="btn btn-sm btn-outline-primary">Open</a></td></tr><?php endforeach; ?><?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Available sessions</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Session</th><th>Course</th><th>Progress</th></tr></thead><tbody><?php foreach ($availableSessions as $session): ?><tr><td><div class="fw-semibold"><?php echo e($session['session_title']); ?></div><div class="small text-muted"><?php echo e($session['topic_title']); ?></div></td><td><?php echo e($session['course_title']); ?></td><td><div class="d-flex gap-2 align-items-center flex-wrap"><span class="status-chip <?php echo (int) $session['progress_percent'] >= 100 ? 'success' : 'info'; ?>"><i class="bi bi-play-circle-fill"></i><?php echo e((string) $session['progress_percent']); ?>%</span><a href="<?php echo e(base_url('student/sessions/view.php?session_id=' . (string) $session['id'])); ?>" class="btn btn-sm btn-outline-primary">Open</a></div></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12"><div class="surface-card table-card p-4"><div class="mb-3"><h2 class="section-title mb-1">Locked sessions</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Session</th><th>Topic</th><th>Course</th><th>Status</th></tr></thead><tbody><?php foreach ($lockedSessions as $lockedSession): ?><tr><td><div class="fw-semibold"><?php echo e($lockedSession['session_title']); ?></div><div class="small text-muted"><?php echo e(ucfirst($lockedSession['session_type'])); ?></div></td><td><?php echo e($lockedSession['topic_title']); ?></td><td><?php echo e($lockedSession['course_title']); ?></td><td><span class="status-chip warning"><i class="bi bi-lock-fill"></i>Locked</span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php require_once __DIR__ . '/../includes/layouts/role_footer.php'; ?>
