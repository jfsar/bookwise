import { useForm } from '@inertiajs/react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

export default function FiscalYearForm({ open, onClose }) {
    const { data, setData, errors, post, processing } = useForm({
        name: '',
        start_date: '',
        end_date: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/fiscal-years', { onSuccess: () => onClose() });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display">New Fiscal Year</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="space-y-4">
                        <div>
                            <Label>Name *</Label>
                            <Input
                                type="text"
                                placeholder="e.g. FY 2026"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                className="mt-1"
                            />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Start Date *</Label>
                            <Input
                                type="date"
                                value={data.start_date}
                                onChange={(event) => setData('start_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.start_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.start_date}</p>}
                        </div>
                        <div>
                            <Label>End Date *</Label>
                            <Input
                                type="date"
                                value={data.end_date}
                                onChange={(event) => setData('end_date', event.target.value)}
                                className="mt-1"
                            />
                            {errors.end_date && <p className="mt-1 text-sm font-medium text-destructive">{errors.end_date}</p>}
                        </div>
                        <p className="text-xs text-on-surface-muted dark:text-on-surface-muted">
                            Monthly accounting periods will be automatically generated for this fiscal year.
                        </p>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            Create Fiscal Year
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}