<?php

namespace Gemvc\Http;

use Gemvc\Http\Request;

/**
 * The ApacheRequest class handles incoming HTTP requests in an Apache environment,
 * sanitizes inputs from various request methods (GET, POST, PUT, PATCH),
 * and extracts essential request details into a Request object.
 */
class ApacheRequest
{
    public Request $request;

    /** Raw request body (read once; php://input can only be read once per request) */
    private string $rawInput = '';

    public function __construct()
    {
        $this->rawInput = (string) file_get_contents('php://input');
        $this->sanitizeAllServerHttpRequestHeaders();
        $this->sanitizeAllHTTPGetRequest();
        $this->sanitizeAllHTTPPostRequest();
        $put = $this->sanitizeAllHTTPPutRequest();
        $patch = $this->sanitizeAllHTTPPatchRequest();
        $this->sanitizeQueryString();
        $this->request = new Request();
        $this->request->requestedUrl = $this->sanitizeRequestURI();
        $this->request->requestMethod = $this->getRequestMethod();
        $this->request->userMachine = $this->getUserAgent();
        $this->request->remoteAddress = $this->getRemoteAddress();
        $this->request->queryString = is_string($_SERVER['QUERY_STRING'] ?? null) ? $_SERVER['QUERY_STRING'] : '';
        $this->request->post = $_POST;
        // Remove '_gemvc_url_path' parameter added by Apache rewrite rule - it's not used by the framework
        // The framework uses $this->request->requestedUrl from $_SERVER['REQUEST_URI'] instead
        // Using '_gemvc_url_path' (with framework prefix) prevents conflicts with developer parameters like 'url'
        $getParams = $_GET;
        if (isset($getParams['_gemvc_url_path'])) {
            unset($getParams['_gemvc_url_path']);
        }
        $this->request->get = $getParams;
        $this->request->put = $put;
        $this->request->patch = $patch;
        $this->request->files = [];
        if (isset($_FILES['file']) && is_array( $_FILES['file'] )) {
            $this->request->files = $_FILES['file'];
        }
        $this->getAuthHeader();
        $this->setHeaders();
    }

    private function sanitizeAllServerHttpRequestHeaders():void
    {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                if(is_string($_SERVER[$key])) {
                    $_SERVER[$key] = $this->sanitizeInput($value);
                }
                if(is_array($_SERVER[$key])) {
                    foreach($_SERVER[$key] as $subKey=>$subValue)
                    {
                        $_SERVER[$key][$subKey] = $this->sanitizeInput($subValue);
                    }
                }
            }
        }
    }

    private function sanitizeAllHTTPPostRequest():void
    {   
        // Check if Content-Type is JSON and parse it
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $contentType = is_string($contentType) ? $contentType : '';
        if (empty($_POST) && strpos($contentType, 'application/json') !== false && $this->rawInput !== '') {
            $jsonData = json_decode($this->rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                $_POST = $this->sanitizeArrayRecursive($jsonData);
                return;
            }
        }

        $_POST = $this->sanitizeArrayRecursive($_POST);
    }

    /**
     * @return array<mixed>|null
     */
    private function sanitizeAllHTTPPatchRequest(): null|array
    {
        parse_str($this->rawInput, $_PATCH);
        if (empty($_PATCH)) {
            return null;
        }
        return $this->sanitizeArrayRecursive($_PATCH);
    }


    private function sanitizeAllHTTPGetRequest(): void
    {
        $_GET = $this->sanitizeArrayRecursive($_GET);
    }

    /**
     * @return array<mixed>|null
     */
    private function sanitizeAllHTTPPutRequest(): null|array
    {
        parse_str($this->rawInput, $_PUT);
        if (empty($_PUT)) {
            return null;
        }
        return $this->sanitizeArrayRecursive($_PUT);
    }


    private function sanitizeQueryString(): void
    {
        if (isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) {
            $trimmed = trim($_SERVER['QUERY_STRING']);
            $filtered = filter_var($trimmed, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $_SERVER['QUERY_STRING'] = $filtered !== false ? $filtered : '';
        }
    }

    private function sanitizeRequestURI(): string
    {
        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $trimmed = trim($_SERVER['REQUEST_URI']);
            $filtered = filter_var($trimmed, FILTER_SANITIZE_URL);
            if ($filtered !== false) {
                return $filtered;
            }
            return '';
        }
        return '';
    }

    /**
     * Recursively sanitize array values (strings only) to prevent XSS in nested payloads.
     *
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function sanitizeArrayRecursive(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $out[$key] = $this->sanitizeInput($value);
            } elseif (is_array($value)) {
                $out[$key] = $this->sanitizeArrayRecursive($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * @param  mixed $input
     * @return mixed
     */
    private function sanitizeInput(mixed $input): mixed
    {
        if (!is_string($input)) {
            return $input;
        }
        $filtered = filter_var(trim($input), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return $filtered !== false ? $filtered : '';
    }

    private function getUserAgent():string
    {
        if(isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        return 'undetected';
    }

    private function getRemoteAddress():string
    {
        if(isset($_SERVER['REMOTE_ADDR'])) {
            if (filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
                /**@phpstan-ignore-next-line */
                return $_SERVER['REMOTE_ADDR'];
            } else {
                return 'invalid_remote_address_ip_format';
            }
        }
        return 'unsetted_remote_address';
    }

    private function getRequestMethod():string
    {
        if(isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = trim($_SERVER['REQUEST_METHOD']);
            $_SERVER['REQUEST_METHOD'] = strtoupper($_SERVER['REQUEST_METHOD']);
            $allowedMethods = array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD');
            if (in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
                return $_SERVER['REQUEST_METHOD'];
            } else {
                return ''; // Invalid request method
            }
        }
        return '';
    }

    private function getAuthHeader():void
    {
        /**@phpstan-ignore-next-line */
        $this->request->authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
        // If the "Authorization" header is empty, you may want to check for the "REDIRECT_HTTP_AUTHORIZATION" header as well.
        if (!$this->request->authorizationHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $res = $this->sanitizeInput($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            if(is_string($res)) {
                $this->request->authorizationHeader = $res;
            }
        }
    }

    /**
     * Set HTTP headers from $_SERVER (PSR-7 compatible)
     * Normalizes headers to lowercase keys for case-insensitive access
     */
    private function setHeaders(): void
    {
        $headers = [];
        
        // Extract HTTP_* headers from $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                // Convert HTTP_HEADER_NAME to header-name
                $headerName = str_replace('_', '-', substr($key, 5));
                $normalized = strtolower($headerName);
                $headers[$normalized] = is_string($value) ? $this->sanitizeInput($value) : '';
            }
        }
        
        // Handle special headers that don't use HTTP_ prefix
        if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $this->sanitizeInput($_SERVER['CONTENT_TYPE']);
        }
        
        if (isset($_SERVER['CONTENT_LENGTH']) && is_string($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $this->sanitizeInput($_SERVER['CONTENT_LENGTH']);
        }
        
        // Handle Authorization header (already handled in getAuthHeader, but add to headers array)
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $this->sanitizeInput($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $this->sanitizeInput($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        
        // Ensure all header values are strings
        /** @var array<string, string> $normalizedHeaders */
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[$key] = is_string($value) ? $value : '';
        }
        $this->request->headers = $normalizedHeaders;
    }
}