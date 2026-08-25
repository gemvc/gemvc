<?php

declare(strict_types=1);

namespace Gemvc\Core\Documentation;

/**
 * Result of a fixture file lookup. Distinguishes missing vs invalid so inference does not hide a bad file.
 *
 * @phpstan-type Envelope array<string, mixed>
 */
final class ResponseExampleLoadResult
{
    /**
     * @param Envelope|null $data
     */
    private function __construct(
        public readonly bool $fileFound,
        public readonly ?array $data
    ) {
    }

    public static function missing(): self
    {
        return new self(false, null);
    }

    public static function invalid(): self
    {
        return new self(true, null);
    }

    /**
     * @param Envelope $data
     */
    public static function ok(array $data): self
    {
        return new self(true, $data);
    }

    public function isOk(): bool
    {
        return $this->fileFound && is_array($this->data);
    }
}
