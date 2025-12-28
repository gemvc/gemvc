<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;
use Gemvc\Helper\TraceKitModel;
use Gemvc\Helper\TraceKitToolkit;


/**
 * Base class for all API services
 * 
 * @function auth(string $role = null):bool
 * @property Request $request
 * public service is suitable for all service without need of Authentication, like Login , Register etc...
 */
class ApiService
{
    protected Request $request;
    
    /**
     * @deprecated Use $errors array and GemvcError instead
     * Kept for backward compatibility - will be removed in future version
     */
    public ?string $error;
    
    /**
     * @var array<GemvcError>
     */
    protected array $errors = [];
    
    /**
     * TraceKitModel instance for automatic request tracing (optional)
     * @var object|null
     */
    private ?object $tracekit = null;
    
    /**
     * Root span for TraceKit tracing (if enabled)
     * @var array<string, mixed>
     */
    private array $rootSpan = [];

    public function __construct(Request $request)
    {
        $this->error = null;
        $this->errors = [];
        $this->request = $request;
        
        // Initialize TraceKitModel if available (optional dependency)
        $this->initializeTraceKit();
    }
    
    /**
     * Initialize TraceKitModel for automatic request tracing
     * 
     * This is optional - if TraceKitModel class doesn't exist, tracing is silently disabled
     * 
     * @return void
     */
    private function initializeTraceKit(): void
    {
        // Check if TraceKitModel class exists (optional dependency)
        if (!class_exists('App\Model\TraceKitModel')) {
            error_log("TraceKit: TraceKitModel class not found, skipping initialization");
            return;
        }
        
        try {
            $this->tracekit = new TraceKitModel();
            
            // Store TraceKitModel instance in Request object so Controller can use the same instance
            $this->request->tracekit = $this->tracekit;
            
            // Only start trace if TraceKit is enabled
            if ($this->tracekit->isEnabled()) {
                error_log("TraceKit: Initialized and enabled for service: " . $this->getServiceName() . '/' . $this->getMethodName());
                
                // Build root span attributes
                $rootAttributes = [
                    'http.method' => $this->request->getMethod(),
                    'http.url' => $this->request->getUri(),
                    'http.user_agent' => $this->request->getHeader('User-Agent') ?? 'unknown',
                    'http.route' => $this->getServiceName() . '/' . $this->getMethodName(),
                ];
                
                // Optionally include request body if enabled
                if ($this->tracekit->shouldTraceRequestBody()) {
                    $requestBody = $this->getRequestBodyForTracing();
                    if ($requestBody !== null) {
                        // Limit body size to avoid huge traces (max 2000 chars)
                        if (strlen($requestBody) > 2000) {
                            $requestBody = substr($requestBody, 0, 1997) . '...';
                        }
                        $rootAttributes['http.request.body'] = $requestBody;
                    }
                }
                
                // Start root trace for this request
                $this->rootSpan = $this->tracekit->startTrace('http-request', $rootAttributes);
                
                if (empty($this->rootSpan)) {
                    error_log("TraceKit: Failed to start trace (sampling or error)");
                    return;
                }
                
                $traceId = is_string($this->rootSpan['trace_id'] ?? null) ? $this->rootSpan['trace_id'] : 'N/A';
                error_log("TraceKit: Root span started - Trace ID: " . substr($traceId, 0, 16) . "...");
                
                // Register shutdown function to flush traces after response is sent
                register_shutdown_function(function() {
                    $this->flushTraceKit();
                });
            } else {
                error_log("TraceKit: Initialized but disabled (check TRACEKIT_ENABLED and TRACEKIT_API_KEY)");
            }
        } catch (\Throwable $e) {
            // Silently fail - don't let TraceKit break the application
            error_log("TraceKit: Failed to initialize: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $this->tracekit = null;
        }
    }
    
    /**
     * Get service name for tracing
     * 
     * @return string
     */
    private function getServiceName(): string
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        return $parts[count($parts) - 1] ?? 'Unknown';
    }
    
