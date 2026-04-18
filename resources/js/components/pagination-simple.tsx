import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type PaginationSimpleProps = {
    currentPage: number;
    lastPage: number;
    prevPageUrl: string | null;
    nextPageUrl: string | null;
};

export function PaginationSimple({
    currentPage,
    lastPage,
    prevPageUrl,
    nextPageUrl,
}: PaginationSimpleProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-3 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border"
            aria-label="Paginación"
        >
            {prevPageUrl ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={prevPageUrl} preserveScroll>
                        Anterior
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Anterior
                </Button>
            )}
            <p className="text-sm text-muted-foreground">
                Página {currentPage} de {lastPage}
            </p>
            {nextPageUrl ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={nextPageUrl} preserveScroll>
                        Siguiente
                    </Link>
                </Button>
            ) : (
                <Button variant="outline" size="sm" disabled>
                    Siguiente
                </Button>
            )}
        </nav>
    );
}
