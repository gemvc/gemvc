<?php
/**
 * Developer Model Layer
 * 
 * Business logic for developer tools (authentication, data preparation, export/import)
 * This is the Model layer - business logic and data transformations
 */
namespace App\Model;

use App\Table\DeveloperTable;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\JWTToken;
use Gemvc\Http\Response;

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
        $databaseReady = $this->isDatabaseReady();
        $tables = [];
        $tableStructure = null;
        $tableRelationships = null;
        $errorMessage = null;
        
        if ($databaseReady) {
            $tables = $this->getAllTables();
            
            if ($selectedTable && $this->tableExists($selectedTable)) {
                $tableStructure = $this->getTableStructure($selectedTable);
                $tableRelationships = $this->getTableRelationships($selectedTable);
            }
        } else {
            $errorMessage = $this->getError();
        }
        
        return [
            'databaseReady' => $databaseReady,
            'tables' => $tables,
            'selectedTable' => $selectedTable,
            'tableStructure' => $tableStructure,
            'tableRelationships' => $tableRelationships,
            'errorMessage' => $errorMessage,
        ];
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
        
        // Get PDO connection
        $pdo = $this->getPdoConnection();
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
        
        $pdo = $this->getPdoConnection();
        if ($pdo === null) {
            return Response::internalError('Database connection failed');
        }
        
        // Execute SQL file
        $pdo->exec($fileContent);
        
        return Response::success([
            'message' => "Successfully executed SQL file for $tableName"
        ]);
    }
}

