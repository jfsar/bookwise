<?php

namespace Tests\Unit;

use App\Services\SignatureFileScanner;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class SignatureFileScannerTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_flags_the_eicar_test_signature_as_infected(): void
    {
        $scanner = new class extends SignatureFileScanner
        {
            public function detects(string $contents): bool
            {
                return $this->containsMalwareSignature($contents);
            }
        };

        $this->assertTrue($scanner->detects('X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'));
        $this->assertFalse($scanner->detects('ordinary invoice contents'));
    }

    public function test_it_marks_a_benign_file_as_clean(): void
    {
        $file = $this->uploadedFileWith('ordinary invoice contents');

        $result = (new SignatureFileScanner)->scan($file);

        $this->assertTrue($result->isClean());
        $this->assertSame('clean', $result->status);
    }

    private function uploadedFileWith(string $contents, string $name = 'invoice.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'attachment-scan');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
