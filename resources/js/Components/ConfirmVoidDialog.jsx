import { useEffect, useState } from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

export default function ConfirmVoidDialog({
    open,
    title,
    description,
    reasonLabel = 'Void reason',
    confirmText = 'Void',
    onClose,
    onConfirm,
}) {
    const [reason, setReason] = useState('');

    useEffect(() => {
        if (open) {
            setReason('');
        }
    }, [open]);

    function submit() {
        if (reason.trim().length === 0) {
            return;
        }

        onConfirm(reason.trim());
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="font-display">{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div>
                    <Label className="text-sm text-muted-foreground">{reasonLabel}</Label>
                    <Input
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="Explain why this is being voided..."
                        className="mt-1"
                    />
                </div>

                <DialogFooter>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant="outline" className="text-warning" disabled={reason.trim().length === 0} onClick={submit}>
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}