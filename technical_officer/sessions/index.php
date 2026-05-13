<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['technical_officer']);

$user = current_user();
$flash = flash_message();
$sessionFormState = consume_form_state('technical_session_ops_form_state', ['errors' => [], 'data' => []]);
$resourceFormState = consume_form_state('technical_resource_form_state', ['errors' => [], 'data' => []]);
$sessionErrors = $sessionFormState['errors'] ?? [];
$resourceErrors = $resourceFormState['errors'] ?? [];
$sessionOldData = $sessionFormState['data'] ?? [];
$resourceOldData = $resourceFormState['data'] ?? [];

$editingSessionId = isset($_GET['edit_session']) ? (int) $_GET['edit_session'] : 0;
$editingResourceId = isset($_GET['edit_resource']) ? (int) $_GET['edit_resource'] : 0;

$editingSession = null;
if ($editingSessionId > 0) {
    $editingSessionStatement = $pdo->prepare(
        "SELECT sessions.*, topics.topic_title, units.unit_title, courses.title AS course_title
         FROM sessions
         INNER JOIN topics ON topics.id = sessions.topic_id
         INNER JOIN units ON units.id = topics.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         WHERE sessions.id = :id
         LIMIT 1"
    );
    $editingSessionStatement->execute(['id' => $editingSessionId]);
    $editingSession = $editingSessionStatement->fetch();

    if (!$editingSession) {
        flash_message('The selected session could not be found.', 'warning');
        redirect(base_url('technical_officer/sessions/index.php'));
    }
}

$editingResource = null;
if ($editingResourceId > 0) {
    $editingResourceStatement = $pdo->prepare(
        "SELECT session_resources.*, sessions.session_title
         FROM session_resources
         INNER JOIN sessions ON sessions.id = session_resources.session_id
         WHERE session_resources.id = :id
         LIMIT 1"
    );
    $editingResourceStatement->execute(['id' => $editingResourceId]);
    $editingResource = $editingResourceStatement->fetch();

    if (!$editingResource) {
        flash_message('The selected resource could not be found.', 'warning');
        redirect(base_url('technical_officer/sessions/index.php'));
    }
}

$countsStatement = $pdo->query(
    "SELECT
        COUNT(*) AS total_sessions,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_sessions,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_sessions,
        SUM(CASE WHEN is_unlocked = 0 THEN 1 ELSE 0 END) AS locked_sessions,
        SUM(CASE WHEN status = 'published' AND is_unlocked = 0 THEN 1 ELSE 0 END) AS ready_to_unlock,
        SUM(CASE WHEN (video_url IS NULL OR video_url = '') AND (video_embed_url IS NULL OR video_embed_url = '') THEN 1 ELSE 0 END) AS missing_video_links
     FROM sessions"
);
$counts = $countsStatement->fetch() ?: [];

$resourceCountStatement = $pdo->query(
    "SELECT
        COUNT(*) AS total_resources,
        SUM(CASE WHEN resource_type = 'link' THEN 1 ELSE 0 END) AS linked_resources
     FROM session_resources"
);
$resourceCounts = $resourceCountStatement->fetch() ?: [];

$sessionsStatement = $pdo->query(
    "SELECT sessions.id, sessions.topic_id, sessions.session_title, sessions.session_date, sessions.video_provider,
            sessions.video_url, sessions.video_embed_url, sessions.duration_minutes, sessions.session_type,
            sessions.is_unlocked, sessions.unlocked_at, sessions.status, topics.topic_title, topics.is_unlocked AS topic_unlocked,
            units.unit_title, courses.title AS course_title,
            COUNT(session_resources.id) AS resource_count
     FROM sessions
     INNER JOIN topics ON topics.id = sessions.topic_id
     INNER JOIN units ON units.id = topics.unit_id
     INNER JOIN courses ON courses.id = units.course_id
     LEFT JOIN session_resources ON session_resources.session_id = sessions.id
     GROUP BY sessions.id, sessions.topic_id, sessions.session_title, sessions.session_date, sessions.video_provider,
              sessions.video_url, sessions.video_embed_url, sessions.duration_minutes, sessions.session_type,
              sessions.is_unlocked, sessions.unlocked_at, sessions.status, topics.topic_title, topics.is_unlocked,
              units.unit_title, courses.title
     ORDER BY
        CASE WHEN sessions.status = 'published' AND sessions.is_unlocked = 0 THEN 0 ELSE 1 END,
        courses.title ASC,
        units.sort_order ASC,
        topics.sort_order ASC,
        sessions.sort_order ASC"
);
$sessions = $sessionsStatement->fetchAll();

