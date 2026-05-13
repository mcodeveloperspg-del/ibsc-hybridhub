<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['student']);
if (!is_post_request()) redirect(base_url('student/dashboard.php'));
$currentUser = current_user();
$studentId = (int) ($currentUser['id'] ?? 0);
$sessionId = (int) ($_POST['session_id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$progressPercent = (int) ($_POST['progress_percent'] ?? 100);
if (!in_array($progressPercent, [70, 100], true) || $sessionId < 1) { flash_message('Invalid progress update request.', 'warning'); redirect(base_url('student/dashboard.php')); }
$today = date('Y-m-d');
$ownershipStatement = $pdo->prepare("SELECT sessions.id FROM enrollments INNER JOIN courses ON courses.id = enrollments.course_id INNER JOIN batches ON batches.id = enrollments.batch_id INNER JOIN units ON units.course_id = courses.id INNER JOIN stages ON stages.id = units.stage_id INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number INNER JOIN topics ON topics.unit_id = units.id INNER JOIN sessions ON sessions.topic_id = topics.id WHERE enrollments.student_id = :student_id AND enrollments.status = 'active' AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date AND sessions.id = :session_id AND sessions.is_unlocked = 1 LIMIT 1");
$ownershipStatement->execute(['student_id' => $studentId, 'session_id' => $sessionId, 'today' => $today]);
if (!$ownershipStatement->fetch()) { flash_message('That session is not available for progress tracking.', 'warning'); redirect(base_url('student/dashboard.php')); }
$upsert = $pdo->prepare("INSERT INTO watched_sessions (student_id, session_id, progress_percent, watched_at) VALUES (:student_id, :session_id, :progress_percent, NOW()) ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), watched_at = NOW()");
$upsert->execute(['student_id' => $studentId, 'session_id' => $sessionId, 'progress_percent' => $progressPercent]);
flash_message($progressPercent === 100 ? 'Session marked as watched.' : 'Session progress updated.', 'success');
redirect(base_url('student/sessions/view.php?session_id=' . (string) $sessionId));
