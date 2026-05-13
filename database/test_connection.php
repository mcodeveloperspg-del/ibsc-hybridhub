<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$databaseName = 'Unknown';
$mysqlVersion = 'Unknown';

$statement = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS mysql_version');
$result = $statement->fetch();

if ($result) {
    $databaseName = $result['database_name'] ?? 'Unknown';
    $mysqlVersion = $result['mysql_version'] ?? 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">Database Connection Successful</h1>
                        <p class="text-muted">
                            Your PDO connection is working correctly for the Hybrid Learning Hub project.
                        </p>

                        <table class="table table-bordered align-middle">
                            <tbody>
                                <tr>
                                    <th style="width: 30%;">Database Name</th>
                                    <td><?php echo e($databaseName); ?></td>
                                </tr>
                                <tr>
                                    <th>MySQL/MariaDB Version</th>
                                    <td><?php echo e($mysqlVersion); ?></td>
                                </tr>
                                <tr>
                                    <th>Connection Mode</th>
                                    <td>PDO with exceptions enabled</td>
                                </tr>
                            </tbody>
                        </table>

                        <a href="<?php echo e(base_url('index.php')); ?>" class="btn btn-primary">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