$sessionOptionsStatement = $pdo->query(
    "SELECT sessions.id, sessions.session_title, topics.topic_title, courses.title AS course_title
     FROM sessions
     INNER JOIN topics ON topics.id = sessions.topic_id
     INNER JOIN units ON units.id = topics.unit_id
     INNER JOIN courses ON courses.id = units.course_id
     ORDER BY courses.title ASC, topics.topic_title ASC, sessions.session_title ASC"
);
$sessionOptions = $sessionOptionsStatement->fetchAll();

$recentResourcesStatement = $pdo->query(
    "SELECT session_resources.id, session_resources.resource_title, session_resources.resource_type,
            session_resources.external_url, session_resources.file_name, session_resources.status,
            sessions.session_title
     FROM session_resources
     INNER JOIN sessions ON sessions.id = session_resources.session_id
     ORDER BY session_resources.id DESC
     LIMIT 8"
);
$recentResources = $recentResourcesStatement->fetchAll();

$sessionFormData = [
    'id' => $sessionOldData['id'] ?? ($editingSession['id'] ?? ''),
    'video_provider' => $sessionOldData['video_provider'] ?? ($editingSession['video_provider'] ?? 'youtube'),
    'video_url' => $sessionOldData['video_url'] ?? ($editingSession['video_url'] ?? ''),
    'video_embed_url' => $sessionOldData['video_embed_url'] ?? ($editingSession['video_embed_url'] ?? ''),
    'duration_minutes' => $sessionOldData['duration_minutes'] ?? ($editingSession['duration_minutes'] ?? ''),
    'status' => $sessionOldData['status'] ?? ($editingSession['status'] ?? 'draft'),
    'is_unlocked' => $sessionOldData['is_unlocked'] ?? ($editingSession['is_unlocked'] ?? 0),
    'unlock_notes' => $sessionOldData['unlock_notes'] ?? '',
];

$resourceFormData = [
    'id' => $resourceOldData['id'] ?? ($editingResource['id'] ?? ''),
    'session_id' => $resourceOldData['session_id'] ?? ($editingResource['session_id'] ?? ($editingSession['id'] ?? '')),
    'resource_title' => $resourceOldData['resource_title'] ?? ($editingResource['resource_title'] ?? ''),
    'resource_type' => $resourceOldData['resource_type'] ?? ($editingResource['resource_type'] ?? 'document'),
    'external_url' => $resourceOldData['external_url'] ?? ($editingResource['external_url'] ?? ''),
    'status' => $resourceOldData['status'] ?? ($editingResource['status'] ?? 'active'),
];

$pageTitle = 'Technical Session Operations - ' . APP_NAME;
$pageHeading = 'Technical Session Operations';
$pageDescription = 'Manage release status, update video links, and attach operational resources without changing the academic structure.';

