<?php

namespace Gemvc\GraphQL;

use Gemvc\Helper\ProjectHelper;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Execute a GraphQL operation from {@see Request} POST ({@code query}, {@code variables}, {@code operationName}).
 *
 * Requires {@code webonyx/graphql-php}. Fail-closed with HTTP 500 if the package is missing.
 * Schema is app-owned: {@code app/graphql/schema.php} or {@see withSchema()}.
 */
final class GraphQlRunner
{
    public const DEFAULT_MAX_DEPTH = 10;
    public const DEFAULT_MAX_COMPLEXITY = 200;

    private Request $request;
    private ?Schema $schema = null;
    private ?string $schemaPath = null;

    private function __construct(Request $request)
    {
        $this->request = $request;
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    public function withSchema(Schema $schema): self
    {
        $clone = clone $this;
        $clone->schema = $schema;

        return $clone;
    }

    public function withSchemaPath(string $path): self
    {
        $clone = clone $this;
        $clone->schemaPath = $path;

        return $clone;
    }

    public function execute(): JsonResponse
    {
        if (!class_exists(GraphQL::class)) {
            return Response::internalError(
                'GraphQL requires webonyx/graphql-php. Install with: composer require webonyx/graphql-php'
            );
        }

        $query = $this->request->post['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            return Response::badRequest('GraphQL query string is required');
        }

        try {
            $schema = $this->resolveSchema();
        } catch (\RuntimeException $e) {
            return Response::internalError($e->getMessage());
        }

        $operationName = $this->operationName();
        $variables = $this->variableValues();
        if ($variables === false) {
            return Response::badRequest('GraphQL variables must be a JSON object');
        }

        try {
            $result = GraphQL::executeQuery(
                $schema,
                $query,
                null,
                $this->request,
                $variables,
                $operationName,
                null,
                $this->validationRules()
            );
            $output = $result->toArray();
        } catch (\Throwable $e) {
            return GraphQlJsonResponse::spec(null, [
                ['message' => $this->clientErrorMessage($e)],
            ]);
        }

        return GraphQlJsonResponse::spec(
            $output['data'] ?? null,
            $output['errors'] ?? null
        );
    }

    private function resolveSchema(): Schema
    {
        if ($this->schema instanceof Schema) {
            return $this->schema;
        }

        $path = $this->schemaPath ?? (ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'graphql' . DIRECTORY_SEPARATOR . 'schema.php');
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('GraphQL schema file not found or not readable: ' . $path);
        }

        $loaded = require $path;
        if ($loaded instanceof Schema) {
            return $loaded;
        }
        if (is_callable($loaded)) {
            $schema = $loaded($this->request);
            if ($schema instanceof Schema) {
                return $schema;
            }
        }

        throw new \RuntimeException('app/graphql/schema.php must return a GraphQL\\Type\\Schema or callable(Request): Schema');
    }

    /**
     * @return array<string, mixed>|null|false false when variables are present but invalid
     */
    private function variableValues(): array|null|false
    {
        $raw = $this->request->post['variables'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return false;
            }
            $raw = $decoded;
        }
        if (!is_array($raw)) {
            return false;
        }
        $out = [];
        foreach ($raw as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function operationName(): ?string
    {
        $name = $this->request->post['operationName'] ?? null;
        if (!is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }

    /**
     * @return array<int|string, ValidationRule>
     */
    private function validationRules(): array
    {
        $rules = array_values(GraphQL::getStandardValidationRules());
        $rules[] = new QueryDepth($this->envInt('GRAPHQL_MAX_DEPTH', self::DEFAULT_MAX_DEPTH));
        $rules[] = new QueryComplexity($this->envInt('GRAPHQL_MAX_COMPLEXITY', self::DEFAULT_MAX_COMPLEXITY));
        if ($this->introspectionDisabled()) {
            $rules[] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        return $rules;
    }

    private function introspectionDisabled(): bool
    {
        $flag = $_ENV['GRAPHQL_INTROSPECTION'] ?? null;
        if (is_string($flag) && ($flag === '0' || strtolower($flag) === 'off' || strtolower($flag) === 'false')) {
            return true;
        }
        if (is_string($flag) && ($flag === '1' || strtolower($flag) === 'on' || strtolower($flag) === 'true')) {
            return false;
        }

        return !ProjectHelper::isDevEnvironment();
    }

    private function envInt(string $key, int $default): int
    {
        $raw = $_ENV[$key] ?? null;
        if (!is_numeric($raw)) {
            return $default;
        }
        $value = (int) $raw;

        return $value > 0 ? $value : $default;
    }

    private function clientErrorMessage(\Throwable $e): string
    {
        if (ProjectHelper::isDevEnvironment()) {
            return $e->getMessage();
        }

        return 'GraphQL execution failed';
    }
}
