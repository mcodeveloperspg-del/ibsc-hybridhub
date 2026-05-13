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
$today = date('Y-m-d');

$unitCardsStatement = $pdo->prepare(
    "SELECT DISTINCT units.id AS unit_id, units.unit_code, units.unit_title, units.description,
            courses.id AS course_id, courses.course_code, courses.title AS course_title,
            batches.batch_name, stages.stage_number, stages.title AS stage_title,
            batch_stages.start_date AS stage_start_date, batch_stages.end_date AS stage_end_date,
            COUNT(DISTINCT sessions.id) AS total_sessions,
            SUM(CASE WHEN sessions.is_unlocked = 1 THEN 1 ELSE 0 END) AS unlocked_sessions,
            SUM(CASE WHEN sessions.is_unlocked = 0 THEN 1 ELSE 0 END) AS locked_sessions,
            SUM(CASE WHEN watched_sessions.student_id IS NOT NULL THEN 1 ELSE 0 END) AS watched_sessions
     FROM enrollments
     INNER JOIN courses ON courses.id = enrollments.course_id
     INNER JOIN batches ON batches.id = enrollments.batch_id
     INNER JOIN units ON units.course_id = courses.id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number
     LEFT JOIN topics ON topics.unit_id = units.id
     LEFT JOIN sessions ON sessions.topic_id = topics.id
        AND sessions.batch_id = batches.id
        AND sessions.status = 'published'
     LEFT JOIN watched_sessions ON watched_sessions.session_id = sessions.id AND watched_sessions.student_id = enrollments.student_id
     WHERE enrollments.student_id = :student_id
       AND enrollments.status = 'active'
       AND units.status = 'active'
       AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date
     GROUP BY units.id, units.unit_code, units.unit_title, units.description,
              courses.id, courses.course_code, courses.title,
              batches.batch_name, stages.stage_number, stages.title,
              batch_stages.start_date, batch_stages.end_date
     ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC, units.unit_title ASC"
);
$unitCardsStatement->execute(['student_id' => $studentId, 'today' => $today]);
$units = $unitCardsStatement->fetchAll();

$stageGroups = [];
foreach ($units as $unit) {
    $groupKey = (string) $unit['course_id'] . ':stage:' . (string) $unit['stage_number'];
    if (!isset($stageGroups[$groupKey])) {
        $stageGroups[$groupKey] = [
            'course_code' => (string) $unit['course_code'],
            'course_title' => (string) $unit['course_title'],
            'stage_number' => (int) $unit['stage_number'],
            'stage_title' => (string) $unit['stage_title'],
            'stage_start_date' => (string) $unit['stage_start_date'],
            'stage_end_date' => (string) $unit['stage_end_date'],
            'units' => [],
        ];
    }

    $stageGroups[$groupKey]['units'][] = $unit;
}

$pageTitle = 'My Units - ' . APP_NAME;
$pageHeading = 'My Units';
$pageDescription = 'Browse units from the stage currently in progress for your active batch enrollment.';
require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<?php if ($units === []): ?>
    <div class="surface-card p-4 text-center">
        <div class="metric-icon text-warning bg-warning-subtle mx-auto mb-3"><i class="bi bi-journal-x"></i></div>
        <h2 class="h5 mb-2">No units currently in progress</h2>
        <p class="text-muted mb-0">This student does not have any unit in a stage that is currently in progress.</p>
    </div>
<?php endif; ?>

<?php foreach ($stageGroups as $stageGroup): ?>
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <div class="eyebrow mb-2"><?php echo e($stageGroup['course_code']); ?></div>
                <h2 class="section-title mb-1">Stage <?php echo e((string) $stageGroup['stage_number']); ?> - <?php echo e($stageGroup['stage_title']); ?></h2>
                <p class="text-muted mb-0"><?php echo e($stageGroup['course_title']); ?> | <?php echo e($stageGroup['stage_start_date']); ?> to <?php echo e($stageGroup['stage_end_date']); ?></p>
            </div>
            <span class="status-chip info"><i class="bi bi-journal-richtext"></i><?php echo e((string) count($stageGroup['units'])); ?> unit<?php echo count($stageGroup['units']) === 1 ? '' : 's'; ?></span>
        </div>

        <div class="row g-4">
            <?php foreach ($stageGroup['units'] as $unit): ?>
                <div class="col-xl-6">
                    <div class="surface-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="eyebrow mb-2"><?php echo e($unit['unit_code'] ?: 'Unit'); ?></div>
                                <h3 class="h4 mb-1"><?php echo e($unit['unit_title']); ?></h3>
                                <p class="text-muted mb-0"><?php echo e($unit['batch_name']); ?></p>
                            </div>
                            <span class="status-chip success"><i class="bi bi-person-check-fill"></i>Enrolled</span>
                        </div>
                        <p class="text-muted"><?php echo e($unit['description'] ?: 'No unit description available yet.'); ?></p>
                        <div class="small text-muted mb-3">
                            <?php echo e($unit['course_code']); ?> | Stage <?php echo e((string) $unit['stage_number']); ?> - <?php echo e($unit['stage_title']); ?>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="p-3 border rounded-4 bg-light h-100"><div class="small text-muted">Sessions</div><div class="h4 mb-0"><?php echo e((string) ($unit['total_sessions'] ?? 0)); ?></div></div></div>
                            <div class="col-md-3"><div class="p-3 border rounded-4 bg-light h-100"><div class="small text-muted">Unlocked</div><div class="h4 mb-0"><?php echo e((string) ($unit['unlocked_sessions'] ?? 0)); ?></div></div></div>
                            <div class="col-md-3"><div class="p-3 border rounded-4 bg-light h-100"><div class="small text-muted">Locked</div><div class="h4 mb-0"><?php echo e((string) ($unit['locked_sessions'] ?? 0)); ?></div></div></div>
                            <div class="col-md-3"><div class="p-3 border rounded-4 bg-light h-100"><div class="small text-muted">Watched</div><div class="h4 mb-0"><?php echo e((string) ($unit['watched_sessions'] ?? 0)); ?></div></div></div>
                        </div>
                        <a href="<?php echo e(base_url('student/courses/view.php?course_id=' . (string) $unit['course_id'] . '&unit_id=' . (string) $unit['unit_id'])); ?>" class="btn btn-primary">Open Sessions</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
