<?php

declare(strict_types=1);

namespace Nomba\Sdk\Exceptions;

final class RateLimitException extends ApiException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        private readonly ?string $limitHeader     = null,
        private readonly ?string $remainingHeader = null,
        private readonly ?string $windowHeader    = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, [], $previous);
    }

    /** Total requests allowed in the current window (e.g. "40"). */
    public function limit(): ?string
    {
        return $this->limitHeader;
    }

    /** Requests remaining in the current window (e.g. "0"). */
    public function remaining(): ?string
    {
        return $this->remainingHeader;
    }

    /** Raw window string as returned by Nomba (e.g. "1s", "1000ms"). */
    public function window(): ?string
    {
        return $this->windowHeader;
    }

    /** Seconds to wait before retrying, derived from X-Rate-Limit-Window. */
    public function retryAfter(): ?int
    {
        if ($this->windowHeader === null) {
            return null;
        }

        if (preg_match('/^(\d+)s$/', $this->windowHeader, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^(\d+)ms$/', $this->windowHeader, $m)) {
            return (int) ceil((int) $m[1] / 1000);
        }

        return null;
    }
}
