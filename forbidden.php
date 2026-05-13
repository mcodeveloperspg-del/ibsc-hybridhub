<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$flash = flash_message();
$forbiddenData = $_SESSION['forbidden'] ?? null;
unset($_SESSION['forbidden']);

$allowedEntries = [];
$attemptedPath = '/';

if (is_array($forbiddenData)) {
    $allowedEntries = $forbiddenData['allowed_roles'] ?? [];
    $attemptedPath = $forbiddenData['attempted_path'] ?? '/';
}

$catalog = module_catalog();
$allowedLabels = [];
foreach ($allowedEntries as $entry) {
    if (isset($catalog[$entry])) {
        $allowedLabels[] = $catalog[$entry]['label'];
        continue;
    }
    $allowedLabels[] = role_label((string) $entry);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?php echo e(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(150deg, #fff5f5 0%, #f8f0ff 100%);
        }
        .forbidden-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 22px 50px rgba(60, 15, 40, 0.10);
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card forbidden-card">
                    <div class="card-body p-4 p-md-5">
                        <span class="badge text-bg-danger mb-3">403 ACCESS DENIED</span>
                        <h1 class="h2 mb-3">You do not have permission to open this page</h1>
                        <p class="text-muted mb-4">
                            The page you tried to open is protected by the module access rules in the Hybrid Learning Hub.
                        </p>

                        <?php if ($flash !== null): ?>
                            <div class="alert alert-<?php echo e($flash['type']); ?>">
                                <?php echo e($flash['message']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <h2 class="h6">Attempted Path</h2>
                                    <p class="mb-0"><code><?php echo e($attemptedPath); ?></code></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <h2 class="h6">Allowed Access</h2>
                                    <?php if ($allowedLabels !== []): ?>
                                        <p class="mb-0"><?php echo e(implode(', ', $allowedLabels)); ?></p>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">No access details are available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if (is_logged_in()): ?>
                                <a href="<?php echo e(role_dashboard_path((string) user_role())); ?>" class="btn btn-primary">Go to My Dashboard</a>
                                <a href="<?php echo e(base_url('auth/logout.php')); ?>" class="btn btn-outline-secondary">Logout</a>
                            <?php else: ?>
                                <a href="<?php echo e(base_url('auth/login.php')); ?>" class="btn btn-primary">Go to Login</a>
                                <a href="<?php echo e(base_url('index.php')); ?>" class="btn btn-outline-secondary">Back to Home</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
