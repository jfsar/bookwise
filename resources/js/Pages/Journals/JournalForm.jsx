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
import { Textarea } from '@/Components/ui/textarea';

export default function JournalForm({ open, onClose }) {
    const { data, setData, errors, post, processing } = useForm({
        code: '',
        name: '',
        description: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/journals', { onSuccess: () => onClose() });
    }

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle className="font-display">New Journal</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="space-y-4">
                        <div>
                            <Label>Code *</Label>
                            <Input
                                type="text"
                                placeholder="e.g. GL"
                                value={data.code}
                                onChange={(event) => setData('code', event.target.value)}
                                className="mt-1"
                            />
                            {errors.code && <p className="mt-1 text-sm font-medium text-destructive">{errors.code}</p>}
                        </div>
                        <div>
                            <Label>Name *</Label>
                            <Input
                                type="text"
                                placeholder="e.g. General Ledger"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                className="mt-1"
                            />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Description</Label>
                            <Textarea rows={3} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1" />
                            {errors.description && <p className="mt-1 text-sm font-medium text-destructive">{errors.description}</p>}
                        </div>
                    </div>

                    <div className="mt-6 flex items-center gap-3 justify-end">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="default" disabled={processing}>
                            Create Journal
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}