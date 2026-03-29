import {
    Form,
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';
import { ChevronLeft, ChevronRight, LayoutGrid, Trash2 } from 'lucide-react';
import ColumnController from '@/actions/App/Http/Controllers/ColumnController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { board as projectBoard, index as teamProjectsIndex } from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type ColumnRow = {
    id: number;
    name: string;
    position: number;
};

type ProjectBoardPageProps = {
    team: {
        id: number;
        name: string;
        owner_id: number;
    };
    project: {
        id: number;
        name: string;
        archived_at: string | null;
    };
    columns: ColumnRow[];
    can: {
        manageColumns: boolean;
    };
};

function swapColumnOrder(ids: number[], index: number, direction: -1 | 1): number[] {
    const next = [...ids];
    const j = index + direction;
    if (j < 0 || j >= next.length) {
        return next;
    }
    [next[index], next[j]] = [next[j], next[index]];

    return next;
}

export default function ProjectBoard() {
    const page = usePage<ProjectBoardPageProps>();
    const { team, project, columns, can } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        {
            title: 'Projects',
            href: teamProjectsIndex(team.id),
        },
        {
            title: project.name,
            href: projectBoard.url({ team: team.id, project: project.id }),
        },
    ];

    const columnIds = columns.map((c) => c.id);

    const submitReorder = (newOrder: number[]): void => {
        router.post(
            ColumnController.reorder.url({
                team: team.id,
                project: project.id,
            }),
            { column_ids: newOrder },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${project.name} — Board`} />

            <div className="space-y-8 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title={project.name}
                        description={
                            project.archived_at
                                ? 'This project is archived. Columns are read-only for members.'
                                : 'Kanban columns for this project.'
                        }
                    />
                    <Link
                        href={teamProjectsIndex.url({ team: team.id })}
                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Back to projects
                    </Link>
                </div>

                {can.manageColumns && (
                    <section className="max-w-md space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            New column
                        </h2>
                        <Form
                            {...ColumnController.store.form({
                                team: team.id,
                                project: project.id,
                            })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['name']}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="column-name">Name</Label>
                                        <Input
                                            id="column-name"
                                            name="name"
                                            required
                                            maxLength={255}
                                            className="mt-1 block w-full"
                                            placeholder="e.g. To do"
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={errors.name}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Add column
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Columns
                    </h2>
                    {columns.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 py-12 text-center dark:border-sidebar-border">
                            <LayoutGrid
                                className="size-10 text-muted-foreground"
                                strokeWidth={1.25}
                                aria-hidden
                            />
                            <p className="text-sm text-muted-foreground">
                                {can.manageColumns
                                    ? 'Add a column above to structure your board.'
                                    : 'No columns yet. An owner or admin can add them.'}
                            </p>
                        </div>
                    ) : (
                        <div className="flex gap-4 overflow-x-auto pb-2">
                            {columns.map((col, index) => (
                                <article
                                    key={col.id}
                                    className="flex w-72 shrink-0 flex-col rounded-lg border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                                >
                                    <div className="border-b border-border px-4 py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <h3 className="font-medium leading-tight">
                                                {col.name}
                                            </h3>
                                            <span className="text-muted-foreground tabular-nums text-xs">
                                                #{col.position}
                                            </span>
                                        </div>
                                    </div>

                                    {can.manageColumns ? (
                                        <div className="flex flex-col gap-4 p-4">
                                            <Form
                                                {...ColumnController.update.form(
                                                    {
                                                        team: team.id,
                                                        project: project.id,
                                                        column: col.id,
                                                    },
                                                )}
                                                options={{ preserveScroll: true }}
                                                className="space-y-3"
                                            >
                                                {({ processing, errors: fe }) => (
                                                    <>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                className="sr-only"
                                                                htmlFor={`column-rename-${col.id}`}
                                                            >
                                                                Rename column
                                                            </Label>
                                                            <Input
                                                                id={`column-rename-${col.id}`}
                                                                name="name"
                                                                required
                                                                maxLength={255}
                                                                defaultValue={col.name}
                                                                className="block w-full"
                                                            />
                                                            <InputError message={fe.name} />
                                                        </div>
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="secondary"
                                                            disabled={processing}
                                                        >
                                                            {processing && <Spinner />}
                                                            Save name
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <div className="flex gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="size-8"
                                                        disabled={index === 0}
                                                        aria-label="Move column left"
                                                        onClick={() => {
                                                            submitReorder(
                                                                swapColumnOrder(
                                                                    columnIds,
                                                                    index,
                                                                    -1,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        <ChevronLeft
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="size-8"
                                                        disabled={
                                                            index ===
                                                            columns.length - 1
                                                        }
                                                        aria-label="Move column right"
                                                        onClick={() => {
                                                            submitReorder(
                                                                swapColumnOrder(
                                                                    columnIds,
                                                                    index,
                                                                    1,
                                                                ),
                                                            );
                                                        }}
                                                    >
                                                        <ChevronRight
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                </div>
                                                <Form
                                                    {...ColumnController.destroy.form(
                                                        {
                                                            team: team.id,
                                                            project: project.id,
                                                            column: col.id,
                                                        },
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Delete column “${col.name}”?`,
                                                        )
                                                    }
                                                    className="inline"
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={processing}
                                                            className="gap-1"
                                                        >
                                                            {processing && (
                                                                <Spinner />
                                                            )}
                                                            <Trash2
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                            Delete
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="p-4 text-xs text-muted-foreground">
                                            Tasks will appear here in a later
                                            phase.
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
