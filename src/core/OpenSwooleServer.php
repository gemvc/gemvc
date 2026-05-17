<?php

namespace Gemvc\Core;

use Gemvc\Helper\ProjectHelper;
use Gemvc\Http\SwooleRequest;
use Gemvc\Core\SwooleBootstrap;
use Gemvc\Core\SecurityManager;
use Gemvc\Core\HotReloadManager;

/**
 * OpenSwoole HTTP Server Manager
 * 
 * Handles the main OpenSwoole server lifecycle and event management
 */
class OpenSwooleServer
{
    /** @var \OpenSwoole\HTTP\Server */
    private $server;
    private SwooleServerConfig $config;
    private SecurityManager $security;
    private HotReloadManager $hotReload;

    public function __construct()
    {
        $this->validateEnvironment();
        $this->loadDependencies();
        $this->initializeComponents();
        $this->createServer();
        $this->configureServer();
        $this->setupEventHandlers();
    }

    /**
     * Start the OpenSwoole server
     */
    public function start(): void
    {
        $this->server->start();
    }

    /**
     * Validate that OpenSwoole extension is loaded
     */
    private function validateEnvironment(): void
    {
        if (!extension_loaded('openswoole')) {
            die("OpenSwoole extension is not installed.\n");
        }
    }

    /**
     * Load Composer autoloader
     */
    private function loadDependencies(): void
    {
        // Check if autoloader is already loaded (e.g., from index.php)
        if (class_exists('Composer\Autoload\ClassLoader')) {
            // Autoloader already loaded, skip loading
            ProjectHelper::loadEnv();
            return;
        }

        $autoloaderFound = false;
        $autoloaderPaths = [
            // First priority: for when index.php is in the project root
            __DIR__ . '/../../vendor/autoload.php',
            
            // Second priority: for library development mode
            __DIR__ . '/../../../vendor/autoload.php',
        ];

        foreach ($autoloaderPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $autoloaderFound = true;
                break;
            }
        }

        if (!$autoloaderFound) {
            die("Composer's autoloader not found. Please run 'composer install'.");
        }

        ProjectHelper::loadEnv();
    }

    /**
     * Initialize all components
     */
    private function initializeComponents(): void
    {
        $this->config = new SwooleServerConfig();
        $this->security = new SecurityManager();
        $this->hotReload = new HotReloadManager();
    }

    /**
     * Create the OpenSwoole HTTP server
     */
    private function createServer(): void
    {
        $this->server = new \OpenSwoole\HTTP\Server(
            $this->config->getHost(),
            $this->config->getPort()
        );
    }

    /**
     * Configure server settings
     */
    private function configureServer(): void
    {
        $this->server->set($this->config->getConfig());
    }

    /**
     * Setup all event handlers
     */
    private function setupEventHandlers(): void
    {
        $this->setupServerStartHandler();
        $this->setupWorkerStartHandler();
        $this->setupRequestHandler();
        $this->setupWorkerErrorHandler();
    }

    /**
     * Handle server start event
     */
    private function setupServerStartHandler(): void
    {
        $this->server->on("start", function ($server) {
            echo "OpenSwoole server started at " . $this->config->getHost() . ":" . $this->config->getPort() . "\n";
        });
    }

    /**
     * Handle worker start event
     */
    private function setupWorkerStartHandler(): void
    {
        $this->server->on("workerStart", function ($server, $workerId) {
            if ($this->config->isDev()) {
                ProjectHelper::disableOpcacheIfDev();
            }
            if ($workerId === 0 && $this->config->isDev()) {
                $this->hotReload->startHotReload($server);
            }
            echo "Worker #$workerId started\n";
        });
    }

    /**
     * Handle HTTP request event
     */
    private function setupRequestHandler(): void
    {
        $this->server->on("request", function ($request, $response) {
            try {
                // Security check
                if (!$this->security->isRequestAllowed($request->server['request_uri'] ?? '')) {
                    $this->security->sendSecurityResponse($response);
                    return;
                }
                
                // Process request
                $sr = new SwooleRequest($request);
                $bs = new SwooleBootstrap($sr->request);
                $result = $bs->processRequest();
                
                // Get response code before sending (for APM tracing)
                $responseCode = 200; // Default
                if ($result instanceof \Gemvc\Http\JsonResponse) {
                    $responseCode = $result->response_code ?? 200;
                    $result->showSwoole($response);
                } elseif ($result instanceof \Gemvc\Http\HtmlResponse) {
                    $responseCode = $result->response_code ?? 200;
                    $result->showSwoole($response);
                }
                
                // Store response code on Request object for APM to access (Swoole doesn't support http_response_code() after headers sent)
                $sr->request->_http_response_code = $responseCode;
                
                // Flush APM traces after response is sent (adds to batch, sends if interval elapsed)
                // Note: Shutdown handler will also call flush() + forceSendBatch() as a safety net
                // The batching system handles time-based sending automatically
                if ($sr->request->apm !== null && $sr->request->apm->isEnabled()) {
                    try {
                        $sr->request->apm->flush();
                    } catch (\Throwable $e) {
                        // Silently fail - don't let APM break the application
                        error_log("APM: Error during flush: " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                $this->handleRequestError($e, $response);
            }
        });
    }

    /**
     * Handle worker error event
     */
    private function setupWorkerErrorHandler(): void
    {
        $this->server->on("workerError", function ($server, $workerId, $workerPid, $exitCode, $signal) {
            error_log("Worker #$workerId crashed. Exit code: $exitCode, Signal: $signal");
        });
    }

    /**
     * Handle request processing errors
     */
    private function handleRequestError(\Throwable $e, object $response): void
    {
        // Log the error
        error_log("Error processing request: " . $e->getMessage());
        
        // Send error response
        // @phpstan-ignore-next-line
        $response->status(500);
        // @phpstan-ignore-next-line
        $response->header('Content-Type', 'application/json');
        // @phpstan-ignore-next-line
        $response->end(json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage()
        ]));
    }
}
