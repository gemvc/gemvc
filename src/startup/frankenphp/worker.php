<?php
/**
 * GEMVC FrankenPHP worker entry (long-lived).
 *
 * Classic mode uses index.php + Bootstrap (may die after response).
 * Worker mode uses this script + FrankenPhpWorker (never die in the request loop).
 *
 * Enable via Caddyfile.worker or:
 *   FRANKENPHP_CONFIG="worker ./worker.php"
 *
 * @see docs/guides/frankenphp.md
 */
require_once __DIR__ . '/vendor/autoload.php';

use Gemvc\Core\FrankenPhpWorker;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->overload(__DIR__ . '/.env');

// Prefer explicit env; do not rely on SERVER_SOFTWARE alone inside the worker boot phase
if (!isset($_ENV['APP_ENV_SERVER']) || $_ENV['APP_ENV_SERVER'] === '') {
    $_ENV['APP_ENV_SERVER'] = 'frankenphp';
}

$worker = new FrankenPhpWorker();
$worker->run();
