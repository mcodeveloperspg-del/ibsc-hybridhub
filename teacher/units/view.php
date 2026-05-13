<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['teacher']);

$user = current_user();
$flash = flash_message();
$teacherId = (int) ($user['id'] ?? 0);
$batchId = (int) ($_GET['batch_id'] ?? 0);
$unitId = (int) ($_GET['unit_id'] ?? 0);

$scope = $batchId > 0 && $unitId > 0 ? teacher_assigned_unit_scope($pdo, $teacherId, $batchId, $unitId) : null;
if ($scope === null) {
    flash_message('The selected unit is not available in this lecturer account.', 'warning');
    redirect(base_url('teacher/units/index.php'));
}

$topicSessionStatement = $pdo->prepare(
    "SELECT topics.id AS topic_id, topics.topic_title, topics.description, topics.sort_order, topics.is_unlocked,
            sessions.id AS session_id, sessions.session_title, sessions.session_summary, sessions.session_date,
            sessions.session_type, sessions.status AS session_status, sessions.is_unlocked AS session_is_unlocked,
            sessions.duration_minutes, sessions.sort_order AS session_sort_order,
            COUNT(session_resources.id) AS resource_count
     FROM topics
     LEFT JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = :batch_id
     LEFT JOIN session_resources ON session_resources.session_id = sessions.id
        AND session_resources.status = 'active'
     WHERE topics.unit_id = :unit_id
       AND topics.status = 'active'
     GROUP BY topics.id, topics.topic_title, topics.description, topics.sort_order, topics.is_unlocked,
              sessions.id, sessions.session_title, sessions.session_summary, sessions.session_date,
              sessions.session_type, sessions.status, sessions.is_unlocked, sessions.duration_minutes, sessions.sort_order
     ORDER BY topics.sort_order ASC, topics.id ASC, sessions.sort_order ASC, sessions.id ASC"
);
$topicSessionStatement->execute(['unit_id' => $unitId, 'batch_id' => $batchId]);
$rows = $topicSessionStatement->fetchAll();

$topics = [];
foreach ($rows as $row) {
    $topicId = (int) $row['topic_id'];
    if (!isset($topics[$topicId])) {
        $topics[$topicId] = [
            'topic_id' => $topicId,
            'topic_title' => $row['topic_title'],
            'description' => $row['description'],
            'sort_order' => (int) $row['sort_order'],
            'is_unlocked' => (int) $row['is_unlocked'],
            'sessions' => [],
        ];
    }

    if (!empty($row['session_id'])) {
        $topics[$topicId]['sessions'][] = [
            'session_id' => (int) $row['session_id'],
            'session_title' => $row['session_title'],
            'session_summary' => $row['session_summary'],
            'session_date' => $row['session_date'],
            'session_type' => $row['session_type'],
            'session_status' => $row['session_status'],
            'session_is_unlocked' => (int) $row['session_is_unlocked'],
            'duration_minutes' => $row['duration_minutes'],
            'resource_count' => (int) $row['resource_count'],
        ];
    }
}
$topics = array_values($topics);

$pageTitle = 'Unit Topics - ' . APP_NAME;
$pageHeading = $scope['unit_title'];
$pageDescription = 'Topics and linked videos available inside this assigned lecturer unit.';

require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Batch</div>
            <div class="metric-value fs-4"><?php echo e($scope['batch_name']); ?></div>
            <div class="metric-trend text-muted"><?php echo e((string) $scope['batch_year']); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Course</div>
            <div class="metric-value fs-4"><?php echo e($scope['course_code']); ?></div>
            <div class="metric-trend text-muted"><?php echo e($scope['course_title']); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="surface-card metric-card">
            <div class="metric-label">Stage</div>
            <div class="metric-value fs-4"><?php echo e('Stage ' . $scope['stage_number']); ?></div>
            <div class="metric-trend text-muted"><?php echo e($scope['stage_title']); ?></div>
        </div>
    </div>
