<?php

namespace Gemvc\Http;

use Gemvc\Http\Request;

/**
 * Class SwooleRequest
 * Handles the conversion of an OpenSwoole request to a Request object.
 * Includes input sanitization to prevent security vulnerabilities.
 */
class SwooleRequest
{
    public Request $request; 
    private object $incomingRequestObject;

    /**
     * SwooleRequest constructor.
     * @param object $swooleRequest The incoming OpenSwoole request object.
     * @throws \InvalidArgumentException If the provided request is not valid.
     */
    public function __construct(object $swooleRequest)
    {
        $this->request = new Request();
        $this->incomingRequestObject = $swooleRequest;

        try {
            if (!isset($swooleRequest->server['request_uri'])) {
                throw new \InvalidArgumentException("Missing request_uri - not a valid OpenSwoole request");
            }
            
            // Additional validation checks could go here
            
            $this->request->requestMethod = $swooleRequest->server['request_method'] ?? 'GET';
            $this->request->requestedUrl = $this->sanitizeRequestURI($swooleRequest->server['request_uri']);
            $this->request->queryString = isset($swooleRequest->server['query_string']) ? 
                $this->sanitizeInput((string) $swooleRequest->server['query_string']) : null;
            $remoteAddr = $swooleRequest->server['remote_addr'] ?? '';
            $remotePort = $swooleRequest->server['remote_port'] ?? '';
            $this->request->remoteAddress = $remoteAddr . ($remotePort ? ':' . $remotePort : '');

            if (isset($swooleRequest->header['user-agent'])) {
                $this->request->userMachine = $this->sanitizeInput((string) $swooleRequest->header['user-agent']);
            }
            $this->setCookies();
            $this->setHeaders();

            $this->setData();
        } catch (\Exception $e) {
            // Set error in Request object for consistent handling
            $this->request->error = $e->getMessage();
            $this->request->response = Response::badRequest($e->getMessage());
            // Log the error
            error_log("SwooleRequest error: " . $e->getMessage());
        }
    }

    /**
     * Get the original Swoole request object.
     * @return object The original Swoole request.
     */
    public function getOriginalSwooleRequest(): object
    {
        return $this->incomingRequestObject;
    }

    /**
     * Set the request data from the incoming Swoole request.
     */
    private function setData(): void
    {
        $this->setAuthorizationToken();
        
        // Set request body data based on method
        switch ($this->request->requestMethod) {
            case 'POST':
                $this->setPost();
                break;
            case 'PUT':
                $this->setPut();
                break;
            case 'PATCH':
                $this->setPatch();
                break;
        }
        
        $this->setFiles();
        $this->setGet();
        $this->setCookies();
    }

    /**
     * Set the POST data from the incoming request.
     */
    private function setPost(): void
    {
        $contentType = $this->incomingRequestObject->header['content-type'] ?? '';
        
        // For standard form submissions
        $postData = $this->incomingRequestObject->post ?? [];
        $this->request->post = $this->sanitizeData($postData);
        
        // Additionally parse JSON if content type indicates JSON
        if (empty($this->request->post) && 
            strpos($contentType, 'application/json') !== false) {
            // @phpstan-ignore-next-line
            $rawContent = $this->incomingRequestObject->rawContent() ?? '';
            $jsonData = json_decode($rawContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $this->request->post = $this->sanitizeData($jsonData);
            }
        }
    }

    /**
     * Set HTTP headers from Swoole request (PSR-7 compatible)
     * Normalizes headers to lowercase keys for case-insensitive access
     */
    private function setHeaders(): void
    {
        $headers = [];
        
        if (isset($this->incomingRequestObject->header) && is_array($this->incomingRequestObject->header)) {
            foreach ($this->incomingRequestObject->header as $name => $value) {
                $normalized = strtolower(is_string($name) ? $name : (string)$name);
                $headerValue = is_string($value) ? $value : (is_scalar($value) ? (string)$value : '');
                $headers[$normalized] = $this->sanitizeInput($headerValue);
            }
        }
        
        $this->request->headers = $headers;
    }

    /**
     * Set the authorization token from the incoming request headers.
     */
    private function setAuthorizationToken(): void
    {
        if (isset($this->incomingRequestObject->header['authorization'])) {
            $authHeader = $this->sanitizeInput((string) $this->incomingRequestObject->header['authorization']);
            $this->request->authorizationHeader = $authHeader;
            $this->request->jwtTokenStringInHeader = $this->parseAuthorizationToken($authHeader);
        }
    }

    /**
     * Set the uploaded files from the incoming request.
     */
    private function setFiles(): void
    {
        $files = $this->incomingRequestObject->files ?? [];
        // Transform structure if needed to match PHP's $_FILES structure
        // that the Request class expects
        $this->request->files = $this->normalizeFilesArray($files);
    }

