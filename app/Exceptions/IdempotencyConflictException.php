<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an idempotency key is reused with a different request
 * fingerprint. §0.26 requires such a reuse to be rejected.
 */
class IdempotencyConflictException extends RuntimeException {}
