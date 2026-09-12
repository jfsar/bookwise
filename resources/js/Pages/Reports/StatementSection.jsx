import { Card } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const money = (value, currency = 'PHP') =>
    Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

function TreeRow({ node, depth = 0, currency }) {
    const hasChildren = node.children?.length > 0;

    return (
        <>
            <TableRow className={hasChildren ? 'bg-muted/30' : undefined}>
                <TableCell className="text-sm tabular-nums text-muted-foreground">{node.code}</TableCell>
                <TableCell
                    className="text-sm"
                    style={{ paddingLeft: `${16 + depth * 20}px` }}
                >
                    <span className={hasChildren ? 'font-semibold' : 'text-muted-foreground'}>{node.name}</span>
                </TableCell>
                <TableCell className="text-right text-sm font-medium tabular-nums">{money(node.total, currency)}</TableCell>
            </TableRow>

            {node.children?.map((child) => (
                <TreeRow key={child.id} node={child} depth={depth + 1} currency={currency} />
            ))}
        </>
    );
}

export default function StatementSection({ title, trees, currency }) {
    return (
        <Card className="overflow-hidden">
            <div className="px-6 py-4">
                <h4 className="font-display text-base font-semibold">{title}</h4>
            </div>

            {trees?.length === 0 ? (
                <div className="px-6 pb-6 text-sm text-on-surface-muted dark:text-on-surface-muted">No activity in this section.</div>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Code</TableHead>
                            <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Account</TableHead>
                            <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {trees.map((node) => (
                            <TreeRow key={node.id} node={node} currency={currency} />
                        ))}
                    </TableBody>
                </Table>
            )}
        </Card>
    );
}