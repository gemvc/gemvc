<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;

abstract class DatabaseTestCase extends TestCase
{
    protected ?PDO $pdo = null;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createTestDatabase();
        $this->migrateTestDatabase();
    }
    
    protected function tearDown(): void
    {
        $this->cleanupTestDatabase();
        parent::tearDown();
    }
    
    protected function createTestDatabase(): PDO
    {
        // Use in-memory SQLite for fast unit tests
        $dsn = 'sqlite::memory:';
        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            $this->fail('Failed to create test database: ' . $e->getMessage());
        }
    }
    
    protected function migrateTestDatabase(): void
    {
        // Override in child classes to run migrations
    }
    
    protected function cleanupTestDatabase(): void
    {
        if ($this->pdo !== null) {
            $this->pdo = null;
        }
    }
    
    /**
     * Check if a table exists in the database
     */
    protected function tableExists(string $tableName): bool
    {
        if ($this->pdo === null) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$tableName]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