    /**
     * Get method name for tracing (will be set when method is called)
     * 
     * @return string
     */
    private function getMethodName(): string
    {
        // Try to get from request URL segments or default to 'index'
        $url = $this->request->requestedUrl ?? '/';
        $segments = array_filter(explode('/', $url));
        return !empty($segments) ? end($segments) : 'index';
    }
    
    /**
     * Get request body for tracing (reconstructs from parsed data)
     * 
     * Since php://input can only be read once and is already consumed,
     * we reconstruct the body from the parsed request data.
     * 
     * Always tries to format as JSON for better readability in traces,
     * falls back to URL-encoded format only if JSON encoding fails.
     * 
     * @return string|null The request body as string, or null if no body data
     */
    private function getRequestBodyForTracing(): ?string
    {
        try {
            $method = $this->request->getMethod();
            
            // Get body data based on method
            $bodyData = null;
            if ($method === 'POST' && !empty($this->request->post)) {
                $bodyData = $this->request->post;
            } elseif ($method === 'PUT' && !empty($this->request->put)) {
                $bodyData = $this->request->put;
            } elseif ($method === 'PATCH' && !empty($this->request->patch)) {
                $bodyData = $this->request->patch;
            }
            
            if ($bodyData === null) {
                return null;
            }
            
            // $bodyData is always an array at this point (from request->post/put/patch)
            // Always try to format as JSON first (more readable in traces)
            $json = json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json !== false) {
                return $json;
            }
            
            // Fallback to URL-encoded format if JSON encoding fails
            return http_build_query($bodyData);
        } catch (\Throwable $e) {
            // Silently fail - don't let request body tracing break the application
            error_log("TraceKit: Failed to get request body for tracing: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Flush TraceKit traces (called on shutdown)
     * 
     * @return void
     */
    private function flushTraceKit(): void
    {
        if ($this->tracekit === null) {
            error_log("TraceKit: Flush skipped - tracekit is null");
            return;
        }
        
        if (empty($this->rootSpan)) {
            error_log("TraceKit: Flush skipped - rootSpan is empty");
            return;
        }
        
        try {
            // Get response status code if available
            $statusCode = http_response_code() ?: 200;
            
            error_log("TraceKit: Flushing trace - Status: " . $statusCode);
            
            // End root span
            /** @var \Gemvc\Helper\TraceKitModel $tracekit */
            $tracekit = $this->tracekit;
            $tracekit->endSpan($this->rootSpan, [
                'http.status_code' => $statusCode,
            ], $statusCode >= 400 ? TraceKitModel::STATUS_ERROR : TraceKitModel::STATUS_OK);
            
            // Flush traces (non-blocking)
            $tracekit->flush();
        } catch (\Throwable $e) {
            // Silently fail - don't let TraceKit break the application
            error_log("TraceKit: Failed to flush traces: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }
    
    /**
     * Call a controller method with automatic TraceKit span creation
     * 
     * This method accepts a Controller object and returns a proxy that intercepts
     * method calls to automatically create spans for controller operations.
     * 
     * Usage in API layer:
     *   return $this->callWithTracing(new ProductController($this->request))->create();
     *   return $this->callWithTracing(new ProductController($this->request))->delete();
     * 
     * @param Controller $controller The controller instance
     * @return ControllerTracingProxy A proxy object that intercepts method calls
     */
    protected function callWithTracing(Controller $controller): ControllerTracingProxy
    {
        return new ControllerTracingProxy($controller, $this->tracekit);
    }
    
    /**
     * Record exception in TraceKit (called automatically on errors)
     * 
     * @param \Throwable $exception
     * @return void
     */
    public function recordTraceKitException(\Throwable $exception): void
    {
        if ($this->tracekit === null) {
            return;
        }
        
        try {
            /** @var \Gemvc\Helper\TraceKitModel $tracekit */
            $tracekit = $this->tracekit;
            // If no root span exists, create one (errors are always logged)
            if (empty($this->rootSpan)) {
                $this->rootSpan = $tracekit->recordException([], $exception, 'http-request', [
                    'http.method' => $this->request->getMethod(),
                    'http.url' => $this->request->getUri(),
                ]);
            } else {
                // Record exception on existing root span
                $tracekit->recordException($this->rootSpan, $exception);
            }
        } catch (\Throwable $e) {
            // Silently fail
            error_log("TraceKit: Failed to record exception: " . $e->getMessage());
        }
    }
    
    /**
     * Add an error to the errors array
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code (default: 400)
     * @return void
     */
    protected function addError(string $message, int $httpCode = 400): void
    {
        $this->errors[] = new GemvcError($message, $httpCode, __FILE__, __LINE__);
        // Keep backward compatibility - set string error to first error message
        $this->error = $this->error ?? $message;
    }
    
    /**
     * Get all errors as GemvcError array
     * 
     * @return array<GemvcError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Check if there are any errors
     * 
     * @return bool True if errors exist, false otherwise
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Clear all errors
     * 
     * @return void
     */
    public function clearErrors(): void
    {
        $this->errors = [];
        $this->error = null;
    }

    /**
     * Default index method - returns welcome message for the service
     * 
     * @return JsonResponse Welcome response with service name
     */
    public function index(): JsonResponse
    {
        $name = get_class($this);
        //because get_class return class name with namespace like App\\Service\\className ,
        //we need only to show className and it is in index 2
        $name = explode('\\', $name)[2];
        return Response::success("welcome to $name service");
    }

    /**
     * Validates POST data against a schema
     * 
     * @param array<string> $post_schema Define Post Schema to validation
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     * 
     * @example validatePosts(['email'=>'email' , 'id'=>'int' , '?name' => 'string'])
     * @help : ?name means it is optional
     */
    protected function validatePosts(array $post_schema): void
    {
        if (!$this->request->definePostSchema($post_schema)) {
            $errorMessage = $this->request->error ?? 'Validation failed';
            throw new ValidationException($errorMessage, 400);
        }
    }

    /**
     * Validates string lengths in POST data against min and max constraints
     * 
     * @param array<string> $post_string_schema Array where keys are post name and values are strings in the format "min-value|max-value" (optional)
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     * 
     * @example validateStringPosts([
     *     'username' => '3|15',  // Min length 3, max length 15
     *     'password' => '8|',    // Min length 8, no max limit
     *     'nickname' => '|20',   // No min limit, max length 20
     *     'bio' => '',           // No min or max limit
     * ])
     */
    protected function validateStringPosts(array $post_string_schema): void
    {
        if (!$this->request->validateStringPosts($post_string_schema)) {
            $errorMessage = $this->request->error ?? 'String validation failed';
            throw new ValidationException($errorMessage, 400);
        }
    }

    /**
     * Parse JSON POST data if Content-Type is application/json
     * 
     * Helper method to handle JSON POST parsing when framework doesn't auto-parse.
     * This is useful for cases where JSON POST data needs to be manually parsed.
     * 
     * Checks if request->post is empty, then attempts to parse JSON from php://input
     * if Content-Type is application/json.
     * 
     * @return void
     * 
     * @example
     * // In your API service method:
     * public function create(): JsonResponse
     * {
     *     $this->parseJsonPostData(); // Parse JSON if needed
     *     // Now $this->request->post contains the parsed JSON data
     *     // ... rest of your logic
     * }
     */
    public function parseJsonPostData(): void
    {
        if (empty($this->request->post)) {
            $contentType = $this->request->getHeader('content-type') 
                        ?? $_SERVER['CONTENT_TYPE'] 
                        ?? $_SERVER['HTTP_CONTENT_TYPE'] 
                        ?? '';
            
            $contentTypeStr = is_string($contentType) ? $contentType : '';
            if ($contentTypeStr !== '' && strpos(strtolower($contentTypeStr), 'application/json') !== false) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $jsonData = json_decode($rawInput, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        $this->request->post = $jsonData;
                    }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return [];
    }

}

/**
 * Proxy class for Controller that intercepts method calls and creates TraceKit spans
 * 
 * This class allows fluent syntax: $apiService->callWithTracing($controller)->method()
 * 
 * This is a magic method proxy - all controller methods are intercepted via __call().
 * Static analysis tools may warn about undefined methods, but this is expected behavior.
 * 
 * @internal This class is used internally by ApiService::callWithTracing()
 * @method JsonResponse create() Intercepts create() method calls
 * @method JsonResponse read() Intercepts read() method calls
 * @method JsonResponse update() Intercepts update() method calls
 * @method JsonResponse delete() Intercepts delete() method calls
 * @method JsonResponse list() Intercepts list() method calls
 * @method JsonResponse __call(string $methodName, array<mixed> $args) Intercepts any controller method call
 */
class ControllerTracingProxy
{
    private Controller $controller;
    private ?object $tracekit;
    
    public function __construct(Controller $controller, ?object $tracekit)
    {
        $this->controller = $controller;
        $this->tracekit = $tracekit;
    }
    
    /**
     * Intercept method calls and create TraceKit spans
     * 
     * This magic method intercepts all method calls to the controller and automatically
     * creates TraceKit spans for the operation.
     * 
     * @param string $methodName The method name being called
     * @param array<mixed> $args The arguments passed to the method
     * @return JsonResponse The JsonResponse from the controller method
     */
    public function __call(string $methodName, array $args): JsonResponse
    {
        // If TraceKit is not available, just call the method directly
        if ($this->tracekit === null) {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            return $result;
        }
        
        /** @var \Gemvc\Helper\TraceKitModel $tracekit */
        $tracekit = $this->tracekit;
        
        if (!$tracekit->isEnabled()) {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            return $result;
        }
        
        // Extract controller name
        $controllerName = get_class($this->controller);
        $parts = explode('\\', $controllerName);
        $controllerName = $parts[count($parts) - 1] ?? 'Unknown';
        
        // Start controller operation span
        $controllerSpan = $tracekit->startSpan('controller-operation', [
            'controller.name' => $controllerName,
            'controller.method' => $methodName,
        ], TraceKitModel::SPAN_KIND_INTERNAL);
        
        try {
            // Call the actual controller method
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            
            // Determine status based on result
            $statusCode = $result->response_code ?? 200;
            $status = $statusCode >= 400 ? TraceKitModel::STATUS_ERROR : TraceKitModel::STATUS_OK;
            
            // Build span attributes
            $spanAttributes = [
                'controller.result' => 'success',
                'http.status_code' => $statusCode,
            ];
            
            // Optionally include response data if enabled
            if ($tracekit->shouldTraceResponse()) {
                // Get the full JSON response
                $responseData = $result->json_response ?? '';
                if ($responseData === false || empty($responseData)) {
                    // Fallback: encode the response object
                    $responseData = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                
                // Limit response size to avoid huge traces (max 2000 chars)
                if (is_string($responseData) && strlen($responseData) > 2000) {
                    $responseData = substr($responseData, 0, 1997) . '...';
                }
                
                $spanAttributes['response.message'] = $result->message ?? '';
                $spanAttributes['response.service_message'] = $result->service_message ?? '';
                $spanAttributes['response.data'] = $responseData;
                $spanAttributes['response.count'] = $result->count !== null ? (string)$result->count : 'null';
            }
            
            // Update span with response details
            $tracekit->endSpan($controllerSpan, $spanAttributes, $status);
            
            return $result;
        } catch (\Throwable $e) {
            // Record exception and end span with error
            if (!empty($controllerSpan)) {
                $tracekit->recordException($controllerSpan, $e);
                $tracekit->endSpan($controllerSpan, [
                    'controller.result' => 'error',
                    'error.message' => $e->getMessage(),
                ], TraceKitModel::STATUS_ERROR);
            }
            
            throw $e;
        }
    }
}
