<?php
require_once __DIR__ . '/vendor/autoload.php';

use Gemvc\Core\Bootstrap;
use Gemvc\Http\NoCors;
use Gemvc\Http\StandardHttpRequest;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->overload(__DIR__ . '/.env');
$_ENV['SMOKE_MODE'] = 'classic';
NoCors::apache();
$webserver = new StandardHttpRequest();
new Bootstrap($webserver->request);
