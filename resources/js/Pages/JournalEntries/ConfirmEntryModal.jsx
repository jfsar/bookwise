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

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        {mode === 'reverse' ? 'Reverse Journal Entry' : 'Cancel Journal Entry'}
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
                            placeholder={mode === 'reverse' ? 'Explain why this entry is being reversed...' : 'Explain why this entry is being cancelled...'}
                        />
                        {errors.reason && <p className="mt-1 text-sm font-medium text-destructive">{errors.reason}</p>}
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {mode === 'reverse' ? 'Reverse Entry' : 'Cancel Entry'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}