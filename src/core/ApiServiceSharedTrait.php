<?php

namespace Gemvc\Core;

/**
 * Shared auth, rate-limit, and controller APM helpers for API service bases.
 *
 * Used by {@see ApiService} (and inherited by deprecated {@see SwooleApiService}).
 * Consuming classes must declare `protected Request $request`.
 *
 * Response delivery stays in Bootstrap / SwooleBootstrap — this trait never die()/exit().
 *
 * @property \Gemvc\Http\Request $request
 *
 * Magic Properties for Controllers:
 * @property-read ControllerTracingProxy $UserController
 * @property-read ControllerTracingProxy $ProfileController
 * @property-read ControllerTracingProxy $AnyController
 */
trait ApiServiceSharedTrait
{
    /**
     * Magic getter for easy Controller access with APM tracing.
     *
     * Allows: $this->UserController->method() or $this->User->method()
     *
     * @param string $name
     * @return mixed|ControllerTracingProxy
     */
    public function __get(string $name)
    {
        if (str_ends_with($name, 'Controller')) {
            $class = 'App\\Controller\\' . $name;
            if (class_exists($class)) {
                $instance = new $class($this->request);
                if ($instance instanceof Controller) {
                    return $this->callController($instance);
                }
            }
        }

        $shortClass = 'App\\Controller\\' . ucfirst($name) . 'Controller';
        if (class_exists($shortClass)) {
            $instance = new $shortClass($this->request);
            if ($instance instanceof Controller) {
                return $this->callController($instance);
            }
        }

        $trace = debug_backtrace();
        $file = $trace[0]['file'] ?? 'unknown';
        $line = $trace[0]['line'] ?? 0;
        trigger_error(
            'Undefined property: ' . static::class . '::$' . $name . ' in ' . $file . ' on line ' . $line,
            E_USER_NOTICE
        );
        return null;
    }

    /**
     * Require authentication (and optionally specific roles) before continuing.
     *
     * Call once in the child constructor to protect every method, or inside one method.
     * Throws AuthException — Bootstrap / SwooleBootstrap catch → 401 or 403.
     *
     * @param array<string>|null $roles null or [] = authenticated only; otherwise one of these roles
     * @throws AuthException when authentication or authorization fails
     */
    public function requireAuth(?array $roles = []): void
    {
        if (!$this->request->auth($roles)) {
            $response = $this->request->returnResponse();
            throw new AuthException($response->service_message ?? 'Unauthorized', $response->response_code ?: 401);
        }
    }

    /**
     * Require family (machine-to-machine) trust before continuing.
     *
     * Verifies HMAC headers — orthogonal to end-user JWT ({@see requireAuth()}).
     * Throws InternalServiceException — Bootstrap / SwooleBootstrap / FrankenPhpBootstrap
     * catch → 401 (bad trust) or 500 (secret misconfigured).
     *
     * @throws InternalServiceException
     */
    public function requireInternalService(): void
    {
        InternalTrust::enforce($this->request);
    }

    /**
     * Require rate limit before continuing — same DX as requireAuth().
     *
     * Uses REQUEST_RATE_LIMIT_DRIVER (no-op if driver=none).
     * Throws RateLimitException → Bootstrap returns HTTP 429.
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @throws RateLimitException
     */
    public function requireRateLimit(
        int $perSec = RateLimiter::DEFAULT_PER_SEC,
        string $scope = RateLimiter::SCOPE_BOTH,
        int $blockSeconds = RateLimiter::DEFAULT_BLOCK_SECONDS
    ): void {
        RateLimiter::enforce($this->request, $perSec, $scope, $blockSeconds, 'api', null);
    }

    /**
     * Force APCu for this call (ignores REQUEST_RATE_LIMIT_DRIVER).
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @throws RateLimitException
     */
    public function requireRateLimitApcu(
        int $perSec = RateLimiter::DEFAULT_PER_SEC,
        string $scope = RateLimiter::SCOPE_BOTH,
        int $blockSeconds = RateLimiter::DEFAULT_BLOCK_SECONDS
    ): void {
        RateLimiter::enforce(
            $this->request,
            $perSec,
            $scope,
            $blockSeconds,
            'api',
            RateLimiter::DRIVER_APCU
        );
    }

    /**
     * Force Redis for this call (ignores REQUEST_RATE_LIMIT_DRIVER).
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @throws RateLimitException
     */
    public function requireRateLimitRedis(
        int $perSec = RateLimiter::DEFAULT_PER_SEC,
        string $scope = RateLimiter::SCOPE_BOTH,
        int $blockSeconds = RateLimiter::DEFAULT_BLOCK_SECONDS
    ): void {
        RateLimiter::enforce(
            $this->request,
            $perSec,
            $scope,
            $blockSeconds,
            'api',
            RateLimiter::DRIVER_REDIS
        );
    }

    /**
     * Force simultaneous APCu + Redis for this call (deny if either over). Not failover.
     *
     * @param 'both'|'ip'|'token'|string $scope
     * @throws RateLimitException
     */
    public function requireRateLimitBoth(
        int $perSec = RateLimiter::DEFAULT_PER_SEC,
        string $scope = RateLimiter::SCOPE_BOTH,
        int $blockSeconds = RateLimiter::DEFAULT_BLOCK_SECONDS
    ): void {
        RateLimiter::enforce(
            $this->request,
            $perSec,
            $scope,
            $blockSeconds,
            'api',
            RateLimiter::DRIVER_BOTH
        );
    }

    /**
     * Call a controller method with automatic APM span creation (when APM_TRACE_CONTROLLER is on).
     *
     *   return $this->callController(new ProductController($this->request))->create();
     *
     * Available on ApiService (and deprecated SwooleApiService subclass).
     *
     * @param Controller $controller The controller instance
     * @return ControllerTracingProxy A proxy that intercepts method calls
     */
    protected function callController(Controller $controller): ControllerTracingProxy
    {
        return new ControllerTracingProxy($controller, $this->request->apm);
    }

    /**
     * @deprecated Use callController() instead.
     * @param Controller $controller
     * @return ControllerTracingProxy
     */
    protected function callWithTracing(Controller $controller): ControllerTracingProxy
    {
        return $this->callController($controller);
    }
}
