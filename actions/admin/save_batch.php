<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

function sync_batch_stage_schedule(PDO $pdo, int $batchId, int $totalStages, array $stageStartDates, array $stageEndDates): void
{
    $upsertStatement = $pdo->prepare(
        'INSERT INTO batch_stages (batch_id, stage_number, start_date, end_date)
         VALUES (:batch_id, :stage_number, :start_date, :end_date)
         ON DUPLICATE KEY UPDATE
            start_date = VALUES(start_date),
            end_date = VALUES(end_date)'
    );

    for ($stageNumber = 1; $stageNumber <= $totalStages; $stageNumber++) {
        $upsertStatement->execute([
            'batch_id' => $batchId,
            'stage_number' => $stageNumber,
            'start_date' => $stageStartDates[$stageNumber],
            'end_date' => $stageEndDates[$stageNumber],
        ]);
    }

    $deleteStatement = $pdo->prepare('DELETE FROM batch_stages WHERE batch_id = :batch_id AND stage_number > :total_stages');
    $deleteStatement->execute(['batch_id' => $batchId, 'total_stages' => $totalStages]);
}

if (!is_post_request()) {
    redirect(base_url('admin/batches/index.php'));
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$batchNumber = (int) ($_POST['batch_number'] ?? 0);
$totalStages = (int) ($_POST['total_stages'] ?? 0);
$startDate = trim((string) ($_POST['start_date'] ?? ''));
$endDate = trim((string) ($_POST['end_date'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'planned'));
$notes = trim((string) ($_POST['notes'] ?? ''));
$postedStageStarts = is_array($_POST['stage_start_dates'] ?? null) ? $_POST['stage_start_dates'] : [];
$postedStageEnds = is_array($_POST['stage_end_dates'] ?? null) ? $_POST['stage_end_dates'] : [];

$allowedStatuses = ['planned', 'active', 'completed', 'archived'];
$errors = [];
$stageStartDates = [];
$stageEndDates = [];
$batchYear = batch_year_from_date($startDate) ?? 0;
$batchName = $batchNumber > 0 ? batch_name_from_number($batchNumber) : '';
$intakeCode = ($batchNumber > 0 && $batchYear > 0) ? batch_intake_code($batchNumber, $batchYear) : '';

if ($batchNumber < 1) {
    $errors[] = 'Batch number must be at least 1.';
}

if ($totalStages < 1) {
    $errors[] = 'Total stages must be at least 1.';
}

if ($startDate === '') {
    $errors[] = 'Start date is required.';
} elseif (!is_valid_date_string($startDate)) {
    $errors[] = 'Start date is invalid.';
}

if ($endDate !== '' && !is_valid_date_string($endDate)) {
    $errors[] = 'End date is invalid.';
}

if ($startDate !== '' && $endDate !== '' && is_valid_date_string($startDate) && is_valid_date_string($endDate) && $endDate < $startDate) {
    $errors[] = 'End date must be on or after the start date.';
}

if (!in_array($status, $allowedStatuses, true)) {
    $errors[] = 'Invalid batch status.';
}

if ($totalStages > 0) {
    $previousStageEndDate = null;

    for ($stageNumber = 1; $stageNumber <= $totalStages; $stageNumber++) {
        $stageStartDate = trim((string) ($postedStageStarts[$stageNumber] ?? ''));
        $stageEndDate = trim((string) ($postedStageEnds[$stageNumber] ?? ''));
        $stageStartDates[$stageNumber] = $stageStartDate;
        $stageEndDates[$stageNumber] = $stageEndDate;

        if ($stageStartDate === '') {
            $errors[] = 'Stage ' . $stageNumber . ' start date is required.';
        } elseif (!is_valid_date_string($stageStartDate)) {
            $errors[] = 'Stage ' . $stageNumber . ' start date is invalid.';
        }

        if ($stageEndDate === '') {
            $errors[] = 'Stage ' . $stageNumber . ' end date is required.';
        } elseif (!is_valid_date_string($stageEndDate)) {
            $errors[] = 'Stage ' . $stageNumber . ' end date is invalid.';
        }

        if (is_valid_date_string($stageStartDate) && is_valid_date_string($stageEndDate) && $stageEndDate < $stageStartDate) {
            $errors[] = 'Stage ' . $stageNumber . ' end date must be on or after its start date.';
        }

        if (is_valid_date_string($startDate) && is_valid_date_string($stageStartDate) && $stageStartDate < $startDate) {
            $errors[] = 'Stage ' . $stageNumber . ' cannot start before the batch start date.';
        }

        if ($endDate !== '' && is_valid_date_string($endDate) && is_valid_date_string($stageEndDate) && $stageEndDate > $endDate) {
            $errors[] = 'Stage ' . $stageNumber . ' cannot end after the batch end date.';
        }

        if ($previousStageEndDate !== null && is_valid_date_string($stageStartDate) && $stageStartDate <= $previousStageEndDate) {
            $errors[] = 'Stage ' . $stageNumber . ' must start after Stage ' . ($stageNumber - 1) . ' ends.';
        }

        if (is_valid_date_string($stageEndDate)) {
            $previousStageEndDate = $stageEndDate;
        }
    }

    if ($endDate === '' && isset($stageEndDates[$totalStages]) && is_valid_date_string($stageEndDates[$totalStages])) {
        $endDate = $stageEndDates[$totalStages];
    }
}

if ($batchNumber > 0 && $batchYear > 0) {
    $duplicateSql = 'SELECT id FROM batches WHERE batch_year = :batch_year AND batch_number = :batch_number';
    $params = ['batch_year' => $batchYear, 'batch_number' => $batchNumber];

    if ($id > 0) {
        $duplicateSql .= ' AND id != :id';
        $params['id'] = $id;
    }

    $duplicateStatement = $pdo->prepare($duplicateSql);
    $duplicateStatement->execute($params);

    if ($duplicateStatement->fetch()) {
        $errors[] = 'This batch number already exists for ' . $batchYear . '.';
    }
}

if ($errors !== []) {
    $_SESSION['batch_form_state'] = [
        'errors' => $errors,
        'data' => [
            'id' => $id,
            'batch_number' => $batchNumber,
            'total_stages' => $totalStages,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stage_start_dates' => $stageStartDates,
            'stage_end_dates' => $stageEndDates,
            'status' => $status,
            'notes' => $notes,
        ],
    ];

    $url = base_url('admin/batches/index.php');

    if ($id > 0) {
        $url = base_url('admin/batches/edit.php?id=' . (string) $id);
    }

    redirect($url);
}

$pdo->beginTransaction();

try {
    if ($id > 0) {
        $statement = $pdo->prepare(
            'UPDATE batches
             SET batch_number = :batch_number,
                 batch_year = :batch_year,
                 batch_name = :batch_name,
                 intake_code = :intake_code,
                 total_stages = :total_stages,
                 start_date = :start_date,
                 end_date = :end_date,
                 status = :status,
                 notes = :notes
             WHERE id = :id'
        );

        $statement->execute([
            'batch_number' => $batchNumber,
            'batch_year' => $batchYear,
            'batch_name' => $batchName,
            'intake_code' => $intakeCode,
            'total_stages' => $totalStages,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
            'id' => $id,
        ]);

        $batchId = $id;
        $successMessage = 'Batch updated successfully.';
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO batches (batch_number, batch_year, batch_name, intake_code, total_stages, start_date, end_date, status, notes)
             VALUES (:batch_number, :batch_year, :batch_name, :intake_code, :total_stages, :start_date, :end_date, :status, :notes)'
        );

        $statement->execute([
            'batch_number' => $batchNumber,
            'batch_year' => $batchYear,
            'batch_name' => $batchName,
            'intake_code' => $intakeCode,
            'total_stages' => $totalStages,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $batchId = (int) $pdo->lastInsertId();
        $successMessage = 'Batch created successfully and all current courses were linked automatically.';
    }

    sync_batch_stage_schedule($pdo, $batchId, $totalStages, $stageStartDates, $stageEndDates);
    sync_batch_courses($pdo, $batchId);
    $pdo->commit();

    flash_message($successMessage, 'success');
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash_message('The batch could not be saved. Please try again.', 'danger');
}

redirect(base_url('admin/batches/index.php'));
