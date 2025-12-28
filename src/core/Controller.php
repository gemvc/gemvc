<?php

namespace Gemvc\Core;

use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Helper\TraceKitModel;

/**
 * Base class for all controllers
 * 
 * @protected  GemLibrary\Http\Request $request
 * @protected  array<GemvcError> $errors
 * @function   validatePosts(array $post_schema):bool
 */
class Controller
{
    protected Request $request;
    
    /**
     * @var array<GemvcError>
     */
    protected array $errors = [];
    
    /**
     * TraceKitModel instance for automatic request tracing (optional)
     * @var object|null
     */
    private ?object $tracekit = null;

    public function __construct(Request $request)
    {
        $this->errors = [];
        $this->request = $request;
        
        // Initialize TraceKitModel if available (optional dependency)
        $this->initializeTraceKit();
    }
    
    /**
     * Initialize TraceKitModel for automatic request tracing
     * 
     * This is optional - if TraceKitModel class doesn't exist, tracing is silently disabled
     * Note: Trace is already started in ApiService, so Controller uses existing trace for child spans
     * 
     * @return void
     */
    private function initializeTraceKit(): void
    {
        // Check if TraceKitModel class exists (optional dependency)
        if (!class_exists('App\Model\TraceKitModel')) {
            return;
        }
        
        try {
            // Get TraceKitModel instance from static registry (set by ApiService)
            // This ensures we use the SAME instance with the SAME traceId and spans array
            $this->tracekit = TraceKitModel::getCurrentInstance();
            
            if ($this->tracekit === null) {
                error_log("TraceKit: Controller - No active TraceKitModel instance found (ApiService should create it)");
            } else {
                error_log("TraceKit: Controller using shared TraceKitModel instance from ApiService");
            }
        } catch (\Throwable $e) {
            // Silently fail - don't let TraceKit break the application
            error_log("TraceKit: Failed to initialize in Controller: " . $e->getMessage());
            $this->tracekit = null;
        }
    }
    
    /**
     * Get TraceKitModel instance (for creating child spans)
     * 
     * @return object|null TraceKitModel instance or null if not available
     */
    protected function getTraceKit(): ?object
    {
        return $this->tracekit;
    }
    
