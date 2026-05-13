<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/topics/index.php'));
}

$topicId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$unitId = (int) ($_POST['unit_id'] ?? 0);
$returnUnitId = (int) ($_POST['return_unit_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? 'active'));
$isUnlocked = (int) ($_POST['is_unlocked'] ?? 0);
$allowedStatuses = ['active', 'inactive'];
$errors = [];

if ($unitId < 1) {
    $errors[] = 'A unit must be selected.';
}

if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'The selected status is invalid.';
}

if (!in_array($isUnlocked, [0, 1], true)) {
    $errors[] = 'The selected access state is invalid.';
}

$unitStatement = $pdo->prepare('SELECT id FROM units WHERE id = :unit_id LIMIT 1');
$unitStatement->execute(['unit_id' => $unitId]);
if (!$unitStatement->fetch()) {
    $errors[] = 'The selected unit does not exist.';
}

if ($topicId > 0) {
    $topicTitle = trim((string) ($_POST['topic_title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);

    if ($topicTitle === '') {
        $errors[] = 'Topic title is required.';
    }

    if ($sortOrder < 1) {
        $errors[] = 'Sort order must be at least 1.';
    }

    $duplicateSql = 'SELECT id FROM topics WHERE unit_id = :unit_id AND topic_title = :topic_title AND id != :id';
    $duplicateStatement = $pdo->prepare($duplicateSql);
    $duplicateStatement->execute([
        'unit_id' => $unitId,
        'topic_title' => $topicTitle,
        'id' => $topicId,
    ]);

    if ($duplicateStatement->fetch()) {
        $errors[] = 'A topic with that title already exists in the selected unit.';
    }

    if ($errors !== []) {
        $_SESSION['topic_form_state'] = [
            'errors' => $errors,
            'data' => [
                'id' => $topicId,
                'unit_id' => $unitId,
                'topic_title' => $topicTitle,
                'description' => $description,
                'sort_order' => $sortOrder,
                'status' => $status,
                'is_unlocked' => $isUnlocked,
                'return_unit_id' => $returnUnitId,
            ],
        ];
        $redirectUrl = base_url('admin/topics/form.php?edit=' . (string) $topicId);
        if ($returnUnitId > 0) {
            $redirectUrl .= '&return_unit_id=' . (string) $returnUnitId;
        }
        redirect($redirectUrl);
    }

    $unlockTimestamp = $isUnlocked === 1 ? date('Y-m-d H:i:s') : null;
    $currentUser = current_user();
    $unlockedBy = $isUnlocked === 1 ? ($currentUser['id'] ?? null) : null;

    $updateStatement = $pdo->prepare('UPDATE topics SET unit_id = :unit_id, topic_title = :topic_title, description = :description, sort_order = :sort_order, is_unlocked = :is_unlocked, unlocked_at = :unlocked_at, unlocked_by = :unlocked_by, status = :status WHERE id = :id');
    $updateStatement->execute([
        'unit_id' => $unitId,
        'topic_title' => $topicTitle,
        'description' => $description !== '' ? $description : null,
        'sort_order' => $sortOrder,
        'is_unlocked' => $isUnlocked,
        'unlocked_at' => $unlockTimestamp,
        'unlocked_by' => $unlockedBy,
        'status' => $status,
        'id' => $topicId,
    ]);

    flash_message('Topic updated successfully.', 'success');
    if ($returnUnitId > 0) {
        redirect(base_url('admin/units/view.php?id=' . (string) $returnUnitId));
    }
    redirect(base_url('admin/topics/index.php'));
}

$topicTitles = $_POST['topic_titles'] ?? [];
$descriptions = $_POST['descriptions'] ?? [];
$sortOrders = $_POST['sort_orders'] ?? [];

if (!is_array($topicTitles) || !is_array($descriptions) || !is_array($sortOrders)) {
    $errors[] = 'Invalid topic submission.';
}

$topicRows = [];
$seenTitles = [];
$preparedTitles = [];

if ($errors === []) {
    $rowCount = max(count($topicTitles), count($descriptions), count($sortOrders));

    for ($index = 0; $index < $rowCount; $index++) {
        $topicTitle = trim((string) ($topicTitles[$index] ?? ''));
        $description = trim((string) ($descriptions[$index] ?? ''));
        $sortOrder = (int) ($sortOrders[$index] ?? 0);

        if ($topicTitle === '' && $description === '' && $sortOrder === 0) {
            continue;
        }

        $topicRows[] = [
            'topic_title' => $topicTitle,
            'description' => $description,
            'sort_order' => $sortOrder,
        ];

        $rowNumber = $index + 1;

        if ($topicTitle === '') {
            $errors[] = 'Topic title is required for row ' . $rowNumber . '.';
        }

        if ($sortOrder < 1) {
            $errors[] = 'Sort order must be at least 1 for row ' . $rowNumber . '.';
        }

        $normalizedTitle = mb_strtolower($topicTitle, 'UTF-8');
        if ($topicTitle !== '') {
            if (isset($seenTitles[$normalizedTitle])) {
                $errors[] = 'Duplicate topic title found in this submission: ' . $topicTitle . '.';
            }
            $seenTitles[$normalizedTitle] = true;
            $preparedTitles[] = $topicTitle;
        }
    }

    if ($topicRows === []) {
        $errors[] = 'Add at least one topic before saving.';
    }
}

if ($errors === [] && $preparedTitles !== []) {
    $placeholders = [];
    $duplicateParams = ['unit_id' => $unitId];
    foreach ($preparedTitles as $index => $preparedTitle) {
        $key = 'title_' . $index;
        $placeholders[] = ':' . $key;
        $duplicateParams[$key] = $preparedTitle;
    }

    $duplicateStatement = $pdo->prepare(
        'SELECT topic_title FROM topics WHERE unit_id = :unit_id AND topic_title IN (' . implode(', ', $placeholders) . ')'
    );
    $duplicateStatement->execute($duplicateParams);
    $existingTitles = $duplicateStatement->fetchAll(PDO::FETCH_COLUMN);

    foreach ($existingTitles as $existingTitle) {
        $errors[] = 'A topic with that title already exists in the selected unit: ' . $existingTitle . '.';
    }
}

if ($errors !== []) {
    $_SESSION['topic_form_state'] = [
        'errors' => $errors,
        'data' => [
            'unit_id' => $unitId,
            'status' => $status,
            'is_unlocked' => $isUnlocked,
            'return_unit_id' => $returnUnitId,
            'topic_rows' => $topicRows !== [] ? $topicRows : [['topic_title' => '', 'description' => '', 'sort_order' => 1]],
        ],
    ];

    $redirectUrl = base_url('admin/topics/form.php');
    if ($unitId > 0) {
        $redirectUrl .= '?unit_id=' . (string) $unitId;
        if ($returnUnitId > 0) {
            $redirectUrl .= '&return_unit_id=' . (string) $returnUnitId;
        }
    }
    redirect($redirectUrl);
}

$unlockTimestamp = $isUnlocked === 1 ? date('Y-m-d H:i:s') : null;
$currentUser = current_user();
$unlockedBy = $isUnlocked === 1 ? ($currentUser['id'] ?? null) : null;

$pdo->beginTransaction();

try {
    $insertStatement = $pdo->prepare('INSERT INTO topics (unit_id, topic_title, description, sort_order, is_unlocked, unlocked_at, unlocked_by, status) VALUES (:unit_id, :topic_title, :description, :sort_order, :is_unlocked, :unlocked_at, :unlocked_by, :status)');

    foreach ($topicRows as $topicRow) {
        $insertStatement->execute([
            'unit_id' => $unitId,
            'topic_title' => $topicRow['topic_title'],
            'description' => $topicRow['description'] !== '' ? $topicRow['description'] : null,
            'sort_order' => (int) $topicRow['sort_order'],
            'is_unlocked' => $isUnlocked,
            'unlocked_at' => $unlockTimestamp,
            'unlocked_by' => $unlockedBy,
            'status' => $status,
        ]);
    }

    $pdo->commit();
    flash_message(count($topicRows) === 1 ? 'Topic created successfully.' : count($topicRows) . ' topics created successfully.', 'success');
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['topic_form_state'] = [
        'errors' => ['The topics could not be saved. Please try again.'],
        'data' => [
            'unit_id' => $unitId,
            'status' => $status,
            'is_unlocked' => $isUnlocked,
            'return_unit_id' => $returnUnitId,
            'topic_rows' => $topicRows,
        ],
    ];

    $redirectUrl = base_url('admin/topics/form.php');
    if ($unitId > 0) {
        $redirectUrl .= '?unit_id=' . (string) $unitId;
        if ($returnUnitId > 0) {
            $redirectUrl .= '&return_unit_id=' . (string) $returnUnitId;
        }
    }
    redirect($redirectUrl);
}

if ($returnUnitId > 0) {
    redirect(base_url('admin/units/view.php?id=' . (string) $returnUnitId));
}
redirect(base_url('admin/topics/index.php'));
