<?php

namespace Gemvc\Http;

use Gemvc\Helper\TypeChecker;
use Gemvc\Helper\TypeHelper;
use Gemvc\Http\JWTToken;
use Gemvc\Http\Response;
use Gemvc\Http\JsonResponse;
/**
 * Class Request provides a structured way for managing and validating incoming HTTP request data,
 * handling errors, and forward request to external APIs
 */
class Request
{
    public ?string $jwtTokenStringInHeader;
    public string $requestedUrl;
    public ?string $queryString;
    public ?string $error;

    public bool $isAuthenticated;
    public bool $isAuthorized;
    private ?JWTToken $token;
    /**
     * @var null|string|array<string>
     */
    public null|string|array $authorizationHeader;
    public ?string $remoteAddress;
    /**
     * @var array<mixed>
     */
    public null|array $files;
    /**
     * @var array<mixed>
     */
    public array $post;

    /**
     * @var array<mixed>
     */
    public null|array $put;

    /**
     * @var array<mixed>
     */
    public null|array $patch;

    /**
     * @var string|array<mixed>
     */
    public string|array $get;
    public string $userMachine;
    public ?string $requestMethod;
    /**
     * HTTP headers normalized to lowercase keys (PSR-7 compatible)
     * Populated by ApacheRequest or SwooleRequest adapters
     * 
     * @var array<string, string>|null
     */
    public ?array $headers = null;
    
    /**
     * APM instance (set by ApiService/ApmFactory for sharing with Controller and other layers)
     * 
     * @var \Gemvc\Core\Apm\ApmInterface|null
     */
    public ?\Gemvc\Core\Apm\ApmInterface $apm = null;
    
    /**
     * APM instance (backward compatibility - same as $apm)
     * 
     * @deprecated Use $apm instead. This property is kept for backward compatibility.
     * @var object|null
     */
    public ?object $tracekit = null;
    
    /**
     * HTTP response code (set by OpenSwooleServer for APM tracing)
     * Used to pass response code to APM flush() since http_response_code() doesn't work in Swoole after headers are sent
     * 
     * @var int|null
     */
    public ?int $_http_response_code = null;
    
    private string $id;
    private string $time;
    private float $start_exec;
    
    /**
     * Service name determined by Bootstrap during routing (e.g., 'User', 'Index')
     * 
     * @var string|null
     */
    private ?string $serviceName = null;
    
    /**
     * Method name determined by Bootstrap during routing (e.g., 'create', 'read', 'index')
     * 
     * @var string|null
     */
    private ?string $methodName = null;

    public mixed $cookies;

    /**
     * Summary of _arr_filterBy
     * @var array<mixed> $_arr_filterBy
     */
    private array $_arr_filterBy = [];

    /**
     * Define which fields are allowed for exact matching
     * @var array<string>
     */
    private array $_arr_find_like = [];

    private ?string $_sort_by;
    private ?string $_sort_by_asc;
    private int $_pageNumber;
    private int $_per_page;

    public ?JsonResponse $response;



    public function __construct()
    {
        $this->token = null;
        $this->response = null;
        $this->files = null;
        $this->cookies = null;
        $this->error = null;
        $this->_sort_by = null;
        $this->_sort_by_asc = null;
        $this->authorizationHeader = null;
        $this->jwtTokenStringInHeader = null;
        $this->requestMethod = null;
        $this->start_exec = microtime(true);
        $this->id = TypeHelper::guid();
        $this->time = TypeHelper::timeStamp();
        $this->isAuthenticated = false;
        $this->isAuthorized = false;
        $this->_pageNumber = 1;
        /**@phpstan-ignore-next-line */
        $this->_per_page = $_ENV["QUERY_LIMIT"] ?? 10;
        $this->post = [];
        $this->put = [];
        $this->patch = [];
        $this->files = [];

    }

    public function returnResponse(): JsonResponse
    {
        if(!$this->response)
        {
            return Response::unknownError(null, "No response property set in Request Object");
        }
        return $this->response;
    }

    /**
     * if is empty $authRules then it will check if the user is authenticated
     * if $authRules is not empty then it will check if the user is authenticated and authorized
     * @param array<string>|null $authRules
     * @return bool
     */
    public function auth(?array $authRules=null): bool
    {
        if(!$this->authenticate())
        {
            return false;
        }
        if ($authRules && $this->token) {
            $this->authorize($this->token,$authRules);
            if(!$this->isAuthorized)
            {
                return false;
            }
        }
        return true;
    }

