<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

bootstrap_module_access($pdo);
$flash = flash_message();
$roleId = (int) ($_GET['role_id'] ?? 0);

$roleStatement = $pdo->prepare('SELECT id, name, description FROM roles WHERE id = :id LIMIT 1');
$roleStatement->execute(['id' => $roleId]);
$role = $roleStatement->fetch();
if (!$role) {
    flash_message('The selected role could not be found.', 'warning');
    redirect(base_url('admin/modules/index.php'));
}

$modules = $pdo->query("SELECT id, slug, label, description, path, available_actions, sort_order FROM app_modules WHERE is_active = 1 ORDER BY sort_order ASC, label ASC")->fetchAll();
$permissionRows = $pdo->prepare('SELECT module_id, can_view, can_create, can_edit, can_delete, can_manage FROM role_module_permissions WHERE role_id = :role_id');
$permissionRows->execute(['role_id' => $roleId]);
$permissionMap = [];
foreach ($permissionRows->fetchAll() as $row) {
    $permissionMap[(int) $row['module_id']] = $row;
}

function module_group_title(string $slug): string
{
    return match (true) {
        str_starts_with($slug, 'admin_'), in_array($slug, ['student_accounts', 'lecturers', 'courses', 'units', 'unit_lecturer_assignment', 'topics', 'sessions', 'batches', 'enrollments', 'analytics', 'role_modules'], true) => 'Admin Workspace',
        in_array($slug, ['technical_dashboard', 'unlock_queue', 'video_links', 'operations_log', 'student_access'], true) => 'Operations Workspace',
        in_array($slug, ['lecturer_dashboard', 'my_units'], true) => 'Lecturer Workspace',
        in_array($slug, ['student_dashboard', 'my_courses', 'unlocked_sessions', 'locked_sessions', 'my_progress'], true) => 'Student Workspace',
        default => 'Other Modules',
    };
}

function action_label(string $action): string
{
    return match ($action) {
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'manage' => 'Manage',
        default => ucfirst($action),
    };
}

$groupedModules = [];
foreach ($modules as $module) {
    $groupedModules[module_group_title((string) $module['slug'])][] = $module;
}

$pageTitle = 'Role Modules - ' . APP_NAME;
$pageHeading = role_label((string) $role['name']) . ' Modules';
$pageDescription = 'Review what is currently assigned to this role, add new modules, and adjust submodule actions in grouped sections.';

require_once __DIR__ . '/../../includes/layouts/admin_header.php';
?>
<div class="surface-card soft-tint p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="eyebrow mb-2"><?php echo e((string) $role['name']); ?></div>
            <h2 class="section-title mb-1"><?php echo e(role_label((string) $role['name'])); ?></h2>
            <p class="section-note mb-0">Each workspace now opens as a dropdown section. Expand only the group you want to manage, then review assigned and available modules inside it.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(base_url('admin/modules/index.php')); ?>" class="btn btn-outline-secondary">Back to Roles</a>
        </div>
    </div>
</div>

