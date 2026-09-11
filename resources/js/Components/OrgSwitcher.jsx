import { router, usePage } from '@inertiajs/react';
import { Building2Icon, CheckIcon, ChevronsUpDownIcon } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export default function OrgSwitcher() {
    const { currentOrganization, organizations } = usePage().props;

    if (!organizations || organizations.length === 0) {
        return null;
    }

    const label = currentOrganization?.display_name ?? currentOrganization?.name ?? 'Select Organization';

    function switchTo(organizationId) {
        router.post(`/organizations/switch/${organizationId}`, {}, { preserveScroll: true });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="w-full justify-start gap-2 rounded-md px-2 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <Building2Icon className="size-4 shrink-0 text-sidebar-foreground/70" />
                    <span className="group-data-[collapsible=icon]:hidden flex-1 truncate text-left text-sm font-medium">
                        {label}
                    </span>
                    <ChevronsUpDownIcon className="group-data-[collapsible=icon]:hidden size-4 shrink-0 text-sidebar-foreground/70" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel>
                    <span className="inline-flex items-center gap-2">
                        <Building2Icon className="size-4" />
                        Organizations
                    </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {organizations.map((organization) => {
                    const isActive = currentOrganization?.id === organization.id;

                    return (
                        <DropdownMenuItem key={organization.id} onClick={() => switchTo(organization.id)}>
                            <div className="flex flex-col">
                                <span className="font-medium">{organization.display_name ?? organization.name}</span>
                                <span className="text-xs text-muted-foreground">{organization.code}</span>
                            </div>
                            {isActive && <CheckIcon className="ml-auto size-4" />}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}