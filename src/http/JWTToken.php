<?php
namespace Gemvc\Http;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
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
     * @var array<mixed> $payload
     */
    public array     $payload;
    public ?string   $token_id;
    public ?string   $iss;
    public ?string   $role;
    public ?int      $company_id;
    public ?int      $employee_id;
    public ?string   $error;
    private ?string  $_token;  

    public function __construct()
    {
        $this->_token = null;
        $this->error = null;
        $this->iss = $_ENV['TOKEN_ISSUER'];
        $this->type = 'not defined';
        $this->user_id = 0;
        $this->employee_id = null;
        $this->company_id = null;
        $this->role = null;
        $this->exp = 0;
        $this->isTokenValid = false;
        $this->payload = [];
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
        return $this->create($user_id, $_ENV['ACCESS_TOKEN_VALIDATION_IN_SECONDS']);
    }

    public function createRefreshToken(int $user_id):string
    {
        $this->type = 'refresh';
        return $this->create($user_id, $_ENV['REFRESH_TOKEN_VALIDATION_IN_SECONDS']);
    }

    public function createLoginToken(int $user_id):string
    {
        $this->type = 'login';
        return $this->create($user_id, $_ENV['LOGIN_TOKEN_VALIDATION_IN_SECONDS']);
    }

    /**
     * @param  int $userId
     * @param  int $timeToLiveSecond
     * @return string
     */
    public function create(int $userId, int $timeToLiveSecond): string
    {
        $payloadArray = [
            'token_id' => microtime(true),
            'user_id' => $userId,
            'company_id' => $this->company_id,
            'employee_id'=>$this->employee_id,
            'iss' => $this->iss,
            'exp' => (time() + $timeToLiveSecond),
            'type' => $this->type,
            'payload' => $this->payload,
            'role' => $this->role
        ];
        if(isset($this->company_id)) {
            $payloadArray['company_id'] = $this->company_id;
        }
        if(isset($this->employee_id)) {
            $payloadArray['employee_id'] = $this->employee_id;
        }
        return JWT::encode($payloadArray,$_ENV['TOKEN_SECRET'], 'HS256');
    }

    /**
     * @return false|JWTToken
     * @description pure token without Bearer you can use WebHelper::BearerTokenPurify() got get pure token
     */
    public function verify(string $token=null): false|JWTToken
    {
        if($token)
        {
            $this->_token = $token;
        }
        if(!$this->_token) {
            $this->error = "no token string is set in JWTToken to verify";
            return false;
        }
        try {
            $decodedToken = JWT::decode($this->_token, new Key($_ENV['TOKEN_SECRET'], 'HS256'));
            if (isset($decodedToken->user_id) && $decodedToken->exp > time() && $decodedToken->user_id>0) {
                $this->token_id = $decodedToken->token_id;
                $this->user_id = (int)$decodedToken->user_id;
                $this->exp = $decodedToken->exp;
                $this->iss = $decodedToken->iss;
                $this->payload = $decodedToken->payload;
                $this->isTokenValid = true;
                $this->type = $decodedToken->type;
                $this->role = $decodedToken->role;
                if(isset($decodedToken->company_id)) {
                    $this->company_id = $decodedToken->company_id;
                }
                if(isset($decodedToken->employee_id)) {
                    $this->employee_id = $decodedToken->employee_id;
                }
                $this->error = null;
                return $this;
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }

        return false;
    }

    /**
     * @param int $extensionTime_sec
     * @param string|null $token
     * @return false|string
     */
    public function renew(int $extensionTime_sec , string $token= null): false|string
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
     * @return  string|null
     * @description Returns type without validation token
     */
    public function GetType(string $token = null):string|null
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
        $payloadBase64 = $tokenParts[1];
        $payload = json_decode(base64_decode($payloadBase64), true);
        /**@phpstan-ignore-next-line */
        if (isset($payload['type'])) {
            /**@phpstan-ignore-next-line */
            return $payload['type'];
        } 
        else { return null;
        }
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
        if(!$result) {
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
        if (preg_match('/Bearer\s(\S+)/', $tokenStringInHttpHeader, $matches)) {
            $tokenStringInHttpHeader = $matches[1];
            return $tokenStringInHttpHeader;
        }
        return null;
    }
}
