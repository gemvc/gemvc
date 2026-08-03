<?php
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;

/**
 * @deprecated Prefer {@see ApiService} on all servers (Apache, Nginx, OpenSwoole).
 * This class remains as a thin subclass for backward compatibility.
 *
 * Behavior note (Phase 3): `validatePosts()` / `validateStringPosts()` now **throw**
 * `ValidationException` (inherited from ApiService). For the old OpenSwoole return style
 * (`if ($err = …) return $err`), use {@see safeValidatePosts()} / {@see safeValidateStringPosts()}.
 *
 * For authenticated CRUD prefer {@see ProtectedApiService} (or deprecated {@see ProtectedSwooleApiService}).
 *
 * @property Request $request
 * @property-read ControllerTracingProxy $UserController
 * @property-read ControllerTracingProxy $ProfileController
 * @property-read ControllerTracingProxy $AnyController
 */
class SwooleApiService extends ApiService
{
    /**
     * Legacy OpenSwoole return-style POST schema validation.
     *
     * Prefer {@see validateOrFail()} or definePostSchema() + returnResponse().
     * Prefer inherited throw-style {@see validatePosts()} when you want Bootstrap/SwooleBootstrap → 400.
     *
     * @param array<string> $post_schema Validation schema
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function safeValidatePosts(array $post_schema): ?JsonResponse
    {
        if (!$this->request->definePostSchema($post_schema)) {
            return Response::badRequest($this->request->error);
        }
        return null;
    }

    /**
     * Legacy OpenSwoole return-style string-length validation.
     *
     * Prefer {@see validateStringOrFail()}.
     *
     * @param array<string> $post_string_schema Validation schema with min/max lengths
     * @return JsonResponse|null Error response or null if validation passes
     */
    protected function safeValidateStringPosts(array $post_string_schema): ?JsonResponse
    {
        if (!$this->request->validateStringPosts($post_string_schema)) {
            return Response::badRequest($this->request->error);
        }
        return null;
    }
}
