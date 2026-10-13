<?php

namespace App\Actions\Attachment;

use App\Contracts\FileScanner;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreAttachment
{
    /**
     * Store an uploaded file for an organization-owned attachable record.
     *
     * The target must be in the allow-list and belong to the same organization
     * (SRS INV-008, acceptance criterion 49), the file is written to a private
     * disk, and a malware scan must pass before the metadata is persisted.
     */
    public function handle(
        UploadedFile $file,
        string $attachableType,
        int $attachableId,
        Organization $organization,
        User $uploader,
    ): Attachment {
        $this->resolveAttachable($attachableType, $attachableId, $organization);

        $disk = (string) config('attachments.disk', 'local');
        $directory = 'attachments/'.$organization->id;
        $storedName = (string) Str::ulid().'.'.$this->extensionFor($file);

        $path = $file->storeAs($directory, $storedName, ['disk' => $disk]);

        if ($path === false) {
            throw new RuntimeException('The attachment could not be stored.');
        }

        $storage = Storage::disk($disk);
        $checksum = hash_file('sha256', $file->getRealPath());

        $scan = app(FileScanner::class)->scan($file);

        if (! $scan->isClean()) {
            $storage->delete($path);

            throw new RuntimeException(
                $scan->isInfected()
                    ? 'The attachment was rejected by the malware scanner.'
                    : 'The attachment could not be scanned and was rejected.'
            );
        }

        try {
            return DB::transaction(function () use ($file, $attachableType, $attachableId, $organization, $uploader, $disk, $path, $storedName, $checksum) {
                $attachment = Attachment::create([
                    'organization_id' => $organization->id,
                    'attachable_type' => $attachableType,
                    'attachable_id' => $attachableId,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $storedName,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'checksum' => 'sha256:'.$checksum,
                    'visibility' => 'private',
                    'scan_status' => 'clean',
                    'content_hash' => $checksum,
                    'uploaded_by' => $uploader->id,
                    'uploaded_at' => now(),
                ]);

                app(AuditLogService::class)->record(
                    $organization,
                    $uploader->id,
                    'attachment.created',
                    Attachment::class,
                    $attachment->id,
                    null,
                    $this->auditValues($attachment),
                );

                return $attachment;
            });
        } catch (Throwable $e) {
            $storage->delete($path);

            throw $e;
        }
    }

    /**
     * Resolve the target model, rejecting anything outside the allow-list or
     * owned by another organization, so a file can never be attached across
     * tenants.
     */
    private function resolveAttachable(string $type, int $id, Organization $organization): Model
    {
        if (! in_array($type, Attachment::attachableTypes(), true)) {
            throw new RuntimeException('Unsupported attachment target.');
        }

        /** @var Model|null $attachable */
        $attachable = (new $type)->newQuery()->find($id);

        if (! $attachable || (int) $attachable->getAttribute('organization_id') !== $organization->id) {
            throw new RuntimeException('The attachment target does not belong to this organization.');
        }

        return $attachable;
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) ?: $file->extension();
        $extension = preg_replace('/[^a-z0-9]/', '', (string) $extension);

        return $extension !== '' ? $extension : 'bin';
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Attachment $attachment): array
    {
        return $attachment->only([
            'attachable_type', 'attachable_id', 'original_name', 'stored_name',
            'disk', 'path', 'mime_type', 'size', 'checksum', 'content_hash',
            'visibility', 'scan_status', 'uploaded_by',
        ]);
    }
}
