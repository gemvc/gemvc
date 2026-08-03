<?php
require_once __DIR__ . '/vendor/autoload.php';

use Gemvc\Core\FrankenPhpWorker;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->overload(__DIR__ . '/.env');
$_ENV['APP_ENV_SERVER'] = 'frankenphp';
$_ENV['SMOKE_MODE'] = 'worker';

(new FrankenPhpWorker())->run();
