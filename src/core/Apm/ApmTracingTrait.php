<?php

namespace Gemvc\Core\Apm;

use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Http\Request;
use Gemvc\Helper\ProjectHelper;

/**
 * APM Tracing Trait
 * 
 * Provides unified APM tracing methods for all framework layers.
 * Use this trait in Bootstrap, ApiService, Controller, UniversalQueryExecuter, and Models.
 * 
 * Automatically uses Request APM when available (shares traceId),
 * falls back to standalone APM for CLI/background jobs.
 * 
 * Usage:
 * ```php
 * class UserModel extends UserTable
 * {
 *     use ApmTracingTrait;
 *     
 *     public function complexOperation(): JsonResponse
 *     {
 *         return $this->traceApm('complex-calculation', function() {
 *             return $this->doComplexWork();
 *         }, ['model' => 'UserModel']);
 *     }
 * }
 * ```
 */
trait ApmTracingTrait
{
    /**
     * Get APM instance (Request APM if available, otherwise standalone)
     * 
     * Tries to get APM from:
     * 1. $this->request->apm (if class has Request property)
     * 2. ApmFactory::create(null) (standalone, for CLI/background jobs)
     * 
     * @return ApmInterface|null
     */
    protected function getApm(): ?ApmInterface
    {
        // Try to use Request APM first (shares traceId)
        // @phpstan-ignore-next-line - Trait is used by multiple classes, not all have request property
        if (property_exists($this, 'request') && $this->request instanceof Request) {
            if ($this->request->apm !== null) {
                return $this->request->apm;
            }
        }
        
        // Fallback: create standalone APM (for CLI, background jobs, etc.)
        $apmName = ApmFactory::isEnabled();
        if ($apmName === null) {
            return null;
        }
        
        return ApmFactory::create(null);
    }
    
    /**
     * Start an APM span
     * 
     * @param string $operationName Name of the operation
     * @param array<string, mixed> $attributes Additional span attributes
     * @param int $kind Span kind (default: SPAN_KIND_INTERNAL)
     * @return array<string, mixed> Span data (empty array if APM disabled or error)
     */
    protected function startApmSpan(
        string $operationName,
        array $attributes = [],
        int $kind = ApmInterface::SPAN_KIND_INTERNAL
    ): array {
        $apm = $this->getApm();
        if ($apm === null || !$apm->isEnabled()) {
            return [];
        }
        
        try {
            // Validate kind is a valid OpenTelemetry span kind integer
            $validKinds = [
                ApmInterface::SPAN_KIND_UNSPECIFIED,
                ApmInterface::SPAN_KIND_INTERNAL,
                ApmInterface::SPAN_KIND_SERVER,
                ApmInterface::SPAN_KIND_CLIENT,
                ApmInterface::SPAN_KIND_PRODUCER,
                ApmInterface::SPAN_KIND_CONSUMER,
            ];
            
            if (!in_array($kind, $validKinds)) {
                $kind = ApmInterface::SPAN_KIND_INTERNAL;
            }
            
            return $apm->startSpan($operationName, $attributes, $kind);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (ProjectHelper::isDevEnvironment()) {
                error_log("ApmTracingTrait::startApmSpan() error: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * End an APM span
     * 
     * @param array<string, mixed> $spanData Span data from startApmSpan()
     * @param array<string, mixed> $attributes Final span attributes
     * @param string $status Status: 'OK' or 'ERROR'
     * @return void
     */
    protected function endApmSpan(
        array $spanData,
        array $attributes = [],
        string $status = 'OK'
    ): void {
        if (empty($spanData)) {
            return; // Span was not started (APM disabled or error)
        }
        
        $apm = $this->getApm();
        if ($apm === null) {
            return;
        }
        
        try {
            $statusValue = ($status === 'ERROR') ? ApmInterface::STATUS_ERROR : ApmInterface::STATUS_OK;
            $apm->endSpan($spanData, $attributes, $statusValue);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (ProjectHelper::isDevEnvironment()) {
                error_log("ApmTracingTrait::endApmSpan() error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Record an exception in an APM span
     * 
     * @param array<string, mixed> $spanData Span data from startApmSpan()
     * @param \Throwable $exception The exception to record
     * @return void
     */
    protected function recordApmException(array $spanData, \Throwable $exception): void
    {
        if (empty($spanData)) {
            return; // Span was not started (APM disabled or error)
        }
        
        $apm = $this->getApm();
        if ($apm === null) {
            return;
        }
        
        try {
            $apm->recordException($spanData, $exception);
        } catch (\Throwable $e) {
            // Silently fail - don't break operations if APM has issues
            if (ProjectHelper::isDevEnvironment()) {
                error_log("ApmTracingTrait::recordApmException() error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Trace an operation with automatic span management
     * 
     * Executes a callback within an APM span, automatically handling start/end/exception.
     * 
     * @param string $operationName Name of the operation
     * @param callable $callback The operation to trace
     * @param array<string, mixed> $attributes Additional span attributes
     * @return mixed The return value of the callback
     * @throws \Throwable Re-throws any exception from callback
     */
    protected function traceApm(string $operationName, callable $callback, array $attributes = []): mixed
    {
        $span = $this->startApmSpan($operationName, $attributes);
        
        try {
            $result = $callback();
            $this->endApmSpan($span, [], 'OK');
            return $result;
        } catch (\Throwable $e) {
            $this->recordApmException($span, $e);
            $this->endApmSpan($span, [], 'ERROR');
            throw $e;
        }
    }
}

