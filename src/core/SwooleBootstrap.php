<?php
//because die() is not available in Swoole
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\ResponseInterface;
use Gemvc\Helper\ProjectHelper;

/**
 * SwooleBootstrap - A Bootstrap alternative for OpenSwoole environment
 * 
 * This class replaces the default Gemvc\Core\Bootstrap to work with Swoole's
 * persistent process model by returning responses instead of using die()
 */
class SwooleBootstrap
{
    private Request $request;

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
                if (ProjectHelper::isDevEnvironment()) {
                    // Route root URL to Developer/app (SPA shell) in dev mode
                    $this->request->setServiceName("Developer");
                    $this->request->setMethodName("app");
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

        // Set service and method name on Request object for framework-wide access
        // This allows ApiService, APM providers, and other components to access routing metadata
        $this->request->setServiceName($service);
        $this->request->setMethodName($method);
    }

    /**
     * Process the request and return a response
     * 
     * @return ResponseInterface|null The API response
     */
    public function processRequest(): ?ResponseInterface
    {
        $serviceName = $this->request->getServiceName();
        if (!file_exists('./app/api/' . $serviceName . '.php')) {
            return Response::notFound("The service path for '$serviceName' does not exist, check your service name if properly typed");
        }

        $serviceInstance = false;
        try {
            $service = 'App\\Api\\' . $serviceName;
            $serviceInstance = new $service($this->request);
        } catch (\Throwable $e) {
            return Response::notFound($e->getMessage());
        }

        $methodName = $this->request->getMethodName();
        if (!method_exists($serviceInstance, $methodName)) {
            return Response::notFound("Requested method '$methodName' does not exist in service, check if you typed it correctly");
        }

        $method = $methodName;
        try {
            return $serviceInstance->$method();
        } catch (\Throwable $e) {
            // Record exception in APM if available (via Request object)
            $this->recordExceptionInApm($e);
            // Re-throw to let Swoole handle it
            throw $e;
        }
    }
    
    /**
     * Record exception in APM (if available via Request object)
     * 
     * @param \Throwable $exception
     * @return void
     */
    private function recordExceptionInApm(\Throwable $exception): void
    {
        // Early return if APM is disabled - avoid unnecessary processing
        if (ProjectHelper::isApmEnabled() === null) {
            return;
        }
        
        // Access APM instance directly via Request object
        $apm = $this->request->apm ?? null;
        if ($apm !== null) {
            // ApmInterface::recordException() already has graceful error handling
            $apm->recordException([], $exception);
        }
    }
}