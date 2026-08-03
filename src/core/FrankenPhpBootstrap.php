<?php
// Worker mode: never call die()/exit() — process must stay alive for frankenphp_handle_request()
namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\ResponseInterface;
use Gemvc\Http\JsonResponse;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;

/**
 * FrankenPHP worker bootstrap — return responses (no die), classic /api/{Service}/{method} routing.
 *
 * Mirrors {@see SwooleBootstrap}'s lifecycle and {@see Bootstrap}'s URL hop for `api`.
 */
class FrankenPhpBootstrap
{
    private Request $request;

    private bool $isApi = true;

    /** @var ApmInterface|null */
    private ?ApmInterface $apm = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->extractRouteInfo();
        $this->initializeApm();
    }

    private function initializeApm(): void
    {
        if (!ApmFactory::isEnabled()) {
            return;
        }
        $this->apm = ApmFactory::create($this->request);
        if ($this->apm !== null) {
            $this->request->setApm($this->apm);
        }
    }

    /**
     * Same hop semantics as Bootstrap::setRequestedService (Apache/Nginx/FrankenPHP classic).
     */
    private function extractRouteInfo(): void
    {
        $method = 'index';
        $urlPath = $this->request->requestedUrl;
        if (($queryPos = strpos($urlPath, '?')) !== false) {
            $urlPath = substr($urlPath, 0, $queryPos);
        }
        $segments = explode('/', $urlPath);

        $isRootUrl = empty($urlPath) ||
            $urlPath === '/' ||
            (count($segments) <= 1 && empty(array_filter($segments)));

        if ($isRootUrl) {
            $this->isApi = true;
            $this->request->setServiceName('Index');
            $this->request->setMethodName('index');
            return;
        }

        $serviceIndex = is_numeric($_ENV['SERVICE_IN_URL_SECTION'] ?? 1)
            ? (int) ($_ENV['SERVICE_IN_URL_SECTION'] ?? 1)
            : 1;
        $service = isset($segments[$serviceIndex])
            ? strtolower((string) $segments[$serviceIndex])
            : '';

        if ($service === 'api') {
            $this->isApi = true;
            if (isset($segments[$serviceIndex + 1]) && $segments[$serviceIndex + 1] !== '') {
                $service = ucfirst($segments[$serviceIndex + 1]);
            } else {
                $service = 'Index';
            }
            if (isset($segments[$serviceIndex + 2]) && $segments[$serviceIndex + 2] !== '') {
                $method = $segments[$serviceIndex + 2];
            }
        } else {
            // Non-/api paths are not served by the worker API stack (use classic or OpenSwoole for web UI)
            $this->isApi = false;
            $service = $service !== '' ? ucfirst($service) : 'Index';
            if (isset($segments[$serviceIndex + 1]) && $segments[$serviceIndex + 1] !== '') {
                $method = $segments[$serviceIndex + 1];
            }
        }

        $this->request->setServiceName($service);
        $this->request->setMethodName($method);
    }

    /**
     * @return ResponseInterface|null
     */
    public function processRequest(): ?ResponseInterface
    {
        if (!$this->isApi) {
            return Response::notFound(
                'FrankenPHP worker serves /api/{Service}/{method} only. Non-API paths are not handled in worker mode.'
            );
        }

        $serviceName = $this->request->getServiceName();
        $apiServicePath = ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . $serviceName . '.php';
        if (!file_exists($apiServicePath)) {
            return Response::notFound("The service path for '$serviceName' does not exist, check your service name if properly typed");
        }

        try {
            RateLimiter::enforceFromEnv($this->request);
        } catch (RateLimitException $e) {
            $this->recordExceptionInApm($e);
            return Response::tooManyRequests($e->getMessage());
        }

        try {
            $service = 'App\\Api\\' . $serviceName;
            $serviceInstance = new $service($this->request);
        } catch (AuthException $e) {
            $this->recordExceptionInApm($e);
            return $this->authExceptionToResponse($e);
        } catch (RateLimitException $e) {
            $this->recordExceptionInApm($e);
            return Response::tooManyRequests($e->getMessage());
        } catch (ValidationException $e) {
            $this->recordExceptionInApm($e);
            return Response::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            return Response::notFound($e->getMessage());
        }

        $methodName = $this->request->getMethodName();
        if (!method_exists($serviceInstance, $methodName)) {
            return Response::notFound("Requested method '$methodName' does not exist in service, check if you typed it correctly");
        }

        try {
            return $serviceInstance->$methodName();
        } catch (AuthException $e) {
            $this->recordExceptionInApm($e);
            return $this->authExceptionToResponse($e);
        } catch (RateLimitException $e) {
            $this->recordExceptionInApm($e);
            return Response::tooManyRequests($e->getMessage());
        } catch (ValidationException $e) {
            $this->recordExceptionInApm($e);
            return Response::badRequest($e->getMessage());
        } catch (\Throwable $e) {
            $this->recordExceptionInApm($e);
            throw $e;
        }
    }

    private function authExceptionToResponse(AuthException $e): JsonResponse
    {
        $httpCode = $e->getCode() > 0 ? $e->getCode() : 401;
        return $httpCode === 403
            ? Response::forbidden($e->getMessage())
            : Response::unauthorized($e->getMessage());
    }

    private function recordExceptionInApm(\Throwable $exception): void
    {
        $apm = $this->request->apm ?? null;
        if ($apm === null && ApmFactory::isEnabled()) {
            $apm = ApmFactory::create($this->request);
            if ($apm !== null) {
                $this->request->setApm($apm);
            }
        }
        if ($apm !== null) {
            $apm->recordException([], $exception);
        }
    }
}
