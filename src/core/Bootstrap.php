<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Core\GemvcError;
use Gemvc\Core\GEMVCErrorHandler;
use Gemvc\Http\HtmlResponse;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;

if(ProjectHelper::isDevEnvironment()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

class Bootstrap
{
    private Request $request;
    private bool $is_web = false;
    /**
     * @var array<GemvcError>
     */
    private array $errors = [];
    
    /**
     * APM instance for request tracing (optional)
     * @var ApmInterface|null
     */
    private ?ApmInterface $apm = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
        
        
        $this->setRequestedService();
        // Initialize APM early to capture full request lifecycle
        $this->initializeApm();
        $this->runApp();
    }
    
    /**
     * Initialize APM provider for request tracing
     * 
     * APM is initialized early (before routing) to capture the full request lifecycle.
     * The APM instance is stored in $request->apm for use by ApiService, Controller, etc.
     * 
     * @return void
     */
    private function initializeApm(): void
    {
        $apmName = ApmFactory::isEnabled();
        if (!$apmName) {
            return;
        }
        $this->apm = ApmFactory::create($this->request);
        // Explicitly set $request->apm to ensure it's available for ApiService, Controller, etc.
        // This ensures trace context propagation even if ApmFactory doesn't set it
        if ($this->apm !== null) {
            $this->request->setApm($this->apm);
        }
    }

    private function runApp(): void
    {
        if ($this->is_web) {
            $this->handleWebRequest();
        } else {
            if(count($this->errors) === 0) {
                $this->handleApiRequest();
            }
            
            // Handle any errors that occurred during API request processing
            if(count($this->errors) > 0) {
                // Get response code from errors (use first error's HTTP code)
                $responseCode = !empty($this->errors) ? $this->errors[0]->http_code : 500;
                
                // Store response code on Request object for APM to access
                $this->request->_http_response_code = $responseCode;
                
                // Flush APM traces before error response is sent (adds to batch, sends if interval elapsed)
                // Note: Shutdown handler will also call flush() + forceSendBatch() as a safety net
                // The batching system handles time-based sending automatically
                if ($this->request->apm !== null && $this->request->apm->isEnabled()) {
                    try {
                        $this->request->apm->flush();
                    } catch (\Throwable $e) {
                        // Silently fail - don't let APM break the application
                        error_log("APM: Error during flush: " . $e->getMessage());
                    }
                }
                
                GEMVCErrorHandler::handleErrors($this->errors);
            }
            die;
        }
    }

    private function handleApiRequest(): void
    {
        $serviceName = $this->request->getServiceName();
        if (!file_exists('./app/api/'.$serviceName.'.php')) {
            $this->errors[] = new GemvcError("The API service '$serviceName' does not exist", 404, __FILE__, __LINE__);
            return;
        }
        try {
            $service = 'App\\Api\\' . $serviceName;
            
            // Validate service class exists and extends ApiService
            if (!class_exists($service)) {
                $this->errors[] = new GemvcError("The API service class '$service' does not exist", 404, __FILE__, __LINE__);
                return;
            }
            
            $serviceInstance = new $service($this->request);
            
            // Validate service extends ApiService
            if (!($serviceInstance instanceof \Gemvc\Core\ApiService)) {
                $this->errors[] = new GemvcError("The API service '$serviceName' must extend ApiService", 500, __FILE__, __LINE__);
                return;
            }
            
            // Use default index method if method is empty (ApiService provides index())
            $method = $this->request->getMethodName();
            
            // Validate method exists
            if (!method_exists($serviceInstance, $method)) {
                $this->errors[] = new GemvcError("API method '$method' does not exist in service '$serviceName'", 404, __FILE__, __LINE__);
                return;
            }
            
            // Call method and get response
            $response = $serviceInstance->$method();
            
            // Check if service has any errors (from service-level error handling)
            if ($serviceInstance->hasErrors()) {
                $serviceErrors = $serviceInstance->getErrors();
                $this->errors = array_merge($this->errors, $serviceErrors);
                return;
            }
            
            // Validate response is JsonResponse (all ApiService methods return JsonResponse)
            if ($response instanceof JsonResponse) {
                /*
                $this->errors[] = new GemvcError("API method '$method' must return a JsonResponse instance", 500, __FILE__, __LINE__);
                GemvcErrorHandler::handleErrors($this->errors);
                */
                // Get response code before sending (for APM tracing)
                $responseCode = $response->response_code ?? 200;
                
                // Store response code on Request object for APM to access
                $this->request->_http_response_code = $responseCode;
                
                // Flush APM traces before response is sent (adds to batch, sends if interval elapsed)
                // Note: Shutdown handler will also call flush() + forceSendBatch() as a safety net
                // The batching system handles time-based sending automatically
                if ($this->request->apm !== null && $this->request->apm->isEnabled()) {
                    try {
                        $this->request->apm->flush();
                    } catch (\Throwable $e) {
                        // Silently fail - don't let APM break the application
                        error_log("APM: Error during flush: " . $e->getMessage());
                    }
                }
                
                $response->show();
                die;
            }
            if ($response instanceof HtmlResponse) {
                // Get response code before sending (for APM tracing)
                $responseCode = $response->response_code ?? 200;
                
                // Store response code on Request object for APM to access
                $this->request->_http_response_code = $responseCode;
                
                // Flush APM traces before response is sent (adds to batch, sends if interval elapsed)
                // Note: Shutdown handler will also call flush() + forceSendBatch() as a safety net
                // The batching system handles time-based sending automatically
                if ($this->request->apm !== null && $this->request->apm->isEnabled()) {
                    try {
                        $this->request->apm->flush();
                    } catch (\Throwable $e) {
                        // Silently fail - don't let APM break the application
                        error_log("APM: Error during flush: " . $e->getMessage());
                    }
                }
                
                $response->show();
                die;
            }
            
            // Get response code before sending (for APM tracing)
            $responseCode = 200; // Default for unknown response types
            
            // Store response code on Request object for APM to access
            $this->request->_http_response_code = $responseCode;
            
            // Flush APM traces before response is sent (adds to batch, sends if interval elapsed)
            // Note: Shutdown handler will also call flush() + forceSendBatch() as a safety net
            // The batching system handles time-based sending automatically
            if ($this->request->apm !== null && $this->request->apm->isEnabled()) {
                try {
                    $this->request->apm->flush();
                } catch (\Throwable $e) {
                    // Silently fail - don't let APM break the application
                    error_log("APM: Error during flush: " . $e->getMessage());
                }
            }
            
            $response->show();
            return;
        } catch (\Gemvc\Core\ValidationException $e) {
            // Handle validation exceptions (400 Bad Request) from ApiService or Controller
            $this->errors[] = new GemvcError($e->getMessage(), 400, $e->getFile(), $e->getLine());
            // Record exception in APM if available (via Request object)
            $this->recordExceptionInApm($e);
        } catch (\RuntimeException $e) {
            // Handle runtime exceptions (500 Internal Server Error) - typically from Controller database operations
            $this->errors[] = new GemvcError($e->getMessage(), 500, $e->getFile(), $e->getLine());
            // Record exception in APM if available (via Request object)
            $this->recordExceptionInApm($e);
        } catch (\Error $e) {
            // Handle PHP 7+ Error exceptions (method not found, etc.)
            $httpCode = self::determineHttpCodeFromError($e);
            $this->errors[] = new GemvcError($e->getMessage(), $httpCode, $e->getFile(), $e->getLine());
            // Record exception in APM if available (via Request object)
            $this->recordExceptionInApm($e);
        } catch (\Throwable $e) {
            // Handle other exceptions (runtime errors, etc.)
            $this->errors[] = new GemvcError($e->getMessage(), 500, $e->getFile(), $e->getLine());
            // Record exception in APM if available (via Request object)
            $this->recordExceptionInApm($e);
        }
    }

    private function handleWebRequest(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        try {
            $serviceName = $this->request->getServiceName();
            // Load the appropriate web service class
            $serviceClass = 'App\\Web\\' . $serviceName;
            
            // If class doesn't exist, try the default controller
            if (!class_exists($serviceClass)) {
                // Check if we're looking for a static page
                $staticPath = './app/web/pages/' . strtolower($serviceName) . '.php';
                if (file_exists($staticPath)) {
                    include $staticPath;
                    die;
                }
                
                // If no static page, show 404
                $this->showWebNotFound();
                die;
            }
            
            // Create controller instance
            $serviceInstance = new $serviceClass($this->request);
                       
            // Check if the requested method exists
            $method = $this->request->getMethodName();
            if (!method_exists($serviceInstance, $method)) {
                // Try using the method as a parameter to the index method
                if (method_exists($serviceInstance, 'index')) {
                    // @phpstan-ignore-next-line
                    $this->request->params['action'] = $method;
                    $method = 'index';
                } else {
                    $this->showWebNotFound();
                    die;
                }
            }

            $serviceInstance->$method();
            
        } catch (\Throwable $e) {
            // Handle errors
            http_response_code(500);
        }
        
        die;
    }

    private function setRequestedService(): void
    {
        $method = "index";
        // Strip query string from URL before parsing segments
        $urlPath = $this->request->requestedUrl;
        if (($queryPos = strpos($urlPath, '?')) !== false) {
            $urlPath = substr($urlPath, 0, $queryPos);
        }
        $segments = explode('/', $urlPath);
        
        // Check if this is a root URL (/) - route to Index/index API
        $isRootUrl = empty($urlPath) ||
            $urlPath === '/' ||
            (count($segments) <= 1 && empty(array_filter($segments)));
        
        if ($isRootUrl) {
            // Route root URL to Index/index API endpoint
            $this->is_web = false;
            $this->request->setServiceName("Index");
            $this->request->setMethodName("index");
            return;
        }
        
        // Get the first segment (service indicator)
        $serviceIndex = is_numeric($_ENV["SERVICE_IN_URL_SECTION"] ?? 1) ? (int) ($_ENV["SERVICE_IN_URL_SECTION"] ?? 1) : 1;
        $service = isset($segments[$serviceIndex]) ? 
            strtolower($segments[$serviceIndex]) : "";
            
        // Check if this is an API request
        if ($service === "api") {
            $this->is_web = false;
            
            // For API requests, get the actual service name from the next segment
            if (isset($segments[$serviceIndex + 1]) && $segments[$serviceIndex + 1]) {
                $service = ucfirst($segments[$serviceIndex + 1]);
            } else {
                $service = "Index";
            }
            
            // Get the method for API
            if (isset($segments[$serviceIndex + 2]) && $segments[$serviceIndex + 2]) {
                $method = $segments[$serviceIndex + 2];
            }
        } else {
            // Default to web
            $this->is_web = true;
            
            // For web requests, map the URL path to service/method
            if (empty($service)) {
                // Root URL - use home controller
                $service = "Home";
            } else {
                // Capitalize the service name
                $service = ucfirst($service);
            }
            
            // Get the method for web pages
            if (isset($segments[$serviceIndex + 1]) && $segments[$serviceIndex + 1]) {
                $method = $segments[$serviceIndex + 1];
            }
        }
        
        // Set service and method name on Request object for framework-wide access
        // This allows ApiService, APM providers, and other components to access routing metadata
        $this->request->setServiceName($service);
        $this->request->setMethodName($method);
    }

    /**
     * @deprecated Use GemvcError and GEMVCErrorHandler::handleErrors() instead
     * This method is kept for backward compatibility but should not be used in new code
     */
    private function showNotFound(string $message): void
    {
        $error = new GemvcError($message, 404, __FILE__, __LINE__);
        GEMVCErrorHandler::handleErrors([$error]);
    }

    private function showWebNotFound(): void
    {
        header('HTTP/1.0 404 Not Found');
        
        // Check if a custom 404 page exists
        if (file_exists('./app/web/Error/404.php')) {
            // @phpstan-ignore-next-line
            include './app/web/Error/404.php';
        } else {
            $this->show404Error();
        }
    }
    
    /**
     * Show 500 server error page
     * 
     * @param \Throwable $exception The exception that caused the error
     * @return void
     */
    private function showServerError(\Throwable $exception): void
    {
        $debugMode = ($_ENV['DEBUG'] ?? false) === true || ($_ENV['DEBUG'] ?? false) === 'true';
        $templatePath = $this->getTemplatePath('error-500.php');
        
        if (file_exists($templatePath)) {
            include $templatePath;
        }
    }
    
    /**
     * Show 404 page not found error
     * 
     * @return void
     */
    private function show404Error(): void
    {
        $templatePath = $this->getTemplatePath('error-404.php');
        
        if (file_exists($templatePath)) {
            include $templatePath;
        }
    }
    
    /**
     * Get template path from startup/common/system_pages directory
     * Uses similar path resolution logic as AbstractInit::findStartupPath()
     * 
     * @param string $templateName Template filename (e.g., 'error-404.php')
     * @return string Full path to template file
     */
    private function getTemplatePath(string $templateName): string
    {
        // From core directory, go up one level to src, then to startup/common/system_pages
        // __DIR__ = vendor/gemvc/library/src/core
        // dirname(__DIR__) = vendor/gemvc/library/src
        $basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'system_pages';
        $templatePath = $basePath . DIRECTORY_SEPARATOR . $templateName;
        
        // If not found, try alternative paths (for different installation structures)
        if (!file_exists($templatePath)) {
            $alternativePaths = [
                // Standard Composer vendor path
                dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'gemvc' . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'startup' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'system_pages',
            ];
            
            foreach ($alternativePaths as $altPath) {
                $altTemplatePath = $altPath . DIRECTORY_SEPARATOR . $templateName;
                if (file_exists($altTemplatePath)) {
                    return $altTemplatePath;
                }
            }
        }
        
        return $templatePath;
    }

    /**
     * Record exception in APM (if available via Request object)
     * 
     * @param \Throwable $exception
     * @return void
     */
    private function recordExceptionInApm(\Throwable $exception): void
    {
        // Use APM instance from Request (initialized in constructor)
        // Fallback to creating APM if not initialized (edge case: exception before initialization)
        $apm = $this->request->apm ?? null;
        if ($apm === null) {
            // Fallback: try to create APM for exception logging
            $apmName = ApmFactory::isEnabled();
            if ($apmName) {
                $apm = ApmFactory::create($this->request);
                // Explicitly set $request->apm to ensure it's available for subsequent operations
                if ($apm !== null) {
                    $this->request->setApm($apm);
                }
            }
        }
        
        if ($apm !== null) {
            // ApmInterface::recordException() already has graceful error handling
            $apm->recordException([], $exception);
        }
    }
    
    /**
     * Determine HTTP status code from Error exception
     * 
     * @param \Error $e The error exception
     * @return int HTTP status code (404 for not found errors, 500 for others)
     */
    private static function determineHttpCodeFromError(\Error $e): int {
        $message = $e->getMessage();
        
        // Check for method/class not found errors
        if (str_contains($message, 'Call to undefined method') ||
            str_contains($message, 'Method') && str_contains($message, 'does not exist') ||
            str_contains($message, 'not found') ||
            str_contains($message, 'does not exist')) {
            return 404;
        }
        
        return 500;
    }
}