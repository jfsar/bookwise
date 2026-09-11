import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';

const modules = [
    { title: 'Chart of Accounts', description: 'Manage your accounts and hierarchy.', href: '/accounts' },
    { title: 'Fiscal Years', description: 'Manage fiscal years and accounting periods.', href: '/fiscal-years' },
    { title: 'Journals', description: 'Manage accounting journals.', href: '/journals' },
    { title: 'Journal Entries', description: 'Create and manage journal entries.', href: '/journal-entries' },
    { title: 'Period Closing', description: 'Close accounting periods.', href: '/period-closing' },
];

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

export default function Dashboard({ currencies }) {
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

    return (
        <>
            <div className="animate-rise flex items-center justify-between">
                <h2 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Dashboard</h2>
            </div>

            <div className="mt-6 grid md:grid-cols-3 gap-4">
                {modules.map((module, index) => (
                    <Link
                        key={module.title}
                        href={module.href}
                        className="animate-rise rounded-xl bg-surface-lowest dark:bg-surface-lowest p-6 shadow-ambient-sm transition duration-150 hover:bg-surface-highest dark:hover:bg-surface-highest"
                        style={{ animationDelay: `${60 + index * 60}ms` }}
                    >
                        <h3 className="text-lg font-semibold text-on-surface dark:text-on-surface">{module.title}</h3>
                        <p className="mt-2 text-sm text-on-surface-muted dark:text-on-surface-muted">{module.description}</p>
                    </Link>
                ))}
            </div>

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
                                            {organization.base_currency?.code} · {organization.status[0].toUpperCase()}{organization.status.slice(1)}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>;