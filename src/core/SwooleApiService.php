<?php
//because die() is not available in Swoole
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;
use Gemvc\Core\AuthException;

/**
 * SwooleApiService - OpenSwoole-compatible API service base class
 * 
 * This class replaces the Gemvc\Core\ApiService class to work with Swoole's
 * persistent process model by returning responses instead of using die()
 */
class SwooleApiService
{
    protected Request $request;
    public ?string $error;

    /**
     * Constructor
     * 
     * @param Request $request The HTTP request object
     */
    public function __construct(Request $request)
    {
        $this->error = null;
        $this->request = $request;
    }

    /**
     * Require authentication (and optionally specific roles) before continuing.
     *
     * Call this ONCE — typically as the first line of your child class's
     * constructor — to protect every method of that service, with no per-method
     * boilerplate at all:
     *
     *   class User extends SwooleApiService {
     *       public function __construct(Request $request) {
     *           parent::__construct($request);
     *           $this->requireAuth(['admin']); // whole service now requires role 'admin'
     *       }
     *   }
     *
     * On failure this THROWS AuthException instead of returning a value. Unlike
     * a plain die()/exit(), this is safe under OpenSwoole: SwooleBootstrap catches
     * AuthException (both around service construction and around the method call)
     * and converts it directly into the correct response (401 Unauthorized or 403
     * Forbidden) without ever crashing or terminating the persistent worker process.
     *
     * Because Bootstrap builds the service object and calls the requested method
     * from the same place, throwing during construction guarantees the target
     * method body never runs.
     *
     * You can still call it inside a single method instead, if you only want to
     * protect that one method rather than the whole service.
     *
     * @param array<string>|null $roles null or [] = authenticated only (any role), otherwise require one of these roles
     * @throws AuthException when authentication or authorization fails
     */
    public function requireAuth(?array $roles = []): void
    {
        $authorized = $roles === null ? $this->request->auth() : $this->request->auth($roles);
        if (!$authorized) {
            $response = $this->request->returnResponse();
            throw new AuthException($response->service_message ?? 'Unauthorized', $response->response_code ?: 401);
        }
    }

    /**
     * Default index method
     * 
     * @return JsonResponse Welcome response
     */
    public function index(): JsonResponse
    {
        $name = get_class($this);
        $name = explode('\\', $name)[2];
        return Response::success("Welcome to $name service");
    }

    /**
     * Validates POST data against a schema
     * 
     * @param array<string> $post_schema Validation schema
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function validatePosts(array $post_schema): ?JsonResponse
    {
        if (!$this->request->definePostSchema($post_schema)) {
            return Response::badRequest($this->request->error);
        }
        return null;
    }

    /**
     * Validates string lengths in POST data
     * 
     * @param array<string> $post_string_schema Validation schema with min/max lengths
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function validateStringPosts(array $post_string_schema): ?JsonResponse
    {
        if (!$this->request->validateStringPosts($post_string_schema)) {
            return Response::badRequest($this->request->error);
        }
        return null;
    }

    /**
     * Safe validation method for use with Swoole
     * Returns the error response if validation fails
     * 
     * @param array<string> $post_schema Validation schema
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function safeValidatePosts(array $post_schema): ?JsonResponse
    {
        // Use our non-die version
        return $this->validatePosts($post_schema);
    }

    /**
     * Safe string validation method for use with Swoole
     * Returns the error response if validation fails
     * 
     * @param array<string> $post_string_schema Validation schema
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function safeValidateStringPosts(array $post_string_schema): ?JsonResponse
    {
        // Use our non-die version
        return $this->validateStringPosts($post_string_schema);
    }

    /**
     * Generates mock response data for API documentation
     * 
     * @param string $method Method name
     * @return array<string, mixed> Mock response data
     */
    public static function mockResponse(string $method): array
    {
        return [];
    }
} 