<form action="<?php echo e(base_url('actions/admin/save_role_modules.php')); ?>" method="POST" class="d-grid gap-3">
    <input type="hidden" name="role_id" value="<?php echo e((string) $role['id']); ?>">

    <?php $groupIndex = 0; ?>
    <?php foreach ($groupedModules as $groupTitle => $groupModules): ?>
        <?php
        $assigned = [];
        $available = [];
        foreach ($groupModules as $module) {
            $row = $permissionMap[(int) $module['id']] ?? null;
            $isAssigned = $row !== null && (
                (int) ($row['can_view'] ?? 0) === 1
                || (int) ($row['can_create'] ?? 0) === 1
                || (int) ($row['can_edit'] ?? 0) === 1
                || (int) ($row['can_delete'] ?? 0) === 1
                || (int) ($row['can_manage'] ?? 0) === 1
            );
            if ($isAssigned) {
                $assigned[] = $module;
            } else {
                $available[] = $module;
            }
        }
        $groupIndex++;
        ?>
        <details class="surface-card" <?php echo $groupIndex === 1 ? 'open' : ''; ?>>
            <summary class="p-4 d-flex flex-wrap justify-content-between align-items-center gap-3" style="cursor: pointer; list-style: none;">
                <div>
                    <h2 class="h4 mb-1"><?php echo e($groupTitle); ?></h2>
                    <p class="text-muted mb-0">Expand to manage the assigned and available modules in this workspace.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="status-chip success"><i class="bi bi-check-circle-fill"></i><?php echo e((string) count($assigned)); ?> assigned</span>
                    <span class="status-chip warning"><i class="bi bi-plus-circle-fill"></i><?php echo e((string) count($available)); ?> available</span>
                </div>
            </summary>

            <div class="px-4 pb-4">
                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="border rounded-4 p-3 h-100 bg-light">
                            <div class="fw-semibold mb-3">Currently Assigned</div>
                            <?php if ($assigned === []): ?>
                                <div class="text-muted small">No modules from this group are assigned to the role yet.</div>
                            <?php else: ?>
                                <div class="d-grid gap-3">
                                    <?php foreach ($assigned as $module): ?>
                                        <?php
                                        $row = $permissionMap[(int) $module['id']];
                                        $moduleActions = array_filter(explode(',', (string) $module['available_actions']));
                                        ?>
                                        <div class="border rounded-4 p-3 bg-white">
                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                <div>
                                                    <div class="fw-semibold"><?php echo e((string) $module['label']); ?></div>
                                                    <div class="small text-muted mb-1"><?php echo e((string) ($module['description'] ?? '')); ?></div>
                                                    <div class="small text-muted"><code><?php echo e((string) $module['path']); ?></code></div>
                                                </div>
                                                <span class="status-chip success"><i class="bi bi-check2-square"></i>Assigned</span>
                                            </div>
                                            <div class="row g-2">
                                                <?php foreach (module_action_columns() as $action): ?>
                                                    <?php if (in_array($action, $moduleActions, true)): ?>
                                                        <?php
                                                        $field = 'can_' . $action;
                                                        $lockedSafety = $role['name'] === 'system_admin' && (
                                                            ($module['slug'] === 'admin_dashboard' && $action === 'view')
                                                            || ($module['slug'] === 'role_modules' && in_array($action, ['view', 'manage'], true))
                                                        );
                                                        ?>
                                                        <div class="col-sm-6 col-lg-4">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="role_<?php echo e((string) $role['id']); ?>_module_<?php echo e((string) $module['id']); ?>_<?php echo e($action); ?>" name="permissions[<?php echo e((string) $role['id']); ?>][<?php echo e((string) $module['id']); ?>][<?php echo e($action); ?>]" value="1" <?php echo (int) $row[$field] === 1 ? 'checked' : ''; ?> <?php echo $lockedSafety ? 'disabled' : ''; ?>>
                                                                <label class="form-check-label small" for="role_<?php echo e((string) $role['id']); ?>_module_<?php echo e((string) $module['id']); ?>_<?php echo e($action); ?>"><?php echo e(action_label($action)); ?></label>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-xl-6">
                        <div class="border rounded-4 p-3 h-100 bg-light">
                            <div class="fw-semibold mb-3">Available To Assign</div>
                            <?php if ($available === []): ?>
                                <div class="text-muted small">All modules in this group are already assigned to the role.</div>
                            <?php else: ?>
                                <div class="d-grid gap-3">
                                    <?php foreach ($available as $module): ?>
                                        <?php $moduleActions = array_filter(explode(',', (string) $module['available_actions'])); ?>
                                        <div class="border rounded-4 p-3 bg-white">
                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                <div>
                                                    <div class="fw-semibold"><?php echo e((string) $module['label']); ?></div>
                                                    <div class="small text-muted mb-1"><?php echo e((string) ($module['description'] ?? '')); ?></div>
                                                    <div class="small text-muted"><code><?php echo e((string) $module['path']); ?></code></div>
                                                </div>
                                                <span class="status-chip warning"><i class="bi bi-plus-square"></i>Available</span>
                                            </div>
                                            <div class="small text-muted mb-2">Select <strong>View</strong> to assign the main module, then choose any extra actions this role should also have.</div>
                                            <div class="row g-2">
                                                <?php foreach (module_action_columns() as $action): ?>
                                                    <?php if (in_array($action, $moduleActions, true)): ?>
                                                        <div class="col-sm-6 col-lg-4">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="role_<?php echo e((string) $role['id']); ?>_module_<?php echo e((string) $module['id']); ?>_<?php echo e($action); ?>" name="permissions[<?php echo e((string) $role['id']); ?>][<?php echo e((string) $module['id']); ?>][<?php echo e($action); ?>]" value="1">
                                                                <label class="form-check-label small" for="role_<?php echo e((string) $role['id']); ?>_module_<?php echo e((string) $module['id']); ?>_<?php echo e($action); ?>"><?php echo e(action_label($action)); ?></label>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </details>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mt-2">
        <button type="submit" class="btn btn-primary">Save <?php echo e(role_label((string) $role['name'])); ?> Modules</button>
        <a href="<?php echo e(base_url('admin/modules/index.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
<?php require_once __DIR__ . '/../../includes/layouts/admin_footer.php'; ?>
