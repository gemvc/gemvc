<?php
//because die() is not available in Swoole
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\ResponseInterface;

/**
 * SwooleBootstrap - A Bootstrap alternative for OpenSwoole environment
 * 
 * This class replaces the default Gemvc\Core\Bootstrap to work with Swoole's
 * persistent process model by returning responses instead of using die()
 */
class SwooleBootstrap
{
    private Request $request;
    private string $requested_service;
    private string $requested_method;

    /**
     * Constructor
     * 
     * @param Request $request The HTTP request object
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->extractRouteInfo();
    }

    /**
     * Extract service and method from the URL
     */
    private function extractRouteInfo(): void
    {
        $method = "index";

        $segments = explode('/', $this->request->requestedUrl);

        // Check if this is a root URL (/) in development mode
        $isRootUrl = empty($this->request->requestedUrl) ||
            $this->request->requestedUrl === '/' ||
            (count($segments) <= 2 && empty(array_filter($segments)));

        if ($isRootUrl) {
            // Load environment to check APP_ENV
            try {
                \Gemvc\Helper\ProjectHelper::loadEnv();
                $isDevelopment = ($_ENV['APP_ENV'] ?? '') === 'dev';

                if ($isDevelopment) {
                    // Route root URL to Developer/app (SPA shell) in dev mode
                    $this->requested_service = "Developer";
                    $this->requested_method = "app";
                    return;
                }
            } catch (\Exception $e) {
                // If env can't be loaded, continue with normal routing
            }
        }

        $serviceIndex = is_numeric($_ENV["SERVICE_IN_URL_SECTION"] ?? 1) ? (int) ($_ENV["SERVICE_IN_URL_SECTION"] ?? 1) : 1;
        $methodIndex = is_numeric($_ENV["METHOD_IN_URL_SECTION"] ?? 2) ? (int) ($_ENV["METHOD_IN_URL_SECTION"] ?? 2) : 2;

        $service = isset($segments[$serviceIndex]) && $segments[$serviceIndex] ? ucfirst($segments[$serviceIndex]) : "Index";

        if (isset($segments[$methodIndex]) && $segments[$methodIndex]) {
            $method = $segments[$methodIndex];
        }

        $this->requested_service = $service;
        $this->requested_method = $method;
    }

    /**
     * Process the request and return a response
     * 
     * @return ResponseInterface|null The API response
     */
    public function processRequest(): ?ResponseInterface
    {
        if (!file_exists('./app/api/' . $this->requested_service . '.php')) {
            return Response::notFound("The service path for '$this->requested_service' does not exist, check your service name if properly typed");
        }

        $serviceInstance = false;
        try {
            $service = 'App\\Api\\' . $this->requested_service;
            $serviceInstance = new $service($this->request);
        } catch (\Throwable $e) {
            return Response::notFound($e->getMessage());
        }

        if (!method_exists($serviceInstance, $this->requested_method)) {
            return Response::notFound("Requested method '$this->requested_method' does not exist in service, check if you typed it correctly");
        }

        $method = $this->requested_method;
        return $serviceInstance->$method();
    }
}