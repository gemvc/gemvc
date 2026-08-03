<?php
/**
 * GEMVC Framework - PHPDoc stub reference for AI assistants
 *
 * Source of truth: library + gemvc/* package source. Keep signatures aligned with code.
 * Not executed — IDE/AI annotations only.
 *
 * @package Gemvc
 * @version 5.12.0
 * @see docs/ai/CORE_REFERENCE.md
 * @see docs/guides/ecosystem.md
 */

namespace AIAssistant;

/**
 * @property array<mixed> $post
 * @property string|array<mixed> $get
 * @property array<mixed>|null $put
 * @property array<mixed>|null $patch
 * @property array<mixed>|null $files
 * @property mixed $cookies
 * @property null|string|array<string> $authorizationHeader
 * @property bool $isAuthenticated
 * @property bool $isAuthorized
 * @property \Gemvc\Core\Apm\ApmInterface|null $apm
 */
interface RequestReference
{
    public function definePostSchema(array $schema): bool;
    public function defineGetSchema(array $schema): bool;
    public function definePutSchema(array $schema): bool;
    public function definePatchSchema(array $schema): bool;
    public function validateStringPosts(array $stringPosts): bool;

    public function intValueGet(string $key): int|false;
    public function intValuePost(string $key): int|false;
    public function floatValueGet(string $key): float|false;
    public function floatValuePost(string $key): float|false;
    public function stringValueGet(string $key): string|false;
    public function stringValuePost(string $key): string|false;
    public function decimalValueGet(string $key, string $type = 'decimal'): string|false;
    public function decimalValuePost(string $key, string $type = 'decimal'): string|false;

    /**
     * Failure responses: 401 = no token / cannot extract Authorization;
     * 403 = token present but invalid, or valid token with wrong role.
     */
    public function auth(?array $authRules = null): bool;
    public function returnResponse(): \Gemvc\Http\JsonResponse;

    public function findable(array $filterableGetValues): bool;
    public function filterable(array $searchableGetValues): bool;
    public function sortable(array $sortableGetValues): bool;
    public function setPageNumber(): bool;
    public function setPerPage(): bool;
    public function getPageNumber(): int;
    public function getPerPage(): int;

    /** Manual map: key = request field (= property). Value ending in () calls method; otherwise value ignored. */
    public function mapPostToObject(object $object, ?array $manualMap = null): object|null;
    public function mapPutToObject(object $object, ?array $manualMap = null): object|null;
    public function mapPatchToObject(object $object, ?array $manualMap = null): object|null;
}

/**
 * Gemvc\Http\Response — all message params are optional (?string).
 */
