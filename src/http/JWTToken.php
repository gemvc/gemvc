<?php
namespace Gemvc\Http;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * HS256 create/verify (TOKEN_SECRET) plus additive RS256 minting (TOKEN_PRIVATE_KEY).
 *
 * Request::auth() verifies HS256 only. RS256 public-key verify is a later step.
 *
 * @public   function setToken(string $token):void
 * @function create(int $userId, int $timeToLiveSecond): string
 * @function verify(): bool
 * @function renew(int $extensionTime_sec): false|string
 * @function GetType():string|null
 */
class JWTToken
{
    public int       $exp;
    public bool      $isTokenValid;
    public int       $user_id;
    public string    $type;//access or refresh
    /**
     * @var \stdClass $payload
     */
    public \stdClass     $payload;
    public ?string   $token_id;
    public ?string   $iss;
    public ?string   $role;
    public ?int      $role_id;
    public ?int      $company_id;
    public ?int      $employee_id;
    public ?string   $error;
    public ?int      $branch_id;
    private ?string  $_token;


    public function __construct()
    {
        $this->_token = null;
        $this->error = null;
        $this->iss = is_string($_ENV['TOKEN_ISSUER'] ?? null) ? $_ENV['TOKEN_ISSUER'] : 'undefined';
        $this->type = 'not defined';
        $this->user_id = 0;
        $this->employee_id = null;
        $this->company_id = null;
        $this->role = null;
        $this->role_id = null;
        $this->branch_id = null;
        $this->exp = 0;
        $this->isTokenValid = false;
        $this->payload = new \stdClass();
    }

    /**
     * @param  string $token
     * @return void
     */
    public function setToken(string $token):void
    {
        $this->_token = $token;
    }

    public function createAccessToken(int $user_id):string
    {
        $this->type = 'access';
        return $this->create($user_id, $this->envTtlSeconds('ACCESS_TOKEN_VALIDATION_IN_SECONDS', 300));
    }

    public function createRefreshToken(int $user_id):string
    {
        $this->type = 'refresh';
        return $this->create($user_id, $this->envTtlSeconds('REFRESH_TOKEN_VALIDATION_IN_SECONDS', 3600));
    }

    public function createLoginToken(int $user_id):string
    {
        $this->type = 'login';
        return $this->create($user_id, $this->envTtlSeconds('LOGIN_TOKEN_VALIDATION_IN_SECONDS', 604800));
    }

    /**
     * Mint an RS256 access token. Does not change HS256 createAccessToken().
     * Request::auth() cannot verify this token until public-key verify ships.
     */
    public function createAsymmetricAccessToken(int $user_id): string
    {
        $this->type = 'access';
        return $this->createAsymmetric($user_id, $this->envTtlSeconds('ACCESS_TOKEN_VALIDATION_IN_SECONDS', 300));
    }

    public function createAsymmetricRefreshToken(int $user_id): string
    {
        $this->type = 'refresh';
        return $this->createAsymmetric($user_id, $this->envTtlSeconds('REFRESH_TOKEN_VALIDATION_IN_SECONDS', 3600));
    }

    public function createAsymmetricLoginToken(int $user_id): string
    {
        $this->type = 'login';
        return $this->createAsymmetric($user_id, $this->envTtlSeconds('LOGIN_TOKEN_VALIDATION_IN_SECONDS', 604800));
    }

    /**
     * @param  int $userId
     * @param  int $timeToLiveSecond
     * @return string
     */
    public function create(int $userId, int $timeToLiveSecond): string
    {
        $secret = $this->hmacSecret();
        return JWT::encode($this->buildPayloadArray($userId, $timeToLiveSecond), $secret, 'HS256');
    }

    /**
     * Mint an RS256 JWT with the same claims as create(). Fail closed: no HS256 fallback.
     *
     * @throws \RuntimeException when TOKEN_PRIVATE_KEY / TOKEN_PRIVATE_KEY_PATH is missing or unreadable
     */
    public function createAsymmetric(int $userId, int $timeToLiveSecond): string
    {
        $privateKey = $this->resolvePrivateKeyPem();
        return JWT::encode($this->buildPayloadArray($userId, $timeToLiveSecond), $privateKey, 'RS256');
    }

