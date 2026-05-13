<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['student']);
$user = current_user();
$studentId = (int) ($user['id'] ?? 0);
$flash = flash_message();
$sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
$today = date('Y-m-d');

$sessionStatement = $pdo->prepare(
    "SELECT sessions.*, topics.topic_title, units.unit_title, courses.id AS course_id, courses.title AS course_title,
            batches.batch_name, batches.batch_year,
            COALESCE(watched_sessions.progress_percent, 0) AS progress_percent
     FROM enrollments
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = 'published'
     LEFT JOIN watched_sessions ON watched_sessions.session_id = sessions.id AND watched_sessions.student_id = enrollments.student_id
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = 'active'
       AND sessions.id = :session_id
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
     LIMIT 1"
);
$sessionStatement->execute(['student_id' => $studentId, 'session_id' => $sessionId, 'today' => $today]);
$session = $sessionStatement->fetch();
if (!$session || (int) $session['is_unlocked'] !== 1) {
    flash_message('That session is not currently available for this student.', 'warning');
    redirect(base_url('student/dashboard.php'));
}

$contentSession = session_content_source($pdo, $session) ?? $session;
$resources = session_resources_for_delivery($pdo, $session);
$embedUrl = session_embed_url($contentSession);

$pageTitle = 'Session View - ' . APP_NAME;
$pageHeading = $session['session_title'];
$pageDescription = 'Watch the unlocked session inside the platform and download the related learning materials.';
require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="surface-card p-4 mb-4">
    <div class="d-flex justify-content-between gap-3 flex-wrap">
        <div>
            <div class="text-muted small mb-1"><?php echo e($session['course_title']); ?> | <?php echo e($session['unit_title']); ?> | <?php echo e($session['topic_title']); ?></div>
            <div class="text-muted small mb-2"><?php echo e($session['batch_name'] . ' (' . $session['batch_year'] . ')'); ?></div>
            <p class="text-muted mb-0"><?php echo e($session['session_summary'] ?: 'No session summary available yet.'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (session_uses_fallback($session)): ?><span class="status-chip info"><i class="bi bi-arrow-repeat"></i>Using approved fallback session</span><?php endif; ?>
            <span class="status-chip <?php echo (int) $session['progress_percent'] >= 100 ? 'success' : 'info'; ?>"><i class="bi bi-graph-up-arrow"></i><?php echo e((string) $session['progress_percent']); ?>%</span>
            <a href="<?php echo e(base_url('student/courses/view.php?course_id=' . (string) $session['course_id'])); ?>" class="btn btn-outline-secondary">Back to Course</a>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="surface-card p-4 h-100">
            <h2 class="h5 mb-3">Video Session</h2>
            <?php if ($embedUrl !== null): ?>
                <div class="ratio ratio-16x9 rounded overflow-hidden mb-3">
                    <iframe src="<?php echo e($embedUrl); ?>" title="Session video" allowfullscreen></iframe>
                </div>
            <?php else: ?>
                <div class="border rounded-4 p-4 bg-light text-muted">A playable embed link has not been configured yet for this session.</div>
            <?php endif; ?>
            <form action="<?php echo e(base_url('actions/student/mark_watched.php')); ?>" method="POST" class="mt-3 d-flex gap-2 flex-wrap">
                <input type="hidden" name="session_id" value="<?php echo e((string) $session['id']); ?>">
                <input type="hidden" name="course_id" value="<?php echo e((string) $session['course_id']); ?>">
                <button type="submit" name="progress_percent" value="70" class="btn btn-outline-primary">Mark 70%</button>
                <button type="submit" name="progress_percent" value="100" class="btn btn-primary">Mark As Watched</button>
            </form>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="surface-card p-4 h-100">
            <h2 class="h5 mb-3">Learning Materials</h2>
            <div class="d-grid gap-3">
                <?php foreach ($resources as $resource): ?>
                    <div class="border rounded-4 p-3 bg-light">
                        <div class="fw-semibold"><?php echo e($resource['resource_title']); ?></div>
                        <div class="small text-muted mb-2"><?php echo e(ucfirst($resource['resource_type'])); ?></div>
                        <?php if (($resource['external_url'] ?? '') !== ''): ?>
                            <a href="<?php echo e($resource['external_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open Link</a>
                        <?php else: ?>
                            <a href="<?php echo e(base_url('actions/student/download_resource.php?resource_id=' . (string) $resource['id'])); ?>" class="btn btn-sm btn-outline-primary">Download</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
