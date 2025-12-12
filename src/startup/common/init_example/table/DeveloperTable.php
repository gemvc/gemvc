<?php
/**
 * Developer Table Layer
 * 
 * Handles database queries for developer tools (database metadata, table structure, etc.)
 * This is the Table layer - database operations only
 */
namespace App\Table;

use Gemvc\Database\Table;
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
        try {
            $pdo = $this->getPdoConnection();
            if ($pdo === null) {
                return false;
            }
            $pdo->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        try {
            $pdo = $this->getPdoConnection();
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
        }
    }

    /**
     * Get PDO connection
     * 
     * @return PDO|null
     */
    protected function getPdoConnection(): ?PDO
    {
        try {
            $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
            // getConnection() already returns ?PDO directly
            $pdo = $dbManager->getConnection();
            
            if ($pdo instanceof PDO) {
                return $pdo;
            }
            
            return null;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return null;
        }
    }
}

