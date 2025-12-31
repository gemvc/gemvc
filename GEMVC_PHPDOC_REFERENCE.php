<?php
/**
 * GEMVC Framework - PHPDoc Reference for AI Assistants
 * 
 * This file provides comprehensive PHPDoc annotations for GitHub Copilot
 * and other AI code completion tools to understand GEMVC framework.
 * 
 * @package Gemvc
 * @version 2.0
 * @see https://gemvc.de
 */

namespace AIAssistant;

/**
 * Core Request Object
 * All inputs are pre-sanitized - NO manual sanitization needed!
 * 
 * @property string $requestedUrl Sanitized URL
 * @property array $post Sanitized POST data
 * @property array $get Sanitized GET data
 * @property array|null $put Sanitized PUT data
 * @property array|null $patch Sanitized PATCH data
 * @property array|null $files Sanitized file uploads
 * @property mixed $cookies Filtered cookies
 * @property int $limit Pagination limit
 * @property int $offset Pagination offset
 */
interface RequestReference {
    /**
     * Validate POST schema - prevents mass assignment
     * 
     * @param array<string, string> $schema Validation schema
     * @param string $schema['field'] 'string'|'int'|'email'|'?field' (optional with ?)
     * @return bool True if valid, false if validation failed
     * 
     * @example
     * $this->request->definePostSchema([
     *     'name' => 'string',
     *     'email' => 'email',
     *     '?phone' => 'string'  // Optional field
     * ])
     */
    public function definePostSchema(array $schema): bool;
    
    /**
     * Validate GET schema
     * 
     * @param array<string, string> $schema Validation schema
     * @return bool
     */
    public function defineGetSchema(array $schema): bool;
    
    /**
     * Validate string lengths in POST data
     * 
     * @param array<string, string> $validations Map of field => 'min|max'
     * @return bool
     * 
     * @example
     * $this->request->validateStringPosts([
     *     'name' => '2|100',      // 2-100 chars
     *     'password' => '8|128'   // 8-128 chars
     * ])
     */
    public function validateStringPosts(array $validations): bool;
    
    /**
     * Get integer value from GET params
     * 
     * @param string $key Parameter key
     * @return int|null Integer value or null
     */
    public function intValueGet(string $key): ?int;
    
    /**
     * Get string value from GET params
     * 
     * @param string $key Parameter key
     * @return string|null String value or null
     */
    public function stringValueGet(string $key): ?string;
    
    /**
     * JWT Authentication check
     * 
     * @param array<string>|null $roles Required roles (null = just authenticated)
     * @return bool True if authenticated/authorized
     * 
     * @example
     * $this->request->auth()  // Just authenticated
     * $this->request->auth(['admin', 'moderator'])  // Role check
     */
    public function auth(?array $roles = null): bool;
    
    /**
     * Map POST data to model object
     * 
     * @param object $object Model instance
     * @param array<string, string> $mapping Field mappings ['field' => 'setMethod()']
     * @return object|false Mapped object or false on error
     * 
     * @example
     * $model = $this->request->mapPostToObject(
     *     new UserModel(),
     *     ['email'=>'email', 'password'=>'setPassword()']
     * );
     */
    public function mapPostToObject(object $object, array $mapping = []): object|false;
    
    /**
     * Set filterable fields for list operations
     * 
     * @param array<string, string> $fields Field definitions
     * @return void
     * 
     * @example
     * $this->request->findable(['name' => 'string', 'email' => 'email'])
     */
    public function findable(array $fields): void;
    
    /**
     * Set sortable fields for list operations
     * 
     * @param array<string> $fields Sortable field names
     * @return void
     * 
     * @example
     * $this->request->sortable(['id', 'name', 'created_at'])
     */
    public function sortable(array $fields): void;
    
    /**
     * Return error response
     * 
     * @return \Gemvc\Http\JsonResponse Error response
     */
    public function returnResponse(): \Gemvc\Http\JsonResponse;
}

/**
 * Response Factory
 * Static methods for creating responses
 */
interface ResponseFactory {
    /**
     * Success response (200 OK)
     * 
     * @param mixed $data Response data
     * @param int $count Number of items
     * @param string $message Success message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function success($data, int $count, string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Created response (201 Created)
     * 
     * @param mixed $data Response data
     * @param int $count Number of items
     * @param string $message Success message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function created($data, int $count, string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Updated response (209 Updated)
     * 
     * @param bool $result Update success
     * @param int $count Number of items
     * @param string $message Success message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function updated(bool $result, int $count, string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Deleted response (210 Deleted)
     * 
     * @param bool $result Delete success
     * @param int $count Number of items
     * @param string $message Success message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function deleted(bool $result, int $count, string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Not found response (404)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function notFound(string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Bad request response (400)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function badRequest(string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Unprocessable entity response (422)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function unprocessableEntity(string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Internal error response (500)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function internalError(string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Unauthorized response (401)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function unauthorized(string $message): \Gemvc\Http\JsonResponse;
    
    /**
     * Forbidden response (403)
     * 
     * @param string $message Error message
     * @return \Gemvc\Http\JsonResponse
     */
    public static function forbidden(string $message): \Gemvc\Http\JsonResponse;
}

