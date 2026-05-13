<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['teacher']);

$user = current_user();
$flash = flash_message();
$teacherId = (int) ($user['id'] ?? 0);
$hasUnitAssignments = teacher_has_unit_assignments($pdo, $teacherId);

function fetch_teacher_total(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

$totalUploadedResources = fetch_teacher_total($pdo, 'SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id', ['teacher_id' => $teacherId]);
$totalSlideResources = fetch_teacher_total($pdo, "SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id AND resource_type = 'slide'", ['teacher_id' => $teacherId]);

if ($hasUnitAssignments) {
    $totalAssignedUnits = fetch_teacher_total(
        $pdo,
        "SELECT COUNT(*) FROM lecturer_unit_assignments WHERE lecturer_id = :teacher_id AND status = 'active'",
        ['teacher_id' => $teacherId]
    );
    $totalAssignedBatches = fetch_teacher_total(
        $pdo,
        "SELECT COUNT(DISTINCT batch_id) FROM lecturer_unit_assignments WHERE lecturer_id = :teacher_id AND status = 'active'",
        ['teacher_id' => $teacherId]
    );
    $totalAssignedSessions = fetch_teacher_total(
        $pdo,
        "SELECT COUNT(DISTINCT sessions.id)
         FROM lecturer_unit_assignments
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN topics ON topics.unit_id = units.id
         INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = lecturer_unit_assignments.batch_id
         WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
           AND lecturer_unit_assignments.status = 'active'",
        ['teacher_id' => $teacherId]
    );

    $assignedScopesStatement = $pdo->prepare(
        "SELECT batches.batch_name, batches.batch_year,
                courses.title AS course_title, courses.course_code,
                stages.stage_number,
                units.unit_title, units.unit_code,
                lecturer_unit_assignments.status
         FROM lecturer_unit_assignments
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         INNER JOIN stages ON stages.id = units.stage_id
         INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
         WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
           AND lecturer_unit_assignments.status = 'active'
         ORDER BY batches.batch_year DESC, batches.batch_name ASC, courses.title ASC, stages.stage_number ASC, units.unit_title ASC"
    );
    $assignedScopesStatement->execute(['teacher_id' => $teacherId]);
    $assignedScopes = $assignedScopesStatement->fetchAll();

    $assignedUnitsStatement = $pdo->prepare(
        "SELECT batches.batch_name, batches.batch_year,
                courses.title AS course_title, courses.course_code,
                stages.stage_number, stages.title AS stage_title,
                units.unit_title, units.unit_code
         FROM lecturer_unit_assignments
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         INNER JOIN stages ON stages.id = units.stage_id
         INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
         WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
           AND lecturer_unit_assignments.status = 'active'
         ORDER BY batches.batch_year DESC, batches.batch_name ASC, courses.title ASC, stages.stage_number ASC, units.unit_title ASC"
    );
    $assignedUnitsStatement->execute(['teacher_id' => $teacherId]);
    $assignedUnits = $assignedUnitsStatement->fetchAll();

    $teachingSessionsStatement = $pdo->prepare(
        "SELECT DISTINCT sessions.session_title, sessions.status, sessions.session_date,
                topics.topic_title, units.unit_title,
                courses.title AS course_title, batches.batch_name, batches.batch_year
         FROM lecturer_unit_assignments
         INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         INNER JOIN topics ON topics.unit_id = units.id
         INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = batches.id
         WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
           AND lecturer_unit_assignments.status = 'active'
         ORDER BY sessions.session_date DESC, sessions.id DESC
         LIMIT 5"
    );
    $teachingSessionsStatement->execute(['teacher_id' => $teacherId]);
    $teachingSessions = $teachingSessionsStatement->fetchAll();
} else {
    $totalAssignedUnits = fetch_teacher_total($pdo, 'SELECT COUNT(DISTINCT units.id) FROM teacher_course_assignments INNER JOIN courses ON courses.id = teacher_course_assignments.course_id INNER JOIN units ON units.course_id = courses.id WHERE teacher_course_assignments.teacher_id = :teacher_id AND teacher_course_assignments.status = :status', ['teacher_id' => $teacherId, 'status' => 'active']);
    $totalAssignedBatches = fetch_teacher_total($pdo, 'SELECT COUNT(DISTINCT batch_id) FROM teacher_course_assignments WHERE teacher_id = :teacher_id AND status = :status', ['teacher_id' => $teacherId, 'status' => 'active']);
    $totalAssignedSessions = fetch_teacher_total($pdo, 'SELECT COUNT(DISTINCT sessions.id) FROM teacher_course_assignments INNER JOIN courses ON courses.id = teacher_course_assignments.course_id INNER JOIN units ON units.course_id = courses.id INNER JOIN topics ON topics.unit_id = units.id INNER JOIN sessions ON sessions.topic_id = topics.id AND (teacher_course_assignments.batch_id IS NULL OR sessions.batch_id = teacher_course_assignments.batch_id) WHERE teacher_course_assignments.teacher_id = :teacher_id AND teacher_course_assignments.status = :status', ['teacher_id' => $teacherId, 'status' => 'active']);

    $assignedScopesStatement = $pdo->prepare('SELECT courses.course_code, courses.title AS course_title, batches.batch_name, batches.batch_year, teacher_course_assignments.status FROM teacher_course_assignments INNER JOIN courses ON courses.id = teacher_course_assignments.course_id LEFT JOIN batches ON batches.id = teacher_course_assignments.batch_id WHERE teacher_course_assignments.teacher_id = :teacher_id ORDER BY courses.title ASC');
    $assignedScopesStatement->execute(['teacher_id' => $teacherId]);
    $assignedScopes = $assignedScopesStatement->fetchAll();
    $assignedUnits = [];

    $teachingSessionsStatement = $pdo->prepare('SELECT DISTINCT sessions.session_title, sessions.status, sessions.session_date, topics.topic_title, units.unit_title, courses.title AS course_title FROM teacher_course_assignments INNER JOIN courses ON courses.id = teacher_course_assignments.course_id INNER JOIN units ON units.course_id = courses.id INNER JOIN topics ON topics.unit_id = units.id INNER JOIN sessions ON sessions.topic_id = topics.id AND (teacher_course_assignments.batch_id IS NULL OR sessions.batch_id = teacher_course_assignments.batch_id) WHERE teacher_course_assignments.teacher_id = :teacher_id AND teacher_course_assignments.status = :status ORDER BY sessions.session_date DESC, sessions.id DESC LIMIT 5');
    $teachingSessionsStatement->execute(['teacher_id' => $teacherId, 'status' => 'active']);
    $teachingSessions = $teachingSessionsStatement->fetchAll();
}

$recentResourcesStatement = $pdo->prepare('SELECT session_resources.resource_title, session_resources.resource_type, session_resources.created_at, sessions.session_title FROM session_resources INNER JOIN sessions ON sessions.id = session_resources.session_id WHERE session_resources.uploaded_by = :teacher_id ORDER BY session_resources.id DESC LIMIT 5');
$recentResourcesStatement->execute(['teacher_id' => $teacherId]);
$recentResources = $recentResourcesStatement->fetchAll();

$pageTitle = 'Lecturer Dashboard - ' . APP_NAME;
$pageHeading = 'Teaching Hub';
$pageDescription = 'Your assignments, materials, and session activity.';

require_once __DIR__ . '/../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Assigned units</div><div class="metric-value"><?php echo e((string) $totalAssignedUnits); ?></div><div class="metric-trend text-muted">Current scope</div></div><div class="metric-icon text-primary bg-primary-subtle"><i class="bi bi-journal-richtext"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Active batches</div><div class="metric-value"><?php echo e((string) $totalAssignedBatches); ?></div><div class="metric-trend text-muted">Teaching groups</div></div><div class="metric-icon text-success bg-success-subtle"><i class="bi bi-layers-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Uploaded materials</div><div class="metric-value"><?php echo e((string) $totalUploadedResources); ?></div><div class="metric-trend text-muted">Resources added</div></div><div class="metric-icon text-warning bg-warning-subtle"><i class="bi bi-folder-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Sessions</div><div class="metric-value"><?php echo e((string) $totalAssignedSessions); ?></div><div class="metric-trend text-muted"><?php echo e((string) $totalSlideResources); ?> slide sets</div></div><div class="metric-icon text-info bg-info-subtle"><i class="bi bi-broadcast"></i></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><a href="<?php echo e(base_url('teacher/resources/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-upload"></i></span><span>Upload Materials</span></div><div class="small text-muted">Open the resource page.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('teacher/units/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-journals"></i></span><span>My Units</span></div><div class="small text-muted">Review assignments.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('teacher/resources/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-folder2-open"></i></span><span>Manage Resources</span></div><div class="small text-muted">Update session files.</div></a></div>
</div>

<?php if ($hasUnitAssignments): ?>
<div class="row g-4 mb-4">
    <div class="col-12"><div class="surface-card p-4"><div class="mb-3"><h2 class="section-title mb-1">My assigned units</h2></div><div class="row g-3"><?php foreach ($assignedUnits as $assignedUnit): ?><div class="col-md-6 col-xl-4"><div class="help-card h-100"><div class="fw-semibold mb-1"><?php echo e($assignedUnit['unit_title']); ?><?php echo $assignedUnit['unit_code'] ? ' (' . e($assignedUnit['unit_code']) . ')' : ''; ?></div><div class="small text-muted mb-1"><?php echo e($assignedUnit['course_title'] . ' (' . $assignedUnit['course_code'] . ')'); ?></div><div class="small text-muted mb-1">Stage <?php echo e((string) $assignedUnit['stage_number']); ?> - <?php echo e($assignedUnit['stage_title']); ?></div><div class="small text-muted"><?php echo e($assignedUnit['batch_name'] . ' (' . $assignedUnit['batch_year'] . ')'); ?></div></div></div><?php endforeach; ?></div></div></div>
</div>
<?php endif; ?>

<div class="row g-4"><div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1"><?php echo $hasUnitAssignments ? 'Teaching scope' : 'Assignments'; ?></h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Scope</th><th>Status</th></tr></thead><tbody><?php foreach ($assignedScopes as $assignedScope): ?><tr><td><?php if ($hasUnitAssignments): ?><div class="fw-semibold"><?php echo e($assignedScope['batch_name'] . ' (' . $assignedScope['batch_year'] . ')'); ?></div><div class="small text-muted"><?php echo e($assignedScope['course_title'] . ' (' . $assignedScope['course_code'] . ')'); ?></div><div class="small text-muted">Stage <?php echo e((string) $assignedScope['stage_number']); ?> - <?php echo e($assignedScope['unit_title']); ?><?php echo $assignedScope['unit_code'] ? ' (' . e($assignedScope['unit_code']) . ')' : ''; ?></div><?php else: ?><div class="fw-semibold"><?php echo e($assignedScope['course_title']); ?></div><div class="small text-muted"><?php echo e($assignedScope['course_code']); ?></div><div class="small text-muted"><?php echo e(($assignedScope['batch_name'] ?? 'All batches') . (!empty($assignedScope['batch_year']) ? ' (' . $assignedScope['batch_year'] . ')' : '')); ?></div><?php endif; ?></td><td><span class="status-chip <?php echo $assignedScope['status'] === 'active' ? 'success' : 'warning'; ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($assignedScope['status'])); ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Recent resources</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Resource</th><th>Type</th><th>Added</th></tr></thead><tbody><?php foreach ($recentResources as $resource): ?><tr><td><div class="fw-semibold"><?php echo e($resource['resource_title']); ?></div><div class="small text-muted"><?php echo e($resource['session_title']); ?></div></td><td><?php echo e(ucfirst($resource['resource_type'])); ?></td><td><?php echo e(format_datetime($resource['created_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<div class="row g-4 mt-1"><div class="col-12"><div class="surface-card table-card p-4"><div class="mb-3"><h2 class="section-title mb-1">Recent sessions</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Session</th><th>Topic / Unit</th><th>Scope</th><th>Status</th></tr></thead><tbody><?php foreach ($teachingSessions as $session): ?><tr><td><div class="fw-semibold"><?php echo e($session['session_title']); ?></div><div class="small text-muted"><?php echo e($session['session_date'] ?: 'No date set'); ?></div></td><td><div class="small"><?php echo e($session['topic_title']); ?></div><div class="small text-muted"><?php echo e($session['unit_title'] ?? 'Assigned course'); ?></div></td><td><div class="small"><?php echo e($session['course_title']); ?></div><?php if (!empty($session['batch_name'])): ?><div class="small text-muted"><?php echo e($session['batch_name'] . (!empty($session['batch_year']) ? ' (' . $session['batch_year'] . ')' : '')); ?></div><?php endif; ?></td><td><span class="status-chip <?php echo $session['status'] === 'published' ? 'success' : 'warning'; ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($session['status'])); ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php require_once __DIR__ . '/../includes/layouts/role_footer.php'; ?>
