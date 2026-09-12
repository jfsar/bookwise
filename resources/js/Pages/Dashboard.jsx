import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const timezones = [
    { value: 'UTC', label: 'UTC' },
    { value: 'America/New_York', label: 'Eastern Time' },
    { value: 'America/Chicago', label: 'Central Time' },
    { value: 'America/Denver', label: 'Mountain Time' },
    { value: 'America/Los_Angeles', label: 'Pacific Time' },
    { value: 'Asia/Manila', label: 'Manila (PHT)' },
    { value: 'Asia/Tokyo', label: 'Tokyo (JST)' },
    { value: 'Europe/London', label: 'London (GMT)' },
];

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function money(value, currency = 'PHP') {
    return Number(value ?? 0).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function StatCard({ label, value, hint, tone }) {
    return (
        <Card className="animate-rise">
            <CardContent className="p-5">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{label}</p>
                <p className={`mt-2 font-display text-2xl font-bold tabular-nums ${tone ?? 'text-on-surface dark:text-on-surface'}`}>{value}</p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ metrics, currencies }) {
    const { currentOrganization, organizations } = usePage().props;
    const [showCreate, setShowCreate] = useState(false);

    const { data, setData, errors, post, processing } = useForm({
        name: '',
        display_name: '',
        code: '',
        country_code: '',
        base_currency_id: '',
        timezone: 'UTC',
        fiscal_year_start_month: 1,
        decimal_places: 2,
    });

    function submit(event) {
        event.preventDefault();
        post('/organizations');
    }

    if (!metrics) {
        return (
            <>
                <div className="animate-rise flex items-center justify-between">
                    <h2 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Dashboard</h2>
                </div>

                <Card className="mt-6">
                    <CardContent className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        Select or create an organization to see your financial dashboard.
                    </CardContent>
                </Card>

                <OrganizationForm
                    showCreate={showCreate}
                    setShowCreate={setShowCreate}
                    data={data}
                    setData={setData}
                    errors={errors}
                    submit={submit}
                    processing={processing}
                    currencies={currencies}
                />
            </>
        );
    }

    const overdueHint = (overdue, label) => (overdue > 0 ? `${overdue} ${label} overdue` : 'All current');

    return (
        <>
            <div className="animate-rise flex items-center justify-between">
                <h2 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Dashboard</h2>
                <span className="text-xs text-muted-foreground">
                    {metrics.measurement_period} · updated {new Date(metrics.generated_at).toLocaleString()}
                </span>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Cash Balance" value={money(metrics.cash_balance, metrics.currency)} />
                <StatCard label="Bank Balance" value={money(metrics.bank_balance, metrics.currency)} />
                <StatCard
                    label="Accounts Receivable"
                    value={money(metrics.accounts_receivable, metrics.currency)}
                    hint={`${metrics.outstanding_invoices} outstanding invoices`}
                />
                <StatCard
                    label="Accounts Payable"
                    value={money(metrics.accounts_payable, metrics.currency)}
                    hint={`${metrics.outstanding_bills} outstanding bills`}
                />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard
                    label="Revenue"
                    value={money(metrics.revenue, metrics.currency)}
                    hint={metrics.measurement_period}
                    tone="text-success"
                />
                <StatCard
                    label="Expenses"
                    value={money(metrics.expenses, metrics.currency)}
                    hint={metrics.measurement_period}
                    tone="text-destructive"
                />
                <StatCard
                    label="Net Income"
                    value={money(metrics.net_income, metrics.currency)}
                    hint={metrics.measurement_period}
                    tone={metrics.net_income >= 0 ? 'text-success' : 'text-destructive'}
                />
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                <StatCard
                    label="Outstanding Invoices"
                    value={String(metrics.outstanding_invoices)}
                    hint={overdueHint(metrics.invoices_overdue, 'invoices')}
                />
                <StatCard
                    label="Outstanding Bills"
                    value={String(metrics.outstanding_bills)}
                    hint={overdueHint(metrics.bills_overdue, 'bills')}
                />
            </div>

            <Card className="mt-6 overflow-hidden">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <h3 className="font-display text-lg font-semibold text-on-surface dark:text-on-surface">Recent Journal Entries</h3>
                        <Link href="/journal-entries" className="text-sm font-medium text-primary hover:underline">
                            View all
                        </Link>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {metrics.recent_entries.length === 0 ? (
                        <div className="px-6 py-10 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                            No posted journal entries yet.
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Entry #</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Date</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Journal</TableHead>
                                    <TableHead className="text-xs uppercase tracking-wider text-muted-foreground">Description</TableHead>
                                    <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Debit</TableHead>
                                    <TableHead className="text-right text-xs uppercase tracking-wider text-muted-foreground">Credit</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {metrics.recent_entries.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-medium text-primary dark:text-primary">{entry.entry_number}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{entry.entry_date}</TableCell>
                                        <TableCell className="text-sm text-muted-foreground">{entry.journal?.name ?? '—'}</TableCell>
                                        <TableCell className="max-w-xs truncate text-sm text-muted-foreground">{entry.description}</TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">{money(entry.total_debit, metrics.currency)}</TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">{money(entry.total_credit, metrics.currency)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <OrganizationForm
                showCreate={showCreate}
                setShowCreate={setShowCreate}
                data={data}
                setData={setData}
                errors={errors}
                submit={submit}
                processing={processing}
                currencies={currencies}
            />
        </>
    );
}

function OrganizationForm({ showCreate, setShowCreate, data, setData, errors, submit, processing, currencies }) {
    const { currentOrganization, organizations } = usePage().props;

    return (
        <Card className="mt-6">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-on-surface dark:text-on-surface font-display">Organizations</h3>
                    <Button variant="secondary" onClick={() => setShowCreate((value) => !value)}>
                        {showCreate ? 'Cancel' : 'New Organization'}
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                {showCreate && (
                    <form onSubmit={submit} className="mt-2 p-6 rounded-xl bg-surface-lowest dark:bg-surface-lowest shadow-ambient-sm">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Name *</Label>
                                <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                                {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                            </div>
                            <div>
                                <Label>Display Name</Label>
                                <Input value={data.display_name} onChange={(event) => setData('display_name', event.target.value)} className="mt-1" />
                            </div>
                            <div>
                                <Label>Code *</Label>
                                <Input value={data.code} onChange={(event) => setData('code', event.target.value)} className="mt-1" />
                                {errors.code && <p className="mt-1 text-sm font-medium text-destructive">{errors.code}</p>}
                            </div>
                            <div>
                                <Label>Country Code *</Label>
                                <Input
                                    value={data.country_code}
                                    maxLength="2"
                                    onChange={(event) => setData('country_code', event.target.value)}
                                    className="mt-1"
                                />
                                {errors.country_code && <p className="mt-1 text-sm font-medium text-destructive">{errors.country_code}</p>}
                            </div>
                            <div>
                                <Label>Base Currency *</Label>
                                <NativeSelect
                                    value={data.base_currency_id}
                                    onChange={(event) => setData('base_currency_id', event.target.value)}
                                    className="mt-1 w-full"
                                >
                                    <NativeSelectOption value="">Select currency...</NativeSelectOption>
                                    {currencies.map((currency) => (
                                        <NativeSelectOption key={currency.id} value={currency.id}>
                                            {currency.code} - {currency.name}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {errors.base_currency_id && <p className="mt-1 text-sm font-medium text-destructive">{errors.base_currency_id}</p>}
                            </div>
                            <div>
                                <Label>Timezone *</Label>
                                <NativeSelect value={data.timezone} onChange={(event) => setData('timezone', event.target.value)} className="mt-1 w-full">
                                    {timezones.map((timezone) => (
                                        <NativeSelectOption key={timezone.value} value={timezone.value}>
                                            {timezone.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {errors.timezone && <p className="mt-1 text-sm font-medium text-destructive">{errors.timezone}</p>}
                            </div>
                            <div>
                                <Label>Fiscal Year Start Month *</Label>
                                <NativeSelect
                                    value={data.fiscal_year_start_month}
                                    onChange={(event) => setData('fiscal_year_start_month', Number(event.target.value))}
                                    className="mt-1 w-full"
                                >
                                    {months.map((month, index) => (
                                        <NativeSelectOption key={month} value={index + 1}>
                                            {month}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                {errors.fiscal_year_start_month && <p className="mt-1 text-sm font-medium text-destructive">{errors.fiscal_year_start_month}</p>}
                            </div>
                            <div>
                                <Label>Decimal Places</Label>
                                <NativeSelect
                                    value={data.decimal_places}
                                    onChange={(event) => setData('decimal_places', Number(event.target.value))}
                                    className="mt-1 w-full"
                                >
                                    {[0, 2, 3, 4].map((places) => (
                                        <NativeSelectOption key={places} value={places}>
                                            {places}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </div>
                        </div>

                        <Button type="submit" variant="default" disabled={processing} className="mt-6">
                            {processing ? 'Creating...' : 'Create Organization'}
                        </Button>
                    </form>
                )}

                {organizations?.length === 0 ? (
                    <div className="mt-4 rounded-lg bg-surface-low dark:bg-surface-low px-4 py-6 text-center text-sm text-on-surface-muted dark:text-on-surface-muted">
                        You don&apos;t belong to any organization yet. Create one to get started.
                    </div>
                ) : (
                    <div className="mt-4 grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {organizations.map((organization) => {
                            const isCurrent = currentOrganization?.id === organization.id;

                            return (
                                <div
                                    key={organization.id}
                                    className={`rounded-lg bg-surface-low dark:bg-surface-low p-4 ${
                                        isCurrent ? 'ring-2 ring-primary dark:ring-primary' : ''
                                    }`}
                                >
                                    <h4 className="font-semibold text-on-surface dark:text-on-surface">
                                        {organization.display_name ?? organization.name}
                                    </h4>
                                    <p className="mt-1 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                        {organization.code} · {organization.country_code}
                                    </p>
                                    <p className="mt-1 text-sm text-on-surface-muted dark:text-on-surface-muted">
                                        {organization.base_currency?.code} · {organization.status[0].toUpperCase()}
                                        {organization.status.slice(1)}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>;