<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by services when a business rule is violated (plan limit, downgrade, owner protection...).
 * The exception handler renders it with its own error code and HTTP status.
 * Concrete rules extend this class in the steps that implement them.
 */
class BusinessRuleException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatus = 422,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
