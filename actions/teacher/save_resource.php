<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['teacher']);

function teacher_resource_redirect_url(string $returnTo, int $sessionId, int $batchId, int $resourceId = 0): string
{
    if ($returnTo === 'session_view' && $sessionId > 0 && $batchId > 0) {
        return base_url('teacher/sessions/view.php?session_id=' . (string) $sessionId . '&batch_id=' . (string) $batchId);
    }

    $redirectUrl = base_url('teacher/resources/index.php');
    if ($resourceId > 0) {
        $redirectUrl .= '?edit=' . (string) $resourceId;
    }

    return $redirectUrl;
}

if (!is_post_request()) {
    redirect(base_url('teacher/units/index.php'));
}

function teacher_resource_redirect(array $errors, array $data, string $returnTo, int $sessionId, int $batchId, int $resourceId = 0): void
{
    $_SESSION['teacher_resource_form_state'] = ['errors' => $errors, 'data' => $data];
    redirect(teacher_resource_redirect_url($returnTo, $sessionId, $batchId, $resourceId));
}

$currentUser = current_user();
$teacherId = (int) ($currentUser['id'] ?? 0);
$hasUnitAssignments = teacher_has_unit_assignments($pdo, $teacherId);
$resourceId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sessionId = (int) ($_POST['session_id'] ?? 0);
$batchId = (int) ($_POST['batch_id'] ?? 0);
$returnTo = trim((string) ($_POST['return_to'] ?? 'resources'));
$resourceTitle = trim((string) ($_POST['resource_title'] ?? ''));
$resourceType = trim((string) ($_POST['resource_type'] ?? 'document'));
$externalUrl = trim((string) ($_POST['external_url'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));

$allowedTypes = ['slide', 'document', 'worksheet', 'link', 'other'];
$allowedStatuses = ['active', 'inactive'];
$allowedReturnTargets = ['resources', 'session_view'];
$errors = [];

if ($sessionId < 1) $errors[] = 'A valid session must be selected.';
if ($resourceTitle === '') $errors[] = 'Resource title is required.';
if (!in_array($resourceType, $allowedTypes, true)) $errors[] = 'The selected resource type is invalid.';
if (!in_array($status, $allowedStatuses, true)) $errors[] = 'The selected resource status is invalid.';
if (!in_array($returnTo, $allowedReturnTargets, true)) $returnTo = 'resources';
if ($externalUrl !== '' && filter_var($externalUrl, FILTER_VALIDATE_URL) === false) $errors[] = 'The external URL must be a valid link.';
if ($resourceType === 'link' && $externalUrl === '') $errors[] = 'A link resource requires an external URL.';

if ($hasUnitAssignments) {
    $sessionOwnershipStatement = $pdo->prepare(
        "SELECT sessions.id
         FROM lecturer_unit_assignments
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN topics ON topics.unit_id = units.id
         INNER JOIN sessions ON sessions.topic_id = topics.id AND sessions.batch_id = lecturer_unit_assignments.batch_id
         WHERE lecturer_unit_assignments.lecturer_id = :teacher_id
           AND lecturer_unit_assignments.status = 'active'
           AND sessions.id = :session_id
         LIMIT 1"
    );
    $sessionOwnershipStatement->execute(['teacher_id' => $teacherId, 'session_id' => $sessionId]);
} else {
    $sessionOwnershipStatement = $pdo->prepare(
        "SELECT sessions.id
         FROM teacher_course_assignments
         INNER JOIN courses ON courses.id = teacher_course_assignments.course_id
         INNER JOIN units ON units.course_id = courses.id
         INNER JOIN topics ON topics.unit_id = units.id
         INNER JOIN sessions ON sessions.topic_id = topics.id AND (teacher_course_assignments.batch_id IS NULL OR sessions.batch_id = teacher_course_assignments.batch_id)
         WHERE teacher_course_assignments.teacher_id = :teacher_id
           AND teacher_course_assignments.status = 'active'
           AND sessions.id = :session_id
         LIMIT 1"
    );
    $sessionOwnershipStatement->execute(['teacher_id' => $teacherId, 'session_id' => $sessionId]);
}
if (!$sessionOwnershipStatement->fetch()) $errors[] = 'The selected session is not available to this lecturer.';

$existingResource = null;
if ($resourceId > 0) {
    $resourceStatement = $pdo->prepare('SELECT * FROM session_resources WHERE id = :id AND uploaded_by = :uploaded_by LIMIT 1');
    $resourceStatement->execute(['id' => $resourceId, 'uploaded_by' => $teacherId]);
    $existingResource = $resourceStatement->fetch();
    if (!$existingResource) $errors[] = 'The selected resource does not exist for this lecturer.';
}

$hasUpload = isset($_FILES['resource_file']) && (int) ($_FILES['resource_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($hasUpload) {
    $extension = strtolower(pathinfo((string) ($_FILES['resource_file']['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, upload_allowed_resource_extensions(), true)) {
        $errors[] = 'The uploaded file type is not allowed.';
    }
}

if (!$hasUpload && $externalUrl === '' && !$existingResource) {
    $errors[] = 'Upload a file or provide an external URL for the resource.';
}

$formData = [
    'id' => $resourceId,
    'session_id' => $sessionId,
    'batch_id' => $batchId,
    'return_to' => $returnTo,
    'resource_title' => $resourceTitle,
    'resource_type' => $resourceType,
    'external_url' => $externalUrl,
    'status' => $status,
];
if ($errors !== []) teacher_resource_redirect($errors, $formData, $returnTo, $sessionId, $batchId, $resourceId);

$fileName = $existingResource['file_name'] ?? null;
$filePath = $existingResource['file_path'] ?? null;
$fileSize = $existingResource['file_size'] ?? null;
$mimeType = $existingResource['mime_type'] ?? null;

if ($hasUpload) {
    $targetDirectory = $resourceType === 'slide' ? UPLOAD_SLIDES_PATH : UPLOAD_RESOURCES_PATH;
    $publicDirectory = $resourceType === 'slide' ? 'uploads/slides' : 'uploads/resources';

    try {
        $upload = upload_store_file($_FILES['resource_file'], $targetDirectory, $publicDirectory, upload_allowed_resource_mimes(), 'resource');
    } catch (Throwable $throwable) {
        teacher_resource_redirect([$throwable->getMessage()], $formData, $returnTo, $sessionId, $batchId, $resourceId);
    }

    $fileName = $upload['original_name'];
    $filePath = $upload['path'];
    $fileSize = $upload['size'];
    $mimeType = $upload['mime_type'];
}

if ($resourceId > 0) {
    $updateStatement = $pdo->prepare('UPDATE session_resources SET session_id = :session_id, resource_title = :resource_title, resource_type = :resource_type, file_name = :file_name, file_path = :file_path, external_url = :external_url, file_size = :file_size, mime_type = :mime_type, status = :status WHERE id = :id');
    $updateStatement->execute(['session_id' => $sessionId,'resource_title' => $resourceTitle,'resource_type' => $resourceType,'file_name' => $fileName,'file_path' => $filePath,'external_url' => $externalUrl !== '' ? $externalUrl : null,'file_size' => $fileSize,'mime_type' => $mimeType,'status' => $status,'id' => $resourceId]);
    flash_message('Lecturer resource updated successfully.', 'success');
    redirect(teacher_resource_redirect_url($returnTo, $sessionId, $batchId, $resourceId));
}

$insertStatement = $pdo->prepare('INSERT INTO session_resources (session_id, uploaded_by, resource_title, resource_type, file_name, file_path, external_url, file_size, mime_type, status) VALUES (:session_id, :uploaded_by, :resource_title, :resource_type, :file_name, :file_path, :external_url, :file_size, :mime_type, :status)');
$insertStatement->execute(['session_id' => $sessionId,'uploaded_by' => $teacherId,'resource_title' => $resourceTitle,'resource_type' => $resourceType,'file_name' => $fileName,'file_path' => $filePath,'external_url' => $externalUrl !== '' ? $externalUrl : null,'file_size' => $fileSize,'mime_type' => $mimeType,'status' => $status]);

flash_message('Lecturer resource saved successfully.', 'success');
redirect(teacher_resource_redirect_url($returnTo, $sessionId, $batchId));