    /**
     * @return false|JWTToken
     * @description pure token without Bearer you can use WebHelper::BearerTokenPurify() got get pure token
     */
    public function verify(?string $token=null): false|JWTToken
    {
        if($token)
        {
            $this->_token = $token;
        }
        if(!$this->_token) {
            $this->isTokenValid = false;
            $this->error = "no token string is set in JWTToken to verify";
            return false;
        }
        try {
            $secret = $this->hmacSecret();
            $key = new Key($secret, 'HS256');
            $decodedToken = JWT::decode($this->_token, $key);
            if (!$this->claimsAreAcceptable($decodedToken)) {
                $this->isTokenValid = false;
                if ($this->error === null) {
                    $this->error = 'JWT token claims are invalid';
                }
                return false;
            }
            $this->hydrateFromDecoded($decodedToken);
            $this->isTokenValid = true;
            $this->error = null;
            return $this;
        } catch (\Throwable $e) {
            $this->isTokenValid = false;
            $this->error = $e->getMessage();
        }

        return false;
    }

    /**
     * @param int $extensionTime_sec
     * @param string|null $token
     * @return false|string
     */
    public function renew(int $extensionTime_sec , ?string $token= null): false|string
    {
        if($token) {
            $this->_token = $token;
        }
        if ($this->verify()) {
            return $this->create($this->user_id, $extensionTime_sec);
        }
        return false;
    }

    /**
     * Unsigned peek at the `type` claim. Not for authorization — prefer `$this->type` after verify().
     *
     * @return  string|null
     */
    public function GetType(?string $token = null):string|null
    {
        if($token) {
            $this->_token = $token;
        }

        if(!$this->_token) {
            $this->error = 'please set token first directly in function GetType or with setToken(string $token)';
            return null;
        }
        if(!$this->isJWT($this->_token)) {
            return null;
        }
        $tokenParts = explode('.', $this->_token);
        $payload = $this->decodeJwtJsonSegment($tokenParts[1]);
        if (is_array($payload) && isset($payload['type']) && is_string($payload['type'])) {
            return $payload['type'];
        }
        return null;
    }

    /**
     * @param  string $string
     * @return bool
     */
    public static function isJWT(string $string):bool
    {
        $tokenParts = explode('.', $string);
        if (count($tokenParts) !== 3) {
            return false;
        }
        return true;
    }

    public function extractToken(Request $request):bool
    {
        if (!isset($request->authorizationHeader) || empty($request->authorizationHeader)) {
            $this->error = 'there is no token request header';
            return false;
        }
        if (!is_string($request->authorizationHeader)) {
            $this->error = 'not well formatted token';
            return false;
        }
        $result = $this->bearerTokenPurify($request->authorizationHeader);
        if($result === null) {
            $this->error = 'Authorization header is not a Bearer token';
            return false;
        }
        $this->_token = $result;
        return true;
    }