/**
 * Table ORM Base Class
 * All table classes MUST extend \Gemvc\Database\Table
 */
interface TableReference {
    /**
     * Start SELECT query
     * 
     * @param array<string>|null $columns Columns to select (null = all)
     * @return self
     */
    public function select(?array $columns = null): self;
    
    /**
     * Add WHERE clause
     * 
     * @param string $column Column name
     * @param mixed $value Value to match
     * @return self
     */
    public function where(string $column, mixed $value): self;
    
    /**
     * Add WHERE IN clause
     * 
     * @param string $column Column name
     * @param array $values Values to match
     * @return self
     */
    public function whereIn(string $column, array $values): self;
    
    /**
     * Add WHERE LIKE clause
     * 
     * @param string $column Column name
     * @param string $pattern LIKE pattern
     * @return self
     */
    public function whereLike(string $column, string $pattern): self;
    
    /**
     * Add WHERE OR clause
     * 
     * @param string $column Column name
     * @param array $values Values to match (OR logic)
     * @return self
     */
    public function whereOr(string $column, array $values): self;
    
    /**
     * Add WHERE IS NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function whereIsNull(string $column): self;
    
    /**
     * Add WHERE IS NOT NULL clause
     * 
     * @param string $column Column name
     * @return self
     */
    public function whereIsNotNull(string $column): self;
    
    /**
     * Add JOIN clause
     * 
     * @param string $table Table to join
     * @param string $condition Join condition
     * @param string $type Join type (INNER, LEFT, RIGHT, etc.)
     * @return self
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self;
    
    /**
     * Add ORDER BY clause
     * 
     * @param string $column Column name
     * @param string $direction ASC or DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;
    
    /**
     * Add LIMIT clause
     * 
     * @param int $limit Number of rows
     * @return self
     */
    public function limit(int $limit): self;
    
    /**
     * Add OFFSET clause
     * 
     * @param int $offset Offset value
     * @return self
     */
    public function offset(int $offset): self;
    
    /**
     * Execute query and return results
     * 
     * @return array<static> Array of model objects
     */
    public function run(): array;
    
    /**
     * Insert single record
     * 
     * @return bool Success
     */
    public function insertSingleQuery(): bool;
    
    /**
     * Update single record
     * 
     * @return bool Success
     */
    public function updateSingleQuery(): bool;
    
    /**
     * Delete by ID
     * 
     * @param int $id Record ID
     * @return bool Success
     */
    public function deleteByIdQuery(int $id): bool;
    
    /**
     * Get last error
     * 
     * @return string|null Error message
     */
    public function getError(): ?string;
    
    /**
     * Set error message
     * 
     * @param string|null $error Error message
     * @return void
     */
    public function setError(?string $error): void;
    
    /**
     * Validate ID parameter
     * 
     * @param int $id ID to validate
     * @param string $operation Operation name
     * @return bool True if valid
     */
    public function validateId(int $id, string $operation = 'operation'): bool;
}

/**
 * Schema Builder Methods
 * Use in defineSchema() method
 */
interface SchemaReference {
    /**
     * Create primary key constraint
     * 
     * @param string|array<string> $columns Column name(s)
     * @return object Schema object
     */
    public static function primary(string|array $columns);
    
    /**
     * Create auto increment constraint
     * 
     * @param string $column Column name
     * @return object Schema object
     */
    public static function autoIncrement(string $column);
    
    /**
     * Create unique constraint
     * 
     * @param string|array<string> $columns Column name(s)
     * @return object Schema object
     */
    public static function unique(string|array $columns);
    
    /**
     * Create foreign key constraint
     * 
     * @param string $column Column name
     * @param string $reference Reference (e.g., 'users.id')
     * @return object Fluent builder object with ->onDeleteCascade(), ->onDeleteRestrict() methods
     */
    public static function foreignKey(string $column, string $reference);
    
    /**
     * Create index
     * 
     * @param string|array<string> $columns Column name(s)
     * @return object Schema object
     */
    public static function index(string|array $columns);
    
    /**
     * Create check constraint
     * 
     * @param string $condition Check condition (e.g., 'age >= 18')
     * @return object Schema object
     */
    public static function check(string $condition);
    
    /**
     * Create fulltext index
     * 
     * @param string|array<string> $columns Column name(s)
     * @return object Schema object
     */
    public static function fulltext(string|array $columns);
}

/**
 * Framework Rules for AI Assistants
 */
interface FrameworkRules {
    /**
     * ALL table classes MUST extend \Gemvc\Database\Table
     */
    const TABLE_BASE_CLASS = 'Gemvc\\Database\\Table';
    
    /**
     * ALL API services MUST extend \Gemvc\Core\ApiService
     */
    const API_BASE_CLASS = 'Gemvc\\Core\\ApiService';
    
