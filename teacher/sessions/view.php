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
$sessionId = (int) ($_GET['session_id'] ?? 0);
$batchId = (int) ($_GET['batch_id'] ?? 0);
$formState = consume_form_state('teacher_resource_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$sessionStatement = $pdo->prepare(
    "SELECT sessions.*, topics.topic_title, units.id AS unit_id, units.unit_title, units.unit_code,
            courses.id AS course_id, courses.title AS course_title, courses.course_code,
            stages.stage_number, stages.title AS stage_title,
            batches.id AS batch_id, batches.batch_name, batches.batch_year
     FROM lecturer_unit_assignments
     INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
     INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN courses ON courses.id = units.course_id
     INNER JOIN topics ON topics.unit_id = units.id
     INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = batches.id
     WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
       AND lecturer_unit_assignments.batch_id = :batch_id
       AND lecturer_unit_assignments.status = 'active'
       AND sessions.id = :session_id
     LIMIT 1"
);
$sessionStatement->execute([
    'teacher_id' => $teacherId,
    'batch_id' => $batchId,
    'session_id' => $sessionId,
]);
$session = $sessionStatement->fetch();

if (!$session || (int) ($session['is_unlocked'] ?? 0) !== 1) {
    flash_message('Only unlocked sessions assigned to this lecturer can be opened.', 'warning');
    redirect(base_url('teacher/units/index.php'));
}

$contentSession = session_content_source($pdo, $session) ?? $session;
$resources = session_resources_for_delivery($pdo, $session);

$resourceFormData = [
    'session_id' => $oldData['session_id'] ?? $sessionId,
    'batch_id' => $oldData['batch_id'] ?? $batchId,
    'return_to' => $oldData['return_to'] ?? 'session_view',
    'resource_title' => $oldData['resource_title'] ?? '',
    'resource_type' => $oldData['resource_type'] ?? 'document',
    'external_url' => $oldData['external_url'] ?? '',
    'status' => $oldData['status'] ?? 'active',
];

$embedUrl = session_embed_url($contentSession);
$pageTitle = 'Session Workspace - ' . APP_NAME;
$pageHeading = $session['session_title'];
$pageDescription = 'Watch the unlocked session video and upload learning materials for enrolled students in this course.';

