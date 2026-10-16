<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request arrives for an idempotency key whose operation is still
 * in progress. The caller should not start a second concurrent execution.
 */
class IdempotencyInProgressException extends RuntimeException {}