    /**
     * @return null|string|false
     * in case of Authenticated user with valid JWT Token return string role, otherwise return false and set $this->error and $this->response
     */
    public function userRole(): null|string|false
    {
        // Check if token exists
        if (!$this->token) {
            return $this->setErrorResponse(['JWT token not found. User is not authenticated.'], 401);
        }
        
        // Verify token validity
        if (!$this->token->isTokenValid) {
            return $this->setErrorResponse(['Invalid JWT token. Authentication failed.'], 401);
        }
        
        // Check if role is actually set
        if (empty($this->token->role)) {
            return $this->setErrorResponse(['User role not found in token.'], 403);
        }
        
        return $this->token->role;
    }

    private function authenticate(): bool
    {
        $JWT = new JWTToken();
        if($JWT->extractToken($this))
        {
            if (!$JWT->verify()) {
                $this->error = $JWT->error;	
                $this->response = Response::forbidden($this->error);
                return false;
            }
            // Token extracted and verified successfully
            $this->token = $JWT;
            $this->isAuthenticated = true;
            return true;
        } else {
            // Token extraction failed - no token found or invalid format
            $this->error = $JWT->error ?? 'Authentication token not found or invalid';
            $this->response = Response::unauthorized($this->error);
            return false;
        }
    }


    /**
     * Authorize the user against a list of roles
     *
     * @param array<string> $roles Allowed roles
     * @return bool Whether the user is authorized
     */
    private function authorize(JWTToken $token,array $roles): bool
    {
        if ($token->role && strlen($token->role) > 1) {
            $user_roles = explode(',', $token->role);
            foreach ($roles as $role) {
                if (in_array($role, $user_roles)) {
                    $this->isAuthorized = true;
                    return true;
                }
            }
        }
        $this->isAuthorized = false;
        $roleText = $token->role;
        $this->error = "Role $roleText not allowed to perform this action";
        $this->response = Response::unauthorized($this->error);
        return false;
    }

    /**
     * you can use string,int,float,bool,array,json,email,date,integer,number,boolean,url,datetime,ip,ipv4,ipv6
     * @param array<string> $searchableGetValues
     * @example $this->request->filterable(['email'=>'email','name' => 'string'])
     * @return bool with response
     */
    public function filterable(array $searchableGetValues): bool
    {
        if (isset($this->get["filter_by"])) {
            $getFilterBy = $this->get["filter_by"];
            if (is_string($getFilterBy) && strlen($getFilterBy) > 0) {
                $split_where = explode(",", $getFilterBy); {
                    foreach ($split_where as $item_string) {
                        $inhalt = explode("=", $item_string);
                        if (count($inhalt) == 2) {
                            if (array_key_exists($inhalt[0], $searchableGetValues)) {
                                if (TypeChecker::check($searchableGetValues[$inhalt[0]], $inhalt[1])) { {
                                        $this->_arr_filterBy[$inhalt[0]] = $inhalt[1];
                                    }
                                } else {
                                    $this->error .= "invalid search value type for" . $inhalt[0] . " , accepted type is: " . $searchableGetValues[$inhalt[0]];
                                    $this->response = Response::badRequest($this->error);
                                    return false;

                                }
                            }
                        } else {
                            $this->error .= "invalid search value type for" . $inhalt[0] . " , accepted type is: " . $searchableGetValues[$inhalt[0]];
                            $this->response = Response::badRequest($this->error);
                            return false;
                        }
                    }
                }
            }
            if ($this->error) {
                $this->response = Response::badRequest($this->error);
                return false;
            }
        }
        return true;
    }

