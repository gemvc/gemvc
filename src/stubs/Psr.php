<?php

/**
 * PSR Interfaces Stub File for Development
 * 
 * This file provides type definitions for PSR standard interfaces
 * to improve IDE support and static analysis during development.
 * 
 * These are minimal stubs - real implementation is in vendor/psr/*
 */

namespace Psr\Container;

interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     * @return mixed Entry.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     *
     * @param string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;
}

namespace Psr\EventDispatcher;

interface EventDispatcherInterface
{
    /**
     * Provide all relevant listeners with an event to process.
     *
     * @param object $event The object to process.
     * @return object The Event that was passed, now modified by listeners.
     */
    public function dispatch(object $event): object;
}

interface ListenerProviderInterface
{
    /**
     * @param object $event An event for which to return the relevant listeners.
     * @return iterable<callable> An iterable (array, iterator, or generator) of callables.
     */
    public function getListenersForEvent(object $event): iterable;
}

interface StoppableEventInterface
{
    /**
     * Is propagation stopped?
     *
     * @return bool True if the Event is complete and no further listeners should be called.
     */
    public function isPropagationStopped(): bool;
}

namespace Psr\Log;

interface LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void;

    /**
     * Detailed debug information.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void;
}

