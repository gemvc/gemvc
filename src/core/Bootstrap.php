<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Core\GemvcError;
use Gemvc\Core\GEMVCErrorHandler;
use Gemvc\Http\HtmlResponse;

if($_ENV['APP_ENV'] === 'dev') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

class Bootstrap
{
    private Request $request;
    private string $requested_service;
    private string $requested_method;
    private bool $is_web = false;
    /**
     * @var array<GemvcError>
     */
    private array $errors = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->setRequestedService();
        $this->runApp();
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
                GEMVCErrorHandler::handleErrors($this->errors);
            }
            die;
        }
    }

    private function handleApiRequest(): void
    {
        if (!file_exists('./app/api/'.$this->requested_service.'.php')) {
            $this->errors[] = new GemvcError("The API service '$this->requested_service' does not exist", 404, __FILE__, __LINE__);
            return;
        }
        try {
            $service = 'App\\Api\\' . $this->requested_service;
            
            // Validate service class exists and extends ApiService
            if (!class_exists($service)) {
                $this->errors[] = new GemvcError("The API service class '$service' does not exist", 404, __FILE__, __LINE__);
                return;
            }
            
            $serviceInstance = new $service($this->request);
            
            // Validate service extends ApiService
            if (!($serviceInstance instanceof \Gemvc\Core\ApiService)) {
                $this->errors[] = new GemvcError("The API service '$this->requested_service' must extend ApiService", 500, __FILE__, __LINE__);
                return;
            }
            
            // Use default index method if method is empty (ApiService provides index())
            $method = $this->requested_method ?: 'index';
            
            // Validate method exists
            if (!method_exists($serviceInstance, $method)) {
                $this->errors[] = new GemvcError("API method '$method' does not exist in service '$this->requested_service'", 404, __FILE__, __LINE__);
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
                $response->show();
                die;
            }
            if ($response instanceof HtmlResponse) {
                $response->show();
                die;
            }
            
            $response->show();
            return;
        } catch (\Gemvc\Core\ValidationException $e) {
            // Handle validation exceptions (400 Bad Request) from ApiService or Controller
            $this->errors[] = new GemvcError($e->getMessage(), 400, $e->getFile(), $e->getLine());
        } catch (\RuntimeException $e) {
            // Handle runtime exceptions (500 Internal Server Error) - typically from Controller database operations
            $this->errors[] = new GemvcError($e->getMessage(), 500, $e->getFile(), $e->getLine());
        } catch (\Error $e) {
            // Handle PHP 7+ Error exceptions (method not found, etc.)
            $httpCode = self::determineHttpCodeFromError($e);
            $this->errors[] = new GemvcError($e->getMessage(), $httpCode, $e->getFile(), $e->getLine());
        } catch (\Throwable $e) {
            // Handle other exceptions (runtime errors, etc.)
            $this->errors[] = new GemvcError($e->getMessage(), 500, $e->getFile(), $e->getLine());
        }
    }

    private function handleWebRequest(): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        try {
            // Load the appropriate web service class
            $serviceClass = 'App\\Web\\' . $this->requested_service;
            
            // If class doesn't exist, try the default controller
            if (!class_exists($serviceClass)) {
                // Check if we're looking for a static page
                $staticPath = './app/web/pages/' . strtolower($this->requested_service) . '.php';
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
            $method = $this->requested_method ?: 'index';
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
            $this->showServerError($e);
        }
        
        die;
    }

    private function setRequestedService(): void
    {
        $method = "index";
        $segments = explode('/', $this->request->requestedUrl);
        
        // Check if this is a root URL (/) in development mode - route to SPA
        $isRootUrl = empty($this->request->requestedUrl) ||
            $this->request->requestedUrl === '/' ||
            (count($segments) <= 1 && empty(array_filter($segments)));
        
        if ($isRootUrl) {
            // Load environment to check APP_ENV
            try {
                \Gemvc\Helper\ProjectHelper::loadEnv();
                $isDevelopment = ($_ENV['APP_ENV'] ?? '') === 'dev';
                
                if ($isDevelopment) {
                    // Route root URL to Developer/app (SPA shell) in dev mode
                    $this->is_web = false;
                    $this->requested_service = "Developer";
                    $this->requested_method = "app";
                    return;
                }
            } catch (\Exception $e) {
                // If env can't be loaded, continue with normal routing
            }
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
        
        $this->requested_service = $service;
        $this->requested_method = $method;
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
        // In development mode, show helpful developer page for root URL
        $isDevelopment = ($_ENV['APP_ENV'] ?? '') === 'dev';
        $isRootUrl = empty($this->requested_service) || strtolower($this->requested_service) === 'home';
        
        if ($isDevelopment && $isRootUrl) {
            $this->showDeveloperWelcomePage();
            return;
        }
        
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
     * Show helpful developer welcome page in development mode
     * 
     * @return void
     */
    private function showDeveloperWelcomePage(): void
    {
        // Construct base URL from server information
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = is_string($host) ? $host : 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? '';
        $port = is_string($port) || is_int($port) ? (string)$port : '';
        $portDisplay = ($port && $port !== '80' && $port !== '443') ? ':' . $port : '';
        $baseUrl = $protocol . '://' . $host . $portDisplay;
        $apiBaseUrl = rtrim($baseUrl, '/') . '/api';
        
        // Detect webserver type
        $webserverType = WebserverDetector::get();
        $webserverName = match($webserverType) {
            'swoole' => 'OpenSwoole',
            'apache' => 'Apache',
            'nginx' => 'Nginx',
            default => ucfirst($webserverType)
        };
        
        // Get template directory path (template handles all presentation logic)
        $templatePath = $this->getTemplatePath('index.php');
        
        // Load central index controller
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            // Fallback if index not found, try developer-welcome
            $fallbackPath = $this->getTemplatePath('developer-welcome.php');
            if (file_exists($fallbackPath)) {
                include $fallbackPath;
            } else {
                // Last resort fallback
                $lastResortPath = $this->getTemplatePath('developer-welcome-fallback.php');
                if (file_exists($lastResortPath)) {
                    include $lastResortPath;
                }
            }
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
