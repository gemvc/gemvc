<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;
use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Core\Apm\ApmTracingTrait;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Core\Apm\AbstractApm;



/**
 * Base class for all API services
 * 
 * @property Request $request
 * @property-read mixed $errors
 * 
 * Magic Properties for Controllers:
 * @property-read \Gemvc\Core\ControllerTracingProxy $UserController  Access App\Controller\UserController
 * @property-read \Gemvc\Core\ControllerTracingProxy $ProfileController Access App\Controller\ProfileController
 * @property-read \Gemvc\Core\ControllerTracingProxy $AnyController   Access App\Controller\AnyController
 * 
 * public service is suitable for all service without need of Authentication, like Login , Register etc...
 */
class ApiService
{
    use ApmTracingTrait;
    protected Request $request;

    /**
     * Magic getter for easy Controller access with APM tracing
     * 
     * Allows accessing controllers as properties:
     * $this->UserController->method()
     * $this->User->method() (resolves to UserController)
     * 
     * @param string $name
     * @return mixed|ControllerTracingProxy
     */
    public function __get(string $name)
    {
        // 1. Try resolving as full Controller name (e.g., $this->UserController)
        if (str_ends_with($name, 'Controller')) {
            $class = 'App\\Controller\\' . $name;
            if (class_exists($class)) {
                $instance = new $class($this->request);
                if ($instance instanceof Controller) {
                    return $this->callController($instance);
                }
            }
        }

        // 2. Try resolving as short name (e.g., $this->User -> UserController)
        $shortClass = 'App\\Controller\\' . ucfirst($name) . 'Controller';
        if (class_exists($shortClass)) {
            $instance = new $shortClass($this->request);
            if ($instance instanceof Controller) {
                return $this->callController($instance);
            }
        }

        // 3. Trigger standard PHP undefined property error
        $trace = debug_backtrace();
        /*@phpstan-ignore-next-line */
        trigger_error('Undefined property: ' . static::class . '::$' . $name .' in ' . $trace[0]['file'] .' on line ' . $trace[0]['line'],            E_USER_NOTICE);
        return null;
    }


    /**
     * @deprecated Use $errors array and GemvcError instead
     * Kept for backward compatibility - will be removed in future version
     */
    public ?string $error;

    /**
     * @var array<GemvcError>
     */
    protected array $errors = [];

    public function __construct(Request $request)
    {
        //$this->error = null;
        $this->errors = [];
        $this->request = $request;

        // APM is now initialized in Bootstrap/SwooleBootstrap, available via $request->apm
        // No need to initialize here - this ensures APM captures the full request lifecycle
    }

    /**
     * Call a controller method with automatic APM span creation
     * 
     * This is the recommended method name. Tracing is controlled by APM_TRACE_CONTROLLER
     * environment variable. When enabled, automatically creates spans for controller operations.
     * 
     * Usage in API layer:
     *   return $this->callController(new ProductController($this->request))->create();
     *   return $this->callController(new ProductController($this->request))->delete();
     * 
     * @param Controller $controller The controller instance
     * @return ControllerTracingProxy A proxy object that intercepts method calls
     */
    protected function callController(Controller $controller): ControllerTracingProxy
    {
        return new ControllerTracingProxy($controller, $this->request->apm);
    }

    /**
     * Call a controller method with automatic APM span creation
     * 
     * @deprecated Use callController() instead. This method will be removed in a future version.
     * 
     * @param Controller $controller The controller instance
     * @return ControllerTracingProxy A proxy object that intercepts method calls
     */
    protected function callWithTracing(Controller $controller): ControllerTracingProxy
    {
        return $this->callController($controller);
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
        //$this->error = $this->error ?? $message;
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
        //$this->error = null;
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
 * Proxy class for Controller that intercepts method calls and creates APM spans
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
    private ?ApmInterface $apm;

    public function __construct(Controller $controller, ?ApmInterface $apm)
    {
        $this->controller = $controller;
        $this->apm = $apm;
    }

    /**
     * Intercept method calls and create APM spans
     * 
     * This magic method intercepts all method calls to the controller and automatically
     * creates APM spans for the operation. Tracing is controlled by APM_TRACE_CONTROLLER
     * environment variable.
     * 
     * @param string $methodName The method name being called
     * @param array<mixed> $args The arguments passed to the method
     * @return JsonResponse The JsonResponse from the controller method
     */
    public function __call(string $methodName, array $args): JsonResponse
    {
        // Check if APM_TRACE_CONTROLLER is enabled (environment-controlled)
        // If disabled, call method directly without tracing overhead
        if (!self::shouldTraceController()) {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            return $result;
        }

        // If APM is not available, just call the method directly
        if ($this->apm === null) {
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
        $controllerSpan = $this->apm->startSpan('controller-operation', [
            'controller.name' => $controllerName,
            'controller.method' => $methodName,
        ], ApmInterface::SPAN_KIND_INTERNAL);

        try {
            // Call the actual controller method
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);

            // Determine status based on result
            $statusCode = $result->response_code ?? 200;
            $status = ($statusCode >= 400) ? ApmInterface::STATUS_ERROR : ApmInterface::STATUS_OK;

            // Build span attributes
            $spanAttributes = [
                'controller.result' => 'success',
                'http.status_code' => $statusCode,
            ];

            // Optionally include response data if enabled
            if ($this->apm->shouldTraceResponse()) {
                // Get the full JSON response
                $responseData = $result->json_response ?? '';
                if ($responseData === false || empty($responseData)) {
                    // Fallback: encode the response object
                    $responseData = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                // Limit response size to avoid huge traces (using centralized helper)
                if (is_string($responseData)) {
                    $responseData = AbstractApm::limitStringForTracing($responseData);
                }

                $spanAttributes['response.message'] = $result->message ?? '';
                $spanAttributes['response.service_message'] = $result->service_message ?? '';
                $spanAttributes['response.data'] = $responseData;
                $spanAttributes['response.count'] = $result->count !== null ? (string) $result->count : 'null';
            }

            // Update span with response details
            $this->apm->endSpan($controllerSpan, $spanAttributes, $status);

            return $result;
        } catch (\Throwable $e) {
            // Record exception and end span with error
            if (!empty($controllerSpan)) {
                $this->apm->recordException($controllerSpan, $e);
                $this->apm->endSpan($controllerSpan, [
                    'controller.result' => 'error',
                    'error.message' => $e->getMessage(),
                ], ApmInterface::STATUS_ERROR);
            }

            throw $e;
        }
    }

    /**
     * Check if controller tracing is enabled via environment variable
     * 
     * Tracing is controlled by APM_TRACE_CONTROLLER environment variable.
     * Supports both '1' and 'true' values for compatibility.
     * 
     * @return bool True if APM_TRACE_CONTROLLER is set to '1' or 'true'
     */
    private static function shouldTraceController(): bool
    {
        $value = $_ENV['APM_TRACE_CONTROLLER'] ?? null;
        return ($value === '1' || $value === 'true');
    }
}
