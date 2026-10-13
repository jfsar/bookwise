<?php

namespace App\Services;

/**
 * Outcome of a file security scan.
 *
 * The status value mirrors the `attachments.scan_status` check constraint:
 * `pending`, `clean`, `infected`, `failed`.
 */
final class FileScanResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $detail = null,
    ) {}

    public static function clean(): self
    {
        return new self('clean');
    }

    public static function infected(string $detail): self
    {
        return new self('infected', $detail);
    }

    public static function failed(string $detail): self
    {
        return new self('failed', $detail);
    }

    public function isClean(): bool
    {
        return $this->status === 'clean';
    }

    public function isInfected(): bool
    {
        return $this->status === 'infected';
    }
}
