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
$hasUnitAssignments = teacher_has_unit_assignments($pdo, $teacherId);
$formState = consume_form_state('teacher_resource_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];
$selectedTopicId = (int) ($_GET['topic_id'] ?? 0);
$selectedBatchId = (int) ($_GET['batch_id'] ?? 0);
$selectedUnitId = (int) ($_GET['unit_id'] ?? 0);
$topicContext = null;

$editingResourceId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingResource = null;
if ($editingResourceId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM session_resources WHERE id = :id AND uploaded_by = :uploaded_by LIMIT 1');
    $editStatement->execute(['id' => $editingResourceId, 'uploaded_by' => $teacherId]);
    $editingResource = $editStatement->fetch();
    if (!$editingResource) {
        flash_message('The selected resource could not be found for this lecturer.', 'warning');
        redirect(base_url('teacher/resources/index.php'));
    }
}

function fetch_teacher_value(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

$totalResources = fetch_teacher_value($pdo, 'SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id', ['teacher_id' => $teacherId]);
$totalSlides = fetch_teacher_value($pdo, "SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id AND resource_type = 'slide'", ['teacher_id' => $teacherId]);
$totalDocuments = fetch_teacher_value($pdo, "SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id AND resource_type IN ('document', 'worksheet', 'other')", ['teacher_id' => $teacherId]);
$totalLinks = fetch_teacher_value($pdo, "SELECT COUNT(*) FROM session_resources WHERE uploaded_by = :teacher_id AND resource_type = 'link'", ['teacher_id' => $teacherId]);

if ($hasUnitAssignments && $selectedTopicId > 0 && $selectedBatchId > 0 && $selectedUnitId > 0) {
    $scope = teacher_assigned_unit_scope($pdo, $teacherId, $selectedBatchId, $selectedUnitId);
    if ($scope !== null) {
        $topicContextStatement = $pdo->prepare(
            'SELECT id, topic_title, description, sort_order
             FROM topics
             WHERE id = :topic_id AND unit_id = :unit_id AND status = :status
             LIMIT 1'
        );
        $topicContextStatement->execute([
            'topic_id' => $selectedTopicId,
            'unit_id' => $selectedUnitId,
            'status' => 'active',
        ]);
        $topicRow = $topicContextStatement->fetch();

        if ($topicRow) {
            $topicContext = [
                'topic_id' => (int) $topicRow['id'],
                'topic_title' => $topicRow['topic_title'],
                'topic_description' => $topicRow['description'],
                'topic_sort_order' => (int) $topicRow['sort_order'],
                'batch_id' => $scope['batch_id'],
                'batch_name' => $scope['batch_name'],
                'batch_year' => $scope['batch_year'],
                'unit_id' => $scope['unit_id'],
                'unit_title' => $scope['unit_title'],
                'unit_code' => $scope['unit_code'],
                'course_title' => $scope['course_title'],
                'course_code' => $scope['course_code'],
                'stage_number' => $scope['stage_number'],
                'stage_title' => $scope['stage_title'],
            ];
        }
    }
}

if ($hasUnitAssignments) {
    if ($topicContext !== null) {
        $sessionOptionsStatement = $pdo->prepare(
            "SELECT DISTINCT sessions.id, sessions.session_title, topics.id AS topic_id, topics.topic_title, units.unit_title,
                    courses.title AS course_title, batches.batch_name, batches.batch_year
             FROM lecturer_unit_assignments
             INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
             INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
             INNER JOIN courses ON courses.id = units.course_id
             INNER JOIN topics ON topics.unit_id = units.id
             INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = lecturer_unit_assignments.batch_id
             WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
               AND lecturer_unit_assignments.status = 'active'
               AND lecturer_unit_assignments.batch_id = :batch_id
               AND lecturer_unit_assignments.unit_id = :unit_id
               AND topics.id = :topic_id
             ORDER BY sessions.sort_order ASC, sessions.id ASC"
        );
        $sessionOptionsStatement->execute([
            'teacher_id' => $teacherId,
            'batch_id' => $topicContext['batch_id'],
            'unit_id' => $topicContext['unit_id'],
            'topic_id' => $topicContext['topic_id'],
        ]);
    } else {
        $sessionOptionsStatement = $pdo->prepare(
            "SELECT DISTINCT sessions.id, sessions.session_title, topics.id AS topic_id, topics.topic_title, units.unit_title,
                    courses.title AS course_title, batches.batch_name, batches.batch_year
             FROM lecturer_unit_assignments
             INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
             INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
             INNER JOIN courses ON courses.id = units.course_id
             INNER JOIN topics ON topics.unit_id = units.id
             INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = lecturer_unit_assignments.batch_id
             WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
               AND lecturer_unit_assignments.status = 'active'
             ORDER BY batches.batch_year DESC, courses.title ASC, units.sort_order ASC, topics.sort_order ASC, sessions.sort_order ASC"
        );
        $sessionOptionsStatement->execute(['teacher_id' => $teacherId]);
    }
} else {
    if ($selectedTopicId > 0) {
        $topicContextStatement = $pdo->prepare(
            "SELECT topics.id, topics.topic_title, topics.description, topics.sort_order,
                    units.id AS unit_id, units.unit_title, units.unit_code,
                    courses.title AS course_title, courses.course_code
             FROM teacher_course_assignments
             INNER JOIN courses ON courses.id = teacher_course_assignments.course_id
             INNER JOIN units ON units.course_id = courses.id
             INNER JOIN topics ON topics.unit_id = units.id
             WHERE teacher_course_assignments.teacher_id = :teacher_id
               AND teacher_course_assignments.status = 'active'
               AND topics.id = :topic_id
             LIMIT 1"
        );
        $topicContextStatement->execute([
            'teacher_id' => $teacherId,
            'topic_id' => $selectedTopicId,
        ]);
        $topicRow = $topicContextStatement->fetch();
        if ($topicRow) {
            $topicContext = [
                'topic_id' => (int) $topicRow['id'],
                'topic_title' => $topicRow['topic_title'],
                'topic_description' => $topicRow['description'],
                'topic_sort_order' => (int) $topicRow['sort_order'],
                'unit_id' => (int) $topicRow['unit_id'],
                'unit_title' => $topicRow['unit_title'],
                'unit_code' => $topicRow['unit_code'],
                'course_title' => $topicRow['course_title'],
                'course_code' => $topicRow['course_code'],
            ];
        }
    }

    if ($topicContext !== null) {
        $sessionOptionsStatement = $pdo->prepare(
            "SELECT DISTINCT sessions.id, sessions.session_title, topics.id AS topic_id, topics.topic_title, units.unit_title, courses.title AS course_title,
                    batches.batch_name, batches.batch_year
             FROM teacher_course_assignments
             INNER JOIN courses ON courses.id = teacher_course_assignments.course_id
             LEFT JOIN batches ON batches.id = teacher_course_assignments.batch_id
             INNER JOIN units ON units.course_id = courses.id
             INNER JOIN topics ON topics.unit_id = units.id
             INNER JOIN sessions ON sessions.topic_id = topics.id AND (teacher_course_assignments.batch_id IS NULL OR sessions.batch_id = teacher_course_assignments.batch_id)
             WHERE teacher_course_assignments.teacher_id = :teacher_id
               AND teacher_course_assignments.status = 'active'
               AND topics.id = :topic_id
             ORDER BY sessions.sort_order ASC, sessions.id ASC"
        );
        $sessionOptionsStatement->execute([
            'teacher_id' => $teacherId,
            'topic_id' => $topicContext['topic_id'],
        ]);
    } else {
        $sessionOptionsStatement = $pdo->prepare(
            "SELECT DISTINCT sessions.id, sessions.session_title, topics.id AS topic_id, topics.topic_title, units.unit_title, courses.title AS course_title,
                    batches.batch_name, batches.batch_year
             FROM teacher_course_assignments
             INNER JOIN courses ON courses.id = teacher_course_assignments.course_id
             LEFT JOIN batches ON batches.id = teacher_course_assignments.batch_id
             INNER JOIN units ON units.course_id = courses.id
             INNER JOIN topics ON topics.unit_id = units.id
             INNER JOIN sessions ON sessions.topic_id = topics.id AND (teacher_course_assignments.batch_id IS NULL OR sessions.batch_id = teacher_course_assignments.batch_id)
             WHERE teacher_course_assignments.teacher_id = :teacher_id
               AND teacher_course_assignments.status = 'active'
             ORDER BY courses.title ASC, units.sort_order ASC, topics.sort_order ASC, sessions.sort_order ASC"
        );
        $sessionOptionsStatement->execute(['teacher_id' => $teacherId]);
    }
}
$sessionOptions = $sessionOptionsStatement->fetchAll();

$resourcesStatement = $pdo->prepare(
    "SELECT session_resources.id, session_resources.resource_title, session_resources.resource_type, session_resources.file_name,
            session_resources.file_path, session_resources.external_url, session_resources.status, session_resources.created_at,
            sessions.session_title, topics.topic_title
     FROM session_resources
     INNER JOIN sessions ON sessions.id = session_resources.session_id
     INNER JOIN topics ON topics.id = sessions.topic_id
     WHERE session_resources.uploaded_by = :teacher_id
     ORDER BY session_resources.id DESC"
);
$resourcesStatement->execute(['teacher_id' => $teacherId]);
$resources = $resourcesStatement->fetchAll();

$defaultSessionId = '';
if ($topicContext !== null && $sessionOptions !== []) {
    $defaultSessionId = (string) ($sessionOptions[0]['id'] ?? '');
}

$resourceFormData = [
    'id' => $oldData['id'] ?? ($editingResource['id'] ?? ''),
    'session_id' => $oldData['session_id'] ?? ($editingResource['session_id'] ?? $defaultSessionId),
    'resource_title' => $oldData['resource_title'] ?? ($editingResource['resource_title'] ?? ''),
    'resource_type' => $oldData['resource_type'] ?? ($editingResource['resource_type'] ?? 'document'),
    'external_url' => $oldData['external_url'] ?? ($editingResource['external_url'] ?? ''),
    'status' => $oldData['status'] ?? ($editingResource['status'] ?? 'active'),
];

$pageTitle = 'Lecturer Resource Workspace - ' . APP_NAME;
$pageHeading = 'Lecturer Resource Workspace';
$pageDescription = 'Upload lecture slides, worksheets, supporting files, and learning links only for sessions inside this lecturer\'s assigned units.';

require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">All Uploads</div><div class="metric-value"><?php echo e((string) $totalResources); ?></div><div class="metric-trend text-muted">Resources saved by this lecturer</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Slides</div><div class="metric-value"><?php echo e((string) $totalSlides); ?></div><div class="metric-trend text-muted">Presentation files prepared for sessions</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Documents</div><div class="metric-value"><?php echo e((string) $totalDocuments); ?></div><div class="metric-trend text-muted">Worksheets and supporting files</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Links</div><div class="metric-value"><?php echo e((string) $totalLinks); ?></div><div class="metric-trend text-muted">External learning links stored for sessions</div></div></div>
</div>

<?php if ($topicContext !== null): ?>
    <div class="surface-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <div class="small text-muted mb-1"><?php echo e('Topic ' . (string) $topicContext['topic_sort_order']); ?></div>
                <h2 class="h5 mb-1"><?php echo e($topicContext['topic_title']); ?></h2>
                <div class="small text-muted mb-1"><?php echo e($topicContext['course_title'] . ' (' . $topicContext['course_code'] . ')'); ?></div>
                <div class="small text-muted mb-1"><?php echo e($topicContext['unit_title'] . ($topicContext['unit_code'] ? ' (' . $topicContext['unit_code'] . ')' : '')); ?></div>
                <?php if (isset($topicContext['batch_name'])): ?>
                    <div class="small text-muted mb-2"><?php echo e($topicContext['batch_name'] . ' (' . (string) $topicContext['batch_year'] . ') | Stage ' . (string) $topicContext['stage_number'] . ' - ' . $topicContext['stage_title']); ?></div>
                <?php endif; ?>
                <div class="small text-muted"><?php echo e($topicContext['topic_description'] ?: 'Use this topic-focused view to upload materials to the sessions that belong to this topic.'); ?></div>
            </div>
            <?php if (isset($topicContext['batch_id'])): ?>
                <a href="<?php echo e(base_url('teacher/units/view.php?batch_id=' . (string) $topicContext['batch_id'] . '&unit_id=' . (string) $topicContext['unit_id'])); ?>" class="btn btn-outline-secondary">Back to Unit Topics</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="surface-card table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Uploaded Teaching Resources</h2><p class="text-muted mb-0">Resources are limited to sessions inside this lecturer's assigned scope.</p></div></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Resource</th><th>Session</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($resource['resource_title']); ?></div>
                                    <div class="small text-muted"><?php echo e($resource['file_name'] ?: $resource['external_url'] ?: 'Stored resource'); ?></div>
                                </td>
                                <td>
                                    <div class="small"><?php echo e($resource['session_title']); ?></div>
                                    <div class="small text-muted"><?php echo e($resource['topic_title']); ?></div>
                                </td>
                                <td><?php echo e(ucfirst($resource['resource_type'])); ?></td>
                                <td><span class="status-chip <?php echo $resource['status'] === 'active' ? 'success' : 'warning'; ?>"><i class="bi bi-circle-fill small"></i><?php echo e(ucfirst($resource['status'])); ?></span></td>
                                <td><a href="<?php echo e(base_url('teacher/resources/index.php?edit=' . (string) $resource['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="surface-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1"><?php echo $editingResource ? 'Edit Resource' : 'Upload Resource'; ?></h2><p class="text-muted mb-0">Upload allowed file types or store an external learning link for an assigned session.</p></div><?php if ($editingResource): ?><a href="<?php echo e(base_url('teacher/resources/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Clear Edit</a><?php endif; ?></div>
            <?php if ($errors !== []): ?><div class="alert alert-danger"><div class="fw-semibold mb-2">Please fix the following issues:</div><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form action="<?php echo e(base_url('actions/teacher/save_resource.php')); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" value="<?php echo e((string) $resourceFormData['id']); ?>">
                <div class="col-12"><label for="session_id" class="form-label">Session</label><select class="form-select" id="session_id" name="session_id" required><option value="">Select session</option><?php foreach ($sessionOptions as $sessionOption): ?><option value="<?php echo e((string) $sessionOption['id']); ?>" <?php echo (string) $resourceFormData['session_id'] === (string) $sessionOption['id'] ? 'selected' : ''; ?>><?php echo e(($sessionOption['batch_name'] ? $sessionOption['batch_name'] . ' - ' : '') . $sessionOption['course_title'] . ' - ' . $sessionOption['unit_title'] . ' - ' . $sessionOption['topic_title'] . ' - ' . $sessionOption['session_title']); ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="resource_title" class="form-label">Resource Title</label><input type="text" class="form-control" id="resource_title" name="resource_title" value="<?php echo e((string) $resourceFormData['resource_title']); ?>" required></div>
                <div class="col-md-6"><label for="resource_type" class="form-label">Resource Type</label><select class="form-select" id="resource_type" name="resource_type" required><?php foreach (['slide', 'document', 'worksheet', 'link', 'other'] as $resourceType): ?><option value="<?php echo e($resourceType); ?>" <?php echo $resourceFormData['resource_type'] === $resourceType ? 'selected' : ''; ?>><?php echo e(ucfirst($resourceType)); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" required><?php foreach (['active', 'inactive'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $resourceFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label for="resource_file" class="form-label">Upload File</label><input type="file" class="form-control" id="resource_file" name="resource_file"></div>
                <div class="col-12"><label for="external_url" class="form-label">External URL</label><input type="url" class="form-control" id="external_url" name="external_url" value="<?php echo e((string) $resourceFormData['external_url']); ?>" placeholder="https://example.com/resource"></div>
                <div class="col-12"><div class="form-text"><?php if ($topicContext !== null): ?>Only sessions from the selected topic are shown here.<?php else: ?><?php echo $hasUnitAssignments ? 'Only sessions from assigned batch-stage units are available here.' : 'Legacy course-wide access is still in use until unit-specific assignments are created.'; ?><?php endif; ?></div></div>
                <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary"><?php echo $editingResource ? 'Update Resource' : 'Save Resource'; ?></button><a href="<?php echo e(base_url('teacher/dashboard.php')); ?>" class="btn btn-outline-secondary">Back to Dashboard</a></div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
