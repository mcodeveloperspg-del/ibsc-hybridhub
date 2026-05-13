<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['technical_officer']);

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

$totalSessions = fetch_total($pdo, 'sessions');
$totalUnlockedSessions = fetch_total($pdo, 'sessions', 'is_unlocked = 1');
$totalLockedSessions = $totalSessions - $totalUnlockedSessions;
$totalDraftSessions = fetch_total($pdo, 'sessions', "status = 'draft'");
$totalReplacementSessions = fetch_total($pdo, 'sessions', "session_type = 'replacement'");
$totalResources = fetch_total($pdo, 'session_resources');
$totalWatchLogs = fetch_total($pdo, 'watched_sessions');

$pendingSessionsStatement = $pdo->query(
    "SELECT sessions.session_title, sessions.session_type, sessions.status, topics.topic_title
     FROM sessions
     INNER JOIN topics ON topics.id = sessions.topic_id
     WHERE sessions.is_unlocked = 0
     ORDER BY sessions.id DESC
     LIMIT 5"
);
$pendingSessions = $pendingSessionsStatement->fetchAll();

$unlockLogStatement = $pdo->query(
    "SELECT unlock_logs.unlock_type, unlock_logs.notes, unlock_logs.unlocked_at,
            users.first_name, users.last_name,
            topics.topic_title,
            sessions.session_title
     FROM unlock_logs
     INNER JOIN users ON users.id = unlock_logs.unlocked_by
     LEFT JOIN topics ON topics.id = unlock_logs.topic_id
     LEFT JOIN sessions ON sessions.id = unlock_logs.session_id
     ORDER BY unlock_logs.id DESC
     LIMIT 5"
);
$unlockLogs = $unlockLogStatement->fetchAll();

$pageTitle = 'Technical Officer Dashboard - ' . APP_NAME;
$pageHeading = 'Operations Hub';
$pageDescription = 'Release queue, session controls, and recent activity.';

require_once __DIR__ . '/../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Locked sessions</div><div class="metric-value"><?php echo e((string) $totalLockedSessions); ?></div><div class="metric-trend text-muted">In release queue</div></div><div class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-lock-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Unlocked sessions</div><div class="metric-value"><?php echo e((string) $totalUnlockedSessions); ?></div><div class="metric-trend text-muted">Visible to students</div></div><div class="metric-icon bg-success-subtle text-success"><i class="bi bi-unlock-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Draft sessions</div><div class="metric-value"><?php echo e((string) $totalDraftSessions); ?></div><div class="metric-trend text-muted">Need updates</div></div><div class="metric-icon bg-info-subtle text-info"><i class="bi bi-file-earmark-text-fill"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><div class="metric-label">Replacement sessions</div><div class="metric-value"><?php echo e((string) $totalReplacementSessions); ?></div><div class="metric-trend text-muted"><?php echo e((string) $totalResources); ?> resources | <?php echo e((string) $totalWatchLogs); ?> watch logs</div></div><div class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-arrow-repeat"></i></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4"><a href="<?php echo e(base_url('technical_officer/sessions/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-camera-video"></i></span><span>Video Links</span></div><div class="small text-muted">Manage session links.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('technical_officer/sessions/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-unlock"></i></span><span>Unlock Queue</span></div><div class="small text-muted">Open release controls.</div></a></div>
    <div class="col-md-4"><a href="<?php echo e(base_url('technical_officer/sessions/index.php')); ?>" class="quick-link"><div class="quick-link-title"><span class="quick-link-icon"><i class="bi bi-clipboard-data"></i></span><span>Operations</span></div><div class="small text-muted">Review recent actions.</div></a></div>
</div>

<div class="row g-4"><div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Pending release queue</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Session</th><th>Type</th><th>Status</th></tr></thead><tbody><?php foreach ($pendingSessions as $pendingSession): ?><tr><td><div class="fw-semibold"><?php echo e($pendingSession['session_title']); ?></div><div class="small text-muted"><?php echo e($pendingSession['topic_title']); ?></div></td><td><?php echo e(ucfirst($pendingSession['session_type'])); ?></td><td><span class="status-chip warning"><i class="bi bi-lock-fill"></i><?php echo e(ucfirst($pendingSession['status'])); ?> / Locked</span></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-lg-6"><div class="surface-card table-card p-4 h-100"><div class="mb-3"><h2 class="section-title mb-1">Recent unlock activity</h2></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Action</th><th>By</th><th>When</th></tr></thead><tbody><?php foreach ($unlockLogs as $unlockLog): ?><tr><td><div class="fw-semibold"><?php echo e(ucfirst($unlockLog['unlock_type'])); ?> unlocked</div><div class="small text-muted"><?php echo e($unlockLog['session_title'] ?: $unlockLog['topic_title'] ?: 'Unnamed record'); ?></div></td><td><?php echo e(trim($unlockLog['first_name'] . ' ' . $unlockLog['last_name'])); ?></td><td><?php echo e(format_datetime($unlockLog['unlocked_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
<?php require_once __DIR__ . '/../includes/layouts/role_footer.php'; ?>
