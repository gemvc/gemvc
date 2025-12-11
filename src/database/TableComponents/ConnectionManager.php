<?php

declare(strict_types=1);

namespace Gemvc\Database\TableComponents;

use Gemvc\Database\PdoQuery;

/**
 * Connection Manager for Table Class
 * 
 * Handles PdoQuery lifecycle management, lazy loading, and error handling.
 * Extracted from Table class to follow Single Responsibility Principle.
 */
class ConnectionManager
{
    private ?PdoQuery $pdoQuery = null;
    private ?string $storedError = null;
    
    /**
     * Get or create PdoQuery instance (lazy loading)
     * 
     * Database connection is created only when this method is called.
     * Any stored error is transferred to the PdoQuery instance upon creation.
     * 
     * @return PdoQuery The PdoQuery instance
     */
    public function getPdoQuery(): PdoQuery
    {
        if ($this->pdoQuery === null) {
            $this->pdoQuery = new PdoQuery();
            // Transfer any stored error to the new PdoQuery instance
            if ($this->storedError !== null) {
                $this->pdoQuery->setError($this->storedError);
                $this->storedError = null;
            }
        }
        return $this->pdoQuery;
    }
    
    /**
     * Set error message - optimized to avoid unnecessary connection creation
     * 
     * If PdoQuery is already instantiated, sets error directly.
     * Otherwise, stores error until PdoQuery is created.
     * 
     * @param string|null $error Error message
     * @return void
     */
    public function setError(?string $error): void
    {
        if ($this->pdoQuery !== null) {
            $this->pdoQuery->setError($error);
        } else {
            // Store the error until PdoQuery is instantiated
            $this->storedError = $error;
        }
    }
    
    /**
     * Get error message
     * 
     * Returns error from PdoQuery if instantiated, otherwise returns stored error.
     * 
     * @return string|null Error message or null if no error
     */
    public function getError(): ?string
    {
        if ($this->pdoQuery !== null) {
            return $this->pdoQuery->getError();
        }
        return $this->storedError;
    }
    
    /**
     * Check if we have an active connection
     * 
     * @return bool True if PdoQuery is instantiated and connected
     */
    public function isConnected(): bool
    {
        return $this->pdoQuery !== null && $this->pdoQuery->isConnected();
    }
    
    /**
     * Disconnect and cleanup resources
     * 
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->pdoQuery !== null) {
            $this->pdoQuery->disconnect();
            $this->pdoQuery = null;
        }
        $this->storedError = null;
    }
    
    /**
     * Check if PdoQuery is instantiated
     * 
     * @return bool True if PdoQuery has been created
     */
    public function hasConnection(): bool
    {
        return $this->pdoQuery !== null;
    }
}

