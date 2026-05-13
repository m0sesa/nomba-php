<?php

declare(strict_types=1);

namespace Nomba\Sdk\Exceptions;

class ApiException extends NombaException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'The Nomba API returned an error.',
        int $code = 0,
        private readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
