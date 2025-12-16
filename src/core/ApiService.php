<?php

namespace Gemvc\Core;

use Gemvc\Http\Request;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;


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

    public function __construct(Request $request)
    {
        $this->error = null;
        $this->errors = [];
        $this->request = $request;
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
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return [];
    }

}
