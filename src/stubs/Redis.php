<?php

class Redis
{
    public function connect(string $host, int $port, float $timeout = 0.0): bool {}
    public function pconnect(string $host, int $port, float $timeout = 0.0): bool {}
    public function auth(string $password): bool {}
    public function select(int $database): bool {}
    public function setOption(int $option, mixed $value): bool {}
    public function close(): bool {}
    public function setex(string $key, int $ttl, mixed $value): bool {}
    public function set(string $key, mixed $value): bool {}
    public function get(string $key): mixed {}
    public function del(string $key): int {}
    public function exists(string $key): bool|int {}
    public function incr(string $key): int|false {}
    public function expire(string $key, int $ttl): bool {}
    public function ttl(string $key): int {}
    public function flushDB(): bool {}
    public function hSet(string $key, string $field, mixed $value): int {}
    public function hGet(string $key, string $field): mixed {}
    /** @return array<string, mixed> */
    public function hGetAll(string $key): array {}
    public function lPush(string $key, mixed $value): int {}
    public function rPush(string $key, mixed $value): int {}
    public function lPop(string $key): mixed {}
    public function rPop(string $key): mixed {}
    public function sAdd(string $key, mixed $value): int {}
    /** @return array<string> */
    public function sMembers(string $key): array {}
    public function sIsMember(string $key, mixed $value): bool {}
    public function zAdd(string $key, float $score, mixed $value): int {}
    /** @return array<string> */
    public function zRange(string $key, int $start, int $end, bool $withScores = false): array {}
    public function publish(string $channel, string $message): int {}
    /** @param array<string> $channels */
    public function subscribe(array $channels, callable $callback): void {}
    public function multi(int $mode): bool|Redis {}
    
    public const PIPELINE = 1;
    public const MULTI = 2;
    public const OPT_PREFIX = 1;
    public const OPT_READ_TIMEOUT = 2;
}
