<?php

declare(strict_types=1);

namespace Gemvc\Database;

use RuntimeException;

/**
 * Thrown when row write APIs are invoked on a {@see ViewTable}.
 * Prefer setError + null return from overridden methods; this exists for callers that catch typed failures.
 */
class ViewTableReadOnlyException extends RuntimeException
{
}
