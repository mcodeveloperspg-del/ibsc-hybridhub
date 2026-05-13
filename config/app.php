<?php

declare(strict_types=1);

if (!function_exists('app_load_local_env')) {
    function app_load_local_env(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $loaded = true;
        $envPath = dirname(__DIR__) . '/.env';
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            $value = trim($value, "\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('app_env')) {
    function app_env(string $key, ?string $default = null): ?string
    {
        app_load_local_env();
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('app_env_bool')) {
    function app_env_bool(string $key, bool $default = false): bool
    {
        $value = app_env($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

if (!function_exists('app_detect_base_url')) {
    function app_detect_base_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $projectDirectory = basename(dirname(__DIR__));
        $basePath = '';

        if ($scriptName !== '') {
            $projectMarker = '/' . $projectDirectory;
            $projectOffset = strpos($scriptName, $projectMarker . '/');

            if ($projectOffset !== false) {
                $basePath = substr($scriptName, 0, $projectOffset + strlen($projectMarker));
            } elseif (str_ends_with($scriptName, $projectMarker)) {
                $basePath = $scriptName;
            } else {
                $basePath = dirname($scriptName);
            }
        }

        if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }

        return $scheme . '://' . $host . rtrim($basePath, '/');
    }
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Hybrid Learning Hub');
}

if (!defined('APP_URL')) {
    define('APP_URL', rtrim((string) app_env('APP_URL', app_detect_base_url()), '/'));
}

if (!defined('APP_ENV')) {
    define('APP_ENV', (string) app_env('APP_ENV', 'local'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', app_env_bool('APP_DEBUG', APP_ENV !== 'production'));
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('UPLOAD_SLIDES_PATH')) {
    define('UPLOAD_SLIDES_PATH', rtrim((string) app_env('UPLOAD_SLIDES_PATH', APP_ROOT . '/uploads/slides'), '/\\') . DIRECTORY_SEPARATOR);
}

if (!defined('UPLOAD_RESOURCES_PATH')) {
    define('UPLOAD_RESOURCES_PATH', rtrim((string) app_env('UPLOAD_RESOURCES_PATH', APP_ROOT . '/uploads/resources'), '/\\') . DIRECTORY_SEPARATOR);
}

if (!defined('UPLOAD_STUDENT_PHOTOS_PATH')) {
    define('UPLOAD_STUDENT_PHOTOS_PATH', rtrim((string) app_env('UPLOAD_STUDENT_PHOTOS_PATH', APP_ROOT . '/uploads/student_photos'), '/\\') . DIRECTORY_SEPARATOR);
}

if (!defined('APP_MAX_UPLOAD_BYTES')) {
    define('APP_MAX_UPLOAD_BYTES', max(1, (int) app_env('APP_MAX_UPLOAD_MB', '20')) * 1024 * 1024);
}

if (!function_exists('app_handle_unexpected_error')) {
    function app_handle_unexpected_error(Throwable $throwable): void
    {
        error_log($throwable::class . ': ' . $throwable->getMessage() . ' in ' . $throwable->getFile() . ':' . $throwable->getLine());

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars((string) $throwable, ENT_QUOTES, 'UTF-8') . '</pre>';
            exit;
        }

        echo 'The application is temporarily unavailable. Please try again later.';
        exit;
    }
}

set_exception_handler('app_handle_unexpected_error');
