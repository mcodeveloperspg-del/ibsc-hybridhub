<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

function upload_allowed_resource_extensions(): array
{
    return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png'];
}

function upload_allowed_resource_mimes(): array
{
    return [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
}

function upload_allowed_image_mimes(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

function upload_detect_mime(string $path): string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);
        if (is_string($detected) && $detected !== '') {
            return $detected;
        }
    }

    return 'application/octet-stream';
}

function upload_assert_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        throw new RuntimeException('Upload directory could not be created.');
    }

    if (!is_writable($directory)) {
        throw new RuntimeException('Upload directory is not writable.');
    }
}

function upload_store_file(array $file, string $targetDirectory, string $publicDirectory, array $allowedMimeMap, string $prefix = 'file'): array
{
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No upload was received.');
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The uploaded file could not be processed.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('The uploaded file could not be read.');
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize < 1 || $fileSize > APP_MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The uploaded file is too large.');
    }

    $originalName = (string) ($file['name'] ?? $prefix);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !isset($allowedMimeMap[$extension])) {
        throw new RuntimeException('The uploaded file type is not allowed.');
    }

    $detectedMimeType = upload_detect_mime($tmpPath);
    if (!in_array($detectedMimeType, $allowedMimeMap[$extension], true)) {
        throw new RuntimeException('The uploaded file content does not match its extension.');
    }

    upload_assert_directory($targetDirectory);

    $safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: $prefix;
    $storedName = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '_' . $safeBaseName . '.' . $extension;
    $targetPath = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('The uploaded file could not be saved.');
    }

    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'path' => rtrim($publicDirectory, '/\\') . '/' . $storedName,
        'size' => $fileSize,
        'mime_type' => $detectedMimeType,
    ];
}
