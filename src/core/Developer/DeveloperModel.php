<?php
/**
 * Developer Model Layer
 * 
 * Business logic for developer tools (authentication, data preparation, export/import)
 * This is the Model layer - business logic and data transformations
 */
namespace Gemvc\Core\Developer;

use Gemvc\Core\Developer\DeveloperTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\JWTToken;
use Gemvc\Http\Response;
use PDO;

class DeveloperModel extends DeveloperTable
{
    private const ADMIN_USER_ID = 1; // Fixed admin user ID for developer tools

    /**
     * Authenticate admin user
     * 
     * @param string $password
     * @return JsonResponse
     */
    public function authenticate(string $password): JsonResponse
    {
        // Get admin password from .env
        $adminPasswordEnv = isset($_ENV['ADMIN_PASSWORD']) && is_string($_ENV['ADMIN_PASSWORD'])
            ? $_ENV['ADMIN_PASSWORD']
            : '';
        $adminPassword = trim($adminPasswordEnv);
        
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
    }

    /**
     * Get welcome page data
     * 
     * @return array<string, mixed>
     */
    public function getWelcomeData(): array
    {
        $databaseReady = $this->isDatabaseReady();
        
        return [
            'databaseReady' => $databaseReady,
        ];
    }

    /**
     * Get database page data
     * 
     * @param string|null $selectedTable
     * @return array<string, mixed>
     */
    public function getDatabaseData(?string $selectedTable = null): array
    {
        // Use a single connection for all operations to prevent connection buildup
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            $databaseReady = false;
            $tables = [];
            $tableStructure = null;
            $tableRelationships = null;
            $errorMessage = null;
            
            if ($pdo !== null) {
                // Test connection
                $result = $pdo->query('SELECT 1');
                $databaseReady = ($result !== false);
                
                if ($databaseReady) {
                    // Get database name once
                    $dbNameResult = $pdo->query("SELECT DATABASE() as db_name");
                    $dbName = 'unknown';
                    if ($dbNameResult !== false) {
                        $row = $dbNameResult->fetch(PDO::FETCH_ASSOC);
                        if (is_array($row) && isset($row['db_name']) && is_string($row['db_name'])) {
                            $dbName = $row['db_name'];
                        }
                    }
                    
                    // Get all tables using the same connection
                    $query = "
                        SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COMMENT
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                        AND TABLE_TYPE = 'BASE TABLE'
                        ORDER BY TABLE_NAME
                    ";
                    $result = $pdo->query($query);
                    if ($result !== false) {
                        $tables = array_values($result->fetchAll(PDO::FETCH_ASSOC));
                    }
                    
                    // If table is selected, get its structure and relationships using same connection
                    if ($selectedTable !== null && $selectedTable !== '') {
                        // Check if table exists
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM INFORMATION_SCHEMA.TABLES 
                            WHERE TABLE_SCHEMA = ? 
                            AND TABLE_NAME = ?
                        ");
                        $stmt->execute([$dbName, $selectedTable]);
                        $tableExistsResult = $stmt->fetch(PDO::FETCH_NUM);
                        $tableExists = false;
                        if (is_array($tableExistsResult) && isset($tableExistsResult[0])) {
                            $count = is_numeric($tableExistsResult[0]) ? (int)$tableExistsResult[0] : 0;
                            $tableExists = $count > 0;
                        }
                        
                        if ($tableExists) {
                            // Get table structure
                            $structureQuery = "
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
                                AND TABLE_NAME = " . $pdo->quote($selectedTable) . "
                                ORDER BY ORDINAL_POSITION
                            ";
                            $structureResult = $pdo->query($structureQuery);
                            if ($structureResult !== false) {
                                $tableStructure = array_values($structureResult->fetchAll(PDO::FETCH_ASSOC));
                            }
                            
                            // Get table relationships
                            $fkQuery = "
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
                                    AND kcu.TABLE_NAME = " . $pdo->quote($selectedTable) . "
                                    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                            ";
                            $fkResult = $pdo->query($fkQuery);
                            if ($fkResult !== false) {
                                $tableRelationships = array_values($fkResult->fetchAll(PDO::FETCH_ASSOC));
                            }
                        } else {
                            $errorMessage = "Table \"{$selectedTable}\" not found or could not be accessed.";
                        }
                    }
                } else {
                    $errorMessage = $this->getError() ?: 'Database connection failed';
                }
            } else {
                $errorMessage = $this->getError() ?: 'Database connection failed';
            }
            
            return [
                'databaseReady' => $databaseReady,
                'tables' => $tables,
                'selectedTable' => $selectedTable,
                'tableStructure' => $tableStructure,
                'tableRelationships' => $tableRelationships,
                'errorMessage' => $errorMessage,
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            return [
                'databaseReady' => false,
                'tables' => [],
                'selectedTable' => $selectedTable,
                'tableStructure' => null,
                'tableRelationships' => null,
                'errorMessage' => $errorMessage,
            ];
        } finally {
            // Always release the single connection
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Export table data as CSV
     * 
     * @param string $tableName
     * @return array<string, mixed> ['content' => string, 'filename' => string]
     */
    public function exportTableAsCsv(string $tableName): array
    {
        if (!$this->tableExists($tableName)) {
            $this->setError("Table '$tableName' does not exist");
            return ['content' => '', 'filename' => ''];
        }
        
        $columns = $this->getTableColumns($tableName);
        $rows = $this->getTableData($tableName);
        
        // Generate CSV content
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            $this->setError('Failed to create temporary file');
            return ['content' => '', 'filename' => ''];
        }
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
        if (!empty($columns)) {
            fputcsv($output, $columns);
        }
        foreach ($rows as $row) {
            $rowValues = array_values($row);
            // Ensure all values are scalar/null for fputcsv
            $rowValues = array_map(function($value) {
                if (is_scalar($value) || $value === null) {
                    return $value;
                }
                // Convert non-scalar to string safely
                if (is_object($value) && method_exists($value, '__toString')) {
                    return $value->__toString();
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }, $rowValues);
            fputcsv($output, $rowValues);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        $content = is_string($content) ? $content : '';
        
        $filename = $tableName . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        return [
            'content' => $content,
            'filename' => $filename,
        ];
    }

    /**
     * Export table structure as SQL
     * 
     * @param string $tableName
     * @return array<string, mixed> ['content' => string, 'filename' => string]
     */
    public function exportTableAsSql(string $tableName): array
    {
        if (!$this->tableExists($tableName)) {
            $this->setError("Table '$tableName' does not exist");
            return ['content' => '', 'filename' => ''];
        }
        
        $createTableSql = $this->getCreateTableStatement($tableName);
        
        if (empty($createTableSql)) {
            $this->setError('Failed to get table structure');
            return ['content' => '', 'filename' => ''];
        }
        
        $createTableSql = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $createTableSql);
        
        $sql = "-- Table Structure: $tableName\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= $createTableSql . ";\n";
        
        $filename = $tableName . '_' . date('Y-m-d_H-i-s') . '.sql';
        
        return [
            'content' => $sql,
            'filename' => $filename,
        ];
    }

    /**
     * Import CSV data into table
     * 
     * @param string $tableName
     * @param string $filePath
     * @return JsonResponse
     */
    public function importCsv(string $tableName, string $filePath): JsonResponse
    {
        if (!$this->tableExists($tableName)) {
            return Response::badRequest("Table '$tableName' does not exist");
        }
        
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return Response::badRequest('Failed to read CSV file');
        }
        
        // Parse CSV - split by newlines first
        $lines = explode("\n", $fileContent);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        
        if (count($lines) === 0) {
            return Response::badRequest('CSV file is empty');
        }
        
        // Get headers from first line
        // After count check, we know $lines is non-empty, so array_shift returns string
        /** @var string $firstLine */
        $firstLine = array_shift($lines);
        $headers = str_getcsv($firstLine);
        // str_getcsv returns array<string|null>, normalize to array<string>
        $headers = array_map(function($header): string {
            return trim($header ?? '');
        }, $headers);
        
        // Get PDO connection with manager for proper release
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return Response::internalError('Database connection failed');
            }
            
            // Prepare INSERT statement
            $placeholders = '(' . implode(', ', array_fill(0, count($headers), '?')) . ')';
            $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $headers) . "`) VALUES $placeholders";
            $stmt = $pdo->prepare($sql);
            
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
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Import SQL file
     * 
     * @param string $tableName
     * @param string $filePath
     * @return JsonResponse
     */
    public function importSql(string $tableName, string $filePath): JsonResponse
    {
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return Response::badRequest('Failed to read SQL file');
        }
        
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        try {
            if ($pdo === null) {
                return Response::internalError('Database connection failed');
            }
            
            // Execute SQL file
            $pdo->exec($fileContent);
            
            return Response::success([
                'message' => "Successfully executed SQL file for $tableName"
            ]);
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Check if database is ready
     * 
     * @return JsonResponse
     */
    public function isDbReady(): JsonResponse
    {
        $isReady = $this->isDatabaseReady();
        
        if ($isReady) {
            return Response::success([
                'databaseReady' => true,
                'message' => 'Database is connected and accessible'
            ]);
        }
        
        // Get error message if available
        $errorMessage = $this->getError();
        $errorData = [
            'databaseReady' => false,
            'message' => 'Database connection failed'
        ];
        
        if (!empty($errorMessage)) {
            $errorData['error'] = $errorMessage;
            // Use Response::unknownError to return data (returns code 0, but accepts data)
            return Response::unknownError($errorData, 'Database connection failed: ' . $errorMessage);
        }
        
        // Generic error if no specific error message
        $errorData['message'] = 'Database is not accessible';
        return Response::unknownError($errorData, 'Database is not accessible');
    }

    /**
     * Initialize database
     * 
     * @return JsonResponse
     */
    public function initDatabase(): JsonResponse
    {
        // Call Table layer method to create database
        $success = $this->createDatabase();
        
        if ($success) {
            $dbName = isset($_ENV['DB_NAME']) && is_string($_ENV['DB_NAME']) 
                ? $_ENV['DB_NAME'] 
                : 'database';
            return Response::success([
                'message' => "Database '{$dbName}' initialized successfully",
                'databaseName' => $dbName
            ]);
        }
        
        // Get error message if available
        $errorMessage = $this->getError();
        $errorData = [
            'message' => 'Failed to initialize database'
        ];
        
        if (!empty($errorMessage)) {
            $errorData['error'] = $errorMessage;
            return Response::unknownError($errorData, 'Database initialization failed: ' . $errorMessage);
        }
        
        // Generic error
        return Response::unknownError($errorData, 'Database initialization failed');
    }

    /**
     * Get services page data
     * 
     * @return array<string, mixed>
     */
    public function getServicesData(): array
    {
        $services = $this->getAllServices();
        
        return [
            'services' => $services,
            'totalServices' => count($services)
        ];
    }

    /**
     * Create a new service
     * 
     * @param string $serviceName
     * @param string $type Type: 'crud', 'service', 'service-controller', 'service-model'
     * @return JsonResponse
     */
    public function createService(string $serviceName, string $type): JsonResponse
    {
        // Validate service name
        if (empty(trim($serviceName))) {
            return Response::badRequest('Service name is required');
        }
        
        // Check if service already exists
        if ($this->serviceExists($serviceName)) {
            return Response::badRequest("Service '{$serviceName}' already exists");
        }
        
        try {
            $basePath = \Gemvc\Helper\ProjectHelper::rootDir();
            $gemvcPath = $basePath . '/vendor/bin/gemvc';
            
            // Build command based on type
            $command = '';
            $message = '';
            
            switch ($type) {
                case 'crud':
                    $command = escapeshellarg($gemvcPath) . ' create:crud ' . escapeshellarg($serviceName);
                    $message = "Full CRUD for {$serviceName} created successfully";
                    break;
                    
                case 'service':
                    $command = escapeshellarg($gemvcPath) . ' create:service ' . escapeshellarg($serviceName);
                    $message = "Service {$serviceName} created successfully";
                    break;
                    
                case 'service-controller':
                    $command = escapeshellarg($gemvcPath) . ' create:service ' . escapeshellarg($serviceName) . ' -c';
                    $message = "Service and Controller for {$serviceName} created successfully";
                    break;
                    
                case 'service-model':
                    $command = escapeshellarg($gemvcPath) . ' create:service ' . escapeshellarg($serviceName) . ' -m';
                    $message = "Service and Model for {$serviceName} created successfully";
                    break;
                    
                default:
                    return Response::badRequest("Invalid service type: {$type}");
            }
            
            // Change to project directory and execute command
            $oldCwd = getcwd();
            if ($oldCwd !== false) {
                chdir($basePath);
            }
            
            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);
            
            // Restore directory
            if ($oldCwd !== false) {
                chdir($oldCwd);
            }
            
            $outputStr = implode("\n", $output);
            
            if ($returnVar === 0) {
                // Verify service was created (check after creation, so it may now exist)
                /** @var bool $serviceCreated */
                $serviceCreated = $this->serviceExists($serviceName);
                if ($serviceCreated) {
                    return Response::success([
                        'message' => $message,
                        'serviceName' => $serviceName,
                        'type' => $type,
                        'output' => $outputStr
                    ]);
                } else {
                    return Response::unknownError([
                        'message' => 'Service creation may have failed',
                        'output' => $outputStr
                    ], 'Service creation command succeeded but service file not found');
                }
            }
            
            return Response::unknownError([
                'message' => 'Failed to create service',
                'error' => $outputStr,
                'exitCode' => $returnVar
            ], 'Service creation failed');
            
        } catch (\Exception $e) {
            return Response::unknownError([
                'message' => 'Failed to create service',
                'error' => $e->getMessage()
            ], 'Service creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get tables page data
     * 
     * @return array<string, mixed>
     */
    public function getTablesData(): array
    {
        $tableClasses = $this->getAllTableClasses();
        $relationships = $this->getAllTableRelationships();
        
        // Don't pass errors to template - they're logged via error_log
        // Only show errors if it's a critical issue (like no directory found)
        $error = $this->getError();
        $showError = false;
        if (!empty($error) && (str_contains($error, 'directory not found') || str_contains($error, 'Database connection failed') || str_contains($error, 'Database name not configured'))) {
            $showError = true;
        }
        
        return [
            'tableClasses' => $tableClasses,
            'totalTables' => count($tableClasses),
            'relationships' => $relationships,
            'error' => $showError ? $error : null // Only show critical errors
        ];
    }

    /**
     * Migrate or update a table
     * 
     * @param string $tableClassName
     * @return JsonResponse
     */
    public function migrateTable(string $tableClassName): JsonResponse
    {
        if (empty(trim($tableClassName))) {
            return Response::badRequest('Table class name is required');
        }
        
        try {
            // Load environment
            \Gemvc\Helper\ProjectHelper::loadEnv();
            
            // For Docker/web environment, override DB_HOST_CLI_DEV with DB_HOST
            // The DbConnect uses DB_HOST_CLI_DEV, but in Docker we need DB_HOST (service name)
            $dbHostWeb = isset($_ENV['DB_HOST']) && is_string($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : null;
            
            // If we have DB_HOST and it's not localhost, we're likely in Docker/web environment
            // In this case, use DB_HOST for the connection too
            if ($dbHostWeb !== null && $dbHostWeb !== 'localhost' && $dbHostWeb !== '127.0.0.1') {
                $_ENV['DB_HOST_CLI_DEV'] = $dbHostWeb;
                putenv('DB_HOST_CLI_DEV=' . $dbHostWeb);
            }
            
            // Get database connection using DatabaseManagerFactory (same as DeveloperTable)
            // This avoids DbConnect::connect() which calls exit() on error
            $dbManager = null;
            $connection = null;
            try {
                $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
                $connection = $dbManager->getConnection();
                
                if ($connection === null) {
                    return Response::unknownError([
                        'message' => 'Failed to connect to database',
                        'error' => $dbManager->getError() ?? 'Database connection failed'
                    ], 'Migration failed');
                }
                
                $pdo = $connection->getConnection();
                if (!($pdo instanceof \PDO)) {
                    return Response::unknownError([
                        'message' => 'Failed to get PDO connection',
                        'error' => 'Connection did not return a valid PDO instance'
                    ], 'Migration failed');
                }
                
                // Load table class
                $tableFile = \Gemvc\Helper\ProjectHelper::rootDir() . '/app/table/' . $tableClassName . '.php';
                if (!file_exists($tableFile)) {
                    return Response::badRequest("Table file not found: {$tableClassName}.php");
                }
                
                require_once $tableFile;
                // Extract class name from file (namespace-aware) - FIXED: dynamic namespace extraction
                $className = $this->getClassNameFromFile($tableFile);
                if (!$className || !class_exists($className)) {
                    return Response::badRequest("Table class not found: {$tableClassName}");
                }
                
                // Create table instance
                $table = new $className();
                if (!($table instanceof \Gemvc\Database\Table)) {
                    return Response::badRequest("Invalid table class: {$className}");
                }
                $generator = new \Gemvc\Database\TableGenerator($pdo);
                
                // Check if table exists using INFORMATION_SCHEMA (more reliable)
                $tableName = $table->getTable();
                $dbName = isset($_ENV['DB_NAME']) && is_string($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : '';
                if (empty($dbName)) {
                    return Response::unknownError([
                        'message' => 'Database name not configured',
                        'error' => 'DB_NAME environment variable is not set'
                    ], 'Migration failed');
                }
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ?
                ");
                $stmt->execute([$dbName, $tableName]);
                $result = $stmt->fetch(\PDO::FETCH_NUM);
                $tableExists = false;
                if (is_array($result) && isset($result[0])) {
                    $count = is_numeric($result[0]) ? (int)$result[0] : 0;
                    $tableExists = $count > 0;
                }
                
                if ($tableExists) {
                    // Update existing table
                    if ($generator->updateTable($table, null, false, false, null)) {
                        // Apply schema constraints
                        $this->applySchemaConstraints($pdo, $table, $tableName);
                        
                        return Response::success([
                            'message' => "Table '{$tableName}' synchronized successfully",
                            'tableClassName' => $tableClassName,
                            'tableName' => $tableName
                        ]);
                    } else {
                        $error = $generator->getError();
                        return Response::unknownError([
                            'message' => 'Failed to sync table',
                            'error' => $error ?: 'Unknown error'
                        ], 'Migration failed');
                    }
                } else {
                    // Create new table
                    if ($generator->createTableFromObject($table)) {
                        // Apply schema constraints
                        $this->applySchemaConstraints($pdo, $table, $tableName);
                        
                        return Response::success([
                            'message' => "Table '{$tableName}' created successfully",
                            'tableClassName' => $tableClassName,
                            'tableName' => $tableName
                        ]);
                    } else {
                        $error = $generator->getError();
                        return Response::unknownError([
                            'message' => 'Failed to create table',
                            'error' => $error ?: 'Unknown error'
                        ], 'Migration failed');
                    }
                }
            } catch (\Exception $e) {
                return Response::unknownError([
                    'message' => 'Failed to connect to database',
                    'error' => $e->getMessage()
                ], 'Migration failed');
            } finally {
                // Always release connection
                if ($connection !== null && $dbManager !== null) {
                    try {
                        $dbManager->releaseConnection($connection);
                    } catch (\Throwable $e) {
                        error_log('Error releasing connection in migrateTable: ' . $e->getMessage());
                    }
                }
            }
            
        } catch (\Exception $e) {
            return Response::unknownError([
                'message' => 'Failed to migrate table',
                'error' => $e->getMessage()
            ], 'Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply schema constraints using the SchemaGenerator
     * 
     * @param \PDO $pdo Database connection
     * @param object $table Table instance
     * @param string $tableName Table name
     * @return bool
     */
    private function applySchemaConstraints(\PDO $pdo, object $table, string $tableName): bool
    {
        // Check if table has schema constraints defined
        if (!method_exists($table, 'defineSchema')) {
            return false;
        }

        try {
            $schemaDefinition = $table->defineSchema();
            
            if (empty($schemaDefinition)) {
                return false;
            }

            // Create SchemaGenerator instance
            $schemaGenerator = new \Gemvc\Database\SchemaGenerator($pdo, $tableName, $schemaDefinition);
            
            // Apply constraints
            return $schemaGenerator->applyConstraints(false);
        } catch (\Exception $e) {
            // Schema application failed, but table migration succeeded
            error_log("Schema constraints failed for {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    public function getDevAssistantUrl(): string
    {
        $port = isset($_ENV['APP_ENV_PUBLIC_SERVER_PORT']) && is_string($_ENV['APP_ENV_PUBLIC_SERVER_PORT']) ? $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] : '80';
        $subUrl = isset($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) && is_string($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) ? $_ENV['APP_ENV_API_DEFAULT_SUB_URL'] : '';
        $url = 'http://localhost';
        if($port !== '80') {
            $url = 'http://localhost:' . $port;
        }
        if($subUrl !== '') {
            $url .= '/' . $subUrl;
        }
        $url .= '/index/developer';
        return $url;
    }

    public function getDocumentationUrl(): string
    {
        $port = isset($_ENV['APP_ENV_PUBLIC_SERVER_PORT']) && is_string($_ENV['APP_ENV_PUBLIC_SERVER_PORT']) ? $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] : '80';
        $subUrl = isset($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) && is_string($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) ? $_ENV['APP_ENV_API_DEFAULT_SUB_URL'] : '';
        $url = 'http://localhost';
        if($port !== '80') {
            $url = 'http://localhost:' . $port;
        }
        if($subUrl !== '') {
            $url .= '/' . $subUrl;
        }
        $url .= '/index/document';
        return $url;
    }

    /**
     * Get class name from file path (extracts namespace dynamically)
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
}   