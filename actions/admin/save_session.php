<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);
if (!is_post_request()) {
    redirect(base_url('admin/sessions/index.php'));
}

$sessionId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$batchId = (int) ($_POST['batch_id'] ?? 0);
$topicId = (int) ($_POST['topic_id'] ?? 0);
$fallbackSourceSessionId = (int) ($_POST['fallback_source_session_id'] ?? 0);
$recordingSource = trim((string) ($_POST['recording_source'] ?? ''));
$sessionTitle = trim((string) ($_POST['session_title'] ?? ''));
$sessionSummary = trim((string) ($_POST['session_summary'] ?? ''));
$sessionDate = trim((string) ($_POST['session_date'] ?? ''));
$videoProvider = trim((string) ($_POST['video_provider'] ?? 'youtube'));
$videoUrl = trim((string) ($_POST['video_url'] ?? ''));
$videoEmbedUrl = trim((string) ($_POST['video_embed_url'] ?? ''));
$durationMinutes = (int) ($_POST['duration_minutes'] ?? 0);
$sessionType = trim((string) ($_POST['session_type'] ?? 'regular'));
$sortOrder = (int) ($_POST['sort_order'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'draft'));
$isUnlocked = (int) ($_POST['is_unlocked'] ?? 0);
$returnBatchId = (int) ($_POST['return_batch_id'] ?? 0);
$returnCourseId = (int) ($_POST['return_course_id'] ?? 0);
$returnStageId = (int) ($_POST['return_stage_id'] ?? 0);
$returnUnitId = (int) ($_POST['return_unit_id'] ?? 0);
$returnQuery = http_build_query(array_filter([
    'batch_id' => $returnBatchId,
    'course_id' => $returnCourseId,
    'stage_id' => $returnStageId,
    'unit_id' => $returnUnitId,
], static fn(int $value): bool => $value > 0));
$returnUrl = base_url('admin/sessions/index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));

$allowedProviders = ['youtube', 'vimeo', 'external_link', 'internal_embed'];
$allowedRecordingSources = ['current_batch', 'previous_session'];
$allowedTypes = ['regular', 'replacement', 'revision'];
$allowedStatuses = ['draft', 'published', 'archived'];
$errors = [];
if ($batchId < 1) $errors[] = 'A batch must be selected.';
if ($topicId < 1) $errors[] = 'A topic must be selected.';
if ($sessionTitle === '') $errors[] = 'Session title is required.';
if ($sortOrder < 1) $errors[] = 'Sort order must be at least 1.';
if ($durationMinutes < 0) $errors[] = 'Duration cannot be negative.';
if (!in_array($videoProvider, $allowedProviders, true)) $errors[] = 'The selected video provider is invalid.';
if (!in_array($sessionType, $allowedTypes, true)) $errors[] = 'The selected session type is invalid.';
if (!in_array($status, $allowedStatuses, true)) $errors[] = 'The selected status is invalid.';
if (!in_array($isUnlocked, [0, 1], true)) $errors[] = 'The selected access state is invalid.';

$batchStatement = $pdo->prepare('SELECT id FROM batches WHERE id = :batch_id LIMIT 1');
$batchStatement->execute(['batch_id' => $batchId]);
if (!$batchStatement->fetch()) $errors[] = 'The selected batch does not exist.';

$topicStatement = $pdo->prepare(
    'SELECT topics.id
     FROM topics
     INNER JOIN units ON units.id = topics.unit_id
     INNER JOIN courses ON courses.id = units.course_id
     INNER JOIN batch_courses ON batch_courses.course_id = courses.id AND batch_courses.batch_id = :batch_id
     WHERE topics.id = :topic_id
     LIMIT 1'
);
$topicStatement->execute(['batch_id' => $batchId, 'topic_id' => $topicId]);
if (!$topicStatement->fetch()) $errors[] = 'The selected topic is not part of the chosen batch course structure.';

$duplicateSql = 'SELECT id FROM sessions WHERE batch_id = :batch_id AND topic_id = :topic_id AND session_title = :session_title';
$params = ['batch_id' => $batchId, 'topic_id' => $topicId, 'session_title' => $sessionTitle];
if ($sessionId > 0) {
    $duplicateSql .= ' AND id != :id';
    $params['id'] = $sessionId;
}
$duplicateStatement = $pdo->prepare($duplicateSql);
$duplicateStatement->execute($params);
if ($duplicateStatement->fetch()) $errors[] = 'A session with that title already exists for this batch and topic.';

if ($fallbackSourceSessionId > 0) {
    $recordingSource = 'previous_session';
    $fallbackStatement = $pdo->prepare(
        'SELECT id
         FROM sessions
         WHERE id = :id
           AND topic_id = :topic_id
           AND (batch_id IS NULL OR batch_id != :batch_id)
         LIMIT 1'
    );
    $fallbackStatement->execute([
        'id' => $fallbackSourceSessionId,
        'topic_id' => $topicId,
        'batch_id' => $batchId,
    ]);
    if (!$fallbackStatement->fetch()) {
        $errors[] = 'The selected fallback session must be an older session under the same topic and from a different batch or legacy topic record.';
    }
    if ($sessionId > 0 && $fallbackSourceSessionId === $sessionId) {
        $errors[] = 'A session cannot be its own fallback source.';
    }
}

if (!in_array($recordingSource, $allowedRecordingSources, true)) {
    $errors[] = ($videoUrl !== '' || $videoEmbedUrl !== '')
        ? 'Choose the recording source before attaching a video link.'
        : 'Please specify whether the recording is from the current batch or a previous session.';
}

if ($errors !== []) {
    $_SESSION['session_form_state'] = ['errors' => $errors, 'data' => ['id' => $sessionId,'batch_id' => $batchId,'topic_id' => $topicId,'fallback_source_session_id' => $fallbackSourceSessionId > 0 ? $fallbackSourceSessionId : '','recording_source' => $recordingSource,'filter_course_id' => trim((string) ($_POST['filter_course_id'] ?? '')),'filter_stage_id' => trim((string) ($_POST['filter_stage_id'] ?? '')),'filter_unit_id' => trim((string) ($_POST['filter_unit_id'] ?? '')),'return_batch_id' => $returnBatchId,'return_course_id' => $returnCourseId,'return_stage_id' => $returnStageId,'return_unit_id' => $returnUnitId,'session_title' => $sessionTitle,'session_summary' => $sessionSummary,'session_date' => $sessionDate,'video_provider' => $videoProvider,'video_url' => $videoUrl,'video_embed_url' => $videoEmbedUrl,'duration_minutes' => $durationMinutes,'session_type' => $sessionType,'sort_order' => $sortOrder,'status' => $status,'is_unlocked' => $isUnlocked]];
    $redirectParams = [];
    if ($sessionId > 0) $redirectParams['edit'] = $sessionId;
    if ($returnBatchId > 0) $redirectParams['batch_id'] = $returnBatchId;
    if ($returnCourseId > 0) $redirectParams['course_id'] = $returnCourseId;
    if ($returnStageId > 0) $redirectParams['stage_id'] = $returnStageId;
    if ($returnUnitId > 0) $redirectParams['unit_id'] = $returnUnitId;
    if ($sessionId < 1 && $topicId > 0) $redirectParams['topic_id'] = $topicId;
    $redirectUrl = base_url('admin/sessions/create.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : ''));
    redirect($redirectUrl);
}

$currentUser = current_user();
$unlockTimestamp = $isUnlocked === 1 ? date('Y-m-d H:i:s') : null;
$unlockedBy = $isUnlocked === 1 ? ($currentUser['id'] ?? null) : null;
$storedVideoEmbedUrl = $videoEmbedUrl !== ''
    ? (normalize_video_embed_url($videoProvider, $videoEmbedUrl) ?? $videoEmbedUrl)
    : null;

if ($sessionId > 0) {
    $updateStatement = $pdo->prepare('UPDATE sessions SET batch_id = :batch_id, topic_id = :topic_id, fallback_source_session_id = :fallback_source_session_id, recording_source = :recording_source, session_title = :session_title, session_summary = :session_summary, session_date = :session_date, video_provider = :video_provider, video_url = :video_url, video_embed_url = :video_embed_url, duration_minutes = :duration_minutes, session_type = :session_type, sort_order = :sort_order, is_unlocked = :is_unlocked, unlocked_at = :unlocked_at, unlocked_by = :unlocked_by, status = :status WHERE id = :id');
    $updateStatement->execute(['batch_id' => $batchId,'topic_id' => $topicId,'fallback_source_session_id' => $fallbackSourceSessionId > 0 ? $fallbackSourceSessionId : null,'recording_source' => $recordingSource,'session_title' => $sessionTitle,'session_summary' => $sessionSummary !== '' ? $sessionSummary : null,'session_date' => $sessionDate !== '' ? $sessionDate : null,'video_provider' => $videoProvider,'video_url' => $videoUrl !== '' ? $videoUrl : null,'video_embed_url' => $storedVideoEmbedUrl,'duration_minutes' => $durationMinutes > 0 ? $durationMinutes : null,'session_type' => $sessionType,'sort_order' => $sortOrder,'is_unlocked' => $isUnlocked,'unlocked_at' => $unlockTimestamp,'unlocked_by' => $unlockedBy,'status' => $status,'id' => $sessionId]);
    flash_message('Batch session updated successfully.', 'success');
} else {
    $insertStatement = $pdo->prepare('INSERT INTO sessions (batch_id, topic_id, fallback_source_session_id, recording_source, session_title, session_summary, session_date, video_provider, video_url, video_embed_url, duration_minutes, session_type, sort_order, is_unlocked, unlocked_at, unlocked_by, status, created_by) VALUES (:batch_id, :topic_id, :fallback_source_session_id, :recording_source, :session_title, :session_summary, :session_date, :video_provider, :video_url, :video_embed_url, :duration_minutes, :session_type, :sort_order, :is_unlocked, :unlocked_at, :unlocked_by, :status, :created_by)');
    $insertStatement->execute(['batch_id' => $batchId,'topic_id' => $topicId,'fallback_source_session_id' => $fallbackSourceSessionId > 0 ? $fallbackSourceSessionId : null,'recording_source' => $recordingSource,'session_title' => $sessionTitle,'session_summary' => $sessionSummary !== '' ? $sessionSummary : null,'session_date' => $sessionDate !== '' ? $sessionDate : null,'video_provider' => $videoProvider,'video_url' => $videoUrl !== '' ? $videoUrl : null,'video_embed_url' => $storedVideoEmbedUrl,'duration_minutes' => $durationMinutes > 0 ? $durationMinutes : null,'session_type' => $sessionType,'sort_order' => $sortOrder,'is_unlocked' => $isUnlocked,'unlocked_at' => $unlockTimestamp,'unlocked_by' => $unlockedBy,'status' => $status,'created_by' => $currentUser['id'] ?? null]);
    flash_message('Batch session created successfully.', 'success');
}

redirect($returnUrl);
