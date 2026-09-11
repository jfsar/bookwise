import { useEffect } from 'react';
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

export default function PeriodForm({ open, onClose, period }) {
    const isClose = period?.is_open;
    const mode = isClose ? 'close' : 'reopen';

    const { data, setData, errors, post, processing, reset } = useForm({ reason: '' });

    useEffect(() => {
        if (open) {
            reset('reason');
        }
    }, [open]);

    if (!period) {
        return null;
    }

    function submit(event) {
        event.preventDefault();
        post(`/accounting-periods/${period.id}/${mode}`, { onSuccess: () => onClose() });
    }

    const title = isClose ? 'Close Period' : 'Reopen Period';
    const subtitle = isClose
        ? 'Closing this period will prevent any new entries from being posted to it. A closing entry will be automatically generated.'
        : 'Reopening this period will allow new entries to be posted. The next period must be open.';
    const label = isClose ? 'Reason (optional)' : 'Reason *';
    const placeholder = isClose ? 'Why is this period being closed?' : 'Why is this period being reopened?';

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display">{title}</DialogTitle>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">{subtitle}</p>

                <form onSubmit={submit} className="mt-4">
                    <Label>{label}</Label>
                    <Textarea
                        rows={3}
                        placeholder={placeholder}
                        value={data.reason}
                        onChange={(event) => setData('reason', event.target.value)}
                        className="mt-1"
                    />
                    {errors.reason && <p className="mt-1 text-sm font-medium text-destructive">{errors.reason}</p>}

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={isClose ? 'destructive' : 'outline'}
                            className={isClose ? '' : 'border-warning/30 text-warning hover:bg-warning/10 hover:text-warning'}
                            disabled={processing}
                        >
                            {title}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}