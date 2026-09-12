import { Card } from '@/Components/ui/card';

export default function MoneyRow({ label, value, muted = false }) {
    return (
        <Card className="flex items-center justify-between px-6 py-4">
            <span className={`text-sm ${muted ? 'text-muted-foreground' : 'font-display font-semibold'}`}>{label}</span>
            <span className={`tabular-nums ${muted ? 'text-muted-foreground' : 'font-display text-lg font-bold'}`}>{value}</span>
        </Card>
    );
}