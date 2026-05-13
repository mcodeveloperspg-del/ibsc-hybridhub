<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
$formState = consume_form_state('session_form_state', ['errors' => [], 'data' => []]);
$errors = $formState['errors'] ?? [];
$oldData = $formState['data'] ?? [];

$editingSessionId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$requestedTopicId = isset($_GET['topic_id']) ? (int) $_GET['topic_id'] : 0;
$returnBatchId = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : (int) ($oldData['return_batch_id'] ?? 0);
$returnCourseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : (int) ($oldData['return_course_id'] ?? 0);
$returnStageId = isset($_GET['stage_id']) ? (int) $_GET['stage_id'] : (int) ($oldData['return_stage_id'] ?? 0);
$returnUnitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : (int) ($oldData['return_unit_id'] ?? 0);
$editingSession = null;
if ($editingSessionId > 0) {
    $editStatement = $pdo->prepare('SELECT * FROM sessions WHERE id = :id LIMIT 1');
    $editStatement->execute(['id' => $editingSessionId]);
    $editingSession = $editStatement->fetch();
    if (!$editingSession) {
        flash_message('The selected session could not be found.', 'warning');
        redirect(base_url('admin/sessions/index.php'));
    }
}

$batches = $pdo->query("SELECT id, batch_name, batch_year, status FROM batches ORDER BY batch_year DESC, batch_number ASC")->fetchAll();
$sessionBatchId = (int) ($oldData['batch_id'] ?? ($editingSession['batch_id'] ?? ($returnBatchId > 0 ? $returnBatchId : 0)));

$topicsSql =
    "SELECT topics.id, topics.topic_title, topics.unit_id,
            units.unit_title,
            stages.id AS stage_id, stages.stage_number, stages.title AS stage_title,
            courses.id AS course_id, courses.title AS course_title
     FROM topics
     INNER JOIN units ON units.id = topics.unit_id
     INNER JOIN stages ON stages.id = units.stage_id
     INNER JOIN courses ON courses.id = units.course_id";
$topicsParams = [];
if ($sessionBatchId > 0) {
    $topicsSql .= " INNER JOIN batch_courses ON batch_courses.course_id = courses.id AND batch_courses.batch_id = :batch_id";
    $topicsParams['batch_id'] = $sessionBatchId;
}
$topicsSql .= " ORDER BY courses.title ASC, stages.stage_number ASC, units.sort_order ASC, topics.sort_order ASC";
$topicsStatement = $pdo->prepare($topicsSql);
$topicsStatement->execute($topicsParams);
$topics = $topicsStatement->fetchAll();

$sessionFormData = [
    'id' => $oldData['id'] ?? ($editingSession['id'] ?? ''),
    'batch_id' => $sessionBatchId,
    'topic_id' => $oldData['topic_id'] ?? ($editingSession['topic_id'] ?? ($requestedTopicId > 0 ? $requestedTopicId : '')),
    'fallback_source_session_id' => $oldData['fallback_source_session_id'] ?? ($editingSession['fallback_source_session_id'] ?? ''),
    'recording_source' => $oldData['recording_source'] ?? ($editingSession ? ($editingSession['recording_source'] ?? (((int) ($editingSession['fallback_source_session_id'] ?? 0) > 0) ? 'previous_session' : 'current_batch')) : ''),
    'session_title' => $oldData['session_title'] ?? ($editingSession['session_title'] ?? ''),
    'session_summary' => $oldData['session_summary'] ?? ($editingSession['session_summary'] ?? ''),
    'session_date' => $oldData['session_date'] ?? ($editingSession['session_date'] ?? ''),
    'video_provider' => $oldData['video_provider'] ?? ($editingSession['video_provider'] ?? 'youtube'),
    'video_url' => $oldData['video_url'] ?? ($editingSession['video_url'] ?? ''),
    'video_embed_url' => $oldData['video_embed_url'] ?? ($editingSession['video_embed_url'] ?? ''),
    'duration_minutes' => $oldData['duration_minutes'] ?? ($editingSession['duration_minutes'] ?? ''),
    'session_type' => $oldData['session_type'] ?? ($editingSession['session_type'] ?? 'regular'),
    'sort_order' => $oldData['sort_order'] ?? ($editingSession['sort_order'] ?? 1),
    'is_unlocked' => $oldData['is_unlocked'] ?? ($editingSession['is_unlocked'] ?? 0),
    'status' => $oldData['status'] ?? ($editingSession['status'] ?? 'draft'),
];

