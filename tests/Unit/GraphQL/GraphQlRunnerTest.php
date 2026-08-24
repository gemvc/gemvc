<?php

declare(strict_types=1);

namespace Tests\Unit\GraphQL;

use Gemvc\Core\AuthException;
use Gemvc\Core\ProtectedApiService;
use Gemvc\GraphQL\GraphQlJsonResponse;
use Gemvc\GraphQL\GraphQlRunner;
use Gemvc\GraphQL\JsonResponseBridge;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use Gemvc\Http\Response;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class TestGraphqlProtectedService extends ProtectedApiService
{
    public function query(): JsonResponse
    {
        return GraphQlRunner::fromRequest($this->request)->withSchema(GraphQlRunnerTest::helloSchema())->execute();
    }
}

final class TestGraphqlPublicService extends \Gemvc\Core\ApiService
{
    public function query(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'query' => 'string',
            '?variables' => 'array',
            '?operationName' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return GraphQlRunner::fromRequest($this->request)->withSchema(GraphQlRunnerTest::helloSchema())->execute();
    }
}

final class GraphQlRunnerTest extends TestCase
{
    public static function helloSchema(): Schema
    {
        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (): string => 'gemvc',
                ],
            ],
        ]);

        return new Schema(['query' => $query]);
    }

    public function testExecuteHelloReturnsSpecEnvelope(): void
    {
        $request = new Request();
        $request->post = ['query' => '{ hello }'];

        $response = GraphQlRunner::fromRequest($request)->withSchema(self::helloSchema())->execute();

        $this->assertInstanceOf(GraphQlJsonResponse::class, $response);
        $this->assertSame(200, $response->response_code);
        $payload = $response->jsonSerialize();
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
        $this->assertSame('gemvc', $payload['data']['hello']);
        $this->assertArrayNotHasKey('errors', $payload);
        $this->assertArrayNotHasKey('response_code', $payload);
    }

    public function testExecuteInvalidQueryReturns200WithErrors(): void
    {
        $request = new Request();
        $request->post = ['query' => '{ notAField }'];

        $response = GraphQlRunner::fromRequest($request)->withSchema(self::helloSchema())->execute();

        $this->assertSame(200, $response->response_code);
        $payload = $response->jsonSerialize();
        $this->assertArrayHasKey('errors', $payload);
        $this->assertIsArray($payload['errors']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testMissingQueryStringReturns400(): void
    {
        $request = new Request();
        $request->post = [];

        $response = GraphQlRunner::fromRequest($request)->withSchema(self::helloSchema())->execute();

        $this->assertSame(400, $response->response_code);
    }

    public function testApiSchemaRejectsMissingQuery(): void
    {
        $request = new Request();
        $request->post = [];
        $service = new TestGraphqlPublicService($request);
        $response = $service->query();

        $this->assertSame(400, $response->response_code);
    }

    public function testMissingSchemaFileReturns500(): void
    {
        $request = new Request();
        $request->post = ['query' => '{ hello }'];

        $response = GraphQlRunner::fromRequest($request)
            ->withSchemaPath('/tmp/gemvc-graphql-missing-schema.php')
            ->execute();

        $this->assertSame(500, $response->response_code);
    }

    public function testSchemaPhpCallableIsLoaded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gemvc-gql-');
        $this->assertIsString($path);
        $schemaFile = $path . '.php';
        unlink($path);
        file_put_contents(
            $schemaFile,
            <<<'PHP'
<?php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
return static function (\Gemvc\Http\Request $request): Schema {
    return new Schema([
        'query' => new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => static fn (): string => 'from-file',
                ],
            ],
        ]),
    ]);
};
PHP
        );

        try {
            $request = new Request();
            $request->post = ['query' => '{ hello }'];
            $response = GraphQlRunner::fromRequest($request)->withSchemaPath($schemaFile)->execute();
            $this->assertSame(200, $response->response_code);
            $payload = $response->jsonSerialize();
            $this->assertIsArray($payload['data']);
            $this->assertSame('from-file', $payload['data']['hello']);
        } finally {
            unlink($schemaFile);
        }
    }

    public function testJsonResponseBridgeThrowsOnHttpError(): void
    {
        $this->expectException(\GraphQL\Error\UserError::class);
        JsonResponseBridge::dataOrThrow(Response::forbidden('nope'));
    }

    public function testJsonResponseBridgeReturnsDataOnSuccess(): void
    {
        $this->assertSame(['ok' => true], JsonResponseBridge::dataOrThrow(Response::success(['ok' => true])));
    }

    public function testProtectedGraphqlThrowsAuthExceptionWithoutToken(): void
    {
        $request = new Request();
        $request->post = ['query' => '{ hello }'];

        try {
            new TestGraphqlProtectedService($request);
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            $this->assertSame(401, $e->getCode());
        }
    }
}
