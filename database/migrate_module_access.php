<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

bootstrap_module_access($pdo);

echo "Module access tables and defaults are ready.";