    /**
     * Start a child span for controller operations
     * 
     * This creates a child span under the root trace started by ApiService
     * 
     * @param string $operationName Operation name (e.g., 'database-query', 'business-logic')
     * @param array<string, mixed> $attributes Optional attributes
     * @param int $kind Span kind: SPAN_KIND_SERVER (2), SPAN_KIND_CLIENT (3), or SPAN_KIND_INTERNAL (1) (default: SPAN_KIND_INTERNAL)
     * @return array<string, mixed> Span data: ['span_id' => string, 'trace_id' => string, 'start_time' => int]
     */
    protected function startTraceSpan(string $operationName, array $attributes = [], int $kind = TraceKitModel::SPAN_KIND_INTERNAL): array
    {
        if ($this->tracekit === null) {
            return [];
        }
        
        /** @var \Gemvc\Helper\TraceKitModel $tracekit */
        $tracekit = $this->tracekit;
        
        if (!$tracekit->isEnabled()) {
            return [];
        }
        
        try {
            // Validate kind is a valid OpenTelemetry span kind integer
            $validKinds = [
                TraceKitModel::SPAN_KIND_UNSPECIFIED,
                TraceKitModel::SPAN_KIND_INTERNAL,
                TraceKitModel::SPAN_KIND_SERVER,
                TraceKitModel::SPAN_KIND_CLIENT,
                TraceKitModel::SPAN_KIND_PRODUCER,
                TraceKitModel::SPAN_KIND_CONSUMER,
            ];
            
            if (!in_array($kind, $validKinds)) {
                $kind = TraceKitModel::SPAN_KIND_INTERNAL;
            }
            
            return $tracekit->startSpan($operationName, $attributes, $kind);
        } catch (\Throwable $e) {
            error_log("TraceKit: Failed to start span in Controller: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * End a child span
     * 
     * @param array<string, mixed> $spanData Span data returned from startTraceSpan()
     * @param array<string, mixed> $finalAttributes Optional attributes to add before ending
     * @param string|null $status Span status: 'OK' or 'ERROR' (default: 'OK')
     * @return void
     */
    protected function endTraceSpan(array $spanData, array $finalAttributes = [], ?string $status = 'OK'): void
    {
        if ($this->tracekit === null || empty($spanData)) {
            return;
        }
        
        try {
            /** @var \Gemvc\Helper\TraceKitModel $tracekit */
            $tracekit = $this->tracekit;
            $statusValue = ($status === 'ERROR') ? TraceKitModel::STATUS_ERROR : TraceKitModel::STATUS_OK;
            $tracekit->endSpan($spanData, $finalAttributes, $statusValue);
        } catch (\Throwable $e) {
            error_log("TraceKit: Failed to end span in Controller: " . $e->getMessage());
        }
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
            // Use existing trace if available, or create one (errors are always logged)
            $tracekit->recordException([], $exception, 'controller-operation', [
                'controller' => $this->getControllerName(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail
            error_log("TraceKit: Failed to record exception in Controller: " . $e->getMessage());
        }
    }
    
    /**
     * Get controller name for tracing
     * 
     * @return string
     */
    private function getControllerName(): string
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        return $parts[count($parts) - 1] ?? 'Unknown';
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
     * columns "id,name,email" only return id name and email
     * @param object $model
     * @param string|null $columns
     * @return JsonResponse
     */
    public function createList(object $model, ?string $columns = null): JsonResponse
    {
        if(!$columns) {
            $columns = implode(',', array_map(fn($k) => "\"$k\"", array_keys(get_object_vars($model))));
        }
        /**@phpstan-ignore-next-line */
        return Response::success($this->_listObjects($model, $columns), $model->getTotalCounts(), 'list of ' . $model->getTable() . ' fetched successfully');
    }

    /**
     * columns "id,name,email" only return id name and email
     * @param object $model
     * @param string|null $columns
     * @return JsonResponse
     */
    public function listJsonResponse(object $model, ?string $columns = null): JsonResponse
    {
        /**@phpstan-ignore-next-line */
        return Response::success($this->_listObjects($model, $columns), $model->getTotalCounts(), 'list of ' . $model->getTable() . ' fetched successfully');
    }

    /**
     * Validates that required properties are set
     * @throws \RuntimeException
     */
    protected function validateRequiredProperties(): void
    {
        if (!method_exists($this, 'getTable')) {
            throw new \RuntimeException('Table name must be defined in the model');
        }
    }

    /**
     * Handles pagination parameters
     */
    /**
     * Handles pagination parameters
     * 
     * @throws ValidationException If pagination parameters are invalid
     */
    private function _handlePagination(object $model): object
    {
        if (isset($this->request->get["page_number"])) {
            /**@phpstan-ignore-next-line */
            if (!is_numeric(trim($this->request->get["page_number"]))) {
                throw new ValidationException("page_number shall be type if integer or number", 400);
            }
            /**@phpstan-ignore-next-line */
            $page_number = (int) $this->request->get["page_number"];
            if ($page_number < 1) {
                throw new ValidationException("page_number shall be positive int", 400);
            }
            /**@phpstan-ignore-next-line */
            $model->setPage($page_number);
            return $model;
        }
        /**@phpstan-ignore-next-line */
        $model->setPage(1);
        return $model;
    }


    /**
     * Handles sorting/ordering parameters
     */
    private function _handleSortable(object $model): object
    {
        $sort_des = $this->request->getSortable();
        $sort_asc = $this->request->getSortableAsc();
        if ($sort_des) {
            /**@phpstan-ignore-next-line */
            $model->orderBy($sort_des);
        }
        if ($sort_asc) {
            /**@phpstan-ignore-next-line */
            $model->orderBy($sort_asc, true);
        }
        return $model;
    }


    /**
     * Handles findable/filterable parameters
     * 
     * @throws ValidationException If filterable key is not found in model properties
     */
    private function _handleFindable(object $model): object
    {
        $array_orderby = $this->request->getFindable();
        if (count($array_orderby) == 0) {
            return $model;
        }
        foreach ($array_orderby as $key => $value) {
            $array_orderby[$key] = $this->_sanitizeInput($value);
        }
        $array_exited_object_properties = get_class_vars(get_class($model));
        foreach ($array_orderby as $key => $value) {
            if (!array_key_exists($key, $array_exited_object_properties)) {
                throw new ValidationException("filterable key $key not found in object properties", 400);
            }
        }
        foreach ($array_orderby as $key => $value) {
            /**@phpstan-ignore-next-line */
            $model->whereLike($key, $value);
        }
        return $model;
    }


    /**
     * Handles all filter types (create where)
     * 
     * @throws ValidationException If searchable key is not found or model property assignment fails
     */
    private function _handleSearchable(object $model): object
    {
        $arr_errors = null;
        $array_searchable = $this->request->getFilterable();
        if (count($array_searchable) == 0) {
            return $model;
        }
        foreach ($array_searchable as $key => $value) {
            $array_searchable[$key] = $this->_sanitizeInput($value);
        }
        $array_exited_object_properties = get_class_vars(get_class($model));
        foreach ($array_searchable as $key => $value) {
            if (!array_key_exists($key, $array_exited_object_properties)) {
                throw new ValidationException("searchable key $key not found in object properties", 400);
            }
        }

        foreach ($array_searchable as $key => $value) {
            try {
                $model->$key = $value;
            } catch (\Exception $e) {
                $arr_errors .= $e->getMessage() . ",";
            }
        }

        if ($arr_errors) {
            throw new ValidationException(rtrim($arr_errors, ','), 400);
        }
        foreach ($array_searchable as $key => $value) {
            /**@phpstan-ignore-next-line */
            $model->where($key, $value);
        }
        return $model;
    }


    /**
     * Basic input sanitization
     */
    private function _sanitizeInput(mixed $input): mixed
    {
        if (is_string($input)) {
            // Remove any null bytes
            $input = str_replace(chr(0), '', $input);
            // Convert special characters to HTML entities
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

        /**
     * @template T of object
     * @param T $model
     * @param string|null $columns
     * @return array<object>
     * array of objects from given model with pagination, sorting and filtering
     * columns "id,name,email" only return id name and email
     */
    /**
     * List objects with pagination, sorting, and filtering
     * 
     * @template T of object
     * @param T $model
     * @param string|null $columns
     * @return array<array<string, mixed>>
     * @throws ValidationException If validation fails during filtering/pagination
     * @throws \RuntimeException If model query execution fails
     */
   private function _listObjects(object $model, ?string $columns = null): array
    {
        $model = $this->_handleSearchable($model);
        $model = $this->_handleFindable($model);
        $model = $this->_handleSortable($model);
        $model = $this->_handlePagination($model);
        //$publicPropertiesString = implode(',', array_map(fn($k) => "\"$k\"", array_keys(get_object_vars($model))));
        //$fastString = trim(json_encode(array_keys(get_object_vars($model))), '[]');
        if(!$columns) {
            $columns = '*';
        }
        /** @phpstan-ignore-next-line */
        $result = $model->select($columns)->run();
        if($result === false) {
            // @phpstan-ignore-next-line
            $errorMessage = $model->getError() ?? 'Database query execution failed';
            throw new \RuntimeException($errorMessage);
        }
        /** @var array<T> $result */
        // Convert objects to arrays to avoid PHP 8.4+ protected property access issues
        // get_object_vars() only returns public properties when called from outside the class
        return array_map(function($item): array {
            /** @var T $item */
            $vars = get_object_vars($item);
            $result = [];
            foreach ($vars as $key => $val) {
                if ($key[0] === '_') continue;
                $result[$key] = $val;
            }
            return $result;
        }, $result);
    }
}
