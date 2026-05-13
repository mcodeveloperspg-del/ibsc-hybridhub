<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['technical_officer']);

if (!is_post_request()) {
    redirect(base_url('technical_officer/sessions/index.php'));
}

$sessionId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$videoProvider = trim((string) ($_POST['video_provider'] ?? 'youtube'));
$videoUrl = trim((string) ($_POST['video_url'] ?? ''));
$videoEmbedUrl = trim((string) ($_POST['video_embed_url'] ?? ''));
$durationMinutes = (int) ($_POST['duration_minutes'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'draft'));
$isUnlocked = (int) ($_POST['is_unlocked'] ?? 0);
$unlockNotes = trim((string) ($_POST['unlock_notes'] ?? ''));

$allowedProviders = ['youtube', 'vimeo', 'external_link', 'internal_embed'];
$allowedStatuses = ['draft', 'published', 'archived'];
$errors = [];

if ($sessionId < 1) {
    $errors[] = 'A valid session must be selected.';
}
if ($durationMinutes < 0) {
    $errors[] = 'Duration cannot be negative.';
}
if (!in_array($videoProvider, $allowedProviders, true)) {
    $errors[] = 'The selected video provider is invalid.';
}
if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected publishing status is invalid.';
}
if (!in_array($isUnlocked, [0, 1], true)) {
    $errors[] = 'The selected access state is invalid.';
}
if ($isUnlocked === 1 && $status !== 'published') {
    $errors[] = 'Only published sessions can be unlocked for students.';
}
if ($videoUrl !== '' && filter_var($videoUrl, FILTER_VALIDATE_URL) === false) {
    $errors[] = 'The video URL must be a valid link.';
}
if ($videoEmbedUrl !== '' && filter_var($videoEmbedUrl, FILTER_VALIDATE_URL) === false) {
    $errors[] = 'The video embed URL must be a valid link.';
}

$sessionStatement = $pdo->prepare('SELECT id, is_unlocked FROM sessions WHERE id = :id LIMIT 1');
$sessionStatement->execute(['id' => $sessionId]);
$existingSession = $sessionStatement->fetch();

if (!$existingSession) {
    $errors[] = 'The selected session does not exist.';
}

if ($errors !== []) {
    $_SESSION['technical_session_ops_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $sessionId,
            'video_provider' => $videoProvider,
            'video_url' => $videoUrl,
            'video_embed_url' => $videoEmbedUrl,
            'duration_minutes' => $durationMinutes,
            'status' => $status,
            'is_unlocked' => $isUnlocked,
            'unlock_notes' => $unlockNotes,
        ],
    ];
    redirect(base_url('technical_officer/sessions/index.php?edit_session=' . (string) $sessionId));
}

$currentUser = current_user();
$wasUnlocked = (int) ($existingSession['is_unlocked'] ?? 0) === 1;
$isUnlockingNow = !$wasUnlocked && $isUnlocked === 1;
$unlockedAt = $isUnlocked === 1 ? date('Y-m-d H:i:s') : null;
$unlockedBy = $isUnlocked === 1 ? ($currentUser['id'] ?? null) : null;
$storedVideoEmbedUrl = $videoEmbedUrl !== ''
    ? (normalize_video_embed_url($videoProvider, $videoEmbedUrl) ?? $videoEmbedUrl)
    : null;

$updateStatement = $pdo->prepare(
    'UPDATE sessions
     SET video_provider = :video_provider,
         video_url = :video_url,
         video_embed_url = :video_embed_url,
         duration_minutes = :duration_minutes,
         status = :status,
         is_unlocked = :is_unlocked,
         unlocked_at = :unlocked_at,
         unlocked_by = :unlocked_by
     WHERE id = :id'
);
$updateStatement->execute([
    'video_provider' => $videoProvider,
    'video_url' => $videoUrl !== '' ? $videoUrl : null,
    'video_embed_url' => $storedVideoEmbedUrl,
    'duration_minutes' => $durationMinutes > 0 ? $durationMinutes : null,
    'status' => $status,
    'is_unlocked' => $isUnlocked,
    'unlocked_at' => $unlockedAt,
    'unlocked_by' => $unlockedBy,
    'id' => $sessionId,
]);

if ($isUnlockingNow) {
    $logStatement = $pdo->prepare(
        'INSERT INTO unlock_logs (unlock_type, session_id, unlocked_by, notes)
         VALUES (:unlock_type, :session_id, :unlocked_by, :notes)'
    );
    $logStatement->execute([
        'unlock_type' => 'session',
        'session_id' => $sessionId,
        'unlocked_by' => $currentUser['id'] ?? null,
        'notes' => $unlockNotes !== '' ? $unlockNotes : null,
    ]);
}

flash_message($isUnlockingNow ? 'Session operations saved and the session has been unlocked.' : 'Session operations updated successfully.', 'success');
redirect(base_url('technical_officer/sessions/index.php?edit_session=' . (string) $sessionId));
