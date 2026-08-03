<?php
// Worker mode: never die()/exit() inside the request handler or after response emit
namespace Gemvc\Core;

use Gemvc\Http\StandardHttpRequest;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\NoCors;
use Gemvc\Http\ResponseInterface;

/**
 * FrankenPHP worker loop — boots once, handles many requests via frankenphp_handle_request().
 *
 * Isolation model (same product rules as OpenSwoole):
 * - New StandardHttpRequest + FrankenPhpBootstrap + ApiService per HTTP hit
 * - Auth / body / APM live on that Request graph
 * - Do not store request state in statics or custom singletons
 * - Never call die()/exit() in app or framework response paths used here
 *
 * @see docs/guides/frankenphp.md
 * @see docs/guides/openswoole.md
 */
class FrankenPhpWorker
{
    private SecurityManager $security;

    public function __construct(?SecurityManager $security = null)
    {
        $this->security = $security ?? new SecurityManager();
    }

    /**
     * Run the worker event loop until FrankenPHP stops or MAX_REQUESTS is reached.
     *
     * @throws \RuntimeException when not running under FrankenPHP worker SAPI
     */
    public function run(): void
    {
        if (!function_exists('frankenphp_handle_request')) {
            throw new \RuntimeException(
                'frankenphp_handle_request() is not available. Run this script under FrankenPHP worker mode (see docs/guides/frankenphp.md).'
            );
        }

        $security = $this->security;

        $handler = static function () use ($security): void {
            try {
                $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
                    ? $_SERVER['REQUEST_URI']
                    : '/';

                // Defense-in-depth (Caddyfile remains primary edge deny)
                if (!$security->isRequestAllowed($uri)) {
                    $security->emitForbidden();
                    return;
                }

                NoCors::apache();
                $adapter = new StandardHttpRequest();
                $bootstrap = new FrankenPhpBootstrap($adapter->request);
                $result = $bootstrap->processRequest();

                if ($result instanceof ResponseInterface) {
                    self::emitAndFlush($adapter, $result);
                }
            } catch (\Throwable $e) {
                self::emitInternalError($e);
            }
        };

        $maxRequests = self::resolveMaxRequests();
        for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
            $keepRunning = self::handleFrankenPhpRequest($handler);

            gc_collect_cycles();

            if (!$keepRunning) {
                break;
            }
        }
    }

    /**
     * @param callable(): void $handler
     */
    private static function handleFrankenPhpRequest(callable $handler): bool
    {
        if (!function_exists('frankenphp_handle_request')) {
            throw new \RuntimeException(
                'frankenphp_handle_request() is not available. Run this script under FrankenPHP worker mode (see docs/guides/frankenphp.md).'
            );
        }

        /** @var callable(callable(): void): bool $frankenphpHandleRequest */
        $frankenphpHandleRequest = 'frankenphp_handle_request';
        return $frankenphpHandleRequest($handler);
    }

    /**
     * Handle a single request without the FrankenPHP loop (unit tests / diagnostics).
     */
    public function handleOnce(): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : '/';

        if (!$this->security->isRequestAllowed($uri)) {
            $this->security->emitForbidden();
            return;
        }

        NoCors::apache();
        $adapter = new StandardHttpRequest();
        $bootstrap = new FrankenPhpBootstrap($adapter->request);
        $result = $bootstrap->processRequest();
        if ($result instanceof ResponseInterface) {
            self::emitAndFlush($adapter, $result);
        }
    }

    private static function resolveMaxRequests(): int
    {
        if (isset($_SERVER['MAX_REQUESTS']) && is_numeric($_SERVER['MAX_REQUESTS'])) {
            return (int) $_SERVER['MAX_REQUESTS'];
        }
        if (isset($_ENV['FRANKENPHP_MAX_REQUESTS']) && is_numeric($_ENV['FRANKENPHP_MAX_REQUESTS'])) {
            return (int) $_ENV['FRANKENPHP_MAX_REQUESTS'];
        }
        return 0;
    }

    private static function emitAndFlush(StandardHttpRequest $adapter, ResponseInterface $result): void
    {
        if ($result instanceof JsonResponse) {
            $adapter->request->_http_response_code = $result->response_code;
        } elseif ($result instanceof HtmlResponse) {
            // HtmlResponse has no public status accessor; default for APM
            $adapter->request->_http_response_code = 200;
        }

        if ($adapter->request->apm !== null && $adapter->request->apm->isEnabled()) {
            try {
                $adapter->request->apm->flush();
            } catch (\Throwable $e) {
                error_log('APM: Error during flush: ' . $e->getMessage());
            }
        }

        if ($result instanceof JsonResponse || $result instanceof HtmlResponse) {
            $result->show();
            return;
        }

        // Fallback for other ResponseInterface implementations
        if (method_exists($result, 'show')) {
            /** @var callable(): void $show */
            $show = [$result, 'show'];
            $show();
        }
    }

    private static function emitInternalError(\Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'response_code' => 500,
            'message' => 'Internal Server Error',
            'service_message' => $e->getMessage(),
            'data' => null,
        ], JSON_THROW_ON_ERROR);
    }
}
