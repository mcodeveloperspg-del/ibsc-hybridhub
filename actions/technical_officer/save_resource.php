<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['technical_officer']);

if (!is_post_request()) {
    redirect(base_url('technical_officer/sessions/index.php'));
}

function store_resource_form_state(array $errors, array $data, int $resourceId = 0): void
{
    $_SESSION['technical_resource_form_state'] = [
        'errors' => $errors,
        'data' => $data,
    ];

    $redirectUrl = base_url('technical_officer/sessions/index.php');
    if ($resourceId > 0) {
        $redirectUrl .= '?edit_resource=' . (string) $resourceId;
    }

    redirect($redirectUrl);
}

$resourceId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$sessionId = (int) ($_POST['session_id'] ?? 0);
$resourceTitle = trim((string) ($_POST['resource_title'] ?? ''));
$resourceType = trim((string) ($_POST['resource_type'] ?? 'document'));
$externalUrl = trim((string) ($_POST['external_url'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'active'));

$allowedTypes = ['slide', 'document', 'worksheet', 'link', 'other'];
$allowedStatuses = ['active', 'inactive'];
$errors = [];

if ($sessionId < 1) {
    $errors[] = 'A valid session must be selected.';
}
if ($resourceTitle === '') {
    $errors[] = 'Resource title is required.';
}
if (!in_array($resourceType, $allowedTypes, true)) {
    $errors[] = 'The selected resource type is invalid.';
}
if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected resource status is invalid.';
}
if ($externalUrl !== '' && filter_var($externalUrl, FILTER_VALIDATE_URL) === false) {
    $errors[] = 'The external URL must be a valid link.';
}
if ($resourceType === 'link' && $externalUrl === '') {
    $errors[] = 'A link resource requires an external URL.';
}

$sessionStatement = $pdo->prepare('SELECT id FROM sessions WHERE id = :id LIMIT 1');
$sessionStatement->execute(['id' => $sessionId]);
if (!$sessionStatement->fetch()) {
    $errors[] = 'The selected session does not exist.';
}

$existingResource = null;
if ($resourceId > 0) {
    $resourceStatement = $pdo->prepare('SELECT * FROM session_resources WHERE id = :id LIMIT 1');
    $resourceStatement->execute(['id' => $resourceId]);
    $existingResource = $resourceStatement->fetch();

    if (!$existingResource) {
        $errors[] = 'The selected resource does not exist.';
    }
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
    'resource_title' => $resourceTitle,
    'resource_type' => $resourceType,
    'external_url' => $externalUrl,
    'status' => $status,
];

if ($errors !== []) {
    store_resource_form_state($errors, $formData, $resourceId);
}

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
        store_resource_form_state([$throwable->getMessage()], $formData, $resourceId);
    }

    $fileName = $upload['original_name'];
    $filePath = $upload['path'];
    $fileSize = $upload['size'];
    $mimeType = $upload['mime_type'];
}

$currentUser = current_user();

if ($resourceId > 0) {
    $updateStatement = $pdo->prepare(
        'UPDATE session_resources
         SET session_id = :session_id,
             resource_title = :resource_title,
             resource_type = :resource_type,
             file_name = :file_name,
             file_path = :file_path,
             external_url = :external_url,
             file_size = :file_size,
             mime_type = :mime_type,
             status = :status
         WHERE id = :id'
    );
    $updateStatement->execute([
        'session_id' => $sessionId,
        'resource_title' => $resourceTitle,
        'resource_type' => $resourceType,
        'file_name' => $fileName,
        'file_path' => $filePath,
        'external_url' => $externalUrl !== '' ? $externalUrl : null,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'status' => $status,
        'id' => $resourceId,
    ]);

    flash_message('Resource updated successfully.', 'success');
    redirect(base_url('technical_officer/sessions/index.php?edit_resource=' . (string) $resourceId));
}

$insertStatement = $pdo->prepare(
    'INSERT INTO session_resources (session_id, uploaded_by, resource_title, resource_type, file_name, file_path, external_url, file_size, mime_type, status)
     VALUES (:session_id, :uploaded_by, :resource_title, :resource_type, :file_name, :file_path, :external_url, :file_size, :mime_type, :status)'
);
$insertStatement->execute([
    'session_id' => $sessionId,
    'uploaded_by' => $currentUser['id'] ?? null,
    'resource_title' => $resourceTitle,
    'resource_type' => $resourceType,
    'file_name' => $fileName,
    'file_path' => $filePath,
    'external_url' => $externalUrl !== '' ? $externalUrl : null,
    'file_size' => $fileSize,
    'mime_type' => $mimeType,
    'status' => $status,
]);

flash_message('Resource saved successfully.', 'success');
redirect(base_url('technical_officer/sessions/index.php'));
