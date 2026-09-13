import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';

const labels = {
    reject: {
        title: 'Reject Journal Entry',
        placeholder: 'Explain why this entry is being rejected...',
        action: 'Reject Entry',
    },
    reverse: {
        title: 'Reverse Journal Entry',
        placeholder: 'Explain why this entry is being reversed...',
        action: 'Reverse Entry',
    },
    cancel: {
        title: 'Cancel Journal Entry',
        placeholder: 'Explain why this entry is being cancelled...',
        action: 'Cancel Entry',
    },
};

export default function ConfirmEntryModal({ open, mode, entry, onClose }) {
    const { data, setData, errors, post, processing, reset } = useForm({
        reason: '',
    });

    function submit(event) {
        event.preventDefault();

        post(`/journal-entries/${entry.id}/${mode}`, {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    const text = labels[mode] ?? labels.reverse;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {text.title}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div>
                        <Label>Reason *</Label>
                        <Textarea
                            rows={3}
                            value={data.reason}
                            onChange={(event) => setData('reason', event.target.value)}
                            className="mt-1"
                            placeholder={text.placeholder}
                        />
                        {errors.reason && <p className="mt-1 text-sm font-medium text-destructive">{errors.reason}</p>}
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {text.action}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}