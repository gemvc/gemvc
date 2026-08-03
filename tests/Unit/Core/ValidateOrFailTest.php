<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Gemvc\Core\ApiService;
use Gemvc\Core\SwooleApiService;
use Gemvc\Core\ValidationException;
use Gemvc\Http\StandardHttpRequest;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Request;
use PHPUnit\Framework\TestCase;

class ValidateOrFailApiService extends ApiService
{
    /** @param array<string> $schema */
    public function publicValidateOrFail(array $schema): void
    {
        $this->validateOrFail($schema);
    }

    /** @param array<string> $schema */
    public function publicValidateStringOrFail(array $schema): void
    {
        $this->validateStringOrFail($schema);
    }
}

class ValidateOrFailSwooleApiService extends SwooleApiService
{
    /** @param array<string> $schema */
    public function publicValidateOrFail(array $schema): void
    {
        $this->validateOrFail($schema);
    }

    /** @param array<string> $schema */
    public function publicValidateStringOrFail(array $schema): void
    {
        $this->validateStringOrFail($schema);
    }

    /** @param array<string> $schema */
    public function publicValidatePosts(array $schema): void
    {
        $this->validatePosts($schema);
    }

    /** @param array<string> $schema */
    public function publicSafeValidatePosts(array $schema): ?JsonResponse
    {
        return $this->safeValidatePosts($schema);
    }
}

/**
 * @outputBuffering enabled
 */
class ValidateOrFailTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
        $_POST = [];
        $_GET = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/Test',
        ];
        $ar = new StandardHttpRequest();
        $this->request = $ar->request;
    }

    public function testApiServiceValidateOrFailPasses(): void
    {
        $_POST['email'] = 'a@b.com';
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailApiService($ar->request);
        $service->publicValidateOrFail(['email' => 'email']);
        $this->assertTrue(true);
    }

    public function testApiServiceValidateOrFailThrows(): void
    {
        $_POST = [];
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailApiService($ar->request);
        $this->expectException(ValidationException::class);
        $service->publicValidateOrFail(['email' => 'email']);
    }

    public function testSwooleApiServiceValidateOrFailThrows(): void
    {
        $_POST = [];
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailSwooleApiService($ar->request);
        $this->expectException(ValidationException::class);
        $service->publicValidateOrFail(['email' => 'email']);
    }

    public function testSwooleApiServiceValidateOrFailPasses(): void
    {
        $_POST['email'] = 'a@b.com';
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailSwooleApiService($ar->request);
        $service->publicValidateOrFail(['email' => 'email']);
        $this->assertTrue(true);
    }

    public function testSwooleValidatePostsNowThrowsLikeApiService(): void
    {
        $_POST = [];
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailSwooleApiService($ar->request);
        $this->expectException(ValidationException::class);
        $service->publicValidatePosts(['email' => 'email']);
    }

    public function testSwooleSafeValidatePostsStillReturnsJsonResponse(): void
    {
        $_POST = [];
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailSwooleApiService($ar->request);
        $result = $service->publicSafeValidatePosts(['email' => 'email']);
        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(400, $result->response_code);
    }

    public function testApiServiceValidateStringOrFailThrows(): void
    {
        $_POST['name'] = 'a';
        $ar = new StandardHttpRequest();
        $service = new ValidateOrFailApiService($ar->request);
        $this->expectException(ValidationException::class);
        $service->publicValidateStringOrFail(['name' => '2|100']);
    }
}