require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="surface-card p-4 mb-4">
    <div class="d-flex justify-content-between gap-3 flex-wrap">
        <div>
            <div class="text-muted small mb-1"><?php echo e($session['batch_name'] . ' (' . $session['batch_year'] . ')'); ?> | <?php echo e($session['course_title']); ?> | <?php echo e('Stage ' . $session['stage_number']); ?></div>
            <div class="text-muted small mb-2"><?php echo e($session['unit_title']); ?><?php echo $session['unit_code'] ? ' (' . e($session['unit_code']) . ')' : ''; ?> | <?php echo e($session['topic_title']); ?></div>
            <p class="text-muted mb-0"><?php echo e($session['session_summary'] ?: 'No session summary available yet.'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="status-chip success"><i class="bi bi-unlock-fill"></i>Unlocked</span>
            <?php if (session_uses_fallback($session)): ?><span class="status-chip info"><i class="bi bi-arrow-repeat"></i>Fallback Source In Use</span><?php endif; ?>
            <a href="<?php echo e(base_url('teacher/units/view.php?batch_id=' . (string) $batchId . '&unit_id=' . (string) $session['unit_id'])); ?>" class="btn btn-outline-secondary">Back to Unit</a>
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
                <div class="border rounded-4 p-4 bg-light text-muted mb-3">A playable embed link has not been configured yet for this session.</div>
            <?php endif; ?>
            <div class="small text-muted">Students enrolled in <?php echo e($session['course_title']); ?> can access the materials attached to this unlocked session.<?php echo session_uses_fallback($session) ? ' This batch session is currently delivering approved fallback content from an older topic session.' : ''; ?></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="surface-card p-4 h-100">
            <h2 class="h5 mb-3">Upload Resource Materials</h2>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please fix the following issues:</div>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form action="<?php echo e(base_url('actions/teacher/save_resource.php')); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="session_id" value="<?php echo e((string) $sessionId); ?>">
                <input type="hidden" name="batch_id" value="<?php echo e((string) $batchId); ?>">
                <input type="hidden" name="return_to" value="session_view">
                <div class="col-12">
                    <label for="resource_title" class="form-label">Resource Title</label>
                    <input type="text" class="form-control" id="resource_title" name="resource_title" value="<?php echo e((string) $resourceFormData['resource_title']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="resource_type" class="form-label">Resource Type</label>
                    <select class="form-select" id="resource_type" name="resource_type" required>
                        <?php foreach (['slide', 'document', 'worksheet', 'link', 'other'] as $resourceType): ?>
                            <option value="<?php echo e($resourceType); ?>" <?php echo $resourceFormData['resource_type'] === $resourceType ? 'selected' : ''; ?>><?php echo e(ucfirst($resourceType)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach (['active', 'inactive'] as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $resourceFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="resource_file" class="form-label">Upload File</label>
                    <input type="file" class="form-control" id="resource_file" name="resource_file">
                    <div class="form-text">Supported formats include PPT, PPTX, PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP, JPG, and PNG.</div>
                </div>
                <div class="col-12">
                    <label for="external_url" class="form-label">External URL</label>
                    <input type="url" class="form-control" id="external_url" name="external_url" value="<?php echo e((string) $resourceFormData['external_url']); ?>" placeholder="https://example.com/resource">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Upload Resource</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="surface-card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">Session Materials</h2>
            <p class="text-muted mb-0">These materials become available to enrolled students when they open this unlocked session.</p>
        </div>
    </div>
    <?php if ($resources === []): ?>
        <p class="text-muted mb-0">No materials have been uploaded for this session yet.</p>
    <?php else: ?>
        <div class="d-grid gap-4">
            <?php foreach ($resources as $resource): ?>
                <?php $previewType = resource_preview_type($resource); ?>
                <?php $previewUrl = resource_public_url($resource); ?>
                <div class="border rounded-4 p-3 bg-light">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                        <div>
                            <div class="fw-semibold"><?php echo e($resource['resource_title']); ?></div>
                            <div class="small text-muted"><?php echo e($resource['file_name'] ?: $resource['external_url'] ?: 'Stored resource'); ?></div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <span class="status-chip <?php echo $resource['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo e(ucfirst((string) $resource['status'])); ?></span>
                            <span class="small text-muted"><?php echo e(format_datetime($resource['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="small text-muted mb-3">Type: <?php echo e(ucfirst((string) $resource['resource_type'])); ?></div>

                    <?php if (($resource['external_url'] ?? '') !== ''): ?>
                        <a href="<?php echo e($resource['external_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open Link</a>
                    <?php elseif ($previewType === 'pdf' && $previewUrl !== null): ?>
                        <div class="ratio ratio-16x9 rounded overflow-hidden border bg-white mb-2">
                            <iframe src="<?php echo e($previewUrl); ?>" title="PDF preview"></iframe>
                        </div>
                    <?php elseif ($previewType === 'image' && $previewUrl !== null): ?>
                        <img src="<?php echo e($previewUrl); ?>" alt="Resource preview" class="img-fluid rounded border mb-2">
                    <?php elseif ($previewType === 'text' && $previewUrl !== null): ?>
                        <div class="ratio ratio-16x9 rounded overflow-hidden border bg-white mb-2">
                            <iframe src="<?php echo e($previewUrl); ?>" title="Text preview"></iframe>
                        </div>
                    <?php else: ?>
                        <div class="small text-muted mb-2">Inline preview is available for PDFs, images, and text files. Other file types can still be downloaded by students.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
