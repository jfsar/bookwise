<?php

namespace App\Http\Controllers;

use App\Actions\Attachment\DeleteAttachment;
use App\Actions\Attachment\StoreAttachment;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\AttachmentDownloadRequest;
use App\Http\Requests\AttachmentIndexRequest;
use App\Http\Requests\AttachmentStoreRequest;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * List an organization-owned attachable record's attachments for the
     * upload/download/delete panel (SRS §7.38, §0.27).
     */
    public function index(AttachmentIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            abort(403, 'No organization selected.');
        }

        $this->authorize('viewAny', [Attachment::class, $organization]);

        $type = $request->string('attachable_type')->toString();
        $id = (int) $request->input('attachable_id');

        $belongsToOrganization = (new $type)->newQuery()
            ->where('organization_id', $organization->id)
            ->whereKey($id)
            ->exists();

        if (! $belongsToOrganization) {
            abort(404);
        }

        $attachments = Attachment::forOrganization($organization)
            ->where('attachable_type', $type)
            ->where('attachable_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Attachment $attachment): array => $this->serialize($attachment, $user))
            ->values();

        return response()->json(['attachments' => $attachments]);
    }

    public function store(AttachmentStoreRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No organization selected.'], 403);
            }

            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [Attachment::class, $organization]);

        try {
            $attachment = (new StoreAttachment)->handle(
                $request->file('file'),
                $request->string('attachable_type')->toString(),
                (int) $request->input('attachable_id'),
                $organization,
                $user,
            );
        } catch (RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            logger()->error('Failed to store attachment.', ['organization_id' => $organization->id, 'exception' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unable to store the attachment. Please try again.'], 500);
            }

            return back()->with('error', 'Unable to store the attachment. Please try again.');
        }

        if ($request->expectsJson()) {
            return response()->json(['attachment' => $this->serialize($attachment, $user)], 201);
        }

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function show(AttachmentDownloadRequest $request, Attachment $attachment): StreamedResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization || $attachment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('view', $attachment);

        if (! $attachment->isScanClean()) {
            abort(403, 'The attachment is unavailable until its security scan completes.');
        }

        $storage = Storage::disk($attachment->disk);

        if (! $storage->exists($attachment->path)) {
            abort(404);
        }

        app(AuditLogService::class)->record(
            $organization,
            $user->id,
            'attachment.accessed',
            Attachment::class,
            $attachment->id,
            null,
            ['original_name' => $attachment->original_name],
        );

        return $storage->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization || $attachment->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('delete', $attachment);

        (new DeleteAttachment)->handle($attachment, $user);

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', 'Attachment deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Attachment $attachment, User $user): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'scan_status' => $attachment->scan_status,
            'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
            'download_url' => route('attachments.download', $attachment),
            'can_delete' => $user->can('delete', $attachment),
        ];
    }
}
