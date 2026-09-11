import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BookOpenCheck,
    CalendarRange,
    FileText,
    LayoutDashboard,
    Lock,
    NotebookPen,
} from 'lucide-react';

import OrgSwitcher from '@/Components/OrgSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/Components/ui/sidebar';
import { TooltipProvider } from '@/Components/ui/tooltip';

const navigation = [
    {
        title: 'Ledger',
        items: [
            { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { label: 'Chart of Accounts', href: '/accounts', icon: BookOpen },
            { label: 'Journals', href: '/journals', icon: NotebookPen },
            { label: 'Journal Entries', href: '/journal-entries', icon: FileText },
        ],
    },
    {
        title: 'Reporting',
        items: [
            { label: 'Fiscal Years', href: '/fiscal-years', icon: CalendarRange },
            { label: 'Period Closing', href: '/period-closing', icon: Lock },
        ],
    },
];

export default function AppLayout({ children }) {
    const { appName, auth, flash } = usePage().props;
    const url = usePage().url;

    function isActive(href) {
        return url === href || url.startsWith(`${href}/`);
    }

    return (
        <TooltipProvider>
            <SidebarProvider defaultOpen>
                <Sidebar variant="floating" collapsible="icon">
                    <SidebarHeader>
                        <Link href="/dashboard" className="flex items-center gap-2.5 px-1 py-1.5">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-ambient-sm">
                                <BookOpenCheck className="size-4" />
                            </span>
                            <span className="group-data-[collapsible=icon]:hidden flex flex-col leading-tight">
                                <span className="font-display text-sm font-bold text-sidebar-foreground">{appName}</span>
                                <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-sidebar-foreground/70">
                                    Ledger suite
                                </span>
                            </span>
                        </Link>
                    </SidebarHeader>

                    <SidebarContent>
                        {navigation.map((group) => (
                            <SidebarGroup key={group.title}>
                                <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                                <SidebarGroupContent>
                                    <SidebarMenu className="gap-1">
                                        {group.items.map((item) => (
                                            <SidebarMenuItem key={item.href}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={isActive(item.href)}
                                                    tooltip={item.label}
                                                >
                                                    <Link href={item.href}>
                                                        <item.icon />
                                                        <span>{item.label}</span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        ))}
                    </SidebarContent>

                    <SidebarFooter>
                        <OrgSwitcher />
                    </SidebarFooter>

                    <SidebarRail />
                </Sidebar>

                <SidebarInset className="bg-surface dark:bg-surface page-grain text-on-surface dark:text-on-surface">
                    <header className="sticky top-0 z-40 flex h-14 items-center gap-3 bg-surface-lowest/70 dark:bg-surface-lowest/70 backdrop-blur-xl px-4 shadow-ambient-sm sm:px-6 lg:px-8">
                        <SidebarTrigger />

                        <div className="ml-auto flex items-center gap-3">
                            {auth?.user && (
                                <span className="hidden text-sm text-on-surface-muted dark:text-on-surface-muted sm:inline">
                                    {auth.user.name}
                                </span>
                            )}

                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-sm text-on-surface-muted dark:text-on-surface-muted hover:text-on-surface dark:hover:text-on-surface transition"
                            >
                                Logout
                            </Link>

                            <ThemeToggle />
                        </div>
                    </header>

                    <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                        {(flash?.success || flash?.error) && (
                            <div className="flex flex-col gap-3">
                                {flash.success && (
                                    <Alert className="border-transparent bg-success-container text-success dark:bg-success-container dark:text-success">
                                        <AlertDescription className="text-success dark:text-success">
                                            {flash.success}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {flash.error && (
                                    <Alert className="border-transparent bg-error-container text-error dark:bg-error-container dark:text-error">
                                        <AlertDescription className="text-error dark:text-error">
                                            {flash.error}
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        )}

                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}

export const layout = (page) => <AppLayout>{page}</AppLayout>;