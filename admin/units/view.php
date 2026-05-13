<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$unitId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($unitId < 1) {
    flash_message('Select a unit to view.', 'warning');
    redirect(base_url('admin/units/index.php'));
}

$unitStatement = $pdo->prepare(
    "SELECT units.*, courses.title AS course_title, courses.course_code,
            stages.title AS stage_title, stages.stage_number
     FROM units
     INNER JOIN courses ON courses.id = units.course_id
     INNER JOIN stages ON stages.id = units.stage_id
     WHERE units.id = :id
     LIMIT 1"
);
$unitStatement->execute(['id' => $unitId]);
$unit = $unitStatement->fetch();

if (!$unit) {
    flash_message('The selected unit could not be found.', 'warning');
    redirect(base_url('admin/units/index.php'));
}

$topicsStatement = $pdo->prepare(
    "SELECT topics.id, topics.topic_title, topics.description, topics.sort_order, topics.is_unlocked,
            topics.status, topics.updated_at, COUNT(sessions.id) AS session_count
     FROM topics
     LEFT JOIN sessions ON sessions.topic_id = topics.id
     WHERE topics.unit_id = :unit_id
     GROUP BY topics.id, topics.topic_title, topics.description, topics.sort_order, topics.is_unlocked,
              topics.status, topics.updated_at
     ORDER BY topics.sort_order ASC, topics.topic_title ASC"
);
$topicsStatement->execute(['unit_id' => $unitId]);
$topics = $topicsStatement->fetchAll();

$pageTitle = $unit['unit_title'] . ' - Unit Topics - ' . APP_NAME;
$pageHeading = 'Unit Topics';
$pageDescription = 'Review this unit and manage the topics attached to it.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="surface-card p-4 mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h2 class="h4 mb-0"><?php echo e($unit['unit_title']); ?></h2>
                <span class="status-chip <?php echo $unit['status'] === 'active' ? 'success' : 'warning'; ?>">
                    <i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($unit['status'])); ?>
                </span>
            </div>
            <div class="text-muted mb-2"><?php echo e($unit['unit_code'] ?: 'No unit code'); ?></div>
            <p class="mb-0"><?php echo e($unit['description'] ?: 'No unit summary has been added yet.'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo e(base_url('admin/topics/form.php?unit_id=' . (string) $unitId . '&return_unit_id=' . (string) $unitId)); ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Topic</a>
            <a href="<?php echo e(base_url('admin/units/form.php?edit=' . (string) $unitId . '&return_unit_id=' . (string) $unitId)); ?>" class="btn btn-outline-primary">Edit Unit</a>
            <a href="<?php echo e(base_url('admin/units/index.php')); ?>" class="btn btn-outline-secondary">Back to Units</a>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Course</div><div class="fw-semibold"><?php echo e($unit['course_title']); ?> (<?php echo e($unit['course_code']); ?>)</div></div></div>
        <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Stage</div><div class="fw-semibold">Stage <?php echo e((string) $unit['stage_number']); ?> - <?php echo e($unit['stage_title']); ?></div></div></div>
        <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Topics</div><div class="fw-semibold"><?php echo e((string) count($topics)); ?> topic<?php echo count($topics) === 1 ? '' : 's'; ?></div></div></div>
    </div>
</div>

<div class="surface-card p-4">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h2 class="h5 mb-1">Topics in This Unit</h2>
            <p class="text-muted mb-0">Open a topic to edit its details on a dedicated page.</p>
        </div>
        <a href="<?php echo e(base_url('admin/topics/form.php?unit_id=' . (string) $unitId . '&return_unit_id=' . (string) $unitId)); ?>" class="btn btn-outline-primary">Add New Topic</a>
    </div>

    <?php if ($topics === []): ?>
        <div class="text-center text-muted py-4">No topics have been added to this unit yet.</div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($topics as $topic): ?>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <div>
                            <div class="fw-semibold"><?php echo e($topic['topic_title']); ?></div>
                            <div class="small text-muted">Sort order <?php echo e((string) $topic['sort_order']); ?> | <?php echo e((string) $topic['session_count']); ?> session<?php echo (int) $topic['session_count'] === 1 ? '' : 's'; ?></div>
                        </div>
                        <span class="status-chip <?php echo (int) $topic['is_unlocked'] === 1 ? 'success' : 'warning'; ?>">
                            <i class="bi <?php echo (int) $topic['is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                            <?php echo (int) $topic['is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?>
                        </span>
                    </div>
                    <p class="small text-muted mb-3"><?php echo e($topic['description'] ?: 'No topic description has been added yet.'); ?></p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo e(base_url('admin/topics/form.php?edit=' . (string) $topic['id'] . '&return_unit_id=' . (string) $unitId)); ?>" class="btn btn-sm btn-outline-primary">Edit Topic</a>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTopicModal<?php echo e((string) $topic['id']); ?>">Delete</button>
                    </div>
                </div>

                <div class="modal fade" id="deleteTopicModal<?php echo e((string) $topic['id']); ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title h5 mb-0">Delete Topic</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">Are you sure you want to delete <strong><?php echo e($topic['topic_title']); ?></strong>?</p>
                                <p class="text-muted mb-0">This will also delete <?php echo e((string) $topic['session_count']); ?> session<?php echo (int) $topic['session_count'] === 1 ? '' : 's'; ?> and any related resources.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form action="<?php echo e(base_url('actions/admin/delete_topic.php')); ?>" method="POST">
                                    <input type="hidden" name="topic_id" value="<?php echo e((string) $topic['id']); ?>">
                                    <input type="hidden" name="return_unit_id" value="<?php echo e((string) $unitId); ?>">
                                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
