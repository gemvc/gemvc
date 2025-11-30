<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load test environment if .env.testing exists
if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
    $dotenv->load();
} elseif (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env');
    $dotenv->load();
}

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set test environment variables if not set
if (!isset($_ENV['APP_ENV'])) {
    $_ENV['APP_ENV'] = 'testing';
}

if (!isset($_ENV['TOKEN_SECRET'])) {
    $_ENV['TOKEN_SECRET'] = 'test-secret-key-for-testing-only';
}