    /**
     * you can use string,int,float,bool,array,json,email,date,integer,number,boolean,url,datetime,ip,ipv4,ipv6
     * @param array<string> $filterableGetValues
     * @example $this->request->filterable(['email'=>'email','name' => 'string'])
     * @return bool with response
     */
    public function findable(array $filterableGetValues): bool
    {
        if (isset($this->get["find_like"])) {
            $getFindLike = $this->get["find_like"];
            if (is_string($getFindLike) && strlen($getFindLike) > 0) {
                $split_where = explode(",", $getFindLike); {
                    foreach ($split_where as $item_string) {
                        $inhalt = explode("=", $item_string);
                        if (count($inhalt) == 2) {
                            if (array_key_exists($inhalt[0], $filterableGetValues)) {
                                if (TypeChecker::check($filterableGetValues[$inhalt[0]], $inhalt[1])) { {
                                        $this->_arr_find_like[$inhalt[0]] = $inhalt[1];
                                    }
                                } else {
                                    $this->error .= "invalid search value type for" . $inhalt[0] . " , accepted type is: " . $filterableGetValues[$inhalt[0]];
                                }
                            }
                        } else {
                            $this->error .= "invalid search value type for" . $inhalt[0] . " , accepted type is: " . $filterableGetValues[$inhalt[0]];
                            $this->response = Response::badRequest($this->error);
                            return false;
                        }
                    }
                }
            }
            if ($this->error) {
                $this->response = Response::badRequest($this->error);
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string> $sortableGetValues
     * @example $this->request->sortable(['email','id])
     * @return bool  with response
     */
    public function sortable(array $sortableGetValues): bool
    {
        if (isset($this->get["sort_by_asc"])) {
            if (is_string($this->get["sort_by_asc"]) && strlen($this->get["sort_by_asc"]) > 0) {
                if (in_array($this->get["sort_by_asc"], $sortableGetValues)) {
                    $this->_sort_by_asc = $this->get["sort_by_asc"];
                } else {
                    $this->error .= "invalid search value type for" . $this->get["sort_by"];
                    $this->response = Response::badRequest($this->error);
                    return false;
                }

                if ($this->error) {
                    $this->response = Response::badRequest($this->error);
                    return false;
                }
            }
        }
        if (isset($this->get["sort_by"])) {
            if (is_string($this->get["sort_by"]) && strlen($this->get["sort_by"]) > 0) {
                if (in_array($this->get["sort_by"], $sortableGetValues)) {
                    $this->_sort_by = $this->get["sort_by"];
                } else {
                    $this->error .= "invalid search value type for " . $this->get["sort_by"];
                    $this->response = Response::badRequest($this->error);
                    return false;
                }
                if ($this->error) { 
                    $this->response = Response::badRequest($this->error);
                    return false;
                }
            }
        }
        return true;
    }


    public function setPageNumber(): bool
    {
        if (isset($this->get["page_number"])) {
            $get_page_number = $this->get["page_number"];
            $result = is_numeric($get_page_number);
            if ($result === false) {
                $this->error .= "page_number shall be integer";
                $this->response = Response::badRequest($this->error);
                return false;
            }
            $number = (int) $get_page_number;
            if ($number < 1) {
                $this->error .= "per_number shall be positive, at least 1";
                $this->response = Response::badRequest($this->error);
                return false;
            }
            $this->_pageNumber = $number;
        }
        return true;
    }

    public function setPerPage(): bool
    {
        if (isset($this->get["per_page"])) {
            $get_per_page = $this->get["per_page"];
            if (!is_numeric($get_per_page)) {
                $this->error .= "per_page shall be integer";
                $this->response = Response::badRequest($this->error);
                return false;
            }
            $result = (int) $get_per_page;
            if ($result < 1) {
                $this->error .= "per_page shall be positive, at least 1";
                $this->response = Response::badRequest($this->error);
                return false;
            }
            $this->_per_page = $result;
        }
        return true;
    }

    public function getPageNumber(): int
    {
        return $this->_pageNumber;
    }

    public function getPerPage(): int
    {
        return $this->_per_page;
    }



    /**
     * Summary of getFilterable
     * @return array<mixed>
     */
    public function getFilterable(): array
    {
        return $this->_arr_filterBy;
    }

    /**
     * Summary of getFindable
     * @return array<mixed>
     */
    public function getFindable(): array
    {
        return $this->_arr_find_like;
    }


    /**
     * return sort by
     * @return string|null
     */
    public function getSortable(): string|null
    {
        return $this->_sort_by;
    }

    /**
     * return sort by asc
     * @return string|null
     */
    public function getSortableAsc(): string|null
    {
        return $this->_sort_by_asc;
    }


    public function __get(string $name): mixed
    {
        return $this->$name;
    }

    public function getJwtToken(): JwtToken|null
    {
        return $this->token;
    }

    public function setJwtToken(JWTToken $jwtToken): bool
    {
        if (!$jwtToken->verify()) {
            return false;
        }
        $this->token = $jwtToken;
        return true;
    }

    /**
     * @return int|null|false     
     * in case of Authenticated user with valid JWT Token return int user_id, otherwise return false and set $this->error and $this->response
     */
    public function userId(): null|int|false
    {
        // Check if token exists
        if (!$this->token) {
            return $this->setErrorResponse(['JWT token not found. User is not authenticated.'], 401);
        }
        
        // Verify token validity
        if (!$this->token->isTokenValid) {
            return $this->setErrorResponse(['Invalid JWT token. Authentication failed.'], 401);
        }
        
        // Check if user_id is actually set and valid
        // @phpstan-ignore-next-line
        if (!isset($this->token->user_id) || !is_numeric($this->token->user_id)) {
            return $this->setErrorResponse(['User ID not found or invalid in token.'], 403);
        }
        
        return $this->token->user_id;
    }

    public function getError(): string|null
    {
        return $this->error;
    }

    public function getId(): string
    {
        return $this->id;
    }
    public function getTime(): string
    {
        return $this->time;
    }

    public function getStartExecutionTime(): float
    {
        return $this->start_exec;
    }

    /**
     * Get the HTTP request method (PSR-7 compliant)
     * 
     * @return string HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string
    {
        return $this->requestMethod ?? 'GET';
    }

    /**
     * Get the request URI (PSR-7 compliant)
     * 
     * @return string The request URI
     */
    public function getUri(): string
    {
        return $this->requestedUrl ?? '/';
    }

    /**
     * Get a specific HTTP header value (PSR-7 compliant)
     * 
     * @param string $name Header name (case-insensitive)
     * @return string|null Header value or null if not found
     */
    public function getHeader(string $name): ?string
    {
        if ($this->headers === null) {
            return null;
        }
        
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? null;
    }
    
    /**
     * Set the service name (called by Bootstrap during routing)
     * 
     * @param string $serviceName The service name (e.g., 'User', 'Index')
     * @return void
     */
    public function setServiceName(string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }
    
    /**
     * Get the service name determined during routing
     * 
     * @return string The service name or 'Index' if not set (default service)
     */
    public function getServiceName(): string
    {
        return $this->serviceName ?? 'Index';
    }
    
    /**
     * Set the method name (called by Bootstrap during routing)
     * 
     * @param string $methodName The method name (e.g., 'create', 'read', 'index')
     * @return void
     */
    public function setMethodName(string $methodName): void
    {
        $this->methodName = $methodName;
    }
    
    /**
     * Get the method name determined during routing
     * 
     * @return string The method name or 'index' if not set
     */
    public function getMethodName(): string
    {
        return $this->methodName ?? 'index';
    }

    public function intValuePost(string $key): int|false
    {
        if (!isset($this->post[$key]) || empty($this->post[$key]) || !is_numeric($this->post[$key])) {
            return $this->setErrorResponse(["Post parameter '$key' is required and must be of type integer"], 400);
        }
        return (int) $this->post[$key];
    }

    public function floatValuePost(string $key): float|false    
    {
        if (!isset($this->post[$key]) || empty($this->post[$key]) || !is_numeric($this->post[$key])) {
            return $this->setErrorResponse(["Post parameter '$key' is required and must be of type float"], 400);
        }
        return (float) $this->post[$key];
    }

    public function intValueGet(string $key): int|false 
    {
        if (!isset($this->get[$key]) || empty($this->get[$key]) || !is_numeric($this->get[$key])) {
            return $this->setErrorResponse(["Get parameter '$key' is required and must be of type integer"], 400);
        }
        return (int) $this->get[$key];
    }

    public function floatValueGet(string $key): float|false 
    {
        if (!isset($this->get[$key]) || empty($this->get[$key]) || !is_numeric($this->get[$key])) {
            return $this->setErrorResponse(["Get parameter '$key' is required and must be of type float"], 400);
        }
        return (float) $this->get[$key];
    }

    /**
     * @param  array<string> $toValidatePost Define Post Schema to validation
     * @return bool
     * definePostSchma(['email'=>'email' , 'id'=>'int' , '?name' => 'string'])
     * @help   : ?name means it is optional
     * @in     case of false $this->error will be set
     */
    public function definePostSchema(array $toValidatePost): bool
    {
        return $this->defineSchema($toValidatePost, 'post');
    }

    /**
     * Summary of defineGetSchma
     * @param array<string> $toValidateGet
     * @return bool
     */
    public function defineGetSchema(array $toValidateGet): bool
    {
        return $this->defineSchema($toValidateGet, 'get');
    }

    /**
     * @param  array<string> $toValidatePut Define PUT Schema to validation
     * @return bool
     * definePutSchma(['email'=>'email' , 'id'=>'int' , '?name' => 'string'])
     * @help   : ?name means it is optional
     * @in     case of false $this->error will be set
     */
    public function definePutSchema(array $toValidatePut): bool
    {
        return $this->defineSchema($toValidatePut, 'put');
    }

    /**
     * @param  array<string> $toValidatePatch Define Patch Schema to validation
     * @return bool
     * definePatchSchma(['email'=>'email' , 'id'=>'int' , '?name' => 'string'])
     * @help   : ?name means it is optional
     * @in     case of false $this->error will be set
     */
    public function definePatchSchema(array $toValidatePatch): bool
    {
        return $this->defineSchema($toValidatePatch, 'patch');
    }

    public function setPostToObject(object $class): bool
    {
        try {
            foreach ($this->post as $key => $value) {
                if (property_exists($class, $key)) {
                    $class->$key = $value;
                }
            }
            return true;
        } catch (\Exception $e) {
            return $this->setErrorResponse(["Error mapping post data to object: " . $e->getMessage()], 422);
        }
    }

    /**
     * Validates string lengths in a dictionary against min and max constraints.
     * @param array<string, string> $stringPosts A dictionary where keys are strings and values are strings in the format "min|max" (both min and max are optional).
     * @example $stringPosts = [
     *     'username' => '3|15',  // Min length 3, max length 15
     *     'password' => '8|',    // Min length 8, no max limit
     *     'nickname' => '|20',   // No min limit, max length 20
     *     'bio' => '',           // No min or max limit
     * ];
     * @return bool Returns true if all validations pass, false otherwise. Sets $this->error on failure.
     */
    public function validateStringPosts(array $stringPosts): bool
    {
        $errors = [];

        foreach ($stringPosts as $key => $constraint) {
            // Check if POST key exists
            if (!isset($this->post[$key])) {
                $errors[] = "Missing POST key '$key'";
                continue;  // Skip further checks if key is missing
            }

            // Set default min and max values
            $min = 0;
            $max = PHP_INT_MAX;

            // Parse the constraint string if provided
            if (!empty($constraint)) {
                list($minConstraint, $maxConstraint) = explode('|', $constraint) + [0, null];
                if (is_numeric($minConstraint)) {
                    $min = (int) $minConstraint;
                }
                if (is_numeric($maxConstraint)) {
                    $max = (int) $maxConstraint;
                }
            }
            if (!is_string($this->post[$key])) {
                $errors[] = "POST key '$key' is not a string";
                continue;
            }

            // Validate string length against min and max constraints
            $stringLength = strlen($this->post[$key]);
            if ($stringLength < $min || $stringLength > $max) {
                $errors[] = "String length for post '$key' is {$stringLength}, which is outside the range ({$min}-{$max})";
            }
        }

        // If errors were found, set them and return false
        if (!empty($errors)) {
            $this->error = implode(', ', $errors);  // Combine all errors into a single string
            return false;
        }

        return true;
    }

    public function forwardToRemoteApi(string $remoteApiUrl): JsonResponse
    {

        $jsonResponse = new JsonResponse();
        $caller = new ApiCall();
        if ($this->files !== null) {
            $caller->files = $this->files;
        }
        $caller->authorizationHeader = $this->authorizationHeader;

        $response = $caller->post($remoteApiUrl, $this->post);
        if (!$response) {
            $this->response = $jsonResponse->create($caller->http_response_code, null, 0, $caller->error);
            return $this->response;
        }
        $response = json_decode($response);
        $this->response = $jsonResponse->create($caller->http_response_code, $response);
        return  $this->response;
    }

    /**
     * @param string $remoteApiUrl
     * @param string|null $authorizationHeader
     * @return JsonResponse
     * this function forward incomming post request as post to remote API and return remote api response as JsonResponse Object
     */
    public function forwardPost(string $remoteApiUrl, ?string $authorizationHeader = null): JsonResponse
    {

        $jsonResponse = new JsonResponse();
        $caller = new ApiCall();
        if ($this->files !== null) {
            $caller->files = $this->files;
        }
        $caller->authorizationHeader = $authorizationHeader ? $authorizationHeader : $this->authorizationHeader;

        $response = $caller->post($remoteApiUrl, $this->post);
        if (!$response) {
            $this->response = $jsonResponse->create($caller->http_response_code, null, 0, $caller->error);
            return $this->response;
        }
        $response = json_decode($response);
        $this->response =$jsonResponse->create($caller->http_response_code, $response);
        return $this->response;
    }

    /**
     * if $manualMap is null then map all post to object
     * if $manualMap is not null then map only the post that are in the $manualMap
     * if success return object, if failed return null and set $this->error and $this->response
     * @param object $object
     * @param array<string>|null  $manualMap
     * @return object|null
     */
    public function mapPostToObject(object $object, ?array $manualMap = null): null|object  
    {
        if(count($this->post) == 0){
            $this->setErrorResponse(["No post data available"], 422);
            return null;
        }
        
        if($manualMap == null){
            return $this->_mapArrayToObject($this->post, $object, 'post');
        }
        $errors = [];
        
        foreach ($manualMap as $keyName => $value) {
            if(!isset($this->post[$keyName])){
                $errors[] = "Manual map post $keyName not found in request";
            }
            try{
                if(str_ends_with($value,'()')){
                    $methodName = substr($value, 0, -2);
                    if(method_exists($object, $methodName)){
                       $object->$methodName($this->post[$keyName]);
                    }
                    else{
                        $errors[] = "Method $value not found in " . get_class($object);
                    }
                }
                else{
                    if(property_exists($object, $keyName)){
                        $object->$keyName = $this->post[$keyName];
                    }
                    else{
                        $errors[] = "Property $keyName not found in " . get_class($object);
                    }
                }
            }catch(\Exception $e){
                $errors[] = "Error mapping post $keyName: " . $e->getMessage();
            }
        }
        
        if(!empty($errors)){
            $this->setErrorResponse($errors, 422);
            return null;
        }
        
        return $object;
    }

    /**
     * if $manualMap is null then map all put to object
     * if $manualMap is not null then map only the put that are in the $manualMap
     * if success return object, if failed return null and set $this->error and $this->response
     * @param object $object
     * @param array<string>|null  $manualMap
     * @return object|null
     */
    public function mapPutToObject(object $object, ?array $manualMap = null): null|object  
    {
        if(!is_array($this->put) || count($this->put) == 0){
            $this->setErrorResponse(["No put data available"], 422);
            return null;
        }
        
        if($manualMap == null){
            return $this->_mapArrayToObject($this->put, $object, 'put');
        }
        $errors = [];
        
        foreach ($manualMap as $keyName => $value) {
            if(!isset($this->put[$keyName])){
                $errors[] = "Manual map put $keyName not found in request";
            }
            try{
                if(str_ends_with($value,'()')){
                    $methodName = substr($value, 0, -2);
                    if(method_exists($object, $methodName)){
                       $object->$methodName($this->put[$keyName]);
                    }
                    else{
                        $errors[] = "Method $value not found in " . get_class($object);
                    }
                }
                else{
                    if(property_exists($object, $keyName)){
                        $object->$keyName = $this->put[$keyName];
                    }
                    else{
                        $errors[] = "Property $keyName not found in " . get_class($object);
                    }
                }
            }catch(\Exception $e){
                $errors[] = "Error mapping put $keyName: " . $e->getMessage();
            }
        }
        
        if(!empty($errors)){
            $this->setErrorResponse($errors, 422);
            return null;
        }
        
        return $object;
    }

    /**
     * @param array<mixed, mixed> $assoc_array
     */
    private function _mapArrayToObject(array $assoc_array, object $object, string $method): null|object
    {
        try {
            foreach ($assoc_array as $keyName => $value) {
                if (property_exists($object, $keyName)) {
                    $object->$keyName = $value;
                }
            }
            return $object;
        } catch (\Exception $e) {
            $errorMsg = "$method $keyName cannot be set to " . get_class($object) . " because: " . $e->getMessage();
            $this->setErrorResponse([$errorMsg], 422);
            return null;
        }
    }

    public static function mapPost(Request $request, object $object): null|object
    {
        $name = get_class($object);
        $errors = [];
        
        try {
            foreach ($request->post as $postName => $value) {
                if (property_exists($object, $postName)) {
                    $object->$postName = $value;
                }
            }
            return $object;
        } catch (\Exception $e) {
            $errorMsg = "Post $postName cannot be set to $name because: " . $e->getMessage();
            $request->setErrorResponse([$errorMsg], 422);
            return null;
        }
    }

    /**
     * Helper method to set error response and return false
     * 
     * @param array<string> $errors List of error messages
     * @param int $responseCode HTTP response code to use
     * @return false Always returns false for convenient method chaining
     */
    private function setErrorResponse(array $errors, int $responseCode = 400): false
    {
        $this->error = implode(', ', $errors);
        
        // Use the response code parameter
        $this->response = match($responseCode) {
            401 => Response::unauthorized($this->error),
            403 => Response::forbidden($this->error),
            422 => Response::unprocessableEntity($this->error),
            default => Response::badRequest($this->error),
        };
        
        return false;
    }

    // Private methods
    /**
     * @param  array<string> $schemaDefinition Define data schema for validation
     * @param  string $dataSource Which data source to validate ('post', 'get', 'put', 'patch')
     * @return bool Success or failure of validation
     */
    private function defineSchema(array $schemaDefinition, string $dataSource): bool
    {
        // Get the correct data source
        $target = match($dataSource) {
            'get' => $this->get,
            'put' => $this->put,
            'patch' => $this->patch,
            default => $this->post,
        };

        // Validate that target is an array
        if (!is_array($target)) {
            return $this->setErrorResponse(["There is no $dataSource data"], 422);
        }

        // Separate required and optional fields
        $required = [];
        $optional = [];
        $allFields = [];
        
        foreach ($schemaDefinition as $fieldKey => $fieldType) {
            if (substr($fieldKey, 0, 1) === '?') {
                $fieldName = ltrim($fieldKey, '?');
                $optional[$fieldName] = $fieldType;
                $allFields[$fieldName] = $fieldType;
            } else {
                $required[$fieldKey] = $fieldType;
                $allFields[$fieldKey] = $fieldType;
            }
        }

        // Step 1: Check for unwanted fields
        $errors = [];
        foreach ($target as $fieldName => $fieldValue) {
            if (!array_key_exists($fieldName, $allFields)) {
                $errors[] = "Unwanted $dataSource field: $fieldName";
            }
        }
        
        if (!empty($errors)) {
            return $this->setErrorResponse($errors, 400);
        }

        // Step 2: Check for required fields existence
        foreach ($required as $fieldName => $fieldType) {
            if (!isset($target[$fieldName]) || empty($target[$fieldName])) {
                $errors[] = "Missing required field: $fieldName";
            }
        }
        
        if (!empty($errors)) {
            return $this->setErrorResponse($errors, 400);
        }

        // Step 3: Validate required fields data types
        foreach ($required as $fieldName => $fieldType) {
            if (!TypeChecker::check($fieldType, $target[$fieldName])) {
                $errors[] = "Invalid value for required field $fieldName, expected type: $fieldType";
            }
        }
        
        if (!empty($errors)) {
            return $this->setErrorResponse($errors, 400);
        }

        // Step 4: Validate optional fields if they are present
        foreach ($optional as $fieldName => $fieldType) {
            if (isset($target[$fieldName]) && !empty($target[$fieldName])) {
                if (!TypeChecker::check($fieldType, $target[$fieldName])) {
                    $errors[] = "Invalid value for optional field $fieldName, expected type: $fieldType";
                }
            }
        }
        
        if (!empty($errors)) {
            return $this->setErrorResponse($errors, 400);
        }

        // All validations passed
        return true;
    }

}
