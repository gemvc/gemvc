<?php
//because die() is not available in Swoole
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;

/**
 * OpenSwoole-compatible API service base class.
 *
 * Shared auth, rate-limit, and callController live in {@see ApiServiceSharedTrait}
 * (same as {@see ApiService}). Legacy validatePosts() still returns ?JsonResponse;
 * prefer validateOrFail() or definePostSchema() + returnResponse().
 *
 * For authenticated CRUD prefer {@see ProtectedSwooleApiService}.
 *
 * @property Request $request
 * @property-read ControllerTracingProxy $UserController
 * @property-read ControllerTracingProxy $ProfileController
 * @property-read ControllerTracingProxy $AnyController
 */
class SwooleApiService
{
    use ApiServiceSharedTrait;

    protected Request $request;
    public ?string $error;

    /**
     * @param Request $request The HTTP request object
     */
    public function __construct(Request $request)
    {
        $this->error = null;
        $this->request = $request;
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
     * Validates POST data against a schema; always throws on failure (cross-runtime).
     *
     * Use this instead of {@see validatePosts()} when you want the same throw-style
     * behavior as Apache/Nginx ApiService. SwooleBootstrap catches ValidationException → 400.
     *
     * Canonical alternative: definePostSchema() + returnResponse().
     *
     * @param array<string> $post_schema Validation schema
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     */
    protected function validateOrFail(array $post_schema): void
    {
        if (!$this->request->definePostSchema($post_schema)) {
            $errorMessage = $this->request->error ?? 'Validation failed';
            throw new ValidationException($errorMessage, 400);
        }
    }

    /**
     * Validates string lengths; always throws on failure (cross-runtime).
     *
     * @param array<string> $post_string_schema 'field' => 'min|max'
     * @return void
     * @throws ValidationException If validation fails (HTTP 400)
     */
    protected function validateStringOrFail(array $post_string_schema): void
    {
        if (!$this->request->validateStringPosts($post_string_schema)) {
            $errorMessage = $this->request->error ?? 'String validation failed';
            throw new ValidationException($errorMessage, 400);
        }
    }

    /**
     * Validates POST data against a schema (legacy OpenSwoole return style).
     *
     * Prefer {@see validateOrFail()} or definePostSchema() + returnResponse().
     * Kept for one cycle so existing `if ($err = $this->validatePosts(...)) return $err;` still works.
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
     * Validates string lengths in POST data (legacy OpenSwoole return style).
     *
     * Prefer {@see validateStringOrFail()}.
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
