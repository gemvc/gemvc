<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Documentation;

use Gemvc\Core\ApiService;
use Gemvc\Core\Documentation\ResponseExampleLoader;
use Gemvc\Core\Documentation\ResponseExampleResolver;
use Gemvc\Database\Table;
use Gemvc\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class DocUser extends ApiService
{
    public function read(): JsonResponse
    {
        return \Gemvc\Http\Response::success([]);
    }
}

final class DocUserTable extends Table
{
    public int $id = 1;
    public string $email = 'a@example.com';
    public ?string $role = null;
    protected string $password = 'secret';

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'email' => 'string',
        'role' => '?string',
        'password' => 'string',
    ];

    public function getTable(): string
    {
        return 'doc_users';
    }

    public function defineSchema(): array
    {
        return [];
    }
}

final class ExplicitEmptyMockService extends ApiService
{
    /**
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return [];
    }
}

final class PartialParentMockService extends ApiService
{
    /**
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        if ($method === 'special') {
            return [
                'response_code' => 200,
                'message' => 'OK',
                'data' => ['ok' => true],
            ];
        }

        return parent::mockResponse($method);
    }
}

final class PhpWinsMockService extends ApiService
{
    /**
     * @return array<string, mixed>
     */
    public static function mockResponse(string $method): array
    {
        return match ($method) {
            'read' => [
                'response_code' => 200,
                'message' => 'OK',
                'data' => ['from' => 'php'],
            ],
            default => [],
        };
    }
}

