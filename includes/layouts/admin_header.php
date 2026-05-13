<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$pageHeading = $pageHeading ?? APP_NAME;
$pageDescription = $pageDescription ?? '';
$bodyClass = $bodyClass ?? 'bg-light';
$user = current_user();
$flash = $flash ?? flash_message();
$adminNavItems = role_nav_items('system_admin');
$adminNavByPath = [];
foreach ($adminNavItems as $item) {
    $adminNavByPath[(string) $item['path']] = $item;
}
$adminNavSections = [
    'Overview' => [
        ['path' => 'admin/dashboard.php', 'label' => 'Dashboard'],
    ],
    'Academic Structure' => [
        ['path' => 'admin/batches/index.php', 'label' => 'Batches'],
        ['path' => 'admin/courses/index.php', 'label' => 'Courses & Stages'],
        ['path' => 'admin/units/index.php', 'label' => 'Units'],
        ['path' => 'admin/topics/index.php', 'label' => 'Topics'],
        ['path' => 'admin/sessions/index.php', 'label' => 'Sessions'],
    ],
    'People & Delivery' => [
        ['path' => 'admin/students/index.php', 'label' => 'Students'],
        ['path' => 'admin/lecturers/index.php', 'label' => 'Lecturers'],
        ['path' => 'admin/lecturer_assignments/index.php', 'label' => 'Lecturer Allocation'],
        ['path' => 'admin/enrollments/index.php', 'label' => 'Enrollments'],
    ],
    'System Tools' => [
        ['path' => 'admin/analytics/index.php', 'label' => 'Analytics'],
        ['path' => 'admin/modules/index.php', 'label' => 'Role Modules'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo e(base_url('assets/css/app.css')); ?>" rel="stylesheet">
</head>
<body class="<?php echo e($bodyClass); ?>">
<div class="admin-shell">
    <aside class="admin-sidebar theme-admin">
        <div>
            <a href="<?php echo e(base_url('admin/dashboard.php')); ?>" class="brand-link text-decoration-none">
                <span class="brand-badge">HLH</span>
                <div class="brand-copy">
                    <div class="fw-semibold"><?php echo e(APP_NAME); ?></div>
                    <div class="small text-white-50">Admin Panel</div>
                </div>
            </a>

            <nav class="nav flex-column gap-2 mt-4">
                <?php foreach ($adminNavSections as $sectionTitle => $sectionItems): ?>
                    <div class="sidebar-section-label small text-uppercase fw-semibold mt-3 mb-1 px-2">
                        <?php echo e($sectionTitle); ?>
                    </div>
                    <?php foreach ($sectionItems as $sectionItem): ?>
                        <?php if (!isset($adminNavByPath[$sectionItem['path']])) { continue; } ?>
                        <?php $item = $adminNavByPath[$sectionItem['path']]; ?>
                        <a href="<?php echo e(base_url($item['path'])); ?>" class="sidebar-link <?php echo is_active_page($item['path']) ? 'active' : ''; ?>">
                            <i class="bi <?php echo e($item['icon']); ?>"></i>
                            <span><?php echo e($sectionItem['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="sidebar-footer">
            <div class="small text-white-50 mb-1">Signed in as</div>
            <div class="fw-semibold text-white"><?php echo e(full_name((array) $user)); ?></div>
            <div class="small text-white-50 mb-3"><?php echo e(role_label((string) user_role())); ?></div>
            <a href="<?php echo e(base_url('auth/logout.php')); ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="admin-content">
        <header class="content-topbar topbar-panel">
            <div class="topbar-grid w-100 align-items-center">
                <div>
                    <p class="eyebrow mb-2">System Overview</p>
                    <h1 class="topbar-title"><?php echo e($pageHeading); ?></h1>
                    <?php if ($pageDescription !== ''): ?>
                        <p class="topbar-description mb-0"><?php echo e($pageDescription); ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-card">
                    <div class="profile-chip mb-2"><i class="bi bi-shield-check"></i>System Admin</div>
                    <div class="fw-semibold"><?php echo e(full_name((array) $user)); ?></div>
                    <div class="small text-muted">Login time: <?php echo e(format_datetime($user['login_time'] ?? null)); ?></div>
                </div>
            </div>
        </header>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> border-0 shadow-sm rounded-4">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>
