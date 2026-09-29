<?php
namespace App\utils;

use RuntimeException;

/** Public API error with an explicit HTTP status and machine-readable code. */
final class ApiException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?string $errorCode = null,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
