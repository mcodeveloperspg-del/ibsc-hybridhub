<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

$user = current_user();
$flash = flash_message();
bootstrap_module_access($pdo);

$roles = $pdo->query(
    "SELECT roles.id, roles.name, roles.description,
            SUM(CASE WHEN role_module_permissions.can_view = 1 THEN 1 ELSE 0 END) AS assigned_modules,
            SUM(CASE WHEN role_module_permissions.can_create = 1 OR role_module_permissions.can_edit = 1 OR role_module_permissions.can_delete = 1 OR role_module_permissions.can_manage = 1 THEN 1 ELSE 0 END) AS elevated_modules
     FROM roles
     LEFT JOIN role_module_permissions ON role_module_permissions.role_id = roles.id
     LEFT JOIN app_modules ON app_modules.id = role_module_permissions.module_id AND app_modules.is_active = 1
     GROUP BY roles.id, roles.name, roles.description
     ORDER BY FIELD(roles.name, 'system_admin', 'technical_officer', 'teacher', 'student'), roles.name ASC"
)->fetchAll();

function role_module_summary(string $roleName): string
{
    return match ($roleName) {
        'system_admin' => 'Open this role to manage setup modules and permission administration.',
        'technical_officer' => 'Open this role to control release operations, links, and operational resources.',
        'teacher' => 'Open this role to manage lecturer-facing unit access and teaching resources.',
        'student' => 'Open this role to manage learning, session release visibility, and progress access.',
        default => 'Open this role to review and adjust its current module assignments.',
    };
}

$pageTitle = 'Role Modules - ' . APP_NAME;
$pageHeading = 'Role Module Assignment';
$pageDescription = 'Choose a role first, then review its assigned and available modules on a dedicated page.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="surface-card soft-tint p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="section-title mb-1">Pick a role to manage</h2>
            <p class="section-note mb-0">Each role opens into its own page so you can focus on what is already assigned, what is still available, and which submodule actions are enabled.</p>
        </div>
        <span class="status-chip info"><i class="bi bi-diagram-3"></i>Grouped module management</span>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($roles as $role): ?>
        <div class="col-lg-6">
            <a href="<?php echo e(base_url('admin/modules/role.php?role_id=' . (string) $role['id'])); ?>" class="text-decoration-none text-reset d-block h-100">
                <div class="surface-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div class="eyebrow mb-2"><?php echo e((string) $role['name']); ?></div>
                            <h2 class="h4 mb-1"><?php echo e(role_label((string) $role['name'])); ?></h2>
                            <p class="text-muted mb-0"><?php echo e(role_module_summary((string) $role['name'])); ?></p>
                        </div>
                        <span class="status-chip <?php echo $role['name'] === 'system_admin' ? 'success' : 'info'; ?>"><i class="bi bi-arrow-right-circle"></i>Open</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="border rounded-4 p-3 bg-light h-100">
                                <div class="small text-muted mb-1">Assigned modules</div>
                                <div class="h4 mb-0"><?php echo e((string) ((int) $role['assigned_modules'])); ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded-4 p-3 bg-light h-100">
                                <div class="small text-muted mb-1">Modules with extra actions</div>
                                <div class="h4 mb-0"><?php echo e((string) ((int) $role['elevated_modules'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
