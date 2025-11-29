<?php
namespace App\Api;

use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\JWTToken;
use Gemvc\Http\NoCors;
use Gemvc\Core\WebserverDetector;

/**
 * Developer System Pages API Service
 * 
 * Provides HTML pages for development tools (welcome, database, login)
 * Uses GEMVC's HtmlResponse for server-agnostic HTML output
 * Uses JWT tokens for authentication (no sessions)
 */
class Developer extends ApiService
{
    private string $templateDir;
    private const ADMIN_USER_ID = 1; // Fixed admin user ID for developer tools

    public function __construct(Request $request)
    {
        parent::__construct($request);
        
        // Start output buffering early to prevent "headers already sent" warnings
        if (ob_get_level() === 0) {
            ob_start();
        }
        
        // Populate $_SERVER for compatibility
        if (!isset($_SERVER['REQUEST_METHOD']) && isset($request->requestMethod)) {
            $_SERVER['REQUEST_METHOD'] = $request->requestMethod;
        }
        
        // Populate $_POST, $_GET, $_FILES for compatibility
        if (empty($_POST) && !empty($request->post)) {
            $_POST = $request->post;
        }
        if (empty($_GET) && !empty($request->get)) {
            $_GET = $request->get;
        }
        if (empty($_FILES) && !empty($request->files)) {
            $_FILES = $request->files;
        }
        
        // Get template directory path
        $this->templateDir = dirname(__DIR__, 2) . '/vendor/gemvc/library/src/startup/common/system_pages';
        
        // Security check: Only allow in development mode
        try {
            \Gemvc\Helper\ProjectHelper::loadEnv();
        } catch (\Exception $e) {
            $this->denyAccess();
        }
        
        if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
            $this->denyAccess();
        }
    }

    /**
     * Admin Login - Returns JWT token
     * 
     * @return JsonResponse
     * @http POST
     * @description Admin login for developer tools - returns JWT token
     */
    public function login(): JsonResponse
    {
        // Add CORS headers for Apache/Nginx (Swoole handles CORS at server level)
        if (WebserverDetector::get() !== 'swoole') {
            NoCors::apache();
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'password' => 'string',
            'admin_login' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        $password = $this->request->post['password'] ?? '';
        
        try {
            // Get admin password from .env
            $adminPassword = trim($_ENV['ADMIN_PASSWORD'] ?? '');
            
            if (empty($adminPassword)) {
                return Response::badRequest('Admin password not configured. Please run: php vendor/bin/gemvc admin:setpassword');
            }
            
            // Verify password
            if (trim($password) !== $adminPassword) {
                return Response::unauthorized('Invalid password. Please try again.');
            }
            
            // Create JWT token for admin
            $jwt = new JWTToken();
            $jwt->role = 'admin';
            $jwt->role_id = 1;
            // Create login token (7 days by default)
            $token = $jwt->createLoginToken(self::ADMIN_USER_ID);
            
            // Return token in response
            return Response::success([
                'token' => $token,
                'message' => 'Login successful'
            ]);
            
        } catch (\Exception $e) {
            return Response::internalError('Login error: ' . $e->getMessage());
        }
    }

    /**
     * SPA Entry Point - Returns the SPA HTML shell
     * 
     * @return HtmlResponse
     * @http GET
     * @description Single Page Application shell
     */
    public function app(): HtmlResponse
    {
        $spaPath = $this->templateDir . DIRECTORY_SEPARATOR . 'spa.html';
        if (file_exists($spaPath)) {
            $html = file_get_contents($spaPath);
            return new HtmlResponse($html);
        }
        
        // Fallback: return simple error page if SPA not found
        return new HtmlResponse('<html><body><h1>SPA file not found</h1></body></html>', 500);
    }

    /**
     * Developer Welcome Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description GEMVC Framework Developer Welcome Page Data
     */
    public function welcome(): JsonResponse
    {
        // Check JWT authentication
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Render page HTML and return as JSON
        ob_start();
        $this->renderPageContent('developer-welcome');
        $html = ob_get_clean();
        
        return Response::success(['html' => $html]);
    }

    /**
     * Database Management Page Data (JSON for SPA)
     * 
     * @return JsonResponse
     * @http GET
     * @description Database Management Tools Data
     */
    public function database(): JsonResponse
    {
        // Require admin authentication
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Get selected table from GET parameter (for SPA)
        $selectedTable = $this->request->get['table'] ?? null;
        
        // Render page HTML and return as JSON
        ob_start();
        $this->renderPageContent('database', $selectedTable ? ['selectedTable' => $selectedTable] : []);
        $html = ob_get_clean();
        
        return Response::success(['html' => $html]);
    }

    /**
     * Export Table - Exports table data as CSV or SQL
     * 
     * @return \Gemvc\Http\HtmlResponse
     * @http POST
     * @description Export table data
     */
    public function export(): HtmlResponse
    {
        // Require admin authentication
        if (!$this->isAuthenticated()) {
            return new HtmlResponse('Unauthorized', 401);
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return new HtmlResponse('Invalid request', 400);
        }
        
        $tableName = $this->request->post['table'] ?? '';
        $format = $this->request->post['format'] ?? 'csv';
        
        if (empty($tableName)) {
            return new HtmlResponse('Table name is required', 400);
        }
        
        try {
            $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
            $connection = $dbManager->getConnection();
            
            if ($connection === null) {
                return new HtmlResponse('Database connection failed', 500);
            }
            
            // Get database name
            $result = $connection->query("SELECT DATABASE() as db_name");
            $dbName = $result->fetch(\PDO::FETCH_ASSOC)['db_name'] ?? '';
            
            // Validate table exists
            $tableCheck = $connection->query("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = " . $connection->quote($dbName) . " 
                AND TABLE_NAME = " . $connection->quote($tableName) . "
                LIMIT 1
            ");
            
            if ($tableCheck->rowCount() === 0) {
                return new HtmlResponse("Table '$tableName' does not exist", 404);
            }
            
            $filename = $tableName . '_' . date('Y-m-d_H-i-s') . '.' . $format;
            
            if ($format === 'csv') {
                // Get columns
                $columnsResult = $connection->query("SHOW COLUMNS FROM `$tableName`");
                $columns = [];
                while ($row = $columnsResult->fetch(\PDO::FETCH_ASSOC)) {
                    $columns[] = $row['Field'];
                }
                
                // Get data
                $dataResult = $connection->query("SELECT * FROM `$tableName`");
                $rows = $dataResult->fetchAll(\PDO::FETCH_ASSOC);
                
                // Generate CSV content
                $output = fopen('php://temp', 'r+');
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
                if (!empty($columns)) {
                    fputcsv($output, $columns);
                }
                foreach ($rows as $row) {
                    fputcsv($output, array_values($row));
                }
                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);
                
                return new HtmlResponse($content, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]);
            } elseif ($format === 'sql') {
                // Get CREATE TABLE statement
                $escapedTable = '`' . str_replace('`', '``', $tableName) . '`';
                $createTableResult = $connection->query("SHOW CREATE TABLE $escapedTable");
                
                if ($createTableResult === false) {
                    return new HtmlResponse('Failed to get table structure', 500);
                }
                
                $createTableRow = $createTableResult->fetch(\PDO::FETCH_ASSOC);
                if (empty($createTableRow) || !isset($createTableRow['Create Table'])) {
                    return new HtmlResponse('Table structure not found', 500);
                }
                
                $createTableSql = $createTableRow['Create Table'];
                $createTableSql = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $createTableSql);
                
                $sql = "-- Table Structure: $tableName\n";
                $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
                $sql .= $createTableSql . ";\n";
                
                return new HtmlResponse($sql, 200, [
                    'Content-Type' => 'text/sql; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]);
            } else {
                return new HtmlResponse('Invalid export format. Use "csv" or "sql"', 400);
            }
        } catch (\Exception $e) {
            return new HtmlResponse('Export error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import Table - Imports data into a table
     * 
     * @return JsonResponse
     * @http POST
     * @description Import data into table
     */
    public function import(): JsonResponse
    {
        // Require admin authentication
        if (!$this->isAuthenticated()) {
            return Response::unauthorized('Authentication required');
        }
        
        // Validate POST schema
        if (!$this->request->definePostSchema([
            'table' => 'string',
            'format' => 'string'
        ])) {
            return $this->request->returnResponse();
        }
        
        $tableName = $this->request->post['table'] ?? '';
        $format = $this->request->post['format'] ?? 'csv';
        
        if (empty($tableName)) {
            return Response::badRequest('Table name is required');
        }
        
        // Check if file was uploaded
        if (empty($this->request->files) || !isset($this->request->files['import_file'])) {
            return Response::badRequest('No file uploaded');
        }
        
        $file = $this->request->files['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return Response::badRequest('File upload error: ' . $file['error']);
        }
        
        try {
            $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
            $connection = $dbManager->getConnection();
            
            if ($connection === null) {
                return Response::internalError('Database connection failed');
            }
            
            if ($format === 'csv') {
                // Import CSV
                $fileContent = file_get_contents($file['tmp_name']);
                
                if ($fileContent === false) {
                    return Response::badRequest('Failed to read CSV file');
                }
                
                // Parse CSV
                $lines = str_getcsv($fileContent, "\n");
                if (empty($lines)) {
                    return Response::badRequest('CSV file is empty');
                }
                
                // Get headers from first line
                $headers = str_getcsv(array_shift($lines));
                $headers = array_map('trim', $headers);
                
                // Prepare INSERT statement
                $placeholders = '(' . implode(', ', array_fill(0, count($headers), '?')) . ')';
                $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $headers) . "`) VALUES $placeholders";
                $stmt = $connection->prepare($sql);
                
                // Insert rows
                $imported = 0;
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $values = str_getcsv($line);
                    if (count($values) === count($headers)) {
                        $stmt->execute($values);
                        $imported++;
                    }
                }
                
                return Response::success([
                    'message' => "Successfully imported $imported rows into $tableName",
                    'rows' => $imported
                ]);
            } elseif ($format === 'sql') {
                // Import SQL
                $fileContent = file_get_contents($file['tmp_name']);
                
                if ($fileContent === false) {
                    return Response::badRequest('Failed to read SQL file');
                }
                
                // Execute SQL file
                $connection->exec($fileContent);
                
                return Response::success([
                    'message' => "Successfully executed SQL file for $tableName"
                ]);
            } else {
                return Response::badRequest('Invalid import format. Use "csv" or "sql"');
            }
        } catch (\Exception $e) {
            return Response::internalError('Import error: ' . $e->getMessage());
        }
    }

    /**
     * Get Logo Data
     * 
     * @return JsonResponse
     * @http GET
     * @description Returns GEMVC logo as base64 and admin password status
     */
    public function logo(): JsonResponse
    {
        $gemvcLogoUrl = null;
        $gemvcLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'gemvc_logo.svg';
        if (file_exists($gemvcLogoPath)) {
            $logoContent = file_get_contents($gemvcLogoPath);
            if ($logoContent !== false) {
                $gemvcLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($logoContent);
            }
        }
        
        // Check if admin password is set
        $adminPasswordSet = !empty(trim($_ENV['ADMIN_PASSWORD'] ?? ''));
        
        return Response::success([
            'gemvcLogo' => $gemvcLogoUrl,
            'adminPasswordSet' => $adminPasswordSet
        ]);
    }

    /**
     * Deny access (security check failed)
     * 
     * @return void
     */
    private function denyAccess(): void
    {
        if (WebserverDetector::get() === 'swoole') {
            throw new \RuntimeException('Not Found');
        }
        http_response_code(404);
        exit('Not Found');
    }

    /**
     * Check if user is authenticated via JWT token
     * 
     * @return bool
     */
    private function isAuthenticated(): bool
    {
        // Extract and verify token using JWTToken's extractToken method
        $jwt = new JWTToken();
        if (!$jwt->extractToken($this->request)) {
            return false;
        }
        
        // Verify token
        $verified = $jwt->verify();
        if (!$verified || !$jwt->isTokenValid) {
            return false;
        }
        
        // Check if user is admin and has correct user_id
        if ($jwt->user_id !== self::ADMIN_USER_ID || $jwt->role !== 'admin') {
            return false;
        }
        
        return true;
    }

    /**
     * Render page content only (for SPA JSON responses)
     * 
     * @param string $pageName The page name (without .php extension)
     * @param array<string, mixed> $additionalVars Additional variables to extract
     * @return void
     */
    private function renderPageContent(string $pageName, array $additionalVars = []): void
    {
        // Prepare common variables and extract them into scope
        $variables = $this->preparePageVariables();
        // Merge additional variables
        $variables = array_merge($variables, $additionalVars);
        extract($variables, EXTR_SKIP);
        
        // Load page content only (no layout)
        $pagePath = $this->templateDir . DIRECTORY_SEPARATOR . $pageName . '.php';
        if (file_exists($pagePath)) {
            require $pagePath;
        } else {
            // Fallback to developer-welcome
            $fallbackPath = $this->templateDir . DIRECTORY_SEPARATOR . 'developer-welcome.php';
            if (file_exists($fallbackPath)) {
                require $fallbackPath;
            }
        }
    }

    /**
     * Prepare common variables for page rendering
     * 
     * @return array<string, mixed> Array of variables to extract into template scope
     */
    private function preparePageVariables(): array
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $webserverType = WebserverDetector::get();
        
        // Construct base URL based on webserver type
        if ($webserverType === 'swoole') {
            // Swoole: Use explicit port 9501 (from env or default), NO /api prefix
            $swoolePort = is_numeric($_ENV["SWOOLE_SERVER_PORT"] ?? 9501) ? (int) ($_ENV["SWOOLE_SERVER_PORT"] ?? 9501) : 9501;
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Remove port from HTTP_HOST if it exists, then add our explicit port
            $host = preg_replace('/:\d+$/', '', $host);
            $baseUrl = $protocol . '://' . $host . ':' . $swoolePort;
            // Swoole: apiBaseUrl is same as baseUrl (no /api prefix)
            $apiBaseUrl = rtrim($baseUrl, '/');
        } else {
            // Apache/Nginx: HTTP_HOST may already include port, WITH /api prefix
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // If HTTP_HOST already has a port, use it as-is
            if (strpos($host, ':') !== false) {
                $baseUrl = $protocol . '://' . $host;
            } else {
                // HTTP_HOST doesn't have port, check SERVER_PORT
                $port = $_SERVER['SERVER_PORT'] ?? '';
                $portDisplay = ($port && $port !== '80' && $port !== '443') ? ':' . $port : '';
                $baseUrl = $protocol . '://' . $host . $portDisplay;
            }
            // Apache/Nginx: apiBaseUrl includes /api prefix
            $apiBaseUrl = rtrim($baseUrl, '/') . '/api';
        }
        $webserverName = match($webserverType) {
            'swoole' => 'OpenSwoole',
            'apache' => 'Apache',
            'nginx' => 'Nginx',
            default => ucfirst($webserverType)
        };

        // Load logos
        $gemvcLogoUrl = null;
        $gemvcLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'gemvc_logo.svg';
        if (file_exists($gemvcLogoPath)) {
            $logoContent = file_get_contents($gemvcLogoPath);
            if ($logoContent !== false) {
                $gemvcLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($logoContent);
            }
        }

        $webserverLogoUrl = null;
        if ($webserverType === 'apache') {
            $apacheLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'apache.svg';
            if (file_exists($apacheLogoPath)) {
                $apacheLogoContent = file_get_contents($apacheLogoPath);
                if ($apacheLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($apacheLogoContent);
                }
            }
        } elseif ($webserverType === 'nginx') {
            $nginxLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'nginx.svg';
            if (file_exists($nginxLogoPath)) {
                $nginxLogoContent = file_get_contents($nginxLogoPath);
                if ($nginxLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($nginxLogoContent);
                }
            }
        } elseif ($webserverType === 'swoole') {
            $swooleLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'swoole.svg';
            if (file_exists($swooleLogoPath)) {
                $swooleLogoContent = file_get_contents($swooleLogoPath);
                if ($swooleLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($swooleLogoContent);
                }
            }
        }
        
        // Return all variables as array for extraction
        return [
            'baseUrl' => $baseUrl,
            'apiBaseUrl' => $apiBaseUrl,
            'webserverType' => $webserverType,
            'webserverName' => $webserverName,
            'gemvcLogoUrl' => $gemvcLogoUrl,
            'webserverLogoUrl' => $webserverLogoUrl,
            'templateDir' => $this->templateDir,
        ];
    }


    /**
     * @hidden
     */
    public static function mockResponse(string $method): array
    {
        return [
            'response_code' => 200,
            'message' => 'OK',
            'count' => null,
            'service_message' => null,
            'data' => null
        ];
    }
}