$selectedTopic = null;
foreach ($topics as $topic) {
    if ((string) $topic['id'] === (string) $sessionFormData['topic_id']) {
        $selectedTopic = $topic;
        break;
    }
}

$courseFilters = [];
$stageFilters = [];
$unitFilters = [];
foreach ($topics as $topic) {
    $courseFilters[(string) $topic['course_id']] = $topic['course_title'];
    $stageFilters[(string) $topic['stage_id']] = [
        'course_id' => $topic['course_id'],
        'label' => 'Stage ' . $topic['stage_number'],
        'number' => (int) $topic['stage_number'],
    ];
    $unitFilters[(string) $topic['unit_id']] = [
        'title' => $topic['unit_title'],
        'course_id' => $topic['course_id'],
        'stage_id' => $topic['stage_id'],
    ];
}

asort($courseFilters);
uasort($stageFilters, static function (array $first, array $second): int {
    if ((int) $first['course_id'] === (int) $second['course_id']) {
        return $first['number'] <=> $second['number'];
    }
    return (int) $first['course_id'] <=> (int) $second['course_id'];
});
uasort($unitFilters, static function (array $first, array $second): int {
    return strcmp($first['title'], $second['title']);
});

$selectedCourseFilter = (string) ($oldData['filter_course_id'] ?? ($selectedTopic['course_id'] ?? ($returnCourseId > 0 ? $returnCourseId : '')));
$selectedStageFilter = (string) ($oldData['filter_stage_id'] ?? ($selectedTopic['stage_id'] ?? ($returnStageId > 0 ? $returnStageId : '')));
$selectedUnitFilter = (string) ($oldData['filter_unit_id'] ?? ($selectedTopic['unit_id'] ?? ($returnUnitId > 0 ? $returnUnitId : '')));

$fallbackCandidates = [];
if ($sessionFormData['topic_id'] !== '' && $sessionBatchId > 0) {
    $fallbackSql =
        "SELECT sessions.id, sessions.session_title, sessions.session_date, sessions.status,
                batches.batch_name, batches.batch_year
         FROM sessions
         LEFT JOIN batches ON batches.id = sessions.batch_id
         WHERE sessions.topic_id = :topic_id
           AND (sessions.batch_id IS NULL OR sessions.batch_id != :batch_id)";
    $fallbackParams = [
        'topic_id' => (int) $sessionFormData['topic_id'],
        'batch_id' => $sessionBatchId,
    ];
    if ($editingSessionId > 0) {
        $fallbackSql .= ' AND sessions.id != :session_id';
        $fallbackParams['session_id'] = $editingSessionId;
    }
    $fallbackSql .= ' ORDER BY sessions.session_date DESC, sessions.id DESC';
    $fallbackStatement = $pdo->prepare($fallbackSql);
    $fallbackStatement->execute($fallbackParams);
    $fallbackCandidates = $fallbackStatement->fetchAll();
}

