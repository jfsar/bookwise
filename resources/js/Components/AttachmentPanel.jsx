import { useCallback, useEffect, useState } from 'react';

import StatusBadge from '@/Components/StatusBadge';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const scanTones = {
    clean: 'success',
    pending: 'warning',
    infected: 'error',
    failed: 'error',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function formatSize(bytes) {
    if (!bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Reusable upload/download/delete panel for an organization-owned attachable
 * record (SRS §7.38). All requests are authorized server-side; the panel only
 * reflects the `can_delete` flag returned by the attachment list endpoint.
 */
export default function AttachmentPanel({ attachableType, attachableId, label = 'Files' }) {
    const [open, setOpen] = useState(false);
    const [attachments, setAttachments] = useState([]);
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/attachments?attachable_type=${encodeURIComponent(attachableType)}&attachable_id=${attachableId}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('Unable to load attachments.');
            }

            const data = await response.json();
            setAttachments(data.attachments ?? []);
        } catch (exception) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    }, [attachableType, attachableId]);

    useEffect(() => {
        if (open) {
            load();
        }
    }, [open, load]);

    async function handleUpload(event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploading(true);
        setError(null);

        const form = new FormData();
        form.append('file', file);
        form.append('attachable_type', attachableType);
        form.append('attachable_id', String(attachableId));

        try {
            const response = await fetch('/attachments', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: form,
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message ?? 'The attachment could not be uploaded.');
            }

            await load();
        } catch (exception) {
            setError(exception.message);
        } finally {
            setUploading(false);
            event.target.value = '';
        }
    }

    async function handleDelete(attachment) {
        if (!window.confirm(`Delete ${attachment.original_name}?`)) {
            return;
        }

        setError(null);

        try {
            const response = await fetch(`/attachments/${attachment.id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message ?? 'The attachment could not be deleted.');
            }

            await load();
        } catch (exception) {
            setError(exception.message);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="font-display">Attachments</DialogTitle>
                    <DialogDescription>
                        Upload supporting documents. Files are stored privately and scanned before download.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center gap-3">
                    <Input type="file" onChange={handleUpload} disabled={uploading} />
                    {uploading && <span className="text-sm text-muted-foreground">Uploading…</span>}
                </div>

                {error && <p className="text-sm font-medium text-destructive">{error}</p>}

                {loading ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">Loading attachments…</p>
                ) : attachments.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">No attachments yet.</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">File</TableHead>
                                <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Status</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Size</TableHead>
                                <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {attachments.map((attachment) => (
                                <TableRow key={attachment.id}>
                                    <TableCell className="max-w-xs truncate text-sm text-on-surface dark:text-on-surface">
                                        {attachment.original_name}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge tone={scanTones[attachment.scan_status] ?? 'default'}>
                                            {attachment.scan_status}
                                        </StatusBadge>
                                    </TableCell>
                                    <TableCell className="text-right text-sm text-muted-foreground">
                                        {formatSize(attachment.size)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            {attachment.scan_status === 'clean' ? (
                                                <Button size="sm" variant="secondary" asChild>
                                                    <a href={attachment.download_url}>Download</a>
                                                </Button>
                                            ) : (
                                                <Button size="sm" variant="secondary" disabled>
                                                    Download
                                                </Button>
                                            )}
                                            {attachment.can_delete && (
                                                <Button size="sm" variant="destructive" onClick={() => handleDelete(attachment)}>
                                                    Delete
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </DialogContent>
        </Dialog>
    );
}
