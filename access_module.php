<?php

declare(strict_types=1);

function module_action_columns(): array
{
    return ['view', 'create', 'edit', 'delete', 'manage'];
}

function module_catalog(): array
{
    return [
        'admin_dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Admin overview and setup summary.',
            'path' => 'admin/dashboard.php',
            'icon' => 'bi-grid-fill',
            'sort_order' => 10,
            'actions' => ['view'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'student_accounts' => [
            'label' => 'Student Accounts',
            'description' => 'Manage student account records.',
            'path' => 'admin/students/index.php',
            'icon' => 'bi-person-plus-fill',
            'sort_order' => 20,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'lecturers' => [
            'label' => 'Lecturers',
            'description' => 'Manage lecturer records.',
            'path' => 'admin/lecturers/index.php',
            'icon' => 'bi-person-workspace',
            'sort_order' => 30,
            'actions' => ['view', 'create', 'edit', 'delete'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'courses' => [
            'label' => 'Courses',
            'description' => 'Manage course structure and setup.',
            'path' => 'admin/courses/index.php',
            'icon' => 'bi-journal-bookmark-fill',
            'sort_order' => 40,
            'actions' => ['view', 'create', 'edit', 'delete'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'units' => [
            'label' => 'Units',
            'description' => 'Manage units within courses.',
            'path' => 'admin/units/index.php',
            'icon' => 'bi-diagram-3-fill',
            'sort_order' => 50,
            'actions' => ['view', 'create', 'edit', 'delete'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'unit_lecturer_assignment' => [
            'label' => 'Unit Lecturer Assignment',
            'description' => 'Assign lecturers to unit delivery.',
            'path' => 'admin/lecturer_assignments/index.php',
            'icon' => 'bi-person-badge-fill',
            'sort_order' => 60,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'topics' => [
            'label' => 'Topics',
            'description' => 'Manage course topics.',
            'path' => 'admin/topics/index.php',
            'icon' => 'bi-diagram-2-fill',
            'sort_order' => 70,
            'actions' => ['view', 'create', 'edit', 'delete'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'sessions' => [
            'label' => 'Sessions',
            'description' => 'Manage learning sessions.',
            'path' => 'admin/sessions/index.php',
            'icon' => 'bi-collection-play-fill',
            'sort_order' => 80,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'batches' => [
            'label' => 'Batches',
            'description' => 'Manage learner intake batches.',
            'path' => 'admin/batches/index.php',
            'icon' => 'bi-layers-fill',
            'sort_order' => 90,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'enrollments' => [
            'label' => 'Enrollments',
            'description' => 'Manage student enrollments.',
            'path' => 'admin/enrollments/index.php',
            'icon' => 'bi-person-lines-fill',
            'sort_order' => 100,
            'actions' => ['view', 'create', 'edit', 'delete'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Review platform analytics.',
            'path' => 'admin/analytics/index.php',
            'icon' => 'bi-bar-chart-line-fill',
            'sort_order' => 110,
            'actions' => ['view'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'role_modules' => [
            'label' => 'Role Modules',
            'description' => 'Assign modules to roles and control submodule actions.',
            'path' => 'admin/modules/index.php',
            'icon' => 'bi-shield-lock-fill',
            'sort_order' => 120,
            'actions' => ['view', 'manage'],
            'default_roles' => ['system_admin'],
            'is_visible_nav' => true,
        ],
        'technical_dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Technical officer overview.',
            'path' => 'technical_officer/dashboard.php',
            'icon' => 'bi-speedometer2',
            'sort_order' => 210,
            'actions' => ['view'],
            'default_roles' => ['technical_officer'],
            'is_visible_nav' => true,
        ],
        'unlock_queue' => [
            'label' => 'Unlock Queue',
            'description' => 'Control which sessions are released to students.',
            'path' => 'technical_officer/sessions/index.php?focus=unlock_queue',
            'icon' => 'bi-unlock-fill',
            'sort_order' => 220,
            'actions' => ['view', 'edit'],
            'default_roles' => ['technical_officer'],
            'is_visible_nav' => true,
        ],
        'video_links' => [
            'label' => 'Video Links',
            'description' => 'Update session video links and embed details.',
            'path' => 'technical_officer/sessions/index.php?focus=video_links',
            'icon' => 'bi-camera-video-fill',
            'sort_order' => 230,
            'actions' => ['view', 'edit'],
            'default_roles' => ['technical_officer'],
            'is_visible_nav' => true,
        ],
        'operations_log' => [
            'label' => 'Operations Log',
            'description' => 'Manage operational session resources and release notes.',
            'path' => 'technical_officer/sessions/index.php?focus=operations_log',
            'icon' => 'bi-clipboard-data-fill',
            'sort_order' => 240,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['technical_officer'],
            'is_visible_nav' => true,
        ],
        'student_access' => [
            'label' => 'Student Access',
            'description' => 'Monitor student-facing release readiness.',
            'path' => 'technical_officer/dashboard.php?focus=student_access',
            'icon' => 'bi-person-video3',
            'sort_order' => 250,
            'actions' => ['view'],
            'default_roles' => ['technical_officer'],
            'is_visible_nav' => true,
        ],
        'lecturer_dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Lecturer overview.',
            'path' => 'teacher/dashboard.php',
            'icon' => 'bi-easel2-fill',
            'sort_order' => 310,
            'actions' => ['view'],
            'default_roles' => ['teacher'],
            'is_visible_nav' => true,
        ],
        'my_units' => [
            'label' => 'My Units',
            'description' => 'View assigned units and manage lecturer resources inside that scope.',
            'path' => 'teacher/units/index.php',
            'icon' => 'bi-journal-richtext',
            'sort_order' => 320,
            'actions' => ['view', 'create', 'edit'],
            'default_roles' => ['teacher'],
            'is_visible_nav' => true,
        ],
        'student_dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Student overview.',
            'path' => 'student/dashboard.php',
            'icon' => 'bi-house-door-fill',
            'sort_order' => 410,
            'actions' => ['view'],
            'default_roles' => ['student'],
            'is_visible_nav' => true,
        ],
        'my_courses' => [
            'label' => 'My Units',
            'description' => 'View units from active enrollments.',
            'path' => 'student/courses/index.php?focus=my_units',
            'icon' => 'bi-journal-richtext',
            'sort_order' => 420,
            'actions' => ['view'],
            'default_roles' => ['student'],
            'is_visible_nav' => true,
        ],
        'unlocked_sessions' => [
            'label' => 'Unlocked Sessions',
            'description' => 'View and open unlocked student sessions.',
            'path' => 'student/courses/index.php?focus=unlocked_sessions',
            'icon' => 'bi-play-circle-fill',
            'sort_order' => 430,
            'actions' => ['view'],
            'default_roles' => ['student'],
            'is_visible_nav' => false,
        ],
        'locked_sessions' => [
            'label' => 'Locked Sessions',
            'description' => 'View student sessions still waiting for release.',
            'path' => 'student/courses/index.php?focus=locked_sessions',
            'icon' => 'bi-lock-fill',
            'sort_order' => 440,
            'actions' => ['view'],
            'default_roles' => ['student'],
            'is_visible_nav' => false,
        ],
        'my_progress' => [
            'label' => 'My Progress',
            'description' => 'Track and update student learning progress.',
            'path' => 'student/courses/index.php?focus=my_progress',
            'icon' => 'bi-graph-up-arrow',
            'sort_order' => 450,
            'actions' => ['view', 'edit'],
            'default_roles' => ['student'],
            'is_visible_nav' => false,
        ],
        'student_profile' => [
            'label' => 'My Profile',
            'description' => 'View and edit student profile details.',
            'path' => 'student/profile.php',
            'icon' => 'bi-person-circle',
            'sort_order' => 460,
            'actions' => ['view', 'edit'],
            'default_roles' => ['student'],
            'is_visible_nav' => true,
        ],
    ];
}

function module_route_permissions(): array
{
    return [
        'admin/dashboard.php' => [
            ['module' => 'admin_dashboard', 'actions' => ['view']],
        ],
        'admin/students/index.php' => [
            ['module' => 'student_accounts', 'actions' => ['view']],
        ],
        'admin/students/form.php' => [
            ['module' => 'student_accounts', 'actions' => ['create', 'edit']],
        ],
        'admin/lecturers/index.php' => [
            ['module' => 'lecturers', 'actions' => ['view']],
        ],
        'admin/lecturers/create.php' => [
            ['module' => 'lecturers', 'actions' => ['create', 'edit']],
        ],
        'admin/lecturers/form.php' => [
            ['module' => 'lecturers', 'actions' => ['create', 'edit']],
        ],
        'admin/courses/index.php' => [
            ['module' => 'courses', 'actions' => ['view']],
        ],
        'admin/courses/form.php' => [
            ['module' => 'courses', 'actions' => ['create', 'edit']],
        ],
        'admin/units/index.php' => [
            ['module' => 'units', 'actions' => ['view']],
        ],
        'admin/units/form.php' => [
            ['module' => 'units', 'actions' => ['create', 'edit']],
        ],
        'admin/lecturer_assignments/index.php' => [
            ['module' => 'unit_lecturer_assignment', 'actions' => ['view']],
        ],
        'admin/lecturer_assignments/create.php' => [
            ['module' => 'unit_lecturer_assignment', 'actions' => ['create', 'edit']],
        ],
        'admin/topics/index.php' => [
            ['module' => 'topics', 'actions' => ['view']],
        ],
        'admin/topics/form.php' => [
            ['module' => 'topics', 'actions' => ['create', 'edit']],
        ],
        'admin/sessions/index.php' => [
            ['module' => 'sessions', 'actions' => ['view']],
        ],
        'admin/sessions/create.php' => [
            ['module' => 'sessions', 'actions' => ['create', 'edit']],
        ],
        'admin/batches/index.php' => [
            ['module' => 'batches', 'actions' => ['view']],
        ],
        'admin/enrollments/index.php' => [
            ['module' => 'enrollments', 'actions' => ['view']],
        ],
        'admin/enrollments/create.php' => [
            ['module' => 'enrollments', 'actions' => ['create', 'edit']],
        ],
        'admin/analytics/index.php' => [
            ['module' => 'analytics', 'actions' => ['view']],
        ],
        'admin/modules/index.php' => [
            ['module' => 'role_modules', 'actions' => ['view']],
        ],
        'admin/modules/role.php' => [
            ['module' => 'role_modules', 'actions' => ['view']],
        ],
        'technical_officer/dashboard.php' => [
            ['module' => 'technical_dashboard', 'actions' => ['view']],
            ['module' => 'student_access', 'actions' => ['view']],
        ],
        'technical_officer/sessions/index.php' => [
            ['module' => 'unlock_queue', 'actions' => ['view']],
            ['module' => 'video_links', 'actions' => ['view']],
            ['module' => 'operations_log', 'actions' => ['view']],
        ],
        'teacher/dashboard.php' => [
            ['module' => 'lecturer_dashboard', 'actions' => ['view']],
        ],
        'teacher/units/index.php' => [
            ['module' => 'my_units', 'actions' => ['view']],
        ],
        'teacher/units/view.php' => [
            ['module' => 'my_units', 'actions' => ['view']],
        ],
        'teacher/sessions/view.php' => [
            ['module' => 'my_units', 'actions' => ['view', 'create', 'edit']],
        ],
        'teacher/resources/index.php' => [
            ['module' => 'my_units', 'actions' => ['view', 'create', 'edit']],
        ],
        'student/dashboard.php' => [
            ['module' => 'student_dashboard', 'actions' => ['view']],
        ],
        'student/courses/index.php' => [
            ['module' => 'my_courses', 'actions' => ['view']],
            ['module' => 'unlocked_sessions', 'actions' => ['view']],
            ['module' => 'locked_sessions', 'actions' => ['view']],
            ['module' => 'my_progress', 'actions' => ['view']],
        ],
        'student/courses/view.php' => [
            ['module' => 'my_courses', 'actions' => ['view']],
            ['module' => 'unlocked_sessions', 'actions' => ['view']],
            ['module' => 'locked_sessions', 'actions' => ['view']],
            ['module' => 'my_progress', 'actions' => ['view']],
        ],
        'student/sessions/view.php' => [
            ['module' => 'unlocked_sessions', 'actions' => ['view']],
            ['module' => 'my_progress', 'actions' => ['view']],
        ],
        'student/profile.php' => [
            ['module' => 'student_profile', 'actions' => ['view']],
        ],
        'actions/admin/save_student.php' => [
            ['module' => 'student_accounts', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/save_lecturer.php' => [
            ['module' => 'lecturers', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/delete_lecturer.php' => [
            ['module' => 'lecturers', 'actions' => ['delete']],
        ],
        'actions/admin/save_course.php' => [
            ['module' => 'courses', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/delete_course.php' => [
            ['module' => 'courses', 'actions' => ['delete']],
        ],
        'actions/admin/save_unit.php' => [
            ['module' => 'units', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/delete_unit.php' => [
            ['module' => 'units', 'actions' => ['delete']],
        ],
        'actions/admin/save_lecturer_assignment.php' => [
            ['module' => 'unit_lecturer_assignment', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/save_topic.php' => [
            ['module' => 'topics', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/delete_topic.php' => [
            ['module' => 'topics', 'actions' => ['delete']],
        ],
        'actions/admin/save_session.php' => [
            ['module' => 'sessions', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/save_batch.php' => [
            ['module' => 'batches', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/save_enrollment.php' => [
            ['module' => 'enrollments', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/delete_enrollment.php' => [
            ['module' => 'enrollments', 'actions' => ['delete']],
        ],
        'actions/admin/preview_enrollment_import.php' => [
            ['module' => 'enrollments', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/confirm_enrollment_import.php' => [
            ['module' => 'enrollments', 'actions' => ['create', 'edit']],
        ],
        'actions/admin/clear_enrollment_import_preview.php' => [
            ['module' => 'enrollments', 'actions' => ['edit']],
        ],
        'actions/admin/save_role_modules.php' => [
            ['module' => 'role_modules', 'actions' => ['manage']],
        ],
        'actions/teacher/save_resource.php' => [
            ['module' => 'my_units', 'actions' => ['create', 'edit']],
        ],
        'actions/technical_officer/save_session_operations.php' => [
            ['module' => 'unlock_queue', 'actions' => ['edit']],
            ['module' => 'video_links', 'actions' => ['edit']],
        ],
        'actions/technical_officer/save_resource.php' => [
            ['module' => 'operations_log', 'actions' => ['create', 'edit']],
        ],
        'actions/student/mark_watched.php' => [
            ['module' => 'my_progress', 'actions' => ['edit']],
        ],
        'actions/student/download_resource.php' => [
            ['module' => 'unlocked_sessions', 'actions' => ['view']],
        ],
        'actions/student/save_profile.php' => [
            ['module' => 'student_profile', 'actions' => ['edit']],
        ],
    ];
}

function app_relative_path(?string $path = null): string
{
    $rawPath = $path ?? current_path();
    $resolvedPath = parse_url($rawPath, PHP_URL_PATH) ?: '';
    $basePath = parse_url(APP_URL, PHP_URL_PATH) ?: '';

    if ($basePath !== '' && str_starts_with($resolvedPath, $basePath)) {
        $resolvedPath = substr($resolvedPath, strlen($basePath));
    }

    return ltrim((string) $resolvedPath, '/');
}

function module_default_permission_values(array $module, string $roleName): array
{
    $actions = $module['actions'] ?? [];
    $allowed = in_array($roleName, $module['default_roles'] ?? [], true);
    $values = [];

    foreach (module_action_columns() as $action) {
        $values[$action] = $allowed && in_array($action, $actions, true) ? 1 : 0;
    }

    return $values;
}

function bootstrap_module_access(PDO $pdo): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_modules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(150) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            path VARCHAR(255) NOT NULL,
            icon VARCHAR(100) DEFAULT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            available_actions VARCHAR(255) NOT NULL,
            is_visible_nav TINYINT(1) NOT NULL DEFAULT 1,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_app_modules_sort_order (sort_order),
            INDEX idx_app_modules_is_visible_nav (is_visible_nav),
            INDEX idx_app_modules_is_active (is_active)
        ) ENGINE=InnoDB"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS role_module_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_id INT UNSIGNED NOT NULL,
            module_id INT UNSIGNED NOT NULL,
            can_view TINYINT(1) NOT NULL DEFAULT 0,
            can_create TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            can_manage TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_role_module_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_role_module_permissions_module FOREIGN KEY (module_id) REFERENCES app_modules(id) ON DELETE CASCADE,
            CONSTRAINT uq_role_module_permissions UNIQUE (role_id, module_id),
            INDEX idx_role_module_permissions_role (role_id),
            INDEX idx_role_module_permissions_module (module_id)
        ) ENGINE=InnoDB"
    );

    $catalog = module_catalog();
    $upsertModule = $pdo->prepare(
        'INSERT INTO app_modules (slug, label, description, path, icon, sort_order, available_actions, is_visible_nav, is_active)
         VALUES (:slug, :label, :description, :path, :icon, :sort_order, :available_actions, :is_visible_nav, 1)
         ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            description = VALUES(description),
            path = VALUES(path),
            icon = VALUES(icon),
            sort_order = VALUES(sort_order),
            available_actions = VALUES(available_actions),
            is_visible_nav = VALUES(is_visible_nav),
            is_active = VALUES(is_active)'
    );

    foreach ($catalog as $slug => $module) {
        $upsertModule->execute([
            'slug' => $slug,
            'label' => $module['label'],
            'description' => $module['description'] ?? null,
            'path' => $module['path'],
            'icon' => $module['icon'] ?? null,
            'sort_order' => $module['sort_order'] ?? 1,
            'available_actions' => implode(',', $module['actions'] ?? ['view']),
            'is_visible_nav' => !empty($module['is_visible_nav']) ? 1 : 0,
        ]);
    }

    $roles = $pdo->query('SELECT id, name FROM roles')->fetchAll();
    $modules = $pdo->query('SELECT id, slug FROM app_modules')->fetchAll();
    $existing = $pdo->query('SELECT role_id, module_id FROM role_module_permissions')->fetchAll();

    $existingMap = [];
    foreach ($existing as $row) {
        $existingMap[(int) $row['role_id'] . ':' . (int) $row['module_id']] = true;
    }

    $insertPermission = $pdo->prepare(
        'INSERT INTO role_module_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete, can_manage)
         VALUES (:role_id, :module_id, :can_view, :can_create, :can_edit, :can_delete, :can_manage)'
    );

    foreach ($roles as $role) {
        foreach ($modules as $moduleRow) {
            $key = (int) $role['id'] . ':' . (int) $moduleRow['id'];
            if (isset($existingMap[$key])) {
                continue;
            }

            $module = $catalog[$moduleRow['slug']] ?? null;
            if ($module === null) {
                continue;
            }

            $defaults = module_default_permission_values($module, (string) $role['name']);
            $insertPermission->execute([
                'role_id' => (int) $role['id'],
                'module_id' => (int) $moduleRow['id'],
                'can_view' => $defaults['view'],
                'can_create' => $defaults['create'],
                'can_edit' => $defaults['edit'],
                'can_delete' => $defaults['delete'],
                'can_manage' => $defaults['manage'],
            ]);
        }
    }

    $bootstrapped = true;
}

function role_module_permission_map(PDO $pdo, string $roleName): array
{
    static $cache = [];

    if (isset($cache[$roleName])) {
        return $cache[$roleName];
    }

    bootstrap_module_access($pdo);

    $statement = $pdo->prepare(
        "SELECT app_modules.slug, app_modules.available_actions, app_modules.is_active,
                role_module_permissions.can_view, role_module_permissions.can_create,
                role_module_permissions.can_edit, role_module_permissions.can_delete,
                role_module_permissions.can_manage
         FROM role_module_permissions
         INNER JOIN roles ON roles.id = role_module_permissions.role_id
         INNER JOIN app_modules ON app_modules.id = role_module_permissions.module_id
         WHERE roles.name = :role_name"
    );
    $statement->execute(['role_name' => $roleName]);

    $permissions = [];
    foreach ($statement->fetchAll() as $row) {
        $permissions[(string) $row['slug']] = [
            'actions' => array_filter(explode(',', (string) $row['available_actions'])),
            'is_active' => (int) $row['is_active'] === 1,
            'can_view' => (int) $row['can_view'] === 1,
            'can_create' => (int) $row['can_create'] === 1,
            'can_edit' => (int) $row['can_edit'] === 1,
            'can_delete' => (int) $row['can_delete'] === 1,
            'can_manage' => (int) $row['can_manage'] === 1,
        ];
    }

    $cache[$roleName] = $permissions;

    return $permissions;
}

function role_nav_modules(PDO $pdo, string $roleName): array
{
    bootstrap_module_access($pdo);

    $statement = $pdo->prepare(
        "SELECT app_modules.slug, app_modules.label, app_modules.path, app_modules.icon, app_modules.sort_order
         FROM role_module_permissions
         INNER JOIN roles ON roles.id = role_module_permissions.role_id
         INNER JOIN app_modules ON app_modules.id = role_module_permissions.module_id
         WHERE roles.name = :role_name
           AND app_modules.is_active = 1
           AND app_modules.is_visible_nav = 1
           AND role_module_permissions.can_view = 1
         ORDER BY app_modules.sort_order ASC, app_modules.label ASC"
    );
    $statement->execute(['role_name' => $roleName]);

    return $statement->fetchAll();
}

function first_accessible_module_path(PDO $pdo, string $roleName): ?string
{
    $modules = role_nav_modules($pdo, $roleName);

    if ($modules === []) {
        return null;
    }

    return (string) ($modules[0]['path'] ?? '');
}

function user_has_module_permission(PDO $pdo, string $roleName, string $moduleSlug, string $action = 'view'): bool
{
    $catalog = module_catalog();
    $module = $catalog[$moduleSlug] ?? null;

    if ($module === null) {
        return false;
    }

    if (!in_array($action, module_action_columns(), true)) {
        return false;
    }

    if (!in_array($action, $module['actions'] ?? [], true)) {
        return false;
    }

    $permissions = role_module_permission_map($pdo, $roleName);
    $modulePermissions = $permissions[$moduleSlug] ?? null;

    if ($modulePermissions === null || !$modulePermissions['is_active']) {
        return false;
    }

    return !empty($modulePermissions['can_' . $action]);
}

function current_user_can(string $moduleSlug, string $action = 'view', ?PDO $pdo = null): bool
{
    $pdo = $pdo ?? app_pdo();
    $roleName = user_role();

    if (!$pdo instanceof PDO || $roleName === null) {
        return false;
    }

    return user_has_module_permission($pdo, $roleName, $moduleSlug, $action);
}

function route_permission_entries_for_path(?string $path = null): array
{
    $routeMap = module_route_permissions();
    $routeKey = app_relative_path($path);

    return $routeMap[$routeKey] ?? [];
}

function current_user_can_access_route(?PDO $pdo = null, ?string $path = null): bool
{
    $pdo = $pdo ?? app_pdo();
    $roleName = user_role();

    if (!$pdo instanceof PDO || $roleName === null) {
        return false;
    }

    $entries = route_permission_entries_for_path($path);
    if ($entries === []) {
        return false;
    }

    foreach ($entries as $entry) {
        $moduleSlug = (string) ($entry['module'] ?? '');
        $actions = $entry['actions'] ?? ['view'];

        foreach ($actions as $action) {
            if (user_has_module_permission($pdo, $roleName, $moduleSlug, (string) $action)) {
                return true;
            }
        }
    }

    return false;
}

function route_permission_modules(?string $path = null): array
{
    $entries = route_permission_entries_for_path($path);
    $modules = [];

    foreach ($entries as $entry) {
        if (!empty($entry['module'])) {
            $modules[] = (string) $entry['module'];
        }
    }

    return array_values(array_unique($modules));
}
