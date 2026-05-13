<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['student']);
$currentUser = current_user();
$studentId = (int) ($currentUser['id'] ?? 0);
$resourceId = isset($_GET['resource_id']) ? (int) $_GET['resource_id'] : 0;
$today = date('Y-m-d');
$statement = $pdo->prepare("SELECT session_resources.file_name, session_resources.file_path, sessions.id AS session_id FROM session_resources INNER JOIN sessions ON sessions.id = session_resources.session_id INNER JOIN topics ON topics.id = sessions.topic_id INNER JOIN units ON units.id = topics.unit_id INNER JOIN stages ON stages.id = units.stage_id INNER JOIN courses ON courses.id = units.course_id INNER JOIN enrollments ON enrollments.course_id = courses.id AND enrollments.student_id = :student_id AND enrollments.status = 'active' INNER JOIN batches ON batches.id = enrollments.batch_id INNER JOIN batch_stages ON batch_stages.batch_id = batches.id AND batch_stages.stage_number = stages.stage_number WHERE session_resources.id = :resource_id AND sessions.is_unlocked = 1 AND :today BETWEEN batch_stages.start_date AND batch_stages.end_date LIMIT 1");
$statement->execute(['student_id' => $studentId, 'resource_id' => $resourceId, 'today' => $today]);
$resource = $statement->fetch();
if (!$resource || empty($resource['file_path'])) { http_response_code(404); exit('Resource not available.'); }
$fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $resource['file_path']);
if (!is_file($fullPath)) { http_response_code(404); exit('File not found.'); }
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename((string) $resource['file_name']) . '"');
header('Content-Length: ' . (string) filesize($fullPath));
readfile($fullPath);
exit;
