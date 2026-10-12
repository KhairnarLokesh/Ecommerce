<?php
/**
 * Kalika Enterprises - Vercel Serverless Gateway Router
 * Routes incoming web requests to the appropriate public/ or admin/ PHP handler.
 */

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';
$path = trim($path, '/');

// Base directory is project root
$rootDir = dirname(__DIR__);

// Route resolution
if ($path === '' || $path === 'index.php') {
    $targetFile = $rootDir . '/public/index.php';
} elseif (str_starts_with($path, 'admin/')) {
    $subPath = substr($path, 6);
    if ($subPath === '' || $subPath === 'index.php') {
        $targetFile = $rootDir . '/admin/index.php';
    } else {
        $targetFile = $rootDir . '/admin/' . $subPath;
        if (!str_ends_with($targetFile, '.php') && is_file($targetFile . '.php')) {
            $targetFile .= '.php';
        }
    }
} elseif (str_starts_with($path, 'public/')) {
    $subPath = substr($path, 7);
    if ($subPath === '' || $subPath === 'index.php') {
        $targetFile = $rootDir . '/public/index.php';
    } else {
        $targetFile = $rootDir . '/public/' . $subPath;
        if (!str_ends_with($targetFile, '.php') && is_file($targetFile . '.php')) {
            $targetFile .= '.php';
        }
    }
} else {
    // Map top-level paths (e.g. /products.php, /cart.php) to public/
    $targetFile = $rootDir . '/public/' . $path;
    if (!str_ends_with($targetFile, '.php') && is_file($targetFile . '.php')) {
        $targetFile .= '.php';
    }
}

if (is_file($targetFile)) {
    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

// Fallback to public storefront
if (is_file($rootDir . '/public/index.php')) {
    $_SERVER['SCRIPT_FILENAME'] = $rootDir . '/public/index.php';
    chdir($rootDir . '/public');
    require $rootDir . '/public/index.php';
    exit;
}

http_response_code(404);
echo "404 Not Found";