</div>

<div class="surface-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2 class="h5 mb-1"><?php echo e($scope['unit_title']); ?><?php echo $scope['unit_code'] ? ' (' . e($scope['unit_code']) . ')' : ''; ?></h2>
            <p class="text-muted mb-0">This unit now shows each topic together with the videos linked to that topic, similar to the student access flow. You can still open a topic workspace to upload supporting materials.</p>
        </div>
        <a href="<?php echo e(base_url('teacher/units/index.php')); ?>" class="btn btn-outline-secondary">Back to My Units</a>
    </div>
</div>

<?php if ($topics === []): ?>
    <div class="surface-card p-4">
        <h2 class="h5 mb-2">No topics yet</h2>
        <p class="text-muted mb-0">This unit does not have any active topics yet.</p>
    </div>
<?php else: ?>
    <div class="d-grid gap-4">
        <?php foreach ($topics as $index => $topic): ?>
            <?php $topicNumber = $index + 1; ?>
            <?php $topicUnlocked = (int) $topic['is_unlocked'] === 1; ?>
            <div class="surface-card p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                        <div class="small text-muted mb-1"><?php echo e('Topic ' . $topicNumber); ?></div>
                        <h2 class="h5 mb-1"><?php echo e($topic['topic_title']); ?></h2>
                        <div class="small text-muted"><?php echo e($topic['description'] ?: 'No topic description has been added yet.'); ?></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="status-chip <?php echo $topicUnlocked ? 'success' : 'warning'; ?>"><i class="bi bi-diagram-2-fill"></i><?php echo e($topicUnlocked ? 'Unlocked' : 'Locked'); ?></span>
                        <a href="<?php echo e(base_url('teacher/resources/index.php?batch_id=' . (string) $batchId . '&unit_id=' . (string) $unitId . '&topic_id=' . (string) $topic['topic_id'])); ?>" class="btn btn-sm btn-outline-primary">Upload Topic Materials</a>
                    </div>
                </div>

                <?php if ($topic['sessions'] === []): ?>
                    <div class="border rounded-4 p-3 bg-light text-muted">No video sessions have been linked to this topic yet.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($topic['sessions'] as $session): ?>
                            <div class="col-12">
                                <div class="border rounded-4 p-3 bg-light h-100">
                                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                        <div>
                                            <div class="fw-semibold mb-1"><?php echo e($session['session_title']); ?></div>
                                            <div class="small text-muted"><?php echo e($scope['unit_title']); ?> | <?php echo e($topic['topic_title']); ?> | <?php echo e($session['session_date'] ?: 'No date set'); ?></div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="status-chip <?php echo $session['session_is_unlocked'] === 1 ? 'success' : 'warning'; ?>"><i class="bi <?php echo $session['session_is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i><?php echo $session['session_is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?></span>
                                            <span class="status-chip <?php echo $session['session_status'] === 'published' ? 'success' : 'info'; ?>"><i class="bi bi-broadcast"></i><?php echo e(ucfirst((string) $session['session_status'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="small text-muted mb-2">Type: <?php echo e(ucfirst((string) ($session['session_type'] ?: 'regular'))); ?></div>
                                    <div class="small text-muted mb-2">Duration: <?php echo e($session['duration_minutes'] ? ((string) $session['duration_minutes'] . ' mins') : 'Not set'); ?></div>
                                    <div class="small text-muted mb-3">Resources: <?php echo e((string) $session['resource_count']); ?></div>
                                    <p class="text-muted mb-3"><?php echo e($session['session_summary'] ?: 'No session summary available yet.'); ?></p>
                                    <?php if ($session['session_is_unlocked'] === 1): ?>
                                        <a href="<?php echo e(base_url('teacher/sessions/view.php?session_id=' . (string) $session['session_id'] . '&batch_id=' . (string) $batchId)); ?>" class="btn btn-primary btn-sm">Open Video Session</a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>Waiting For Release</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