$returnQuery = http_build_query(array_filter([
    'batch_id' => $returnBatchId,
    'course_id' => $returnCourseId,
    'stage_id' => $returnStageId,
    'unit_id' => $returnUnitId,
], static fn(int $value): bool => $value > 0));
$returnUrl = base_url('admin/sessions/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));

$pageTitle = ($editingSession ? 'Edit Session' : 'Create Session') . ' - ' . APP_NAME;
$pageHeading = $editingSession ? 'Edit Session' : 'Create Session';
$pageDescription = 'Create a batch-specific session for a topic, and only attach older topic content as an admin-approved fallback when the lecturer cannot record a new one.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="surface-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1"><?php echo $editingSession ? 'Update Batch Session' : 'New Batch Session'; ?></h2>
            <p class="text-muted mb-0">Sessions now belong to a specific batch and topic. Older topic sessions can only be linked as fallback delivery content.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($editingSession): ?>
                <a href="<?php echo e(base_url('admin/sessions/create.php')); ?>" class="btn btn-outline-secondary">Clear Edit</a>
            <?php endif; ?>
            <a href="<?php echo e($returnUrl); ?>" class="btn btn-outline-secondary">Back to Sessions</a>
        </div>
    </div>

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

    <form action="<?php echo e(base_url('actions/admin/save_session.php')); ?>" method="POST" class="row g-3">
        <input type="hidden" name="id" value="<?php echo e((string) $sessionFormData['id']); ?>">
        <input type="hidden" name="return_batch_id" value="<?php echo e((string) $returnBatchId); ?>">
        <input type="hidden" name="return_course_id" value="<?php echo e((string) $returnCourseId); ?>">
        <input type="hidden" name="return_stage_id" value="<?php echo e((string) $returnStageId); ?>">
        <input type="hidden" name="return_unit_id" value="<?php echo e((string) $returnUnitId); ?>">
        <div class="col-md-6">
            <label for="batch_id" class="form-label">Batch</label>
            <select class="form-select" id="batch_id" name="batch_id" required>
                <option value="">Select batch</option>
                <?php foreach ($batches as $batch): ?>
                    <option value="<?php echo e((string) $batch['id']); ?>" <?php echo (string) $sessionFormData['batch_id'] === (string) $batch['id'] ? 'selected' : ''; ?>><?php echo e($batch['batch_name'] . ' (' . $batch['batch_year'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Each topic now needs its own session record for each batch.</div>
        </div>
        <div class="col-md-6">
            <label for="fallback_source_session_id" class="form-label">Fallback Session Source</label>
            <select class="form-select" id="fallback_source_session_id" name="fallback_source_session_id">
                <option value="">No fallback source</option>
                <?php foreach ($fallbackCandidates as $candidate): ?>
                    <option value="<?php echo e((string) $candidate['id']); ?>" <?php echo (string) $sessionFormData['fallback_source_session_id'] === (string) $candidate['id'] ? 'selected' : ''; ?>><?php echo e($candidate['session_title']); ?><?php echo !empty($candidate['batch_name']) ? ' - ' . e($candidate['batch_name'] . ' (' . $candidate['batch_year'] . ')') : ' - Legacy topic session'; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Use this only when a lecturer cannot deliver a new recording for the batch and the admin approves an older topic session for delivery.</div>
        </div>
        <div class="col-md-4">
            <label for="filter_course_id" class="form-label">Search Course</label>
            <select class="form-select" id="filter_course_id" name="filter_course_id">
                <option value="">All courses</option>
                <?php foreach ($courseFilters as $courseId => $courseTitle): ?>
                    <option value="<?php echo e((string) $courseId); ?>" <?php echo $selectedCourseFilter === (string) $courseId ? 'selected' : ''; ?>><?php echo e($courseTitle); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="filter_stage_id" class="form-label">Search Stage</label>
            <select class="form-select" id="filter_stage_id" name="filter_stage_id">
                <option value="">All stages</option>
                <?php foreach ($stageFilters as $stageId => $stage): ?>
                    <option value="<?php echo e((string) $stageId); ?>" data-course-id="<?php echo e((string) $stage['course_id']); ?>" <?php echo $selectedStageFilter === (string) $stageId ? 'selected' : ''; ?>><?php echo e($stage['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="filter_unit_id" class="form-label">Search Unit</label>
            <select class="form-select" id="filter_unit_id" name="filter_unit_id">
                <option value="">All units</option>
                <?php foreach ($unitFilters as $unitId => $unit): ?>
                    <option value="<?php echo e((string) $unitId); ?>" data-course-id="<?php echo e((string) $unit['course_id']); ?>" data-stage-id="<?php echo e((string) $unit['stage_id']); ?>" <?php echo $selectedUnitFilter === (string) $unitId ? 'selected' : ''; ?>><?php echo e($unit['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label for="topic_id" class="form-label">Topic</label>
            <select class="form-select" id="topic_id" name="topic_id" required>
                <option value="">Select topic</option>
                <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo e((string) $topic['id']); ?>" data-course-id="<?php echo e((string) $topic['course_id']); ?>" data-stage-id="<?php echo e((string) $topic['stage_id']); ?>" data-unit-id="<?php echo e((string) $topic['unit_id']); ?>" <?php echo (string) $sessionFormData['topic_id'] === (string) $topic['id'] ? 'selected' : ''; ?>><?php echo e($topic['topic_title']); ?></option>
                <?php endforeach; ?>
            </select>
            <div id="topicFilterHelp" class="form-text">Select a course, stage, and unit to quickly filter the topic list inside the chosen batch.</div>
        </div>
        <div class="col-12">
            <label for="session_title" class="form-label">Session Title</label>
            <input type="text" class="form-control" id="session_title" name="session_title" value="<?php echo e((string) $sessionFormData['session_title']); ?>" required>
        </div>
        <div class="col-12">
            <label for="session_summary" class="form-label">Session Summary</label>
            <textarea class="form-control" id="session_summary" name="session_summary" rows="3"><?php echo e((string) $sessionFormData['session_summary']); ?></textarea>
        </div>
        <div class="col-md-6">
            <label for="session_date" class="form-label">Session Date</label>
            <input type="date" class="form-control" id="session_date" name="session_date" value="<?php echo e((string) $sessionFormData['session_date']); ?>">
        </div>
        <div class="col-md-6">
            <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
            <input type="number" min="1" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo e((string) $sessionFormData['duration_minutes']); ?>">
        </div>
        <div class="col-md-6">
            <label for="video_provider" class="form-label">Video Provider</label>
            <select class="form-select" id="video_provider" name="video_provider" required>
                <?php foreach (['youtube', 'vimeo', 'external_link', 'internal_embed'] as $provider): ?>
                    <option value="<?php echo e($provider); ?>" <?php echo $sessionFormData['video_provider'] === $provider ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $provider))); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="recording_source" class="form-label">Recording Source</label>
            <select class="form-select" id="recording_source" name="recording_source" required>
                <option value="">Select recording source</option>
                <option value="current_batch" <?php echo $sessionFormData['recording_source'] === 'current_batch' ? 'selected' : ''; ?>>Current batch recording</option>
                <option value="previous_session" <?php echo $sessionFormData['recording_source'] === 'previous_session' ? 'selected' : ''; ?>>Previous session recording</option>
            </select>
            <div id="recordingSourceHelp" class="form-text">Choose previous session when using an already edited recording from an earlier batch, even if there is no fallback session record to link.</div>
        </div>
        <div class="col-md-6">
            <label for="session_type" class="form-label">Session Type</label>
            <select class="form-select" id="session_type" name="session_type" required>
                <?php foreach (['regular', 'replacement', 'revision'] as $type): ?>
                    <option value="<?php echo e($type); ?>" <?php echo $sessionFormData['session_type'] === $type ? 'selected' : ''; ?>><?php echo e(ucfirst($type)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label for="video_url" class="form-label">Video URL</label>
            <input type="url" class="form-control" id="video_url" name="video_url" value="<?php echo e((string) $sessionFormData['video_url']); ?>">
        </div>
        <div class="col-12">
            <label for="video_embed_url" class="form-label">Video Embed URL</label>
            <input type="url" class="form-control" id="video_embed_url" name="video_embed_url" value="<?php echo e((string) $sessionFormData['video_embed_url']); ?>">
        </div>
        <div class="col-md-4">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input type="number" min="1" class="form-control" id="sort_order" name="sort_order" value="<?php echo e((string) $sessionFormData['sort_order']); ?>" required>
        </div>
        <div class="col-md-4">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['draft', 'published', 'archived'] as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $sessionFormData['status'] === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="is_unlocked" class="form-label">Access State</label>
            <select class="form-select" id="is_unlocked" name="is_unlocked" required>
                <option value="0" <?php echo (string) $sessionFormData['is_unlocked'] === '0' || (int) $sessionFormData['is_unlocked'] === 0 ? 'selected' : ''; ?>>Locked</option>
                <option value="1" <?php echo (int) $sessionFormData['is_unlocked'] === 1 ? 'selected' : ''; ?>>Unlocked</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo $editingSession ? 'Update Session' : 'Create Session'; ?></button>
            <a href="<?php echo e($returnUrl); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
(() => {
    const fallbackSelect = document.getElementById('fallback_source_session_id');
    const recordingSourceSelect = document.getElementById('recording_source');
    const recordingSourceHelp = document.getElementById('recordingSourceHelp');

    if (!fallbackSelect || !recordingSourceSelect || !recordingSourceHelp) {
        return;
    }

    const updateRecordingSource = () => {
        if (fallbackSelect.value !== '') {
            recordingSourceSelect.value = 'previous_session';
            recordingSourceHelp.textContent = 'A linked fallback session always uses a previous-session recording.';
            return;
        }

        if (recordingSourceSelect.value === 'previous_session') {
            recordingSourceHelp.textContent = 'Use this when the video URL points to an already edited recording from an earlier batch.';
        } else {
            recordingSourceHelp.textContent = 'Use this when the video URL points to the recording made for this batch.';
        }
    };

    fallbackSelect.addEventListener('change', updateRecordingSource);
    recordingSourceSelect.addEventListener('change', updateRecordingSource);
    updateRecordingSource();
})();

(() => {
    const courseSelect = document.getElementById('filter_course_id');
    const stageSelect = document.getElementById('filter_stage_id');
    const unitSelect = document.getElementById('filter_unit_id');
    const topicSelect = document.getElementById('topic_id');
    const topicFilterHelp = document.getElementById('topicFilterHelp');

    if (!courseSelect || !stageSelect || !unitSelect || !topicSelect || !topicFilterHelp) {
        return;
    }

    const stageOptions = Array.from(stageSelect.options).slice(1);
    const unitOptions = Array.from(unitSelect.options).slice(1);
    const topicOptions = Array.from(topicSelect.options).slice(1);

    const updateStageOptions = () => {
        const selectedCourseId = courseSelect.value;
        stageOptions.forEach((option) => {
            const matchesCourse = selectedCourseId === '' || option.dataset.courseId === selectedCourseId;
            option.hidden = !matchesCourse;
            option.disabled = !matchesCourse;
        });
        if (stageSelect.value !== '' && stageSelect.selectedOptions.length > 0 && stageSelect.selectedOptions[0].disabled) {
            stageSelect.value = '';
        }
    };

    const updateUnitOptions = () => {
        const selectedCourseId = courseSelect.value;
        const selectedStageId = stageSelect.value;
        unitOptions.forEach((option) => {
            const matchesCourse = selectedCourseId === '' || option.dataset.courseId === selectedCourseId;
            const matchesStage = selectedStageId === '' || option.dataset.stageId === selectedStageId;
            const isVisible = matchesCourse && matchesStage;
            option.hidden = !isVisible;
            option.disabled = !isVisible;
        });
        if (unitSelect.value !== '' && unitSelect.selectedOptions.length > 0 && unitSelect.selectedOptions[0].disabled) {
            unitSelect.value = '';
        }
    };

    const updateTopicOptions = () => {
        const selectedCourseId = courseSelect.value;
        const selectedStageId = stageSelect.value;
        const selectedUnitId = unitSelect.value;
        let visibleTopicCount = 0;
        topicOptions.forEach((option) => {
            const matchesCourse = selectedCourseId === '' || option.dataset.courseId === selectedCourseId;
            const matchesStage = selectedStageId === '' || option.dataset.stageId === selectedStageId;
            const matchesUnit = selectedUnitId === '' || option.dataset.unitId === selectedUnitId;
            const isVisible = matchesCourse && matchesStage && matchesUnit;
            option.hidden = !isVisible;
            option.disabled = !isVisible;
            if (isVisible) {
                visibleTopicCount += 1;
            }
        });
        if (topicSelect.value !== '' && topicSelect.selectedOptions.length > 0 && topicSelect.selectedOptions[0].disabled) {
            topicSelect.value = '';
        }
        if (selectedCourseId === '' && selectedStageId === '' && selectedUnitId === '') {
            topicFilterHelp.textContent = 'Select a course, stage, and unit to quickly filter the topic list inside the chosen batch.';
        } else if (visibleTopicCount === 0) {
            topicFilterHelp.textContent = 'No topics match the selected filters for this batch.';
        } else {
            topicFilterHelp.textContent = visibleTopicCount + ' topic' + (visibleTopicCount === 1 ? '' : 's') + ' available for the current filters.';
        }
    };

    const updateFilters = () => {
        updateStageOptions();
        updateUnitOptions();
        updateTopicOptions();
    };

    courseSelect.addEventListener('change', updateFilters);
    stageSelect.addEventListener('change', () => {
        updateUnitOptions();
        updateTopicOptions();
    });
    unitSelect.addEventListener('change', updateTopicOptions);
    updateFilters();
})();
</script>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
