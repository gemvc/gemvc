<?php

/**
 * Hyperf Framework Stub File for Development
 * 
 * This file provides type definitions for Hyperf framework classes
 * to improve IDE support and static analysis during development.
 * 
 * These are minimal stubs - real implementation is in vendor/hyperf/*
 */

namespace Hyperf\Di;

class Container implements \Psr\Container\ContainerInterface
{
    public function __construct(Definition\DefinitionSource $definitionSource) {}
    
    public function get(string $id): mixed {}
    
    public function has(string $id): bool {}
    
    public function set(string $id, mixed $value): void {}
}

namespace Hyperf\Di\Definition;

class DefinitionSource
{
    /** @param array<string, mixed> $definitions */
    public function __construct(array $definitions) {}
}

namespace Hyperf\Contract;

interface ConfigInterface
{
    /** @return mixed */
    public function get(string $key, mixed $default = null);
    
    public function has(string $key): bool;
    
    public function set(string $key, mixed $value): void;
}

interface StdoutLoggerInterface extends \Psr\Log\LoggerInterface
{
    // Inherits all methods from PSR LoggerInterface
}

namespace Hyperf\Config;

class Config implements \Hyperf\Contract\ConfigInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config = []) {}
    
    public function get(string $key, mixed $default = null): mixed {}
    
    public function has(string $key): bool {}
    
    public function set(string $key, mixed $value): void {}
}

namespace Hyperf\Event;

class ListenerProvider implements \Psr\EventDispatcher\ListenerProviderInterface
{
    public function __construct() {}
    
    /** @return iterable<callable> */
    public function getListenersForEvent(object $event): iterable {}
}

class EventDispatcher implements \Psr\EventDispatcher\EventDispatcherInterface
{
    public function __construct(
        \Psr\EventDispatcher\ListenerProviderInterface $listenerProvider,
        ?\Psr\Log\LoggerInterface $logger = null
    ) {}
    
    public function dispatch(object $event): object {}
}

namespace Hyperf\DbConnection\Pool;

class PoolFactory
{
    public function __construct(\Psr\Container\ContainerInterface $container) {}
    
    public function getPool(string $name): Pool {}
}

class Pool
{
    public function get(): \Hyperf\DbConnection\Connection {}
    
    public function release(\Hyperf\DbConnection\Connection $connection): void {}
}

namespace Hyperf\DbConnection;

class Connection
{
    public function getPdo(): \PDO {}
    
    public function release(): void {}
    
    public function beginTransaction(): bool {}
    
    public function commit(): bool {}
    
    public function rollBack(): bool {}
}

