<?php

namespace App\Services;

use App\Contracts\FileScanner;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Default file scanner.
 *
 * It detects the industry-standard EICAR antivirus test signature, which lets
 * the malware-rejection path be exercised without an external engine. A real
 * deployment may swap this binding for a ClamAV or vendor SDK implementation
 * without changing the domain code.
 */
class SignatureFileScanner implements FileScanner
{
    private const EICAR_SIGNATURE = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function scan(UploadedFile $file): FileScanResult
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return FileScanResult::failed('The uploaded file could not be read for scanning.');
        }

        try {
            $contents = file_get_contents($path);
        } catch (Throwable $e) {
            return FileScanResult::failed($e->getMessage());
        }

        if ($contents === false) {
            return FileScanResult::failed('The uploaded file could not be read for scanning.');
        }

        if ($this->containsMalwareSignature($contents)) {
            return FileScanResult::infected('Malware signature detected.');
        }

        return FileScanResult::clean();
    }

    protected function containsMalwareSignature(string $contents): bool
    {
        return str_contains($contents, self::EICAR_SIGNATURE);
    }
}
