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
$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$unitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : 0;
$today = date('Y-m-d');

$courseStatement = $pdo->prepare(
    "SELECT courses.id, courses.course_code, courses.title, courses.description, batches.id AS batch_id, batches.batch_name
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     WHERE enrollments.student_id = :student_id AND enrollments.course_id = :course_id AND enrollments.status = 'active'
     LIMIT 1"
);
$courseStatement->execute(['student_id' => $studentId, 'course_id' => $courseId]);
$course = $courseStatement->fetch();
if (!$course) {
    flash_message('That course is not available for this student.', 'warning');
    redirect(base_url('student/courses/index.php'));
}

$unitStatement = $pdo->prepare(
    "SELECT units.id, units.unit_title, units.unit_code,
            stages.stage_number, stages.title AS stage_title,
            batch_stages.start_date AS stage_start_date, batch_stages.end_date AS stage_end_date
     FROM units
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = :batch_id AND batch_stages.stage_number = stages.stage_number
     WHERE units.id = :unit_id
       AND units.course_id = :course_id
       AND units.status = 'active'
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
     LIMIT 1"
);
$unitStatement->execute([
    'unit_id' => $unitId,
    'course_id' => $courseId,
    'batch_id' => (int) $course['batch_id'],
    'today' => $today,
]);
$unit = $unitStatement->fetch();
if (!$unit) {
    flash_message('That unit is not currently available for this student.', 'warning');
    redirect(base_url('student/courses/index.php'));
}

$sessionsStatement = $pdo->prepare(
    "SELECT sessions.id, sessions.session_title, sessions.session_summary, sessions.session_date, sessions.session_type, sessions.is_unlocked,
            sessions.status, topics.topic_title, units.unit_title,
            COALESCE(watched_sessions.progress_percent, 0) AS progress_percent
     FROM units
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = :batch_id AND batch_stages.stage_number = stages.stage_number
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = :session_batch_id
        AND sessions.status = 'published'
     LEFT JOIN watched_sessions ON watched_sessions.session_id = sessions.id AND watched_sessions.student_id = :student_id
     WHERE units.course_id = :course_id
       AND units.id = :unit_id
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
     ORDER BY units.sort_order ASC, topics.sort_order ASC, sessions.sort_order ASC"
);
$sessionsStatement->execute([
    'student_id' => $studentId,
    'course_id' => $courseId,
    'unit_id' => $unitId,
    'batch_id' => (int) $course['batch_id'],
    'session_batch_id' => (int) $course['batch_id'],
    'today' => $today,
]);
$sessions = $sessionsStatement->fetchAll();
$topicHeadingNumbers = [];
$nextTopicHeadingNumber = 1;
foreach ($sessions as $index => $session) {
    $topicKey = (string) ($session['topic_title'] ?? '');
    if (!isset($topicHeadingNumbers[$topicKey])) {
        $topicHeadingNumbers[$topicKey] = $nextTopicHeadingNumber;
        $nextTopicHeadingNumber++;
    }
    $sessions[$index]['topic_heading'] = 'Topic ' . (string) $topicHeadingNumbers[$topicKey];
}

$pageTitle = 'Course View - ' . APP_NAME;
$pageHeading = $unit['unit_title'];
$pageDescription = 'View sessions for the selected unit in the stage currently in progress for this batch.';
require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="surface-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="eyebrow mb-2"><?php echo e($course['course_code']); ?></div>
            <p class="text-muted mb-0"><?php echo e($course['title']); ?> | <?php echo e($course['batch_name']); ?> | Stage <?php echo e((string) $unit['stage_number']); ?></p>
        </div>
        <a href="<?php echo e(base_url('student/courses/index.php')); ?>" class="btn btn-outline-secondary">Back to My Units</a>
    </div>
</div>
<div class="row g-4">
    <?php if ($sessions === []): ?>
        <div class="col-12">
            <div class="surface-card p-4 text-center">
                <div class="metric-icon text-warning bg-warning-subtle mx-auto mb-3"><i class="bi bi-calendar-x"></i></div>
                <h2 class="h5 mb-2">No current stage sessions</h2>
                <p class="text-muted mb-0">There are no sessions available because no stage for this batch is currently in progress.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($sessions as $session): ?>
        <div class="col-12">
            <div class="surface-card p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?php echo e($session['topic_heading']); ?></h2>
                        <div class="text-muted small"><?php echo e($session['unit_title']); ?> | <?php echo e($session['topic_title']); ?> | <?php echo e($session['session_title']); ?> | <?php echo e($session['session_date'] ?: 'No date set'); ?></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="status-chip <?php echo (int) $session['is_unlocked'] === 1 ? 'success' : 'warning'; ?>"><i class="bi <?php echo (int) $session['is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i><?php echo (int) $session['is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?></span>
                        <span class="status-chip <?php echo (int) $session['progress_percent'] >= 100 ? 'success' : 'info'; ?>"><i class="bi bi-graph-up-arrow"></i><?php echo e((string) $session['progress_percent']); ?>%</span>
                    </div>
                </div>
                <p class="text-muted"><?php echo e($session['session_summary'] ?: 'No session summary available yet.'); ?></p>
                <?php if ((int) $session['is_unlocked'] === 1): ?>
                    <a href="<?php echo e(base_url('student/sessions/view.php?session_id=' . (string) $session['id'])); ?>" class="btn btn-primary">Open Session</a>
                <?php else: ?>
                    <button class="btn btn-outline-secondary" disabled>Waiting For Release</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