    /**
     * Normalize the files array to match PHP's $_FILES structure.
     * 
     * @param array<string, mixed> $files The files array from Swoole request
     * @return array<string, mixed> Normalized files array
     */
    private function normalizeFilesArray(array $files): array
    {
        // If empty, return empty array
        if (empty($files)) {
            return [];
        }
        
        $normalized = [];
        
        // Process the files array to match PHP's $_FILES structure
        foreach ($files as $key => $file) {
            if (is_array($file) && isset($file['name'])) {
                // Already in expected format
                $normalized[$key] = $file;
                // Sanitize file name if it's a string
                if (is_string($file['name'])) {
                    $normalized[$key]['name'] = $this->sanitizeInput($file['name']);
                }
                if (is_string($file['type'])) {
                    $normalized[$key]['type'] = $this->sanitizeInput($file['type']);
                }
            } elseif (is_object($file) && isset($file->name)) {
                // Convert object to array format
                $normalized[$key] = [
                    'name' => is_string($file->name) ? $this->sanitizeInput($file->name) : $file->name,
                    'type' => isset($file->type) && is_string($file->type) ? $this->sanitizeInput($file->type) : '',
                    'tmp_name' => $file->tmp_name ?? '',
                    'error' => $file->error ?? 0,
                    'size' => $file->size ?? 0
                ];
            }
        }
        
        return $normalized;
    }

    /**
     * Set the GET data from the incoming request.
     */
    private function setGet(): void
    {
        $getData = $this->incomingRequestObject->get ?? [];
        $this->request->get = $this->sanitizeData($getData);
    }

    /**
     * Set the cookies from the incoming request with security validation.
     * Filters out dangerous cookies that could be used for hacking attempts.
     */
    private function setCookies(): void
    {
        $cookieData = $this->incomingRequestObject->cookie ?? [];
        if (empty($cookieData)) {
            $this->request->cookies = null;
            return;
        }
        
        // Security: Filter out dangerous cookies
        $secureCookies = $this->filterDangerousCookies($cookieData);
        
        if (empty($secureCookies)) {
            $this->request->cookies = null;
            return;
        }
        
        // Convert cookie array to string format like PHP's $_COOKIE
        $cookieString = '';
        foreach ($secureCookies as $name => $value) {
            $stringValue = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
            $cookieString .= $name . '=' . urlencode($stringValue) . '; ';
        }
        $this->request->cookies = rtrim($cookieString, '; ');
    }

    /**
     * Filter out dangerous cookies that could be used for hacking attempts.
     * 
     * @param array<string, mixed> $cookies Raw cookie data
     * @return array<string, mixed> Filtered safe cookies
     */
    private function filterDangerousCookies(array $cookies): array
    {
        $filteredCookies = [];
        
        // List of dangerous cookie names that should be blocked
        $dangerousCookieNames = [
            // Session hijacking attempts
            'PHPSESSID', 'JSESSIONID', 'ASP.NET_SessionId', 'ASPSESSIONID',
            'sessionid', 'session_id', 'sessid', 'sid',
            
            // Authentication bypass attempts
            'auth', 'authentication', 'login', 'user', 'admin', 'root',
            'token', 'jwt', 'bearer', 'access_token', 'refresh_token',
            'api_key', 'apikey', 'secret', 'password', 'passwd',
            
            // CSRF and security tokens
            'csrf_token', 'csrf', 'xsrf', 'xsrf_token', 'csrf_token',
            'security_token', 'form_token', 'nonce',
            
            // Admin and privilege escalation
            'admin_token', 'admin_session', 'admin_auth', 'admin_login',
            'privilege', 'role', 'permission', 'access_level',
            
            // File upload and path traversal
            'file_path', 'upload_path', 'temp_path', 'upload_dir',
            'file_name', 'upload_name', 'file_type', 'upload_type',
            
            // SQL injection and XSS attempts
            'sql', 'query', 'script', 'javascript', 'vbscript',
            'onload', 'onerror', 'onclick', 'onmouseover',
            
            // Command injection
            'cmd', 'command', 'exec', 'system', 'shell', 'bash',
            'powershell', 'cmdline', 'terminal',
            
            // LDAP injection
            'ldap', 'ldap_filter', 'ldap_query', 'ldap_search',
            
            // NoSQL injection
            'mongo', 'mongodb', 'nosql', 'db_query', 'db_filter',
            
            // Framework-specific dangerous cookies
            'laravel_session', 'symfony_session', 'cakephp_session',
            'yii_session', 'codeigniter_session', 'zend_session',
            
            // Custom application dangerous patterns
            'debug', 'test', 'dev', 'development', 'staging',
            'config', 'configuration', 'settings', 'preferences',
            'backup', 'restore', 'migrate', 'upgrade', 'install'
        ];
        
        // List of dangerous cookie value patterns (regex)
        $dangerousValuePatterns = [
            // Script injection patterns
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/on\w+\s*=/i',
            
            // SQL injection patterns
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\balter\b.*\btable\b)/i',
            '/(\bcreate\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bsp_\w+)/i', // SQL Server stored procedures
            
            // Command injection patterns
            '/[;&|`$(){}[\]]/', // Shell metacharacters
            '/\b(cmd|command|exec|system|shell|bash|powershell)\b/i',
            '/\b(rm|del|delete|remove|mv|move|cp|copy)\b/i',
            '/\b(cat|type|more|less|head|tail|grep|find)\b/i',
            
            // Path traversal patterns
            '/\.\.\//', // Directory traversal
            '/\.\.\\\\/', // Windows directory traversal
            '/%2e%2e%2f/i', // URL encoded directory traversal
            '/%2e%2e%5c/i', // URL encoded Windows directory traversal
            
            // LDAP injection patterns
            '/[()=*!&|]/', // LDAP special characters
            '/\b(ldap|ldap_filter|ldap_query)\b/i',
            
            // NoSQL injection patterns
            '/\b(mongo|mongodb|nosql)\b/i',
            '/\$where/i',
            '/\$ne/i',
            '/\$gt/i',
            '/\$lt/i',
            '/\$regex/i',
            
            // XSS patterns
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/<form[^>]*>/i',
            '/<input[^>]*>/i',
            '/<link[^>]*>/i',
            '/<meta[^>]*>/i',
            '/<style[^>]*>/i',
            '/<link[^>]*>/i',
            
            // Base64 encoded dangerous content
            '/^[A-Za-z0-9+\/]+=*$/', // Base64 pattern
        ];
        
