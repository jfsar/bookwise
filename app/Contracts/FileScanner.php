<?php

namespace App\Contracts;

use App\Services\FileScanResult;
use Illuminate\Http\UploadedFile;

/**
 * Scans an uploaded file for malware before it is accepted (SRS §0.27).
 *
 * Implementations may delegate to an external antivirus engine; the default
 * binding performs a signature-based scan so the scan status is always
 * recorded and non-clean files are rejected.
 */
interface FileScanner
{
    public function scan(UploadedFile $file): FileScanResult;
}
