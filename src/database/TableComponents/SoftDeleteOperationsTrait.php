<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

/**
 * Soft Delete Operations Trait for Table Class
 * 
 * Provides soft delete, restore, activate, and deactivate operations.
 * Extracted from Table class to follow Single Responsibility Principle.
 * Uses trait for optimal performance (zero delegation overhead, direct method calls).
 */
trait SoftDeleteOperationsTrait
{
    /**
     * Marks a record as deleted (soft delete)
     * 
     * @return static|null Current instance on success, null on error
     */
    public function safeDeleteQuery(): ?static
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue();
        
        if ($pkValue === null) {
            $this->setError("Primary key '{$pkColumn}' must be set for safe delete");
            return null;
        }
        
        if (!$this->validatePrimaryKey($pkValue, 'safe delete')) {
            return null;
        }
        
        if (!$this->validateProperties(['deleted_at'])) {
            $this->setError("For safe delete, deleted_at must exist in the Database table and object");
            return null;
        }
        
        $query = "UPDATE {$this->getTable()} SET deleted_at = NOW() WHERE {$pkColumn} = :{$pkColumn}";
        
        if (property_exists($this, 'is_active')) {
            $query = "UPDATE {$this->getTable()} SET deleted_at = NOW(), is_active = 0 WHERE {$pkColumn} = :{$pkColumn}";
        }
        
        $result = $this->getPdoQuery()->updateQuery($query, [":{$pkColumn}" => $pkValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Safe delete failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        if (property_exists($this, 'deleted_at')) {
            $this->deleted_at = date('Y-m-d H:i:s');
        }
        
        // Only set is_active if the property exists
        if (property_exists($this, 'is_active')) {
            $this->is_active = 0;
        }
        
        return $this;
    }

    /**
     * Restores a soft-deleted record
     * 
     * @return static|null Current instance on success, null on error
     */
    public function restoreQuery(): ?static
    {
        $pkColumn = $this->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue();
        
        if ($pkValue === null) {
            $this->setError("Primary key '{$pkColumn}' must be set for restore");
            return null;
        }
        
        if (!$this->validatePrimaryKey($pkValue, 'restore')) {
            return null;
        }
        
        if (!$this->validateProperties(['deleted_at'])) {
            $this->setError("For restore operation, deleted_at must exist in the Database table and object");
            return null;
        }
        
        $query = "UPDATE {$this->getTable()} SET deleted_at = NULL WHERE {$pkColumn} = :{$pkColumn}";
               
        $result = $this->getPdoQuery()->updateQuery($query, [":{$pkColumn}" => $pkValue]);
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Restore failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        if (property_exists($this, 'deleted_at')) {
            $this->deleted_at = null;
        }       
        return $this;
    }

    /**
     * Sets is_active to 1 (activate record)
     * 
     * @param int|string $id Record ID to activate
     * @return int|null Number of affected rows on success, null on error
     */
    public function activateQuery(int|string $id): ?int
    {
        if (!$this->validateProperties(['is_active'])) {
            $this->setError('is_active column is not present in the table');
            return null;
        }

        $pkColumn = $this->getPrimaryKeyColumn();
        
        if (!$this->validatePrimaryKey($id, 'activate')) {
            return null;
        }
        
        $result = $this->getPdoQuery()->updateQuery(
            "UPDATE {$this->getTable()} SET is_active = 1 WHERE {$pkColumn} = :{$pkColumn}", 
            [":{$pkColumn}" => $id]
        );
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Activate failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }

    /**
     * Sets is_active to 0 (deactivate record)
     * 
     * @param int|string $id Record ID to deactivate
     * @return int|null Number of affected rows on success, null on error
     */
    public function deactivateQuery(int|string $id): ?int
    {
        if (!$this->validateProperties(['is_active'])) {
            $this->setError('is_active column is not present in the table');
            return null;
        }

        $pkColumn = $this->getPrimaryKeyColumn();
        
        if (!$this->validatePrimaryKey($id, 'deactivate')) {
            return null;
        }
        
        $result = $this->getPdoQuery()->updateQuery(
            "UPDATE {$this->getTable()} SET is_active = 0 WHERE {$pkColumn} = :{$pkColumn}", 
            [":{$pkColumn}" => $id]
        );
        
        if ($result === null) {
            $currentError = $this->getError();
            $this->setError("Deactivate failed in {$this->getTable()}: {$currentError}");
            return null;
        }
        
        return $result;
    }
}