        foreach ($cookies as $name => $value) {
            // Skip if cookie name is dangerous
            if (in_array(strtolower($name), array_map('strtolower', $dangerousCookieNames))) {
                continue;
            }
            
            // Skip if cookie name contains dangerous patterns
            if (preg_match('/[<>"\'\s;=]/', $name)) {
                continue;
            }
            
            // Convert value to string for pattern matching
            $stringValue = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
            
            // Skip if cookie value is too long (potential buffer overflow)
            if (strlen($stringValue) > 4096) {
                continue;
            }
            
            // Skip if cookie value matches dangerous patterns
            $isDangerous = false;
            foreach ($dangerousValuePatterns as $pattern) {
                if (preg_match($pattern, $stringValue)) {
                    $isDangerous = true;
                    break;
                }
            }
            
            if ($isDangerous) {
                continue;
            }
            
            // Additional security checks
            // Check for null bytes
            if (strpos($stringValue, "\0") !== false) {
                continue;
            }
            
            // Check for control characters (except tab, newline, carriage return)
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $stringValue)) {
                continue;
            }
            
            // Check for suspicious encoding attempts
            if (preg_match('/%[0-9a-f]{2}/i', $stringValue) && 
                strlen(urldecode($stringValue)) !== strlen($stringValue)) {
                // Contains URL encoding, check if it's suspicious
                $decoded = urldecode($stringValue);
                foreach ($dangerousValuePatterns as $pattern) {
                    if (preg_match($pattern, $decoded)) {
                        $isDangerous = true;
                        break;
                    }
                }
                if ($isDangerous) {
                    continue;
                }
            }
            
            // If we get here, the cookie is safe
            $filteredCookies[$name] = $value;
        }
        
        return $filteredCookies;
    }

    /**
     * Set the PUT data from the incoming request.
     */
    private function setPut(): void
    {
        // OpenSwoole stores PUT data in the request body
        // Need to parse the raw content based on content type
        // @phpstan-ignore-next-line
        $rawContent = $this->incomingRequestObject->rawContent() ?? '';
        $contentType = $this->incomingRequestObject->header['content-type'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $jsonData = json_decode($rawContent, true);
            if (is_array($jsonData)) {
                $this->request->put = $this->sanitizeData($jsonData);
            }
        } else {
            // Parse form data format
            parse_str($rawContent, $putData);
            $this->request->put = $this->sanitizeData($putData);
        }
    }

    /**
     * Set the PATCH data from the incoming request.
     */
    private function setPatch(): void
    {
        // Similar implementation to setPut
        // @phpstan-ignore-next-line
        $rawContent = $this->incomingRequestObject->rawContent() ?? '';
        $contentType = $this->incomingRequestObject->header['content-type'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $jsonData = json_decode($rawContent, true);
            if (is_array($jsonData)) {
                $this->request->patch = $this->sanitizeData($jsonData);
            }
        } else {
            // Parse form data format
            parse_str($rawContent, $patchData);
            $this->request->patch = $this->sanitizeData($patchData);
        }
    }

    /**
     * Parse the authorization token from the header.
     * @param string $authorizationHeader The full authorization header.
     * @return string|null The parsed token, if present.
     */
    public function parseAuthorizationToken(string $authorizationHeader): ?string
    {
        if (preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return $matches[1]; // Return the token part
        }
        return null; // Or handle other types of tokens if necessary
    }

    /**
     * Sanitize input string to prevent XSS attacks.
     * 
     * @param string $input The input to sanitize
     * @return string Sanitized input
     */
    private function sanitizeInput(string $input): string
    {
        return filter_var(trim($input), FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
    }

    /**
     * Sanitize array data recursively.
     * 
     * @param array $data The data array to sanitize
     * @return array Sanitized data array
     */
    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize the request URI.
     * 
     * @param string $uri The URI to sanitize
     * @return string Sanitized URI
     */
    private function sanitizeRequestURI(string $uri): string
    {
        $sanitizedURI = trim($uri);
        if (!filter_var($sanitizedURI, FILTER_SANITIZE_URL)) {
            return '';
        }
        return $sanitizedURI;
    }
}