interface ResponseFactory
{
    public static function success(mixed $data, ?int $count = null, ?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function created(mixed $data, ?int $count = null, ?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function updated(mixed $data, ?int $count = null, ?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function deleted(mixed $data, ?int $count = null, ?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function successButNoContentToShow(mixed $data, ?int $count = null, ?string $service_message = null): \Gemvc\Http\JsonResponse; // 204
    public static function badRequest(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function unauthorized(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function forbidden(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function notFound(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function unprocessableEntity(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function internalError(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function conflict(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function tooManyRequests(?string $service_message = null): \Gemvc\Http\JsonResponse; // 429
    public static function notAcceptable(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function unsupportedMediaType(?string $service_message = null): \Gemvc\Http\JsonResponse;
    public static function unknownError(mixed $data, ?string $service_message = null): \Gemvc\Http\JsonResponse;
}

interface JsonResponseReference
{
    public function show(): void;
    public function showSwoole(object $swooleResponse): void;
}

interface ApiServiceReference
{
    public function __construct(\Gemvc\Http\Request $request);
    /** @throws \Gemvc\Core\AuthException */
    public function requireAuth(?array $roles = []): void;
    /** @return \Gemvc\Core\ControllerTracingProxy */
    public function callController(\Gemvc\Core\Controller $controller);
    public function index(): \Gemvc\Http\JsonResponse;
    /** @throws \Gemvc\Core\ValidationException */
    public function validateOrFail(array $post_schema): void;
    /** @throws \Gemvc\Core\ValidationException */
    public function validateStringOrFail(array $post_string_schema): void;
    public function validatePosts(array $post_schema): void;
    public static function mockResponse(string $method): array;
}

interface SwooleApiServiceReference
{
    public function __construct(\Gemvc\Http\Request $request);
    /** @throws \Gemvc\Core\AuthException */
    public function requireAuth(?array $roles = []): void;
    /** @return \Gemvc\Core\ControllerTracingProxy — shared via ApiServiceSharedTrait */
    public function callController(\Gemvc\Core\Controller $controller);
    /** @throws \Gemvc\Core\ValidationException */
    public function validateOrFail(array $post_schema): void;
    /** @throws \Gemvc\Core\ValidationException */
    public function validateStringOrFail(array $post_string_schema): void;
    public function validatePosts(array $post_schema): ?\Gemvc\Http\JsonResponse;
    public function validateStringPosts(array $post_string_schema): ?\Gemvc\Http\JsonResponse;
    public static function mockResponse(string $method): array;
}

interface ControllerReference
{
    public function __construct(\Gemvc\Http\Request $request);
    public function createModel(object $model): object;
    public function createList(object $model, ?string $columns = null): \Gemvc\Http\JsonResponse;
    public function listJsonResponse(object $model, ?string $columns = null): \Gemvc\Http\JsonResponse;
}

/**
 * Gemvc\Database\Table (+ CrudOperationsTrait, SoftDeleteOperationsTrait)
 *
 * Base Table only declares abstract getTable(). defineSchema() is a subclass
 * convention (public) used by db:migrate / generators via method_exists — not
 * declared on the base class.
 *
 * For SQL views use ViewTableReference (extends Table) — never migrate a plain
 * Table that only points at a view name.
 */
interface TableReference
{
    public function getTable(): string;
    // Convention on subclasses (not on base Table):
    // public function defineSchema(): array;

    public function select(?string $columns = null): self;
    public function where(string $column, mixed $value): self;
    public function whereEqual(string $column, mixed $value): self;
    public function whereLike(string $column, string $value): self;
    public function whereIn(string $column, array $values): self;
    public function whereNotIn(string $column, array $values): self;
    public function whereOr(string $column, mixed $value): self;
    public function join(string $table, string $condition, string $type = 'INNER'): self;
    /** true = ASC; false or null = DESC; null column = primary key */
    public function orderBy(?string $columnName = null, ?bool $ascending = null): self;
    public function limit(int $limit): self;
    /** Disable pagination LIMIT/OFFSET */
    public function noLimit(): self;
    /** Alias of noLimit() */
    public function all(): self;
    /** SELECT … FOR UPDATE — use inside beginTransaction() (MySQL InnoDB / PostgreSQL) */
    public function forUpdate(bool $enable = true): self;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
    public function run(): ?array;

    public function insertSingleQuery(): ?static;
    public function updateSingleQuery(): ?static;
    public function deleteByIdQuery(int|string $id): int|string|null;
    public function deleteSingleQuery(): ?int;
    public function safeDeleteQuery(): ?static;
    public function restoreQuery(): ?static;
    public function activateQuery(int|string $id): ?int;
    public function deactivateQuery(int|string $id): ?int;

    public function getError(): ?string;
    public function setError(?string $error): void;
    public function setRequest(?\Gemvc\Http\Request $request): void;
}

/**
 * Gemvc\Database\ViewTable extends Table
 *
 * defineView() + flat column props/aliases. Row writes hard-fail.
 * Migrate via ViewGenerator (db:migrate / --all).
 */
interface ViewTableReference
{
    public function getTable(): string;
    public function defineView(): string;
    /** @return list<class-string<\Gemvc\Database\Table>> */
    public function viewDependsOn(): array;
    public function createViewQuery(?\PDO $pdo = null): bool;
    public function replaceViewQuery(?\PDO $pdo = null): bool;
    public function dropViewQuery(?\PDO $pdo = null): bool;
    // select/where/run inherited; insert/update/delete blocked
}

interface SchemaReference
{
    public static function primary(string|array $columns): object;
    public static function autoIncrement(string $column): object;
    public static function unique(string|array $columns): object;
    public static function foreignKey(string $column, string $references): object;
    public static function index(string|array $columns): object;
    public static function check(string $expression): object;
    /** MySQL; method name is fullText (camelCase T) */
    public static function fullText(string|array $columns): object;
}

interface HelperReference
{
    public static function CryptHelper_hashPassword(string $password): string;
    public static function CryptHelper_passwordVerify(string $passwordToCheck, string $hash): bool;
    public static function CryptHelper_encryptString(string $string, string $key): false|string;
    public static function CryptHelper_decryptString(string $encryptedString, string $key): false|string;
    /** @param mixed $type */
    public static function TypeChecker_check(mixed $type, mixed $value, array $options = []): bool;
}

/**
 * Schema type strings accepted by TypeChecker / define*Schema
 */
interface ValidationTypes
{
    const STRING = 'string';
    const INT = 'int';
    const INTEGER = 'integer';
    const FLOAT = 'float';
    const NUMBER = 'number';
    const DECIMAL = 'decimal';
    const BOOL = 'bool';
    const BOOLEAN = 'boolean';
    const ARRAY = 'array';
    const EMAIL = 'email';
    const URL = 'url';
    const DATE = 'date';
    const DATETIME = 'datetime';
    const JSON = 'json';
    const JSONB = 'jsonb';
    const IP = 'ip';
    const IPV4 = 'ipv4';
    const IPV6 = 'ipv6';
    const HEX = 'hex';
    const UUID = 'uuid';
    const SLUG = 'slug';
    const POSITIVE_INT = 'positive_int';
    const TIMESTAMP = 'timestamp';
    const OPTIONAL_PREFIX = '?';
}

interface FrameworkRules
{
    const TABLE_BASE_CLASS = 'Gemvc\\Database\\Table';
    const API_BASE_CLASS = 'Gemvc\\Core\\ApiService'; // or SwooleApiService on OpenSwoole
    const CONTROLLER_BASE_CLASS = 'Gemvc\\Core\\Controller';
    const AGGREGATION_PREFIX = '_';
    const AUTOMATIC_ROUTING = true;
}

/**
 * @see vendor/gemvc/cli-base/AI-Assistant.md
 */
interface CliBaseReference
{
    public function Command_execute(): bool;
    public function Command_write(string $message, \Gemvc\CLI\CliColor $color): void;
}