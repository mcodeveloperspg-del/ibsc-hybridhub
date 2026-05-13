<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role(['system_admin']);

if (!is_post_request()) {
    redirect(base_url('admin/modules/index.php'));
}

bootstrap_module_access($pdo);

$permissionsInput = $_POST['permissions'] ?? [];
$selectedRoleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
$roles = $pdo->query('SELECT id, name FROM roles')->fetchAll();
$modules = $pdo->query('SELECT id, slug, available_actions FROM app_modules WHERE is_active = 1')->fetchAll();

$roleNames = [];
foreach ($roles as $role) {
    $roleNames[(int) $role['id']] = (string) $role['name'];
}

if ($selectedRoleId > 0 && !isset($roleNames[$selectedRoleId])) {
    flash_message('The selected role could not be found.', 'warning');
    redirect(base_url('admin/modules/index.php'));
}

$targetRoleIds = $selectedRoleId > 0 ? [$selectedRoleId] : array_keys($roleNames);

$updateStatement = $pdo->prepare(
    'UPDATE role_module_permissions
     SET can_view = :can_view,
         can_create = :can_create,
         can_edit = :can_edit,
         can_delete = :can_delete,
         can_manage = :can_manage
     WHERE role_id = :role_id AND module_id = :module_id'
);

foreach ($modules as $module) {
    $supportedActions = array_filter(explode(',', (string) $module['available_actions']));

    foreach ($targetRoleIds as $roleId) {
        $roleName = $roleNames[$roleId];
        $submitted = $permissionsInput[$roleId][$module['id']] ?? [];
        $values = [
            'view' => 0,
            'create' => 0,
            'edit' => 0,
            'delete' => 0,
            'manage' => 0,
        ];

        foreach (module_action_columns() as $action) {
            if (in_array($action, $supportedActions, true) && isset($submitted[$action])) {
                $values[$action] = 1;
            }
        }

        if ($values['create'] === 1 || $values['edit'] === 1 || $values['delete'] === 1 || $values['manage'] === 1) {
            $values['view'] = 1;
        }

        if ($roleName === 'system_admin') {
            if ($module['slug'] === 'admin_dashboard') {
                $values['view'] = 1;
            }
            if ($module['slug'] === 'role_modules') {
                $values['view'] = 1;
                $values['manage'] = 1;
            }
        }

        $updateStatement->execute([
            'can_view' => $values['view'],
            'can_create' => $values['create'],
            'can_edit' => $values['edit'],
            'can_delete' => $values['delete'],
            'can_manage' => $values['manage'],
            'role_id' => $roleId,
            'module_id' => (int) $module['id'],
        ]);
    }
}

flash_message('Role module permissions updated successfully.', 'success');

if ($selectedRoleId > 0) {
    redirect(base_url('admin/modules/role.php?role_id=' . (string) $selectedRoleId));
}

redirect(base_url('admin/modules/index.php'));