final class ResponseExampleLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gemvc_re_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testValidJsonLoads(): void
    {
        $this->write('DocUser.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 1, 'email' => 'developer@example.com'],
        ]);

        $result = ResponseExampleLoader::load(DocUser::class, 'read', $this->dir);
        $this->assertTrue($result->isOk());
        $this->assertIsArray($result->data);
        $this->assertSame(200, $result->data['response_code']);
    }

    public function testMissingFile(): void
    {
        $result = ResponseExampleLoader::load(DocUser::class, 'read', $this->dir);
        $this->assertFalse($result->fileFound);
        $this->assertNull($result->data);
    }

    public function testBrokenJsonIsInvalid(): void
    {
        file_put_contents($this->dir . '/DocUser.read.json', '{not json');
        $result = ResponseExampleLoader::load(DocUser::class, 'read', $this->dir);
        $this->assertTrue($result->fileFound);
        $this->assertNull($result->data);
    }

    public function testWrongEnvelopeIsInvalid(): void
    {
        $this->write('DocUser.read.json', ['foo' => 1]);
        $result = ResponseExampleLoader::load(DocUser::class, 'read', $this->dir);
        $this->assertTrue($result->fileFound);
        $this->assertNull($result->data);
    }

    public function testPathTraversalMethodRejected(): void
    {
        $result = ResponseExampleLoader::load(DocUser::class, '../secret', $this->dir);
        $this->assertTrue($result->fileFound);
        $this->assertNull($result->data);
    }

    public function testSymlinkEscapeIsInvalid(): void
    {
        $outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gemvc_out_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($outside, json_encode([
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['leaked' => true],
        ], JSON_THROW_ON_ERROR));
        $link = $this->dir . DIRECTORY_SEPARATOR . 'DocUser.read.json';
        if (!@symlink($outside, $link)) {
            @unlink($outside);
            $this->markTestSkipped('symlinks not available');
        }

        $result = ResponseExampleLoader::load(DocUser::class, 'read', $this->dir);
        $this->assertTrue($result->fileFound);
        $this->assertNull($result->data);
        @unlink($outside);
    }

    public function testPhpOverrideBeatsFixture(): void
    {
        $this->write('PhpWinsMockService.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['from' => 'json'],
        ]);

        $resolved = PhpWinsMockService::mockResponse('read');
        $this->assertSame('php', $resolved['data']['from'] ?? null);
    }

    public function testFixtureUsedWhenNoPhpOverride(): void
    {
        $this->write('DocUser.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 9, 'email' => 'fixture@example.com'],
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame(9, $resolved['data']['id'] ?? null);
    }

    public function testParentMockResponseLoadsFixture(): void
    {
        $this->write('PartialParentMockService.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 3, 'email' => 'from-parent@example.com'],
        ]);

        $special = PartialParentMockService::mockResponse('special');
        $this->assertTrue($special['data']['ok'] ?? false);

        $read = ResponseExampleResolver::resolve(PartialParentMockService::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame(3, $read['data']['id'] ?? null);
    }

    public function testExplicitEmptyArrayDoesNotLoadFixture(): void
    {
        $this->write('ExplicitEmptyMockService.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 1, 'email' => 'x@y.z'],
        ]);

        $this->assertSame([], ExplicitEmptyMockService::mockResponse('read'));
    }

    public function testPasswordInCrudFixtureRejected(): void
    {
        $this->write('DocUser.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 1, 'email' => 'a@b.c', 'password' => 'nope'],
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testLoginFixtureAllowsTokens(): void
    {
        $this->write('DocUser.login.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => [
                'access_token' => '<access_token>',
                'refresh_token' => '<refresh_token>',
            ],
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'login', $this->dir, DocUserTable::class);
        $this->assertSame('<access_token>', $resolved['data']['access_token'] ?? null);
    }

    public function testWrongIdTypeRejectedForCrud(): void
    {
        $this->write('DocUser.read.json', [
            'response_code' => 200,
            'message' => 'OK',
            'data' => ['id' => 'wrong', 'email' => 'a@b.c'],
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testInferenceForRead(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame(200, $resolved['response_code'] ?? null);
        $this->assertIsArray($resolved['data'] ?? null);
        $this->assertArrayHasKey('id', $resolved['data']);
        $this->assertArrayHasKey('email', $resolved['data']);
        $this->assertArrayNotHasKey('password', $resolved['data']);
    }

    public function testInferenceSkippedForLogin(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'login', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testBrokenJsonDoesNotInfer(): void
    {
        file_put_contents($this->dir . '/DocUser.read.json', '{broken');
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testListInferenceIsArrayOfRows(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'list', $this->dir, DocUserTable::class);
        $this->assertSame(200, $resolved['response_code'] ?? null);
        $this->assertIsArray($resolved['data'] ?? null);
        $this->assertTrue(array_is_list($resolved['data']));
        $this->assertArrayNotHasKey('password', $resolved['data'][0]);
    }

    public function testMissingReadFixtureAllowsInference(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame(200, $resolved['response_code'] ?? null);
        $this->assertIsArray($resolved['data'] ?? null);
        $this->assertArrayNotHasKey('password', $resolved['data']);
    }

    public function testInvalidEnvelopeDoesNotInfer(): void
    {
        $this->write('DocUser.read.json', ['foo' => 1]);
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'read', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testUnsafeIdentifierDoesNotInfer(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, '../secret', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testCreateInference(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'create', $this->dir, DocUserTable::class);
        $this->assertSame(201, $resolved['response_code'] ?? null);
        $this->assertIsArray($resolved['data'] ?? null);
        $this->assertArrayHasKey('id', $resolved['data']);
        $this->assertArrayNotHasKey('password', $resolved['data']);
        $this->assertFalse(array_is_list($resolved['data']));
    }

    public function testUpdateInference(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'update', $this->dir, DocUserTable::class);
        $this->assertSame(209, $resolved['response_code'] ?? null);
        $this->assertIsArray($resolved['data'] ?? null);
        $this->assertArrayHasKey('email', $resolved['data']);
        $this->assertArrayNotHasKey('password', $resolved['data']);
    }

    public function testDeleteIsNotInferred(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'delete', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testCustomMethodIsNotInferred(): void
    {
        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'report', $this->dir, DocUserTable::class);
        $this->assertSame([], $resolved);
    }

    public function testDeleteScalarFixtureIsAccepted(): void
    {
        $this->write('DocUser.delete.json', [
            'response_code' => 210,
            'message' => 'deleted',
            'count' => 1,
            'service_message' => 'User deleted',
            'data' => 42,
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'delete', $this->dir, DocUserTable::class);
        $this->assertSame(210, $resolved['response_code'] ?? null);
        $this->assertSame(42, $resolved['data'] ?? null);
    }

    public function testDeleteUuidFixtureIsAccepted(): void
    {
        $this->write('DocUser.delete.json', [
            'response_code' => 210,
            'message' => 'deleted',
            'data' => 'user-uuid',
        ]);

        $resolved = ResponseExampleResolver::resolve(DocUser::class, 'delete', $this->dir, DocUserTable::class);
        $this->assertSame('user-uuid', $resolved['data'] ?? null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function write(string $name, array $payload): void
    {
        file_put_contents(
            $this->dir . DIRECTORY_SEPARATOR . $name,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
