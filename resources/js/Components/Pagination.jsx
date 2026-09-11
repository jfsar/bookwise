import { router } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import {
    Pagination as PaginationRoot,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/Components/ui/pagination';

function renderLabel(rawLabel) {
    const entities = {
        '&laquo;': '«',
        '&raquo;': '»',
        '&quot;': '"',
        '&amp;': '&',
    };

    return rawLabel.replace(/&(laquo|raquo|quot|amp);/g, (match) => entities[match]);
}

export default function Pagination({ paginator, className = '' }) {
    if (!paginator || paginator.last_page <= 1) {
        return null;
    }

    const { links, from, to, total } = paginator;

    function visit(url) {
        router.visit(url, { preserveState: true, preserveScroll: true });
    }

    const items = links.map((link, index) => {
        const label = renderLabel(link.label);
        const isPrevious = label.includes('«') || label.toLowerCase().includes('previous');
        const isNext = label.includes('»') || label.toLowerCase().includes('next');

        if (link.url === null) {
            return (
                <PaginationItem key={index}>
                    {isPrevious || isNext ? (
                        <span className="pointer-events-none flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground/50">
                            {isPrevious ? <ChevronLeftIcon className="size-4" /> : <ChevronRightIcon className="size-4" />}
                        </span>
                    ) : (
                        <span className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground/50">{label}</span>
                    )}
                </PaginationItem>
            );
        }

        if (isPrevious || isNext) {
            const LinkComponent = isPrevious ? PaginationPrevious : PaginationNext;

            return (
                <PaginationItem key={index}>
                    <LinkComponent
                        href={link.url}
                        onClick={(event) => {
                            event.preventDefault();
                            visit(link.url);
                        }}
                    />
                </PaginationItem>
            );
        }

        return (
            <PaginationItem key={index}>
                <PaginationLink
                    isActive={link.active}
                    href={link.url}
                    onClick={(event) => {
                        event.preventDefault();
                        visit(link.url);
                    }}
                >
                    {label}
                </PaginationLink>
            </PaginationItem>
        );
    });

    return (
        <div className={`mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between ${className}`}>
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium text-foreground">{from}</span> to{' '}
                <span className="font-medium text-foreground">{to}</span> of{' '}
                <span className="font-medium text-foreground">{total}</span>
            </p>

            <PaginationRoot>
                <PaginationContent>{items}</PaginationContent>
            </PaginationRoot>
        </div>
    );
}