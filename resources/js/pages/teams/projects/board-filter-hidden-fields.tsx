import { usePage } from '@inertiajs/react';

type BoardFilters = {
    filter_column: number | null;
    filter_assignee: number | null;
    search: string;
};

type PageWithFilters = {
    filters?: BoardFilters;
};

export function BoardFilterHiddenFields() {
    const page = usePage<PageWithFilters>();
    const f = page.props.filters;

    if (!f) {
        return null;
    }

    return (
        <>
            {f.search ? <input type="hidden" name="search" value={f.search} /> : null}
            {f.filter_column != null ? (
                <input type="hidden" name="filter_column" value={f.filter_column} />
            ) : null}
            {f.filter_assignee != null ? (
                <input type="hidden" name="filter_assignee" value={f.filter_assignee} />
            ) : null}
        </>
    );
}
