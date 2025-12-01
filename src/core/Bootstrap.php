<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Core\WebService;

class Bootstrap
{
    private Request $request;
    private string $requested_service;
    private string $requested_method;
    private bool $is_web = false;

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
            $this->handleApiRequest();
        }
    }

    private function handleApiRequest(): void
    {
        if (!file_exists('./app/api/'.$this->requested_service.'.php')) {
            $this->showNotFound("The API service '$this->requested_service' does not exist");
            die;
        }
        
        $serviceInstance = false;
        try {
            $service = 'App\\Api\\' . $this->requested_service;
            $serviceInstance = new $service($this->request);
        } catch (\Throwable $e) {
            $this->showNotFound($e->getMessage());
            die;
        }
        
        if (!method_exists($serviceInstance, $this->requested_method)) {
            $this->showNotFound("API method '$this->requested_method' does not exist in service '$this->requested_service'");
            die;
        }
        
        $method = $this->requested_method;
        $response = $serviceInstance->$method();
        
        // Handle different response types (JsonResponse, HtmlResponse, etc.)
        if ($response instanceof JsonResponse) {
            $response->show();
        } elseif ($response instanceof \Gemvc\Http\HtmlResponse) {
            // For Apache/Nginx, use the show() method
            $response->show();
        } elseif (is_object($response) && method_exists($response, 'show')) {
            // For any response with a show() method
            $response->show();
        } else {
            Response::internalError("API method '$method' does not provide a valid Response as return value")->show();
        }
        
        die;
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

    private function showNotFound(string $message): void
    {
        $jsonResponse = new JsonResponse();
        $jsonResponse->notFound($message);
        $jsonResponse->show();
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
        $templateDir = dirname($templatePath);
        
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
}
