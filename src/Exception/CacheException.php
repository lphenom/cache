<?php

/**
 * @lphenom-build shared,kphp
 *
 * CacheException — base exception for all LPhenom Cache errors.
 */

declare(strict_types=1);

namespace LPhenom\Cache\Exception;

/**
 * Base exception for all cache-related errors.
 *
 * KPHP-compatible: explicit constructor with (string, int) only.
 * KPHP does not support passing $previous (chained exception) to
 * \RuntimeException::__construct — the 3rd argument is omitted.
 *
 * @lphenom-build shared,kphp
 */
class CacheException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
