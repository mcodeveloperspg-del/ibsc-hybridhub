<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/units/index.php'));
}

$unitId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$returnUnitId = (int) ($_POST['return_unit_id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);
$stageId = (int) ($_POST['stage_id'] ?? 0);
$unitTitle = trim((string) ($_POST['unit_title'] ?? ''));
$unitCode = strtoupper(trim((string) ($_POST['unit_code'] ?? '')));
$description = trim((string) ($_POST['description'] ?? ''));
$sortOrder = (int) ($_POST['sort_order'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'active'));

$allowedStatuses = ['active', 'inactive'];
$errors = [];

if ($courseId < 1) {
    $errors[] = 'A course must be selected.';
}

if ($stageId < 1) {
    $errors[] = 'A stage must be selected.';
}

if ($unitTitle === '') {
    $errors[] = 'Unit title is required.';
}

if ($sortOrder < 1) {
    $errors[] = 'Sort order must be at least 1.';
}

if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected status is invalid.';
}

$stageValidationStatement = $pdo->prepare('SELECT id, course_id FROM stages WHERE id = :stage_id LIMIT 1');
$stageValidationStatement->execute(['stage_id' => $stageId]);
$stageRecord = $stageValidationStatement->fetch();

if (!$stageRecord) {
    $errors[] = 'The selected stage does not exist.';
} elseif ((int) $stageRecord['course_id'] !== $courseId) {
    $errors[] = 'The selected stage does not belong to the selected course.';
}

$duplicateSql = 'SELECT id FROM units WHERE course_id = :course_id AND unit_title = :unit_title';
$params = [
    'course_id' => $courseId,
    'unit_title' => $unitTitle,
];

if ($unitId > 0) {
    $duplicateSql .= ' AND id != :id';
    $params['id'] = $unitId;
}

$duplicateStatement = $pdo->prepare($duplicateSql);
$duplicateStatement->execute($params);

if ($duplicateStatement->fetch()) {
    $errors[] = 'A unit with that title already exists in the selected course.';
}

if ($errors !== []) {
    $_SESSION['unit_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $unitId,
            'course_id' => $courseId,
            'stage_id' => $stageId,
            'unit_title' => $unitTitle,
            'unit_code' => $unitCode,
            'description' => $description,
            'sort_order' => $sortOrder,
            'status' => $status,
            'return_unit_id' => $returnUnitId,
        ],
    ];

    $redirectUrl = base_url('admin/units/form.php');

    if ($unitId > 0) {
        $redirectUrl .= '?edit=' . (string) $unitId;
        if ($returnUnitId > 0) {
            $redirectUrl .= '&return_unit_id=' . (string) $returnUnitId;
        }
    }

    redirect($redirectUrl);
}

if ($unitId > 0) {
    $updateStatement = $pdo->prepare(
        'UPDATE units
         SET course_id = :course_id,
             stage_id = :stage_id,
             unit_title = :unit_title,
             unit_code = :unit_code,
             description = :description,
             sort_order = :sort_order,
             status = :status
         WHERE id = :id'
    );

    $updateStatement->execute([
        'course_id' => $courseId,
        'stage_id' => $stageId,
        'unit_title' => $unitTitle,
        'unit_code' => $unitCode !== '' ? $unitCode : null,
        'description' => $description !== '' ? $description : null,
        'sort_order' => $sortOrder,
        'status' => $status,
        'id' => $unitId,
    ]);

    flash_message('Unit updated successfully.', 'success');
} else {
    $insertStatement = $pdo->prepare(
        'INSERT INTO units (course_id, stage_id, unit_title, unit_code, description, sort_order, status)
         VALUES (:course_id, :stage_id, :unit_title, :unit_code, :description, :sort_order, :status)'
    );

    $insertStatement->execute([
        'course_id' => $courseId,
        'stage_id' => $stageId,
        'unit_title' => $unitTitle,
        'unit_code' => $unitCode !== '' ? $unitCode : null,
        'description' => $description !== '' ? $description : null,
        'sort_order' => $sortOrder,
        'status' => $status,
    ]);

    flash_message('Unit created successfully.', 'success');
}

if ($returnUnitId > 0) {
    redirect(base_url('admin/units/view.php?id=' . (string) $returnUnitId));
}
redirect(base_url('admin/units/index.php'));