    /**
     * ALL controllers MUST extend \Gemvc\Core\Controller
     */
    const CONTROLLER_BASE_CLASS = 'Gemvc\\Core\\Controller';
    
    /**
     * Properties starting with _ are IGNORED in CRUD operations
     */
    const AGGREGATION_PREFIX = '_';
    
    /**
     * Use protected for sensitive data (hidden from SELECT)
     */
    const USE_PROTECTED_FOR_SENSITIVE = true;
    
    /**
     * All inputs are pre-sanitized - NO manual sanitization
     */
    const SANITIZATION_AUTOMATIC = true;
    
    /**
     * All queries use prepared statements - NO string concatenation
     */
    const SQL_INJECTION_PREVENTION = true;
    
    /**
     * URL mapping is automatic - NO routes config needed
     */
    const AUTOMATIC_ROUTING = true;
    
    /**
     * PHPStan Level 9 compliance required
     */
    const TYPE_SAFETY_REQUIRED = true;
}

/**
 * Validation Types Reference
 * Use in definePostSchema() and defineGetSchema()
 */
interface ValidationTypes {
    const STRING = 'string';
    const INTEGER = 'int';
    const FLOAT = 'float';
    const BOOLEAN = 'bool';
    const ARRAY = 'array';
    const EMAIL = 'email';
    const URL = 'url';
    const DATE = 'date';
    const DATETIME = 'datetime';
    const JSON = 'json';
    const IP = 'ip';
    const IPV4 = 'ipv4';
    const IPV6 = 'ipv6';
    
    /**
     * Optional field prefix
     * Add ? before field name to make it optional
     * 
     * @example '?phone' => 'string'
     */
    const OPTIONAL_PREFIX = '?';
}

/**
 * Common Helper Classes
 */
interface HelperClasses {
    /**
     * Password hashing (Argon2i)
     * 
     * @param string $plainPassword Plain text password
     * @return string Hashed password
     */
    public static function CryptHelper_hashPassword(string $plainPassword): string;
    
    /**
     * Password verification
     * 
     * @param string $plain Plain text password
     * @param string $hashed Hashed password
     * @return bool True if valid
     */
    public static function CryptHelper_passwordVerify(string $plain, string $hashed): bool;
    
    /**
     * File encryption (AES-256-CBC + HMAC)
     * 
     * @param string $data Data to encrypt
     * @param string $secret Encryption key
     * @return string Encrypted data
     */
    public static function CryptHelper_encrypt(string $data, string $secret): string;
    
    /**
     * File decryption
     * 
     * @param string $encrypted Encrypted data
     * @param string $secret Encryption key
     * @return string Decrypted data
     */
    public static function CryptHelper_decrypt(string $encrypted, string $secret): string;
    
    /**
     * Image validation and conversion
     * 
     * @param string $sourceFile Source image file
     * @param int $quality Quality (1-100)
     * @return bool True if valid image and converted successfully
     */
    public static function ImageHelper_convertToWebP(string $sourceFile, int $quality = 80): bool;
    
    /**
     * Get memory usage metrics (cross-platform)
     * 
     * @return array<string, mixed> Memory usage information
     */
    public static function ServerMonitorHelper_getMemoryUsage(): array;
    
    /**
     * Get CPU load average
     * 
     * @return array<string, float> Load average (1min, 5min, 15min)
     */
    public static function ServerMonitorHelper_getCpuLoad(): array;
    
    /**
     * Get CPU core count
     * 
     * @return int Number of CPU cores
     */
    public static function ServerMonitorHelper_getCpuCores(): int;
    
    /**
     * Get CPU usage percentage
     * 
     * @return float|null CPU usage percentage or null if unavailable
     */
    public static function ServerMonitorHelper_getCpuUsage(): ?float;
    
    /**
     * Get network statistics for all interfaces
     * 
     * @return array<string, mixed> Network statistics
     */
    public static function NetworkHelper_getNetworkStats(): array;
    
    /**
     * Get list of network interfaces
     * 
     * @return array<string> Interface names
     */
    public static function NetworkHelper_getNetworkInterfaces(): array;
    
    /**
     * Get statistics for specific network interface
     * 
     * @param string $interface Interface name
     * @return array<string, mixed> Interface statistics
     */
    public static function NetworkHelper_getInterfaceStats(string $interface): array;
    
    /**
     * Check if APM is enabled and return provider name
     * 
     * @return string|null APM provider name if enabled, null otherwise
     */
    public static function ProjectHelper_isApmEnabled(): ?string;
    
    /**
     * Create APM instance via factory
     * 
     * @param \Gemvc\Http\Request|null $request Request object
     * @return \Gemvc\Core\Apm\ApmInterface|null APM instance or null
     */
    public static function ApmFactory_create(?\Gemvc\Http\Request $request): ?\Gemvc\Core\Apm\ApmInterface;
    
    /**
     * Check if APM is enabled
     * 
     * @return bool True if APM is enabled
     */
    public static function ApmFactory_isEnabled(): bool;
}

