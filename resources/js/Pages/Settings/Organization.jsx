import { useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/Components/ui/native-select';

const emptyForm = {
    name: '',
    display_name: '',
    registration_number: '',
    tax_number: '',
    email: '',
    phone: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country_code: '',
    base_currency_id: '',
    timezone: 'UTC',
    fiscal_year_start_month: '1',
    date_format: 'Y-m-d',
    decimal_places: '2',
};

export default function OrganizationSettings() {
    const { organization, currencies, timezones } = usePage().props;
    const { data, setData, errors, put, processing } = useForm(emptyForm);

    useEffect(() => {
        if (organization) {
            setData({
                name: organization.name ?? '',
                display_name: organization.display_name ?? '',
                registration_number: organization.registration_number ?? '',
                tax_number: organization.tax_number ?? '',
                email: organization.email ?? '',
                phone: organization.phone ?? '',
                address_line_1: organization.address_line_1 ?? '',
                address_line_2: organization.address_line_2 ?? '',
                city: organization.city ?? '',
                state: organization.state ?? '',
                postal_code: organization.postal_code ?? '',
                country_code: organization.country_code ?? '',
                base_currency_id: organization.base_currency_id ?? '',
                timezone: organization.timezone ?? 'UTC',
                fiscal_year_start_month: String(organization.fiscal_year_start_month ?? 1),
                date_format: organization.date_format ?? 'Y-m-d',
                decimal_places: String(organization.decimal_places ?? 2),
            });
        }
    }, [organization]);

    function submit(event) {
        event.preventDefault();

        put(`/organizations/${organization.id}`, { preserveScroll: true });
    }

    if (!organization) {
        return (
            <>
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Organization Settings</h3>
                <p className="mt-4 text-sm text-muted-foreground">No organization selected.</p>
            </>
        );
    }

    return (
        <>
            <div>
                <h3 className="text-2xl font-display font-bold text-on-surface dark:text-on-surface">Organization Settings</h3>
                <p className="mt-1 text-sm text-muted-foreground">{organization.name} · {organization.code}</p>
            </div>

            <form onSubmit={submit} className="mt-4 space-y-6">
                <Card className="p-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Legal Name *</Label>
                            <Input value={data.name} onChange={(event) => setData('name', event.target.value)} className="mt-1" />
                            {errors.name && <p className="mt-1 text-sm font-medium text-destructive">{errors.name}</p>}
                        </div>
                        <div>
                            <Label>Display Name</Label>
                            <Input value={data.display_name} onChange={(event) => setData('display_name', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Registration Number</Label>
                            <Input value={data.registration_number} onChange={(event) => setData('registration_number', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Tax Number</Label>
                            <Input value={data.tax_number} onChange={(event) => setData('tax_number', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Email</Label>
                            <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className="mt-1" />
                            {errors.email && <p className="mt-1 text-sm font-medium text-destructive">{errors.email}</p>}
                        </div>
                        <div>
                            <Label>Phone</Label>
                            <Input value={data.phone} onChange={(event) => setData('phone', event.target.value)} className="mt-1" />
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <Label>Address</Label>
                            <Input value={data.address_line_1} onChange={(event) => setData('address_line_1', event.target.value)} className="mt-1" placeholder="Line 1" />
                        </div>
                        <div className="col-span-2">
                            <Input value={data.address_line_2} onChange={(event) => setData('address_line_2', event.target.value)} className="mt-1" placeholder="Line 2" />
                        </div>
                        <div>
                            <Label>City</Label>
                            <Input value={data.city} onChange={(event) => setData('city', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>State / Province</Label>
                            <Input value={data.state} onChange={(event) => setData('state', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Postal Code</Label>
                            <Input value={data.postal_code} onChange={(event) => setData('postal_code', event.target.value)} className="mt-1" />
                        </div>
                        <div>
                            <Label>Country Code *</Label>
                            <Input value={data.country_code} onChange={(event) => setData('country_code', event.target.value)} className="mt-1" maxLength={2} />
                            {errors.country_code && <p className="mt-1 text-sm font-medium text-destructive">{errors.country_code}</p>}
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Base Currency *</Label>
                            <NativeSelect value={data.base_currency_id} onChange={(event) => setData('base_currency_id', event.target.value)} className="mt-1 w-full">
                                <NativeSelectOption value="">Select currency...</NativeSelectOption>
                                {currencies.map((currency) => (
                                    <NativeSelectOption key={currency.id} value={String(currency.id)}>
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
                            <NativeSelect value={data.fiscal_year_start_month} onChange={(event) => setData('fiscal_year_start_month', event.target.value)} className="mt-1 w-full">
                                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((month) => (
                                    <NativeSelectOption key={month} value={String(month)}>
                                        {new Date(2000, month - 1, 1).toLocaleString('en', { month: 'long' })}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {errors.fiscal_year_start_month && <p className="mt-1 text-sm font-medium text-destructive">{errors.fiscal_year_start_month}</p>}
                        </div>
                        <div>
                            <Label>Decimal Places</Label>
                            <Input
                                type="number"
                                min={0}
                                max={9}
                                value={data.decimal_places}
                                onChange={(event) => setData('decimal_places', event.target.value)}
                                className="mt-1"
                            />
                            {errors.decimal_places && <p className="mt-1 text-sm font-medium text-destructive">{errors.decimal_places}</p>}
                        </div>
                        <div>
                            <Label>Date Format</Label>
                            <Input value={data.date_format} onChange={(event) => setData('date_format', event.target.value)} className="mt-1" placeholder="Y-m-d" />
                        </div>
                    </div>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        Save Settings
                    </Button>
                </div>
            </form>
        </>
    );
}

OrganizationSettings.layout = (page) => <AppLayout>{page}</AppLayout>;