require_once __DIR__ . '/../../includes/layouts/role_header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Ready To Unlock</div><div class="metric-value"><?php echo e((string) ($counts['ready_to_unlock'] ?? 0)); ?></div><div class="metric-trend text-muted">Published sessions still locked</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Locked Sessions</div><div class="metric-value"><?php echo e((string) ($counts['locked_sessions'] ?? 0)); ?></div><div class="metric-trend text-muted">Sessions waiting on release control</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Missing Video Links</div><div class="metric-value"><?php echo e((string) ($counts['missing_video_links'] ?? 0)); ?></div><div class="metric-trend text-muted">Sessions that still need a playable link</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="surface-card metric-card"><div class="metric-label">Resources</div><div class="metric-value"><?php echo e((string) ($resourceCounts['total_resources'] ?? 0)); ?></div><div class="metric-trend text-muted">Links and uploaded learning files on record</div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="surface-card table-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1">Release Queue</h2>
                    <p class="text-muted mb-0">Technical Officers can publish links, unlock access, and attach resources from one workspace.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Session</th>
                            <th>Status</th>
                            <th>Video</th>
                            <th>Resources</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($session['session_title']); ?></div>
                                    <div class="small text-muted"><?php echo e($session['course_title']); ?> | <?php echo e($session['unit_title']); ?> | <?php echo e($session['topic_title']); ?></div>
                                </td>
                                <td>
                                    <div class="small mb-1"><?php echo e(ucfirst($session['status'])); ?></div>
                                    <span class="status-chip <?php echo (int) $session['is_unlocked'] === 1 ? 'success' : 'warning'; ?>">
                                        <i class="bi <?php echo (int) $session['is_unlocked'] === 1 ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                                        <?php echo (int) $session['is_unlocked'] === 1 ? 'Unlocked' : 'Locked'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($session['video_url'] ?? '') !== '' || ($session['video_embed_url'] ?? '') !== ''): ?>
                                        <span class="status-chip success"><i class="bi bi-camera-video-fill"></i>Ready</span>
                                    <?php else: ?>
                                        <span class="status-chip warning"><i class="bi bi-exclamation-triangle-fill"></i>Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e((string) $session['resource_count']); ?></td>
                                <td>
                                    <a href="<?php echo e(base_url('technical_officer/sessions/index.php?edit_session=' . (string) $session['id'])); ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="surface-card p-4 h-100">
            <h2 class="h5 mb-3">Operations Summary</h2>
            <div class="d-grid gap-3">
                <div class="border rounded-4 p-3 bg-light">
                    <div class="fw-semibold mb-1">Published Sessions</div>
                    <div class="small text-muted"><?php echo e((string) ($counts['published_sessions'] ?? 0)); ?> sessions are already in published state.</div>
                </div>
                <div class="border rounded-4 p-3 bg-light">
                    <div class="fw-semibold mb-1">Draft Sessions</div>
                    <div class="small text-muted"><?php echo e((string) ($counts['draft_sessions'] ?? 0)); ?> sessions still need completion before release.</div>
                </div>
                <div class="border rounded-4 p-3 bg-light">
                    <div class="fw-semibold mb-1">Linked Resources</div>
                    <div class="small text-muted"><?php echo e((string) ($resourceCounts['linked_resources'] ?? 0)); ?> resources are external links rather than uploaded files.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="surface-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo $editingSession ? 'Update Session Operations' : 'Select A Session'; ?></h2>
                    <p class="text-muted mb-0">Control publish state, unlock status, and video delivery details for a session.</p>
                </div>
                <?php if ($editingSession): ?>
                    <a href="<?php echo e(base_url('technical_officer/sessions/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>

            <?php if ($sessionErrors !== []): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please fix the following issues:</div>
                    <ul class="mb-0">
                        <?php foreach ($sessionErrors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($editingSession): ?>
                <div class="border rounded-4 p-3 bg-light mb-3">
                    <div class="fw-semibold"><?php echo e($editingSession['session_title']); ?></div>
                    <div class="small text-muted"><?php echo e($editingSession['course_title']); ?> | <?php echo e($editingSession['unit_title']); ?> | <?php echo e($editingSession['topic_title']); ?></div>
                </div>
                <form action="<?php echo e(base_url('actions/technical_officer/save_session_operations.php')); ?>" method="POST" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo e((string) $sessionFormData['id']); ?>">
                    <div class="col-md-6">
                        <label for="video_provider" class="form-label">Video Provider</label>
                        <select class="form-select" id="video_provider" name="video_provider" required>
                            <?php foreach (['youtube', 'vimeo', 'external_link', 'internal_embed'] as $provider): ?>
                                <option value="<?php echo e($provider); ?>" <?php echo $sessionFormData['video_provider'] === $provider ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $provider))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
                        <input type="number" min="1" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo e((string) $sessionFormData['duration_minutes']); ?>">
                    </div>
                    <div class="col-12">
                        <label for="video_url" class="form-label">Video URL</label>
                        <input type="url" class="form-control" id="video_url" name="video_url" value="<?php echo e((string) $sessionFormData['video_url']); ?>">
                    </div>
                    <div class="col-12">
                        <label for="video_embed_url" class="form-label">Video Embed URL</label>
                        <input type="url" class="form-control" id="video_embed_url" name="video_embed_url" value="<?php echo e((string) $sessionFormData['video_embed_url']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Publishing Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <?php foreach (['draft', 'published', 'archived'] as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $sessionFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="is_unlocked" class="form-label">Access State</label>
                        <select class="form-select" id="is_unlocked" name="is_unlocked" required>
                            <option value="0" <?php echo (string) $sessionFormData['is_unlocked'] === '0' || (int) $sessionFormData['is_unlocked'] === 0 ? 'selected' : ''; ?>>Locked</option>
                            <option value="1" <?php echo (int) $sessionFormData['is_unlocked'] === 1 ? 'selected' : ''; ?>>Unlocked</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="unlock_notes" class="form-label">Release Notes</label>
                        <textarea class="form-control" id="unlock_notes" name="unlock_notes" rows="3" placeholder="Optional note for the unlock log or operational history."><?php echo e((string) $sessionFormData['unlock_notes']); ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Session Operations</button>
                        <a href="<?php echo e(base_url('technical_officer/dashboard.php')); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="border rounded-4 p-4 bg-light text-muted">
                    Choose any session from the release queue to update its link details and access controls.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="surface-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h5 mb-1"><?php echo $editingResource ? 'Edit Resource' : 'Attach Resource'; ?></h2>
                    <p class="text-muted mb-0">Add an external learning link or upload a supporting file to any session.</p>
                </div>
                <?php if ($editingResource): ?>
                    <a href="<?php echo e(base_url('technical_officer/sessions/index.php')); ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>

            <?php if ($resourceErrors !== []): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please fix the following issues:</div>
                    <ul class="mb-0">
                        <?php foreach ($resourceErrors as $error): ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo e(base_url('actions/technical_officer/save_resource.php')); ?>" method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" value="<?php echo e((string) $resourceFormData['id']); ?>">
                <div class="col-12">
                    <label for="session_id" class="form-label">Session</label>
                    <select class="form-select" id="session_id" name="session_id" required>
                        <option value="">Select session</option>
                        <?php foreach ($sessionOptions as $sessionOption): ?>
                            <option value="<?php echo e((string) $sessionOption['id']); ?>" <?php echo (string) $resourceFormData['session_id'] === (string) $sessionOption['id'] ? 'selected' : ''; ?>><?php echo e($sessionOption['course_title'] . ' - ' . $sessionOption['topic_title'] . ' - ' . $sessionOption['session_title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <label for="resource_title" class="form-label">Resource Title</label>
                    <input type="text" class="form-control" id="resource_title" name="resource_title" value="<?php echo e((string) $resourceFormData['resource_title']); ?>" required>
                </div>
                <div class="col-md-5">
                    <label for="resource_type" class="form-label">Resource Type</label>
                    <select class="form-select" id="resource_type" name="resource_type" required>
                        <?php foreach (['slide', 'document', 'worksheet', 'link', 'other'] as $resourceType): ?>
                            <option value="<?php echo e($resourceType); ?>" <?php echo $resourceFormData['resource_type'] === $resourceType ? 'selected' : ''; ?>><?php echo e(ucfirst($resourceType)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="external_url" class="form-label">External URL</label>
                    <input type="url" class="form-control" id="external_url" name="external_url" value="<?php echo e((string) $resourceFormData['external_url']); ?>" placeholder="Optional for uploaded files, required for link resources.">
                </div>
                <div class="col-12">
                    <label for="resource_file" class="form-label">Upload File</label>
                    <input type="file" class="form-control" id="resource_file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.jpg,.jpeg,.png">
                    <?php if ($editingResource && ($editingResource['file_name'] ?? '') !== ''): ?>
                        <div class="small text-muted mt-2">Current file: <?php echo e($editingResource['file_name']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="resource_status" class="form-label">Resource Status</label>
                    <select class="form-select" id="resource_status" name="status" required>
                        <?php foreach (['active', 'inactive'] as $resourceStatus): ?>
                            <option value="<?php echo e($resourceStatus); ?>" <?php echo $resourceFormData['status'] === $resourceStatus ? 'selected' : ''; ?>><?php echo e(ucfirst($resourceStatus)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><?php echo $editingResource ? 'Update Resource' : 'Save Resource'; ?></button>
                </div>
            </form>

            <hr class="my-4">

            <h3 class="h6 mb-3">Recent Resources</h3>
            <div class="d-grid gap-3">
                <?php foreach ($recentResources as $resource): ?>
                    <div class="border rounded-4 p-3 bg-light">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-semibold"><?php echo e($resource['resource_title']); ?></div>
                                <div class="small text-muted"><?php echo e($resource['session_title']); ?> | <?php echo e(ucfirst($resource['resource_type'])); ?></div>
                                <div class="small text-muted"><?php echo e($resource['file_name'] ?: $resource['external_url'] ?: 'Stored without file metadata'); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted mb-2"><?php echo e(ucfirst($resource['status'])); ?></div>
                                <a href="<?php echo e(base_url('technical_officer/sessions/index.php?edit_resource=' . (string) $resource['id'])); ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/role_footer.php'; ?>
