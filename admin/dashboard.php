<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['system_admin']);
$user = current_user();
$flash = flash_message();

function fetch_total(PDO $pdo, string $table, ?string $whereClause = null): int
{
    $sql = 'SELECT COUNT(*) FROM ' . $table;
    if ($whereClause !== null) {
        $sql .= ' WHERE ' . $whereClause;
    }

    return (int) $pdo->query($sql)->fetchColumn();
}

$totalUsers = fetch_total($pdo, 'users');
$totalStudents = fetch_total($pdo, 'users', "role_id = (SELECT id FROM roles WHERE name = 'student' LIMIT 1)");
$totalLecturers = fetch_total($pdo, 'users', "role_id = (SELECT id FROM roles WHERE name = 'teacher' LIMIT 1)");
$totalCourses = fetch_total($pdo, 'courses');
$totalUnits = fetch_total($pdo, 'units');
$totalTopics = fetch_total($pdo, 'topics');
$totalSessions = fetch_total($pdo, 'sessions');
$totalUnlockedSessions = fetch_total($pdo, 'sessions', 'is_unlocked = 1');
$totalBatches = fetch_total($pdo, 'batches');
$totalEnrollments = fetch_total($pdo, 'enrollments');
$totalWatchLogs = fetch_total($pdo, 'watched_sessions');

$recentUsersStatement = $pdo->query("SELECT users.first_name, users.last_name, users.email, users.status, roles.name AS role_name FROM users INNER JOIN roles ON roles.id = users.role_id ORDER BY users.id DESC LIMIT 5");
$recentUsers = $recentUsersStatement->fetchAll();
$recentSessionsStatement = $pdo->query("SELECT sessions.session_title, sessions.status, sessions.is_unlocked, topics.topic_title FROM sessions INNER JOIN topics ON topics.id = sessions.topic_id ORDER BY sessions.id DESC LIMIT 5");
$recentSessions = $recentSessionsStatement->fetchAll();

$pageTitle = 'Admin Dashboard - ' . APP_NAME;
$pageHeading = 'Campus Setup Hub';
$pageDescription = 'Follow the learning structure from batches to sessions, then manage people and access.';
require_once __DIR__ . '/../includes/layouts/admin_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Batches</div><div class="metric-value"><?php echo e((string) $totalBatches); ?></div><div class="metric-trend text-muted">Delivery groups</div></div><div class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-layers-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Courses</div><div class="metric-value"><?php echo e((string) $totalCourses); ?></div><div class="metric-trend text-muted"><?php echo e((string) $totalUnits); ?> units in structure</div></div><div class="metric-icon bg-success-subtle text-success"><i class="bi bi-journal-bookmark-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Topics</div><div class="metric-value"><?php echo e((string) $totalTopics); ?></div><div class="metric-trend text-muted"><?php echo e((string) $totalSessions); ?> sessions built</div></div><div class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-diagram-2-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">People & access</div><div class="metric-value"><?php echo e((string) $totalUsers); ?></div><div class="metric-trend text-muted"><?php echo e((string) $totalEnrollments); ?> enrollments | <?php echo e((string) $totalUnlockedSessions); ?> unlocked</div></div><div class="metric-icon bg-info-subtle text-info"><i class="bi bi-people-fill"></i></div></div></div></div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="mb-3">
        <h2 class="section-title mb-1">Academic structure</h2>
        <p class="section-note mb-0">Build the learning hierarchy in the same order students experience it.</p>
    </div>
    <div class="row g-3">
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/batches/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-layers"></i></span><span>Batches</span></div><div class="small text-muted">Create intake groups.</div></a></div>
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/courses/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-journal-bookmark"></i></span><span>Courses & Stages</span></div><div class="small text-muted">Define the main program flow.</div></a></div>
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/units/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-diagram-3"></i></span><span>Units</span></div><div class="small text-muted">Break courses into teachable blocks.</div></a></div>
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/topics/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-diagram-2"></i></span><span>Topics</span></div><div class="small text-muted">Organize unit content.</div></a></div>
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/sessions/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-collection-play"></i></span><span>Sessions</span></div><div class="small text-muted">Set up lesson records.</div></a></div>
        <div class="col-md-6 col-xl-2"><a href="<?php echo e(base_url('admin/analytics/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-graph-up"></i></span><span>Analytics</span></div><div class="small text-muted">Review structure health.</div></a></div>
    </div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="mb-3">
        <h2 class="section-title mb-1">People and delivery</h2>
        <p class="section-note mb-0">After the structure is ready, connect the right people to the right learning path.</p>
    </div>
    <div class="row g-3">
        <div class="col-md-6 col-xl-3"><a href="<?php echo e(base_url('admin/students/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-person-plus"></i></span><span>Students</span></div><div class="small text-muted">Manage learner accounts.</div></a></div>
        <div class="col-md-6 col-xl-3"><a href="<?php echo e(base_url('admin/lecturers/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-person-workspace"></i></span><span>Lecturers</span></div><div class="small text-muted">Manage teaching accounts.</div></a></div>
        <div class="col-md-6 col-xl-3"><a href="<?php echo e(base_url('admin/lecturer_assignments/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-person-badge"></i></span><span>Lecturer Allocation</span></div><div class="small text-muted">Link lecturers to units and batches.</div></a></div>
        <div class="col-md-6 col-xl-3"><a href="<?php echo e(base_url('admin/enrollments/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-person-lines-fill"></i></span><span>Enrollments</span></div><div class="small text-muted">Control student access.</div></a></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Recent users</h2><p class="section-note mb-0">New people added to the platform.</p></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach ($recentUsers as $recentUser): ?><tr><td><div class="fw-semibold"><?php echo e(trim($recentUser['first_name'] . ' ' . $recentUser['last_name'])); ?></div><div class="small text-muted"><?php echo e($recentUser['email']); ?></div></td><td><?php echo e(role_label($recentUser['role_name'])); ?></td><td><span class="status-chip <?php echo $recentUser['status'] === 'active' ? 'success' : 'warning'; ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($recentUser['status'])); ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Recent sessions</h2><p class="section-note mb-0">Latest session records in the learning structure.</p></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Session</th><th>Status</th><th>Access</th></tr></thead><tbody><?php foreach ($recentSessions as $recentSession): ?><tr><td><div class="fw-semibold"><?php echo e($recentSession['session_title']); ?></div><div class="small text-muted"><?php echo e($recentSession['topic_title']); ?></div></td><td><?php echo e(ucfirst($recentSession['status'])); ?></td><td><?php if ((int) $recentSession['is_unlocked'] === 1): ?><span class="status-chip success"><i class="bi bi-unlock-fill"></i>Unlocked</span><?php else: ?><span class="status-chip warning"><i class="bi bi-lock-fill"></i>Locked</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php require_once __DIR__ . '/../includes/layouts/admin_footer.php'; ?>
