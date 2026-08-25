<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Core\Apm\ApmTracingTrait;
use Gemvc\Core\Apm\AbstractApm;
use Gemvc\Core\Documentation\ResponseExampleResolver;


/**
 * Base class for all API services (Apache, Nginx PHP-FPM, and OpenSwoole).
 *
 * Shared auth, rate-limit, and callController live in {@see ApiServiceSharedTrait}.
 * OpenSwoole apps should extend this class (or {@see ProtectedApiService}); the old
 * {@see SwooleApiService} / {@see ProtectedSwooleApiService} names are deprecated aliases.
 *
 * @property Request $request
 * @property-read mixed $errors
 *
 * Magic Properties for Controllers:
 * @property-read \Gemvc\Core\ControllerTracingProxy $UserController  Access App\Controller\UserController
 * @property-read \Gemvc\Core\ControllerTracingProxy $ProfileController Access App\Controller\ProfileController
 * @property-read \Gemvc\Core\ControllerTracingProxy $AnyController   Access App\Controller\AnyController
 *
 * Public service is suitable for endpoints without Authentication (Login, Register, etc.).
 * For authenticated CRUD prefer {@see ProtectedApiService}.
 */
class ApiService
{
    use ApmTracingTrait;
    use ApiServiceSharedTrait;

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

    public function __construct(Request $request)
    {
        $this->errors = [];
        $this->request = $request;

        // APM is initialized in Bootstrap/SwooleBootstrap, available via $request->apm
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
    }

    /**
     * Default index method - returns welcome message for the service
     *
     * @return JsonResponse Welcome response with service name
     */
    public function index(): JsonResponse
    {
        $name = get_class($this);
        $name = explode('\\', $name)[2];
        return Response::success("welcome to $name service");
    }

    /**
     * Validates POST data against a schema.
     *
     * Preferred cross-runtime helper (Apache/Nginx and OpenSwoole): throws on failure.
     * Prefer {@see Request::definePostSchema()} + {@see Request::returnResponse()} for the
     * canonical bool path, or this method when you want throw-style like requireAuth().
     *
     * @param array<string> $post_schema Define Post Schema to validation
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     *
     * @example $this->validateOrFail(['email'=>'email' , 'id'=>'int' , '?name' => 'string']);
     */
    protected function validateOrFail(array $post_schema): void
    {
        $this->validatePosts($post_schema);
    }

    /**
     * Validates string lengths in POST data; throws on failure (cross-runtime).
     *
     * @param array<string> $post_string_schema 'field' => 'min|max'
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     */
    protected function validateStringOrFail(array $post_string_schema): void
    {
        $this->validateStringPosts($post_string_schema);
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
     * Checks if request->post is empty, then attempts to parse JSON from php://input
     * if Content-Type is application/json.
     *
     * @return void
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
     * Documentation example for one API method.
     *
     * Override in a subclass to supply a PHP example (always wins).
     * Otherwise: app/response_example/{ShortName}.{method}.json, then safe create/read/list/update inference, then [].
     *
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return ResponseExampleResolver::resolve(static::class, $method);
    }

}

/**
 * Proxy class for Controller that intercepts method calls and creates APM spans
 *
 * This class allows fluent syntax: $apiService->callController($controller)->method()
 *
 * This is a magic method proxy - all controller methods are intercepted via __call().
 * Static analysis tools may warn about undefined methods, but this is expected behavior.
 *
 * @internal Used by ApiServiceSharedTrait::callController()
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
     * Tracing is controlled by APM_TRACE_CONTROLLER environment variable.
     *
     * @param string $methodName The method name being called
     * @param array<mixed> $args The arguments passed to the method
     * @return JsonResponse The JsonResponse from the controller method
     */
    public function __call(string $methodName, array $args): JsonResponse
    {
        if (!self::shouldTraceController()) {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            return $result;
        }

        if ($this->apm === null) {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);
            return $result;
        }

        $controllerName = get_class($this->controller);
        $parts = explode('\\', $controllerName);
        $controllerName = $parts[count($parts) - 1] ?? 'Unknown';

        $controllerSpan = $this->apm->startSpan('controller-operation', [
            'controller.name' => $controllerName,
            'controller.method' => $methodName,
        ], ApmInterface::SPAN_KIND_INTERNAL);

        try {
            /** @var callable $callable */
            $callable = [$this->controller, $methodName];
            /** @var JsonResponse $result */
            $result = call_user_func_array($callable, $args);

            $statusCode = $result->response_code ?? 200;
            $status = ($statusCode >= 400) ? ApmInterface::STATUS_ERROR : ApmInterface::STATUS_OK;

            $spanAttributes = [
                'controller.result' => 'success',
                'http.status_code' => $statusCode,
            ];

            if ($this->apm->shouldTraceResponse()) {
                $responseData = $result->json_response ?? '';
                if ($responseData === false || empty($responseData)) {
                    $responseData = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                if (is_string($responseData)) {
                    $responseData = AbstractApm::limitStringForTracing($responseData);
                }

                $spanAttributes['response.message'] = $result->message ?? '';
                $spanAttributes['response.service_message'] = $result->service_message ?? '';
                $spanAttributes['response.data'] = $responseData;
                $spanAttributes['response.count'] = $result->count !== null ? (string) $result->count : 'null';
            }

            $this->apm->endSpan($controllerSpan, $spanAttributes, $status);

            return $result;
        } catch (\Throwable $e) {
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
     * @return bool True if APM_TRACE_CONTROLLER is set to '1' or 'true'
     */
    private static function shouldTraceController(): bool
    {
        $value = $_ENV['APM_TRACE_CONTROLLER'] ?? null;
        return ($value === '1' || $value === 'true');
    }
}
