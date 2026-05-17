<?php
/**
 * Developer Table Layer
 * 
 * Handles database queries for developer tools (database metadata, table structure, etc.)
 * This is the Table layer - database operations only
 */
namespace Gemvc\Core\Developer;

use Gemvc\Database\Table;
use Gemvc\Helper\ProjectHelper;
use PDO;

class DeveloperTable extends Table
{
    /**
     * Get table name (not used for this utility class, but required by parent)
     * 
     * @return string
     */
    public function getTable(): string
    {
        return ''; // Not applicable - this is a utility class
    }

    /**
     * Define schema (not used for this utility class, but required by parent)
     * 
     * @return array<string, mixed>
     */
    public function defineSchema(): array
    {
        return [];
    }

    /**
     * Type map (not used for this utility class, but required by parent)
     * 
     * @var array<string, string>
     */
    protected array $_type_map = [];

    /**
     * Check if database is ready/accessible
     * 
     * @return bool
     */
    public function isDatabaseReady(): bool
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return false;
            }
            // Execute test query and check if it succeeds
            $result = $pdo->query('SELECT 1');
            // Query returns false on failure, PDOStatement on success
            return $result !== false;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        } catch (\Throwable $e) {
            // Catch PDO exceptions and other errors
            $this->setError($e->getMessage());
            return false;
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get database name
     * 
     * @return string
     */
    public function getDatabaseName(): string
    {
        try {
            $pdo = $this->getPdoConnection();
            if ($pdo === null) {
                return 'unknown';
            }
            $result = $pdo->query("SELECT DATABASE() as db_name");
            if ($result === false) {
                return 'unknown';
            }
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !isset($row['db_name'])) {
                return 'unknown';
            }
            return is_string($row['db_name']) ? $row['db_name'] : 'unknown';
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return 'unknown';
        }
    }

    /**
     * Get all tables in database
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getAllTables(): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }
            
            $dbName = $this->getDatabaseName();
            $query = "
                SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COMMENT
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
            ";
            
            $result = $pdo->query($query);
            if ($result === false) {
                return [];
            }
            $data = $result->fetchAll(PDO::FETCH_ASSOC);
            // Ensure we return the correct type: array<int, array<string, mixed>>
            return array_values($data);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get table structure (columns)
     * 
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     */
    public function getTableStructure(string $tableName): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }
            
            $dbName = $this->getDatabaseName();
            $query = "
                SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    COLUMN_KEY,
                    COLUMN_DEFAULT,
                    EXTRA,
                    COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                AND TABLE_NAME = " . $pdo->quote($tableName) . "
                ORDER BY ORDINAL_POSITION
            ";
            
            $result = $pdo->query($query);
            if ($result === false) {
                return [];
            }
            $data = $result->fetchAll(PDO::FETCH_ASSOC);
            // Ensure we return the correct type: array<int, array<string, mixed>>
            return array_values($data);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get foreign key relationships for a table
     * 
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     */
    public function getTableRelationships(string $tableName): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }
            
            $dbName = $this->getDatabaseName();
            $query = "
                SELECT 
                    kcu.CONSTRAINT_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = " . $pdo->quote($dbName) . "
                    AND kcu.TABLE_NAME = " . $pdo->quote($tableName) . "
                    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ";
            
            $result = $pdo->query($query);
            if ($result === false) {
                return [];
            }
            $data = $result->fetchAll(PDO::FETCH_ASSOC);
            // Ensure we return the correct type: array<int, array<string, mixed>>
            return array_values($data);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Check if table exists
     * 
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return false;
            }
            
            $dbName = $this->getDatabaseName();
            $query = "
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                AND TABLE_NAME = " . $pdo->quote($tableName) . "
                LIMIT 1
            ";
            
            $result = $pdo->query($query);
            if ($result === false) {
                return false;
            }
            return $result->rowCount() > 0;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get table columns
     * 
     * @param string $tableName
     * @return array<int, string>
     */
    public function getTableColumns(string $tableName): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }
            
            $result = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $tableName) . "`");
            if ($result === false) {
                return [];
            }
            $columns = [];
            while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (is_array($row) && isset($row['Field']) && is_string($row['Field'])) {
                    $columns[] = $row['Field'];
                }
            }
            return $columns;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get all table data
     * 
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     */
    public function getTableData(string $tableName): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }
            
            $result = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $tableName) . "`");
            if ($result === false) {
                return [];
            }
            $data = $result->fetchAll(PDO::FETCH_ASSOC);
            // Ensure we return the correct type: array<int, array<string, mixed>>
            return array_values($data);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get CREATE TABLE statement
     * 
     * @param string $tableName
     * @return string
     */
    public function getCreateTableStatement(string $tableName): string
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return '';
            }
            
            $escapedTable = '`' . str_replace('`', '``', $tableName) . '`';
            $result = $pdo->query("SHOW CREATE TABLE $escapedTable");
            
            if ($result === false) {
                return '';
            }
            
            $row = $result->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !isset($row['Create Table'])) {
                return '';
            }
            
            $createTable = $row['Create Table'];
            return is_string($createTable) ? $createTable : '';
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return '';
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get PDO connection and connection interface for proper release
     * 
     * @return array{0: ?PDO, 1: ?\Gemvc\Database\Connection\Contracts\ConnectionInterface, 2: ?\Gemvc\Database\Connection\Contracts\ConnectionManagerInterface}
     */
    protected function getPdoConnectionWithManager(): array
    {
        try {
            $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
            // getConnection() returns ConnectionInterface, need to call getConnection() on it to get PDO
            $connection = $dbManager->getConnection();
            
            if ($connection === null) {
                $error = $dbManager->getError();
                $this->setError($error ?? 'Failed to get database connection');
                return [null, null, null];
            }
            
            // Get the actual PDO from the connection interface
            $pdo = $connection->getConnection();
            
            if ($pdo instanceof PDO) {
                return [$pdo, $connection, $dbManager];
            }
            
            $this->setError('Connection did not return a valid PDO instance');
            return [null, null, null];
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [null, null, null];
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return [null, null, null];
        }
    }

    /**
     * Get PDO connection (backward compatibility)
     * 
     * @return ?PDO
     */
    protected function getPdoConnection(): ?PDO
    {
        [$pdo] = $this->getPdoConnectionWithManager();
        return $pdo;
    }

    /**
     * Release database connection
     * 
     * @param \Gemvc\Database\Connection\Contracts\ConnectionInterface|null $connection
     * @param \Gemvc\Database\Connection\Contracts\ConnectionManagerInterface|null $dbManager
     * @return void
     */
    protected function releaseConnection(?\Gemvc\Database\Connection\Contracts\ConnectionInterface $connection, ?\Gemvc\Database\Connection\Contracts\ConnectionManagerInterface $dbManager): void
    {
        if ($connection !== null && $dbManager !== null) {
            try {
                $dbManager->releaseConnection($connection);
            } catch (\Throwable $e) {
                error_log('Error releasing connection: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create database if not exists
     * 
     * @return bool
     */
    public function createDatabase(): bool
    {
        try {
            ProjectHelper::loadEnv();
            
            // Get database configuration
            // Use DB_HOST for web API (Docker service name), fallback to DB_HOST_CLI_DEV for CLI
            $dbHost = (isset($_ENV['DB_HOST']) && is_string($_ENV['DB_HOST']) && !empty($_ENV['DB_HOST']))
                ? $_ENV['DB_HOST']
                : (isset($_ENV['DB_HOST_CLI_DEV']) && is_string($_ENV['DB_HOST_CLI_DEV']) 
                    ? $_ENV['DB_HOST_CLI_DEV'] 
                    : 'localhost');
            $dbUser = isset($_ENV['DB_USER']) && is_string($_ENV['DB_USER']) 
                ? $_ENV['DB_USER'] 
                : 'root';
            $dbPass = isset($_ENV['DB_PASSWORD']) && is_string($_ENV['DB_PASSWORD']) 
                ? $_ENV['DB_PASSWORD'] 
                : '';
            $dbPort = isset($_ENV['DB_PORT']) && is_string($_ENV['DB_PORT']) 
                ? $_ENV['DB_PORT'] 
                : '3306';
            $dbCharset = isset($_ENV['DB_CHARSET']) && is_string($_ENV['DB_CHARSET']) 
                ? $_ENV['DB_CHARSET'] 
                : 'utf8mb4';
            $dbName = isset($_ENV['DB_NAME']) && is_string($_ENV['DB_NAME']) 
                ? $_ENV['DB_NAME'] 
                : '';
            
            if (empty($dbName)) {
                $this->setError('Database name not found in environment variables');
                return false;
            }
            
            // Create connection without database name (as root)
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $dbHost,
                $dbPort,
                $dbCharset
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset}"
            ];
            
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            
            // Create database if not exists
            $sql = "CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "`";
            $pdo->exec($sql);
            
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Get all API services with their endpoints (including hidden ones for developer panel)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getAllServices(): array
    {
        try {
            $apiPath = ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'api';
            if (!is_dir($apiPath)) {
                return [];
            }

            // Scan API files directly to include hidden services/endpoints
            $services = [];
            $files = $this->scanApiDirectory($apiPath);
            
            foreach ($files as $filePath) {
                $className = $this->getClassNameFromFile($filePath);
                if (!$className) {
                    continue;
                }
                
                try {
                    if (!class_exists($className)) {
                        continue;
                    }
                    /** @var \ReflectionClass<object> $reflection */
                    $reflection = new \ReflectionClass($className);
                    
                    // Check if service is hidden
                    $serviceHidden = $this->isServiceHidden($reflection);
                    
                    // Get endpoint name
                    $serviceName = lcfirst($reflection->getShortName());
                    
                    // Get all endpoints (including hidden ones)
                    $endpoints = $this->getAllEndpoints($reflection);
                    
                    if (!empty($endpoints)) {
                        $services[] = [
                            'name' => $serviceName,
                            'className' => $reflection->getShortName(),
                            'description' => $this->getClassDocComment($reflection),
                            'endpoints' => $endpoints,
                            'endpointCount' => count($endpoints),
                            'hidden' => $serviceHidden
                        ];
                    }
                } catch (\ReflectionException $e) {
                    // Skip if class can't be reflected
                    continue;
                }
            }
            
            return $services;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return [];
        }
    }

    /**
     * Scan API directory for PHP files
     * 
     * @param string $apiPath
     * @return array<int, string>
     */
    private function scanApiDirectory(string $apiPath): array
    {
        try {
            $files = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($apiPath)
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }

            return $files;
        } catch (\UnexpectedValueException $e) {
            return [];
        }
    }

    /**
     * Get class name from file path
     * 
     * @param string $filePath
     * @return string|null
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = (string)file_get_contents($filePath);
        
        $matches = [];
        if (!preg_match('/namespace\s+(.+?);/s', $content, $matches)) {
            return null;
        }

        $namespace = $matches[1];
        $className = basename($filePath, '.php');
        return $namespace . '\\' . $className;
    }

    /**
     * Check if service is hidden
     * 
     * @param \ReflectionClass<object> $reflection
     */
    private function isServiceHidden(\ReflectionClass $reflection): bool
    {
        $docComment = $reflection->getDocComment();
        if ($docComment === false) {
            return false;
        }
        return str_contains($docComment, '@hidden');
    }

    /**
     * Get all endpoints including hidden ones
     * 
     * @param \ReflectionClass<object> $reflection
     * @return array<int, array<string, mixed>>
     */
    private function getAllEndpoints(\ReflectionClass $reflection): array
    {
        $endpoints = [];
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        // Use ApiDocGenerator to get endpoint details
        $generator = new \Gemvc\Core\ApiDocGenerator();
        
        foreach ($methods as $method) {
            if ($method->getName() === '__construct' || $method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            
            // Check if endpoint is hidden
            $docComment = $method->getDocComment();
            $endpointHidden = ($docComment !== false && str_contains($docComment, '@hidden'));
            
            // Get endpoint details using reflection
            $methodDetails = $this->getEndpointDetails($method, $reflection);
            
            $endpoints[] = [
                'name' => $method->getName(),
                'method' => $methodDetails['method'] ?? 'GET',
                'url' => $methodDetails['url'] ?? '',
                'description' => $methodDetails['description'] ?? '',
                'parameters' => $methodDetails['parameters'] ?? [],
                'get_parameters' => $methodDetails['get_parameters'] ?? [],
                'hidden' => $endpointHidden
            ];
        }
        
        return $endpoints;
    }

    /**
     * Get endpoint details from method reflection
     * 
     * @param \ReflectionMethod $method
     * @param \ReflectionClass<object> $class
     * @return array<string, mixed>
     */
    private function getEndpointDetails(\ReflectionMethod $method, \ReflectionClass $class): array
    {
        $details = [
            'method' => $this->getHttpMethodFromDoc($method),
            'url' => $this->getEndpointUrl($class->getShortName(), $method->getName()),
            'description' => $this->getMethodDocComment($method) ?: 'No description available'
        ];
        
        // Get method file content
        $methodFile = $method->getFileName();
        if ($methodFile !== false) {
            $content = (string)file_get_contents($methodFile);
            
            // Get the method's content
            $lines = explode("\n", $content);
            $methodContent = implode("\n", array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1
            ));
            
            // Get validation rules from definePostSchema
            if (preg_match('/definePostSchema\(\s*\[\s*(.*?)\s*\]\s*\)/s', $methodContent, $matches)) {
                $details['parameters'] = $this->parseValidationRules($matches[1]);
            }
            
            // Get validation rules from defineGetSchema
            if (preg_match('/defineGetSchema\(\s*\[\s*(.*?)\s*\]\s*\)/s', $methodContent, $matches)) {
                $details['get_parameters'] = $this->parseValidationRules($matches[1]);
            }
        }
        
        return $details;
    }

    /**
     * Get HTTP method from doc comment
     * 
     * @param \ReflectionMethod $method
     * @return string
     */
    private function getHttpMethodFromDoc(\ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        if ($docComment !== false && preg_match('/@http\s+(GET|POST|PUT|DELETE|PATCH)/i', $docComment, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'POST';
    }

    /**
     * Get endpoint URL
     * 
     * @param string $className
     * @param string $methodName
     * @return string
     */
    private function getEndpointUrl(string $className, string $methodName): string
    {
        $baseUrl = lcfirst($className);
        return "/{$baseUrl}/{$methodName}";
    }

    /**
     * Get method doc comment
     * 
     * @param \ReflectionMethod $method
     * @return string
     */
    private function getMethodDocComment(\ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        if ($docComment === false) {
            return '';
        }
        
        // Extract description (first line after /** or lines before @)
        $lines = explode("\n", $docComment);
        $description = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '/**') || str_starts_with($line, '*') || str_starts_with($line, '*/')) {
                $line = trim($line, '/* ');
                if (!empty($line) && !str_starts_with($line, '@')) {
                    $description .= $line . ' ';
                }
            }
        }
        
        return trim($description);
    }

    /**
     * Get class doc comment
     * 
     * @param \ReflectionClass<object> $reflection
     */
    private function getClassDocComment(\ReflectionClass $reflection): string
    {
        $docComment = $reflection->getDocComment();
        if ($docComment === false) {
            return '';
        }
        
        // Extract description
        $lines = explode("\n", $docComment);
        $description = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '/**') || str_starts_with($line, '*') || str_starts_with($line, '*/')) {
                $line = trim($line, '/* ');
                if (!empty($line) && !str_starts_with($line, '@')) {
                    $description .= $line . ' ';
                }
            }
        }
        
        return trim($description);
    }

    /**
     * Parse validation rules
     * 
     * @param string $rules
     * @return array<string, array{type: string, required: bool}>
     */
    private function parseValidationRules(string $rules): array
    {
        $parameters = [];
        // Simple parsing - look for 'key' => 'type' patterns
        if (preg_match_all("/['\"]([^'\"]+)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $rules, $matches)) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $parameters[$matches[1][$i]] = [
                    'type' => $matches[2][$i],
                    'required' => true // Default to required
                ];
            }
        }
        return $parameters;
    }

    /**
     * Get table name from class using reflection (without instantiating)
     * 
     * @param \ReflectionClass<object> $reflection
     * @param string $className
     * @return string|null
     */
    private function getTableNameFromClass(\ReflectionClass $reflection, string $className): ?string
    {
        try {
            // Try to get table name from getTable() method using reflection
            if ($reflection->hasMethod('getTable')) {
                $method = $reflection->getMethod('getTable');
                if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                    // Try to read the method body to extract table name
                    $file = $reflection->getFileName();
                    if ($file !== false) {
                        $content = (string)file_get_contents($file);
                        $lines = explode("\n", $content);
                        $startLine = $method->getStartLine() - 1;
                        $endLine = $method->getEndLine();
                        
                        // Extract method body (lines between start and end)
                        $methodLines = array_slice($lines, $startLine, $endLine - $startLine);
                        $methodBody = implode("\n", $methodLines);
                        
                        // Try multiple patterns to find return statement with table name
                        // Pattern 1: return 'tablename';
                        if (preg_match("/return\s+['\"]([^'\"]+)['\"]\s*;/", $methodBody, $matches)) {
                            return $matches[1];
                        }
                        // Pattern 2: return "tablename";
                        if (preg_match('/return\s+["\']([^"\']+)["\']\s*;/', $methodBody, $matches)) {
                            return $matches[1];
                        }
                        // Pattern 3: return 'tablename' (with comments)
                        if (preg_match("/return\s+['\"]([^'\"]+)['\"]/", $methodBody, $matches)) {
                            return $matches[1];
                        }
                        // Pattern 4: Look for return in any line of the method
                        foreach ($methodLines as $line) {
                            $line = trim($line);
                            if (preg_match("/return\s+['\"]([^'\"]+)['\"]/", $line, $matches)) {
                                return $matches[1];
                            }
                        }
                    }
                }
            }
            
            // Fallback: try to infer from class name
            $shortName = $reflection->getShortName();
            if (str_ends_with($shortName, 'Table')) {
                // substr with negative offset always returns string (never false)
                $baseName = substr($shortName, 0, -5); // Remove "Table" suffix
                if ($baseName === '') {
                    return null;
                }
                // Convert PascalCase to snake_case and pluralize
                $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $baseName);
                if ($replaced === null) {
                    return null;
                }
                $tableName = strtolower($replaced) . 's';
                return $tableName;
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if service exists
     * 
     * @param string $serviceName
     * @return bool
     */
    public function serviceExists(string $serviceName): bool
    {
        $apiPath = ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'api';
        $serviceFile = $apiPath . DIRECTORY_SEPARATOR . ucfirst($serviceName) . '.php';
        return file_exists($serviceFile);
    }

    /**
     * Get all Table Layer classes with migration status
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getAllTableClasses(): array
    {
        try {
            $tablePath = ProjectHelper::appDir() . DIRECTORY_SEPARATOR . 'table';
            if (!is_dir($tablePath)) {
                $this->setError("Table directory not found: {$tablePath} (project root: " . ProjectHelper::rootDir() . ')');
                return [];
            }

            [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
            try {
                if ($pdo === null) {
                    $this->setError("Database connection failed");
                    return [];
                }

                $dbName = isset($_ENV['DB_NAME']) && is_string($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
                if (empty($dbName)) {
                    $this->setError("Database name not configured");
                    return [];
                }
                
                $tableClasses = [];
                $files = $this->scanApiDirectory($tablePath); // Reuse the scan method
                
                if (empty($files)) {
                    $this->setError("No PHP files found in {$tablePath}");
                    return [];
                }
                
                foreach ($files as $filePath) {
                    try {
                        $className = $this->getClassNameFromFile($filePath);
                        if (!$className) {
                            continue;
                        }
                        
                        // Check if class name ends with Table
                        if (!str_ends_with($className, 'Table')) {
                            continue;
                        }
                        
                        // Skip DeveloperTable itself (check before requiring) - FIXED: use static::class
                        if ($className === static::class) {
                            continue;
                        }
                        
                        // Load the file
                        try {
                            require_once $filePath;
                        } catch (\Throwable $e) {
                            error_log("Failed to require {$filePath}: " . $e->getMessage());
                            continue;
                        }
                        
                        if (!class_exists($className)) {
                            continue;
                        }
                        
                        // Use reflection - safe because class_exists already validated
                        /** @var \ReflectionClass<object> $reflection */
                        $reflection = new \ReflectionClass($className);
                        
                        if (!$reflection->isSubclassOf('Gemvc\Database\Table')) {
                            continue;
                        }
                        
                        // Get table name using reflection to avoid database connection issues
                        $tableName = $this->getTableNameFromClass($reflection, $className);
                        if (empty($tableName)) {
                            // If we can't get table name from reflection, skip this table
                            error_log("Could not extract table name from {$className}");
                            continue;
                        }
                        
                        // Check if table exists in database
                        // Use INFORMATION_SCHEMA for more reliable table existence check
                        try {
                            // Use INFORMATION_SCHEMA for more reliable check
                            // This works regardless of which database is currently selected
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) 
                                FROM INFORMATION_SCHEMA.TABLES 
                                WHERE TABLE_SCHEMA = ? 
                                AND TABLE_NAME = ?
                            ");
                            $stmt->execute([$dbName, $tableName]);
                            $result = $stmt->fetch(\PDO::FETCH_NUM);
                            $isMigrated = false;
                            if (is_array($result) && isset($result[0])) {
                                $count = is_numeric($result[0]) ? (int)$result[0] : 0;
                                $isMigrated = $count > 0;
                            }
                            
                            // Get foreign key relationships
                            $foreignKeys = $isMigrated ? $this->getTableForeignKeys($pdo, $dbName, $tableName) : [];
                        } catch (\Exception $e) {
                            // If database query fails, assume not migrated
                            $isMigrated = false;
                            $foreignKeys = [];
                            error_log("Database query failed for {$tableName}: " . $e->getMessage());
                        }
                        
                        $tableClasses[] = [
                            'className' => $reflection->getShortName(),
                            'fullClassName' => $className,
                            'tableName' => $tableName,
                            'isMigrated' => $isMigrated,
                            'description' => $this->getClassDocComment($reflection),
                            'foreignKeys' => $foreignKeys
                        ];
                    } catch (\PDOException $e) {
                        // SQL errors - skip this file silently, it might be a connection issue
                        error_log("SQL error processing {$filePath}: " . $e->getMessage());
                        continue;
                    } catch (\Exception $e) {
                        // Skip this file and continue with others - don't set error that blocks everything
                        error_log("Error processing {$filePath}: " . $e->getMessage());
                        continue;
                    } catch (\Throwable $e) {
                        error_log("Error processing {$filePath}: " . $e->getMessage());
                        continue;
                    }
                }
                
                return $tableClasses;
            } finally {
                $this->releaseConnection($connection, $dbManager);
            }
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return [];
        }
    }

    /**
     * Get foreign key relationships for a table
     * 
     * @param PDO $pdo
     * @param string $dbName
     * @param string $tableName
     * @return array<int, array<string, mixed>>
     */
    private function getTableForeignKeys(PDO $pdo, string $dbName, string $tableName): array
    {
        try {
            $query = "
                SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$dbName, $tableName]);
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($foreignKeys as $fk) {
                $result[] = [
                    'constraint_name' => $fk['CONSTRAINT_NAME'] ?? '',
                    'column_name' => $fk['COLUMN_NAME'] ?? '',
                    'referenced_table' => $fk['REFERENCED_TABLE_NAME'] ?? '',
                    'referenced_column' => $fk['REFERENCED_COLUMN_NAME'] ?? ''
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all table relationships (for schema diagram)
     * 
     * @return array<string, mixed>
     */
    public function getAllTableRelationships(): array
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return [];
            }

            $dbName = isset($_ENV['DB_NAME']) && is_string($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES FROM `{$dbName}`");
            if ($stmt === false) {
                return [];
            }
            
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $relationships = [];
            foreach ($tables as $tableName) {
                $foreignKeys = $this->getTableForeignKeys($pdo, $dbName, $tableName);
                if (!empty($foreignKeys)) {
                    $relationships[$tableName] = $foreignKeys;
                }
            }
            
            return $relationships;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }
}
