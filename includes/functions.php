<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('HYBRID_HUB_SESSION');
    session_set_cookie_params(['lifetime' => 0,'path' => '/','domain' => '','secure' => $isHttps,'httponly' => true,'samesite' => 'Lax']);
    session_start();
}

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function base_url(string $path = ''): string { $baseUrl = defined('APP_URL') ? APP_URL : ''; return $path === '' ? $baseUrl : rtrim($baseUrl, '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . $path); exit; }
function is_post_request(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function user_role(): ?string { return $_SESSION['user']['role_name'] ?? null; }
function flash_message(?string $message = null, string $type = 'success'): ?array { if ($message !== null) { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; return null; } if (!isset($_SESSION['flash'])) { return null; } $flash = $_SESSION['flash']; unset($_SESSION['flash']); return $flash; }
function format_datetime(?string $dateTime): string { return empty($dateTime) ? 'N/A' : date('d M Y, h:i A', strtotime($dateTime)); }
function full_name(array $user): string { return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); }
function is_valid_date_string(string $date): bool { $value = DateTime::createFromFormat('Y-m-d', $date); return $value instanceof DateTime && $value->format('Y-m-d') === $date; }
function batch_year_from_date(string $date): ?int { if (!is_valid_date_string($date)) return null; return (int) date('Y', strtotime($date)); }
function batch_name_from_number(int $batchNumber): string { return 'Batch ' . $batchNumber; }
function batch_intake_code(int $batchNumber, int $batchYear): string { return 'BATCH-' . $batchNumber . '-' . $batchYear; }
function role_id_by_name(PDO $pdo, string $roleName): int {
    $statement = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $statement->execute(['name' => $roleName]);
    return (int) $statement->fetchColumn();
}
function sync_batch_courses(PDO $pdo, int $batchId): void {
    $courseIds = $pdo->query('SELECT id FROM courses')->fetchAll(PDO::FETCH_COLUMN);
    if ($courseIds === []) return;
    $statement = $pdo->prepare('INSERT IGNORE INTO batch_courses (batch_id, course_id) VALUES (:batch_id, :course_id)');
    foreach ($courseIds as $courseId) $statement->execute(['batch_id' => $batchId, 'course_id' => (int) $courseId]);
}
function attach_course_to_open_batches(PDO $pdo, int $courseId, ?string $today = null): void {
    $today = $today ?: date('Y-m-d');
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO batch_courses (batch_id, course_id)
         SELECT id, :course_id
         FROM batches
         WHERE end_date IS NULL OR end_date >= :today'
    );
    $statement->execute(['course_id' => $courseId, 'today' => $today]);
}
function batch_stage_progress(string $batchStartDate, int $weeksPerStage, int $totalStages, ?string $today = null): ?array {
    if (!is_valid_date_string($batchStartDate) || $weeksPerStage < 1 || $totalStages < 1) {
        return null;
    }
    $today = $today ?: date('Y-m-d');
    if (!is_valid_date_string($today)) {
        $today = date('Y-m-d');
    }
    $start = new DateTimeImmutable($batchStartDate);
    $current = new DateTimeImmutable($today);
    $elapsedDays = (int) $start->diff($current)->format('%r%a');
    if ($elapsedDays < 0) {
        return [
            'stage_number' => 1,
            'status' => 'upcoming',
            'elapsed_days' => $elapsedDays,
            'elapsed_weeks' => 0,
            'label' => 'Stage 1 starts when the batch begins',
        ];
    }
    $elapsedWeeks = intdiv($elapsedDays, 7);
    $stageNumber = intdiv($elapsedWeeks, $weeksPerStage) + 1;
    if ($stageNumber > $totalStages) {
        return [
            'stage_number' => $totalStages,
            'status' => 'completed',
            'elapsed_days' => $elapsedDays,
            'elapsed_weeks' => $elapsedWeeks,
            'label' => 'Course delivery window has moved past the final stage',
        ];
    }
    return [
        'stage_number' => $stageNumber,
        'status' => 'in_progress',
        'elapsed_days' => $elapsedDays,
        'elapsed_weeks' => $elapsedWeeks,
        'label' => 'Stage ' . $stageNumber . ' is currently in progress for this batch',
    ];
}
function batch_stage_schedule(PDO $pdo, int $batchId): array {
    $statement = $pdo->prepare(
        'SELECT stage_number, start_date, end_date
         FROM batch_stages
         WHERE batch_id = :batch_id
         ORDER BY stage_number ASC'
    );
    $statement->execute(['batch_id' => $batchId]);
    return $statement->fetchAll();
}
function batch_stage_progress_from_schedule(array $stageSchedule, ?string $today = null): ?array {
    if ($stageSchedule === []) {
        return null;
    }
    $today = $today ?: date('Y-m-d');
    if (!is_valid_date_string($today)) {
        $today = date('Y-m-d');
    }
    $firstStage = $stageSchedule[0];
    $lastStage = $stageSchedule[count($stageSchedule) - 1];

    foreach ($stageSchedule as $stage) {
        $stageStart = (string) ($stage['start_date'] ?? '');
        $stageEnd = (string) ($stage['end_date'] ?? '');
        if (!is_valid_date_string($stageStart) || !is_valid_date_string($stageEnd)) {
            return null;
        }
        if ($today >= $stageStart && $today <= $stageEnd) {
            return [
                'stage_number' => (int) $stage['stage_number'],
                'status' => 'in_progress',
                'start_date' => $stageStart,
                'end_date' => $stageEnd,
                'label' => 'Stage ' . (int) $stage['stage_number'] . ' runs from ' . $stageStart . ' to ' . $stageEnd,
            ];
        }
    }

    if ($today < (string) $firstStage['start_date']) {
        return [
            'stage_number' => (int) $firstStage['stage_number'],
            'status' => 'upcoming',
            'start_date' => (string) $firstStage['start_date'],
            'end_date' => (string) $firstStage['end_date'],
            'label' => 'Stage ' . (int) $firstStage['stage_number'] . ' starts on ' . (string) $firstStage['start_date'],
        ];
    }

    return [
        'stage_number' => (int) $lastStage['stage_number'],
        'status' => 'completed',
        'start_date' => (string) $lastStage['start_date'],
        'end_date' => (string) $lastStage['end_date'],
        'label' => 'The batch stage schedule ended on ' . (string) $lastStage['end_date'],
    ];
}
function batch_stage_progress_for_batch(PDO $pdo, int $batchId, string $batchStartDate, int $weeksPerStage, int $totalStages, ?string $today = null): ?array {
    $scheduledProgress = batch_stage_progress_from_schedule(batch_stage_schedule($pdo, $batchId), $today);
    if ($scheduledProgress !== null) {
        return $scheduledProgress;
    }

    return batch_stage_progress($batchStartDate, $weeksPerStage, $totalStages, $today);
}
function active_stage_number_for_batch(string $batchStartDate, int $weeksPerStage, int $totalStages, ?string $today = null): ?int {
    $progress = batch_stage_progress($batchStartDate, $weeksPerStage, $totalStages, $today);
    return $progress['stage_number'] ?? null;
}
function active_stage_number_for_batch_record(PDO $pdo, int $batchId, string $batchStartDate, int $weeksPerStage, int $totalStages, ?string $today = null): ?int {
    $progress = batch_stage_progress_for_batch($pdo, $batchId, $batchStartDate, $weeksPerStage, $totalStages, $today);
    return $progress['stage_number'] ?? null;
}
function lecturer_assignment_contexts(PDO $pdo, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');
    $rows = $pdo->query(
        "SELECT batches.id AS batch_id, batches.batch_name, batches.batch_year, batches.start_date, batches.status AS batch_status,
                courses.id AS course_id, courses.title AS course_title, courses.course_code, courses.weeks_per_stage, courses.total_stages
         FROM batch_courses
         INNER JOIN batches ON batches.id = batch_courses.batch_id
         INNER JOIN courses ON courses.id = batch_courses.course_id
         ORDER BY batches.batch_year DESC, batches.batch_number ASC, courses.title ASC"
    )->fetchAll();
    $stages = $pdo->query(
        "SELECT id, course_id, stage_number, title
         FROM stages
         ORDER BY course_id ASC, stage_number ASC"
    )->fetchAll();
    $units = $pdo->query(
        "SELECT units.id, units.course_id, units.stage_id, units.unit_title, units.unit_code, units.status,
                stages.stage_number, stages.title AS stage_title
         FROM units
         INNER JOIN stages ON stages.id = units.stage_id
         ORDER BY units.course_id ASC, stages.stage_number ASC, units.sort_order ASC, units.unit_title ASC"
    )->fetchAll();

    $stageLookup = [];
    foreach ($stages as $stage) {
        $stageLookup[(int) $stage['course_id']][(int) $stage['stage_number']] = $stage;
    }

    $unitLookup = [];
    foreach ($units as $unit) {
        $courseId = (int) $unit['course_id'];
        $stageNumber = (int) $unit['stage_number'];
        $unitLookup[$courseId][$stageNumber][] = [
            'id' => (int) $unit['id'],
            'label' => $unit['unit_title'] . (!empty($unit['unit_code']) ? ' (' . $unit['unit_code'] . ')' : ''),
            'status' => $unit['status'],
            'stage_id' => (int) $unit['stage_id'],
            'stage_title' => $unit['stage_title'],
        ];
    }

    $contexts = [];
    foreach ($rows as $row) {
        $courseId = (int) $row['course_id'];
        $stageProgress = batch_stage_progress_for_batch($pdo, (int) $row['batch_id'], (string) $row['start_date'], (int) $row['weeks_per_stage'], (int) $row['total_stages'], $today);
        $stageNumber = $stageProgress['stage_number'] ?? null;
        if ($stageNumber === null) {
            continue;
        }
        $stage = $stageLookup[$courseId][$stageNumber] ?? null;
        $key = (int) $row['batch_id'] . '-' . $courseId;
        $contexts[$key] = [
            'key' => $key,
            'batch_id' => (int) $row['batch_id'],
            'batch_label' => $row['batch_name'] . ' (' . $row['batch_year'] . ')',
            'batch_status' => $row['batch_status'],
            'course_id' => $courseId,
            'course_label' => $row['course_title'] . ' (' . $row['course_code'] . ')',
            'stage_number' => $stageNumber,
            'stage_id' => $stage !== null ? (int) $stage['id'] : 0,
            'stage_title' => $stage['title'] ?? ('Stage ' . $stageNumber),
            'units' => $unitLookup[$courseId][$stageNumber] ?? [],
            'progress' => $stageProgress,
        ];
    }

    return $contexts;
}
function teacher_has_unit_assignments(PDO $pdo, int $teacherId): bool {
    $statement = $pdo->prepare(
        "SELECT id
         FROM lecturer_unit_assignments
         WHERE lecturer_id = :lecturer_id
           AND status = 'active'
         LIMIT 1"
    );
    $statement->execute(['lecturer_id' => $teacherId]);
    return (bool) $statement->fetchColumn();
}
function teacher_assigned_units(PDO $pdo, int $teacherId): array {
    $statement = $pdo->prepare(
        "SELECT lecturer_unit_assignments.id, lecturer_unit_assignments.assigned_at,
                batches.id AS batch_id, batches.batch_name, batches.batch_year, batches.status AS batch_status,
                courses.id AS course_id, courses.title AS course_title, courses.course_code,
                stages.id AS stage_id, stages.stage_number, stages.title AS stage_title,
                units.id AS unit_id, units.unit_title, units.unit_code, units.status AS unit_status
         FROM lecturer_unit_assignments
         INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         INNER JOIN stages ON stages.id = units.stage_id
         WHERE lecturer_unit_assignments.lecturer_id = :lecturer_id
           AND lecturer_unit_assignments.status = 'active'
         ORDER BY batches.batch_year DESC, batches.batch_name ASC, courses.title ASC, stages.stage_number ASC, units.unit_title ASC"
    );
    $statement->execute(['lecturer_id' => $teacherId]);
    return $statement->fetchAll();
}
function teacher_assigned_unit_scope(PDO $pdo, int $teacherId, int $batchId, int $unitId): ?array {
    $statement = $pdo->prepare(
        "SELECT lecturer_unit_assignments.id, lecturer_unit_assignments.assigned_at,
                batches.id AS batch_id, batches.batch_name, batches.batch_year, batches.status AS batch_status,
                courses.id AS course_id, courses.title AS course_title, courses.course_code,
                stages.id AS stage_id, stages.stage_number, stages.title AS stage_title,
                units.id AS unit_id, units.unit_title, units.unit_code, units.status AS unit_status
         FROM lecturer_unit_assignments
         INNER JOIN batches ON batches.id = lecturer_unit_assignments.batch_id
         INNER JOIN units ON units.id = lecturer_unit_assignments.unit_id
         INNER JOIN courses ON courses.id = units.course_id
         INNER JOIN stages ON stages.id = units.stage_id
         WHERE lecturer_unit_assignments.lecturer_id = :lecturer_id
           AND lecturer_unit_assignments.batch_id = :batch_id
           AND lecturer_unit_assignments.unit_id = :unit_id
           AND lecturer_unit_assignments.status = 'active'
         LIMIT 1"
    );
    $statement->execute([
        'lecturer_id' => $teacherId,
        'batch_id' => $batchId,
        'unit_id' => $unitId,
    ]);
    $scope = $statement->fetch();
    return $scope !== false ? $scope : null;
}
function app_pdo(): ?PDO { $pdo = $GLOBALS['pdo'] ?? null; return $pdo instanceof PDO ? $pdo : null; }
function role_dashboard_path(string $roleName): string {
    $pdo = app_pdo();
    if ($pdo instanceof PDO) {
        $path = first_accessible_module_path($pdo, $roleName);
        if (is_string($path) && $path !== '') {
            return base_url($path);
        }
    }
    $map = ['system_admin' => 'admin/dashboard.php','technical_officer' => 'technical_officer/dashboard.php','teacher' => 'teacher/dashboard.php','student' => 'student/dashboard.php'];
    return base_url($map[$roleName] ?? 'index.php');
}
function role_label(string $roleName): string { $labels = ['system_admin' => 'System Admin','technical_officer' => 'Technical Officer','teacher' => 'Lecturer','student' => 'Student']; return $labels[$roleName] ?? 'User'; }
function current_path(): string { $requestUri = $_SERVER['REQUEST_URI'] ?? '/'; return strtok($requestUri, '?') ?: '/'; }
function client_ip_address(): string { return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; }
function session_fingerprint(): string { $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent'; return hash('sha256', $userAgent); }
function is_active_page(string $path): bool { return current_path() === parse_url(base_url($path), PHP_URL_PATH); }
function role_theme(string $roleName): array { $themes = ['system_admin' => ['panel_label' => 'Admin Panel','initials' => 'HLH','eyebrow' => 'System Overview','sidebar_class' => 'theme-admin'],'technical_officer' => ['panel_label' => 'Operations Panel','initials' => 'OPS','eyebrow' => 'Release Operations','sidebar_class' => 'theme-technical'],'teacher' => ['panel_label' => 'Lecturer Panel','initials' => 'LCT','eyebrow' => 'Teaching Workspace','sidebar_class' => 'theme-teacher'],'student' => ['panel_label' => 'Student Panel','initials' => 'STD','eyebrow' => 'Learning Space','sidebar_class' => 'theme-student']]; return $themes[$roleName] ?? ['panel_label' => 'Dashboard','initials' => 'APP','eyebrow' => 'Workspace','sidebar_class' => 'theme-admin']; }
function role_nav_items(?string $roleName = null): array {
    $roleName = $roleName ?? user_role() ?? '';
    $pdo = app_pdo();
    if (!$pdo instanceof PDO || $roleName === '') {
        return [];
    }
    return array_map(static function (array $module): array {
        return [
            'label' => (string) $module['label'],
            'path' => (string) $module['path'],
            'icon' => (string) ($module['icon'] ?? 'bi-grid-fill'),
        ];
    }, role_nav_modules($pdo, $roleName));
}
function consume_form_state(string $key, array $default = []): array {
    $state = $_SESSION[$key] ?? $default;
    unset($_SESSION[$key]);
    return is_array($state) ? $state : $default;
}
function session_content_source_id(array $session): int {
    $sourceId = (int) ($session['fallback_source_session_id'] ?? 0);
    if ($sourceId > 0) {
        return $sourceId;
    }
    return (int) ($session['id'] ?? 0);
}
function session_uses_fallback(array $session): bool {
    return (int) ($session['fallback_source_session_id'] ?? 0) > 0;
}
function session_content_source(PDO $pdo, array $session): ?array {
    $sourceId = session_content_source_id($session);
    if ($sourceId < 1) {
        return null;
    }
    if ($sourceId === (int) ($session['id'] ?? 0)) {
        return $session;
    }
    $statement = $pdo->prepare('SELECT * FROM sessions WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $sourceId]);
    $source = $statement->fetch();
    return $source !== false ? $source : null;
}
function session_resources_for_delivery(PDO $pdo, array $session, string $status = 'active'): array {
    $sourceId = session_content_source_id($session);
    if ($sourceId < 1) {
        return [];
    }
    $statement = $pdo->prepare('SELECT id, resource_title, resource_type, external_url, file_name, file_path, mime_type, status FROM session_resources WHERE session_id = :session_id AND status = :status ORDER BY id DESC');
    $statement->execute(['session_id' => $sourceId, 'status' => $status]);
    return $statement->fetchAll();
}
function session_embed_url(array $session): ?string {
    $provider = (string) ($session['video_provider'] ?? '');
    $embedUrl = trim((string) ($session['video_embed_url'] ?? ''));
    if ($embedUrl !== '') {
        $normalizedEmbedUrl = normalize_video_embed_url($provider, $embedUrl);
        return $normalizedEmbedUrl ?? $embedUrl;
    }

    $videoUrl = trim((string) ($session['video_url'] ?? ''));
    if ($videoUrl === '') return null;

    return normalize_video_embed_url($provider, $videoUrl);
}

function normalize_video_embed_url(string $provider, string $videoUrl): ?string {
    if ($provider === 'youtube') {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $videoUrl, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        return null;
    }

    if ($provider === 'vimeo') {
        if (preg_match('~(?:player\.vimeo\.com/video/|vimeo\.com/)(\d+)~', $videoUrl, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    if (in_array($provider, ['external_link', 'internal_embed'], true)) {
        return $videoUrl;
    }

    return null;
}
function resource_public_url(array $resource): ?string {
    $filePath = trim((string) ($resource['file_path'] ?? ''));
    if ($filePath === '') {
        return null;
    }
    return base_url(str_replace('\\', '/', $filePath));
}
function resource_preview_type(array $resource): ?string {
    $fileName = strtolower((string) ($resource['file_name'] ?? ''));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = strtolower((string) ($resource['mime_type'] ?? ''));
    if ($extension === 'pdf' || $mimeType === 'application/pdf') {
        return 'pdf';
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_starts_with($mimeType, 'image/')) {
        return 'image';
    }
    if (in_array($extension, ['txt'], true) || str_starts_with($mimeType, 'text/')) {
        return 'text';
    }
    return null;
}








require_once dirname(__DIR__) . '/access_module.php';
