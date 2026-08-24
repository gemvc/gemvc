<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\JWTToken;

class JWTTokenTest extends TestCase
{
    private JWTToken $jwtToken;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set required environment variables
        $_ENV['TOKEN_SECRET'] = 'test-secret-key-for-testing-only';
        $_ENV['TOKEN_ISSUER'] = 'TestIssuer';
        $_ENV['ACCESS_TOKEN_VALIDATION_IN_SECONDS'] = '300';
        $_ENV['REFRESH_TOKEN_VALIDATION_IN_SECONDS'] = '3600';
        $_ENV['LOGIN_TOKEN_VALIDATION_IN_SECONDS'] = '604800';
        
        $this->jwtToken = new JWTToken();
    }
    
    public function testCreateAccessToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $this->assertNotEmpty($token);
        $this->assertEquals('access', $this->jwtToken->type);
    }
    
    public function testCreateRefreshToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createRefreshToken($userId);
        
        $this->assertNotEmpty($token);
        $this->assertEquals('refresh', $this->jwtToken->type);
    }
    
    public function testCreateLoginToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createLoginToken($userId);
        
        $this->assertNotEmpty($token);
        $this->assertEquals('login', $this->jwtToken->type);
    }
    
    public function testVerifyValidToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertTrue($verifyToken->isTokenValid);
        $this->assertEquals($userId, $verifyToken->user_id);
    }
    
    public function testVerifyInvalidToken(): void
    {
        $invalidToken = 'invalid.token.here';
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($invalidToken);
        $result = $verifyToken->verify();
        
        $this->assertFalse($result);
        $this->assertFalse($verifyToken->isTokenValid);
    }
    
    public function testVerifyExpiredToken(): void
    {
        $userId = 123;
        
        // Create token with very short expiration (1 second)
        $this->jwtToken->type = 'access';
        $token = $this->jwtToken->create($userId, 1);
        
        // Wait for token to expire
        sleep(2);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertFalse($result);
        $this->assertFalse($verifyToken->isTokenValid);
    }
    
    public function testTokenContainsUserId(): void
    {
        $userId = 456;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $verifyToken->verify();
        
        $this->assertEquals($userId, $verifyToken->user_id);
    }
    
    public function testTokenRenewal(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $verifyResult = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $verifyResult);
        
        // Renew token (extends by 300 seconds from now)
        $newToken = $verifyToken->renew(300);
        
        $this->assertIsString($newToken);
        $this->assertNotEquals($token, $newToken);
        
        // Verify new token
        $newVerifyToken = new JWTToken();
        $newVerifyToken->setToken($newToken);
        $newVerifyResult = $newVerifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $newVerifyResult);
        $this->assertTrue($newVerifyToken->isTokenValid);
        // New token should expire in the future (at least 300 seconds from now)
        $this->assertGreaterThan(time() + 290, $newVerifyToken->exp);
    }
    
    // ============================================
    // Additional Method Tests
    // ============================================
    
    public function testSetToken(): void
    {
        $token = 'test.token.here';
        $this->jwtToken->setToken($token);
        
        // Token is private, so we verify by using verify() which uses it
        $this->assertTrue(method_exists($this->jwtToken, 'setToken'));
    }
    
    public function testGetTypeWithValidToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $newToken = new JWTToken();
        $type = $newToken->GetType($token);
        
        $this->assertEquals('access', $type);
    }
    
    public function testGetTypeWithRefreshToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createRefreshToken($userId);
        
        $newToken = new JWTToken();
        $type = $newToken->GetType($token);
        
        $this->assertEquals('refresh', $type);
    }
    
    public function testGetTypeWithLoginToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createLoginToken($userId);
        
        $newToken = new JWTToken();
        $type = $newToken->GetType($token);
        
        $this->assertEquals('login', $type);
    }
    
    public function testGetTypeWithSetToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $newToken = new JWTToken();
        $newToken->setToken($token);
        $type = $newToken->GetType();
        
        $this->assertEquals('access', $type);
    }
    
    public function testGetTypeWithInvalidToken(): void
    {
        $newToken = new JWTToken();
        $type = $newToken->GetType('invalid.token');
        
        $this->assertNull($type);
    }
    
    public function testGetTypeWithNoToken(): void
    {
        $newToken = new JWTToken();
        $type = $newToken->GetType();
        
        $this->assertNull($type);
        $this->assertNotNull($newToken->error);
    }
    
    public function testIsJWTWithValidFormat(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $this->assertTrue(JWTToken::isJWT($token));
    }
    
    public function testIsJWTWithInvalidFormat(): void
    {
        $this->assertFalse(JWTToken::isJWT('invalid'));
        $this->assertFalse(JWTToken::isJWT('invalid.token'));
        $this->assertFalse(JWTToken::isJWT('too.many.parts.here'));
    }
    
    public function testExtractTokenWithValidBearerToken(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = 'Bearer ' . $token;
        
        $newToken = new JWTToken();
        $result = $newToken->extractToken($request);
        
        $this->assertTrue($result);
        $this->assertNull($newToken->error);
    }
    
    public function testExtractTokenWithNoHeader(): void
    {
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = null;
        
        $newToken = new JWTToken();
        $result = $newToken->extractToken($request);
        
        $this->assertFalse($result);
        $this->assertNotNull($newToken->error);
    }
    
    public function testExtractTokenWithEmptyHeader(): void
    {
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = '';
        
        $newToken = new JWTToken();
        $result = $newToken->extractToken($request);
        
        $this->assertFalse($result);
        $this->assertNotNull($newToken->error);
    }
    
    public function testExtractTokenWithNonStringHeader(): void
    {
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = ['not', 'a', 'string'];
        
        $newToken = new JWTToken();
        $result = $newToken->extractToken($request);
        
        $this->assertFalse($result);
        $this->assertNotNull($newToken->error);
    }
    
    public function testExtractTokenWithInvalidBearerFormat(): void
    {
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = 'InvalidFormat token';
        
        $newToken = new JWTToken();
        $result = $newToken->extractToken($request);
        
        $this->assertFalse($result);
    }
    
    public function testVerifyWithTokenParameter(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $result = $verifyToken->verify($token);
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertTrue($verifyToken->isTokenValid);
        $this->assertEquals($userId, $verifyToken->user_id);
    }
    
    public function testVerifyWithNoTokenSet(): void
    {
        $verifyToken = new JWTToken();
        $result = $verifyToken->verify();
        
        $this->assertFalse($result);
        $this->assertNotNull($verifyToken->error);
    }
    
    public function testVerifyWithMissingTokenSecret(): void
    {
        // Create a valid token first
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        // Temporarily remove TOKEN_SECRET for verification
        $originalSecret = $_ENV['TOKEN_SECRET'] ?? null;
        unset($_ENV['TOKEN_SECRET']);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        // Restore secret
        $_ENV['TOKEN_SECRET'] = $originalSecret ?? 'test-secret-key-for-testing-only';
        
        $this->assertFalse($result);
        $this->assertNotNull($verifyToken->error);
    }
    
    public function testRenewWithTokenParameter(): void
    {
        $userId = 123;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $newToken = new JWTToken();
        $renewedToken = $newToken->renew(600, $token);
        
        $this->assertIsString($renewedToken);
        $this->assertNotEquals($token, $renewedToken);
    }
    
    public function testRenewWithInvalidToken(): void
    {
        $newToken = new JWTToken();
        $result = $newToken->renew(600, 'invalid.token.here');
        
        $this->assertFalse($result);
    }
    
    // ============================================
    // Token with Additional Properties Tests
    // ============================================
    
    public function testTokenWithCompanyId(): void
    {
        $userId = 123;
        $companyId = 456;
        
        $this->jwtToken->company_id = $companyId;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertEquals($companyId, $verifyToken->company_id);
    }
    
    public function testTokenWithEmployeeId(): void
    {
        $userId = 123;
        $employeeId = 789;
        
        $this->jwtToken->employee_id = $employeeId;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertEquals($employeeId, $verifyToken->employee_id);
    }
    
    public function testTokenWithRole(): void
    {
        $userId = 123;
        $role = 'admin';
        
        $this->jwtToken->role = $role;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertEquals($role, $verifyToken->role);
    }
    
    public function testTokenWithRoleId(): void
    {
        $userId = 123;
        $roleId = 5;
        
        $this->jwtToken->role_id = $roleId;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertEquals($roleId, $verifyToken->role_id);
    }
    
    public function testTokenWithBranchId(): void
    {
        $userId = 123;
        $branchId = 10;
        
        $this->jwtToken->branch_id = $branchId;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertEquals($branchId, $verifyToken->branch_id);
    }
    
    public function testTokenWithPayload(): void
    {
        $userId = 123;
        $payload = new \stdClass();
        $payload->custom = 'data';
        
        $this->jwtToken->payload = $payload;
        $token = $this->jwtToken->createAccessToken($userId);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertIsObject($verifyToken->payload);
        $this->assertTrue(property_exists($verifyToken->payload, 'custom'));
        $this->assertEquals('data', $verifyToken->payload->custom);
    }
    
    public function testConstructorWithMissingEnvVars(): void
    {
        $originalIssuer = $_ENV['TOKEN_ISSUER'] ?? null;
        unset($_ENV['TOKEN_ISSUER']);
        
        $token = new JWTToken();
        
        // Should default to 'undefined' when TOKEN_ISSUER is missing
        $this->assertEquals('undefined', $token->iss);
        
        // Restore
        $_ENV['TOKEN_ISSUER'] = $originalIssuer ?? 'TestIssuer';
    }
    
    public function testCreateWithCustomTimeToLive(): void
    {
        $userId = 123;
        $timeToLive = 7200; // 2 hours
        
        $token = $this->jwtToken->create($userId, $timeToLive);
        
        $this->assertNotEmpty($token);
        
        $verifyToken = new JWTToken();
        $verifyToken->setToken($token);
        $result = $verifyToken->verify();
        
        $this->assertInstanceOf(JWTToken::class, $result);
        // Token should expire approximately timeToLive seconds from creation
        $this->assertGreaterThan(time() + $timeToLive - 10, $verifyToken->exp);
        $this->assertLessThan(time() + $timeToLive + 10, $verifyToken->exp);
    }

    public function testFailedVerifyAfterSuccessSetsIsTokenValidFalse(): void
    {
        $token = $this->jwtToken->createAccessToken(123);
        $verifyToken = new JWTToken();
        $this->assertInstanceOf(JWTToken::class, $verifyToken->verify($token));
        $this->assertTrue($verifyToken->isTokenValid);

        $result = $verifyToken->verify('invalid.token.here');
        $this->assertFalse($result);
        $this->assertFalse($verifyToken->isTokenValid);
        $this->assertNotNull($verifyToken->error);
    }

    public function testCreateThrowsWhenTokenSecretMissing(): void
    {
        $original = $_ENV['TOKEN_SECRET'] ?? null;
        unset($_ENV['TOKEN_SECRET']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TOKEN_SECRET');
        try {
            (new JWTToken())->create(1, 60);
        } finally {
            $_ENV['TOKEN_SECRET'] = $original ?? 'test-secret-key-for-testing-only';
        }
    }

    public function testCreateThrowsWhenTokenSecretEmpty(): void
    {
        $original = $_ENV['TOKEN_SECRET'] ?? null;
        $_ENV['TOKEN_SECRET'] = '';

        $this->expectException(\RuntimeException::class);
        try {
            (new JWTToken())->create(1, 60);
        } finally {
            $_ENV['TOKEN_SECRET'] = $original ?? 'test-secret-key-for-testing-only';
        }
    }

    public function testTokenIdIsHexAndIatIsPresent(): void
    {
        $token = $this->jwtToken->createAccessToken(123);
        $verifyToken = new JWTToken();
        $this->assertInstanceOf(JWTToken::class, $verifyToken->verify($token));
        $this->assertNotNull($verifyToken->token_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $verifyToken->token_id);

        $parts = explode('.', $token);
        $b64 = strtr($parts[1], '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($b64, true);
        $this->assertIsString($json);
        $payload = json_decode($json, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertIsInt($payload['iat']);
    }

    public function testVerifyAcceptsLegacyNumericTokenId(): void
    {
        $legacy = \Firebase\JWT\JWT::encode([
            'token_id' => 1730000000.1234,
            'user_id' => 9,
            'iss' => 'TestIssuer',
            'exp' => time() + 300,
            'type' => 'access',
            'payload' => new \stdClass(),
            'role' => null,
        ], $_ENV['TOKEN_SECRET'], 'HS256');

        $verifyToken = new JWTToken();
        $result = $verifyToken->verify($legacy);
        $this->assertInstanceOf(JWTToken::class, $result);
        $this->assertSame('1730000000.1234', $verifyToken->token_id);
        $this->assertSame(9, $verifyToken->user_id);
    }

    public function testVerifyRejectsIssuerMismatchWhenTokenIssuerIsSet(): void
    {
        $token = $this->jwtToken->createAccessToken(123);
        $original = $_ENV['TOKEN_ISSUER'];
        $_ENV['TOKEN_ISSUER'] = 'OtherIssuer';

        $verifyToken = new JWTToken();
        $result = $verifyToken->verify($token);

        $_ENV['TOKEN_ISSUER'] = $original;

        $this->assertFalse($result);
        $this->assertFalse($verifyToken->isTokenValid);
        $this->assertNotNull($verifyToken->error);
        $this->assertStringContainsString('issuer', strtolower((string) $verifyToken->error));
    }

    public function testGetTypeDecodesBase64UrlPayload(): void
    {
        $payloadJson = '{"type":"access","x":"??"}';
        $segment = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');
        $this->assertTrue(str_contains($segment, '-') || str_contains($segment, '_'));
        $jwt = 'eyJhbGciOiJub25lIn0.' . $segment . '.sig';

        $newToken = new JWTToken();
        $this->assertSame('access', $newToken->GetType($jwt));
    }

    public function testExtractTokenWithLowercaseBearer(): void
    {
        $token = $this->jwtToken->createAccessToken(123);
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = 'bearer ' . $token;

        $newToken = new JWTToken();
        $this->assertTrue($newToken->extractToken($request));
        $this->assertNull($newToken->error);
        $this->assertInstanceOf(JWTToken::class, $newToken->verify());
    }

    public function testExtractTokenWithInvalidBearerFormatSetsError(): void
    {
        $request = $this->createMock(\Gemvc\Http\Request::class);
        $request->authorizationHeader = 'InvalidFormat token';

        $newToken = new JWTToken();
        $this->assertFalse($newToken->extractToken($request));
        $this->assertNotNull($newToken->error);
    }

    public function testCreateAsymmetricAccessTokenRs256(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl extension required for RS256 minting');
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);
        $privatePem = '';
        $this->assertTrue(openssl_pkey_export($resource, $privatePem));
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('key', $details);
        $this->assertIsString($details['key']);

        $originalKey = $_ENV['TOKEN_PRIVATE_KEY'] ?? null;
        $originalPath = $_ENV['TOKEN_PRIVATE_KEY_PATH'] ?? null;
        unset($_ENV['TOKEN_PRIVATE_KEY_PATH']);
        $_ENV['TOKEN_PRIVATE_KEY'] = $privatePem;

        $jwt = new JWTToken();
        $token = $jwt->createAsymmetricAccessToken(77);

        if ($originalKey === null) {
            unset($_ENV['TOKEN_PRIVATE_KEY']);
        } else {
            $_ENV['TOKEN_PRIVATE_KEY'] = $originalKey;
        }
        if ($originalPath !== null) {
            $_ENV['TOKEN_PRIVATE_KEY_PATH'] = $originalPath;
        }

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        $headerB64 = strtr($parts[0], '-_', '+/');
        $headerPad = strlen($headerB64) % 4;
        if ($headerPad > 0) {
            $headerB64 .= str_repeat('=', 4 - $headerPad);
        }
        $headerJson = base64_decode($headerB64, true);
        $this->assertIsString($headerJson);
        $header = json_decode($headerJson, true);
        $this->assertIsArray($header);
        $this->assertSame('RS256', $header['alg']);

        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($details['key'], 'RS256'));
        $this->assertSame(77, $decoded->user_id);
        $this->assertSame('access', $decoded->type);
        $this->assertTrue(isset($decoded->iat));
    }

    public function testCreateAsymmetricThrowsWithoutPrivateKey(): void
    {
        $originalKey = $_ENV['TOKEN_PRIVATE_KEY'] ?? null;
        $originalPath = $_ENV['TOKEN_PRIVATE_KEY_PATH'] ?? null;
        unset($_ENV['TOKEN_PRIVATE_KEY'], $_ENV['TOKEN_PRIVATE_KEY_PATH']);

        $this->expectException(\RuntimeException::class);
        try {
            (new JWTToken())->createAsymmetric(1, 60);
        } finally {
            if ($originalKey !== null) {
                $_ENV['TOKEN_PRIVATE_KEY'] = $originalKey;
            }
            if ($originalPath !== null) {
                $_ENV['TOKEN_PRIVATE_KEY_PATH'] = $originalPath;
            }
        }
    }

    public function testCreateAsymmetricFromKeyPath(): void
    {
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl extension required for RS256 minting');
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);
        $privatePem = '';
        $this->assertTrue(openssl_pkey_export($resource, $privatePem));

        $path = tempnam(sys_get_temp_dir(), 'gemvc-jwt-');
        $this->assertIsString($path);
        file_put_contents($path, $privatePem);

        $originalKey = $_ENV['TOKEN_PRIVATE_KEY'] ?? null;
        $originalPath = $_ENV['TOKEN_PRIVATE_KEY_PATH'] ?? null;
        unset($_ENV['TOKEN_PRIVATE_KEY']);
        $_ENV['TOKEN_PRIVATE_KEY_PATH'] = $path;

        try {
            $token = (new JWTToken())->createAsymmetricRefreshToken(3);
            $this->assertNotEmpty($token);
            $this->assertTrue(JWTToken::isJWT($token));
        } finally {
            unlink($path);
            if ($originalKey !== null) {
                $_ENV['TOKEN_PRIVATE_KEY'] = $originalKey;
            } else {
                unset($_ENV['TOKEN_PRIVATE_KEY']);
            }
            if ($originalPath !== null) {
                $_ENV['TOKEN_PRIVATE_KEY_PATH'] = $originalPath;
            } else {
                unset($_ENV['TOKEN_PRIVATE_KEY_PATH']);
            }
        }
    }

    public function testVerifyWithZeroUserIdRejectsSignedToken(): void
    {
        $zero = \Firebase\JWT\JWT::encode([
            'token_id' => 'abc',
            'user_id' => 0,
            'iss' => 'TestIssuer',
            'exp' => time() + 300,
            'type' => 'access',
        ], $_ENV['TOKEN_SECRET'], 'HS256');

        $verifyToken = new JWTToken();
        $this->assertFalse($verifyToken->verify($zero));
        $this->assertFalse($verifyToken->isTokenValid);
    }
}

