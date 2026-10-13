<?php

namespace App\Actions\Attachment;

use App\Models\Attachment;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAttachment
{
    public function handle(Attachment $attachment, User $actor): void
    {
        $organization = $attachment->organization;
        $values = $attachment->only([
            'attachable_type', 'attachable_id', 'original_name', 'stored_name',
            'disk', 'path', 'mime_type', 'size', 'checksum', 'scan_status',
        ]);
        $disk = $attachment->disk;
        $path = $attachment->path;
        $id = $attachment->id;

        DB::transaction(function () use ($attachment, $organization, $actor, $values, $id) {
            $attachment->delete();

            app(AuditLogService::class)->record(
                $organization,
                $actor->id,
                'attachment.deleted',
                Attachment::class,
                $id,
                $values,
                null,
            );
        });

        Storage::disk($disk)->delete($path);
    }
}