    /**
     * @param       string $tokenStringInHttpHeader
     * @return      string|null
     * @description BearerToken in header is like Bearer ey... this function remove Bearer and space return pure token to be used in JWT
     */
    private function bearerTokenPurify(string $tokenStringInHttpHeader): null|string
    {
        if (preg_match('/Bearer\s+(\S+)/i', $tokenStringInHttpHeader, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadArray(int $userId, int $timeToLiveSecond): array
    {
        $now = time();
        return [
            'token_id' => bin2hex(random_bytes(16)),
            'user_id' => $userId,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'iss' => $this->iss,
            'iat' => $now,
            'exp' => ($now + $timeToLiveSecond),
            'type' => $this->type,
            'payload' => $this->payload,
            'role' => $this->role,
            'role_id' => $this->role_id,
            'branch_id' => $this->branch_id,
        ];
    }

    private function envTtlSeconds(string $envKey, int $default): int
    {
        $raw = $_ENV[$envKey] ?? null;
        return is_numeric($raw) ? (int) $raw : $default;
    }

    private function hmacSecret(): string
    {
        $secret = $_ENV['TOKEN_SECRET'] ?? null;
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('TOKEN_SECRET is not defined or is empty. Define a non-empty string secret for HS256 tokens.');
        }
        return $secret;
    }

    /**
     * TOKEN_ISSUER used as a verify constraint. Null means skip iss check (env missing / "undefined").
     */
    private function configuredIssuerForVerify(): ?string
    {
        $iss = $_ENV['TOKEN_ISSUER'] ?? null;
        if (!is_string($iss) || $iss === '' || $iss === 'undefined') {
            return null;
        }
        return $iss;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedClaims(object $decodedToken): array
    {
        return get_object_vars($decodedToken);
    }

    private function claimsAreAcceptable(object $decodedToken): bool
    {
        $claims = $this->decodedClaims($decodedToken);
        if (!isset($claims['user_id']) || !is_numeric($claims['user_id']) || (int) $claims['user_id'] <= 0) {
            $this->error = 'JWT token claims are invalid';
            return false;
        }
        if (!isset($claims['exp']) || !is_numeric($claims['exp']) || (int) $claims['exp'] <= time()) {
            $this->error = 'JWT token claims are invalid';
            return false;
        }
        $expectedIss = $this->configuredIssuerForVerify();
        if ($expectedIss !== null) {
            $tokenIss = isset($claims['iss']) && is_string($claims['iss']) ? $claims['iss'] : null;
            if ($tokenIss !== $expectedIss) {
                $this->error = 'JWT issuer does not match TOKEN_ISSUER';
                return false;
            }
        }
        return true;
    }

    private function hydrateFromDecoded(object $decodedToken): void
    {
        $claims = $this->decodedClaims($decodedToken);
        $tokenId = $claims['token_id'] ?? null;
        $this->token_id = (is_string($tokenId) || is_numeric($tokenId)) ? (string) $tokenId : null;
        $this->user_id = isset($claims['user_id']) && is_numeric($claims['user_id']) ? (int) $claims['user_id'] : 0;
        $this->exp = isset($claims['exp']) && is_numeric($claims['exp']) ? (int) $claims['exp'] : 0;
        $this->iss = isset($claims['iss']) && is_string($claims['iss']) ? $claims['iss'] : $this->iss;
        $payloadClaim = $claims['payload'] ?? null;
        if ($payloadClaim instanceof \stdClass) {
            $this->payload = $payloadClaim;
        } else {
            $this->payload = new \stdClass();
        }
        $this->type = isset($claims['type']) && is_string($claims['type']) ? $claims['type'] : 'not defined';
        $this->role = isset($claims['role']) && is_string($claims['role']) ? $claims['role'] : null;
        $this->company_id = $this->optionalIntClaim($claims, 'company_id');
        $this->employee_id = $this->optionalIntClaim($claims, 'employee_id');
        $this->role_id = $this->optionalIntClaim($claims, 'role_id');
        $this->branch_id = $this->optionalIntClaim($claims, 'branch_id');
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function optionalIntClaim(array $claims, string $claim): ?int
    {
        if (!isset($claims[$claim]) || !is_numeric($claims[$claim])) {
            return null;
        }
        return (int) $claims[$claim];
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeJwtJsonSegment(string $segment): ?array
    {
        $b64 = strtr($segment, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($b64, true);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function resolvePrivateKeyPem(): string
    {
        $inline = $_ENV['TOKEN_PRIVATE_KEY'] ?? null;
        if (is_string($inline) && trim($inline) !== '') {
            return str_replace(['\\n', "\r\n", "\r"], ["\n", "\n", "\n"], trim($inline));
        }
        $path = $_ENV['TOKEN_PRIVATE_KEY_PATH'] ?? null;
        if (!is_string($path) || $path === '') {
            throw new \RuntimeException('TOKEN_PRIVATE_KEY or TOKEN_PRIVATE_KEY_PATH must be set to create RS256 tokens.');
        }
        if (!is_readable($path)) {
            throw new \RuntimeException('TOKEN_PRIVATE_KEY_PATH is not readable.');
        }
        $pem = file_get_contents($path);
        if (!is_string($pem) || trim($pem) === '') {
            throw new \RuntimeException('TOKEN_PRIVATE_KEY_PATH did not contain a PEM key.');
        }
        return $pem;
    }
}
