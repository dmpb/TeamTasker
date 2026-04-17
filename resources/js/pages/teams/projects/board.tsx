import {
    Form,
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight, LayoutGrid, Trash2 } from 'lucide-react';
import ColumnController from '@/actions/App/Http/Controllers/ColumnController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { ConfirmDestructiveDialog } from '@/components/confirm-destructive-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { index as projectActivityIndex } from '@/routes/teams/projects/activity/index';
import { board as projectBoard, index as teamProjectsIndex } from '@/routes/teams/projects';
import { index as taskCommentsIndex } from '@/routes/teams/projects/tasks/comments/index';
import type { BreadcrumbItem } from '@/types';
import { BoardFilterHiddenFields } from '@/pages/teams/projects/board-filter-hidden-fields';

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground flex min-h-[4rem] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

type TaskRow = {
    id: number;
    title: string;
    description: string | null;
    position: number;
    assignee: { id: number; name: string } | null;
};

type ColumnRow = {
    id: number;
    name: string;
    position: number;
    tasks: TaskRow[];
};

type AssignableUser = {
    id: number;
    name: string;
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
    assignableUsers: AssignableUser[];
    can: {
        manageColumns: boolean;
        manageTasks: boolean;
    };
    filters: {
        filter_column: number | null;
        filter_assignee: number | null;
        search: string;
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
    const { team, project, columns, assignableUsers, can, filters } = page.props;

    const [taskPendingDelete, setTaskPendingDelete] = useState<TaskRow | null>(null);
    const [columnPendingDelete, setColumnPendingDelete] = useState<ColumnRow | null>(null);

    const [draftSearch, setDraftSearch] = useState(filters.search);
    const [draftColumn, setDraftColumn] = useState<string>(
        filters.filter_column != null ? String(filters.filter_column) : '',
    );
    const [draftAssignee, setDraftAssignee] = useState<string>(
        filters.filter_assignee != null ? String(filters.filter_assignee) : '',
    );

    useEffect(() => {
        setDraftSearch(filters.search);
        setDraftColumn(filters.filter_column != null ? String(filters.filter_column) : '');
        setDraftAssignee(filters.filter_assignee != null ? String(filters.filter_assignee) : '');
    }, [filters.search, filters.filter_column, filters.filter_assignee]);

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
        const payload: Record<string, string | number | number[]> = {
            column_ids: newOrder,
        };
        if (filters.search) {
            payload.search = filters.search;
        }
        if (filters.filter_column != null) {
            payload.filter_column = filters.filter_column;
        }
        if (filters.filter_assignee != null) {
            payload.filter_assignee = filters.filter_assignee;
        }

        router.post(
            ColumnController.reorder.url({
                team: team.id,
                project: project.id,
            }),
            payload,
            { preserveScroll: true },
        );
    };

    const applyBoardFilters = (): void => {
        router.get(
            projectBoard.url(
                { team: team.id, project: project.id },
                {
                    query: {
                        ...(draftSearch.trim() !== '' ? { search: draftSearch.trim() } : {}),
                        ...(draftColumn !== '' ? { filter_column: draftColumn } : {}),
                        ...(draftAssignee !== '' ? { filter_assignee: draftAssignee } : {}),
                    },
                },
            ),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const clearBoardFilters = (): void => {
        setDraftSearch('');
        setDraftColumn('');
        setDraftAssignee('');
        router.get(
            projectBoard.url({ team: team.id, project: project.id }),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const otherColumns = (columnId: number): ColumnRow[] =>
        columns.filter((c) => c.id !== columnId);

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
                    <Link
                        href={projectActivityIndex.url({ team: team.id, project: project.id })}
                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        View activity
                    </Link>
                </div>

                <section className="flex flex-col gap-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border lg:flex-row lg:flex-wrap lg:items-end">
                    <div className="grid min-w-[10rem] flex-1 gap-2">
                        <Label htmlFor="board-search">Search titles</Label>
                        <Input
                            id="board-search"
                            value={draftSearch}
                            onChange={(e) => setDraftSearch(e.target.value)}
                            placeholder="Filter by title…"
                            maxLength={255}
                            className="max-w-md"
                        />
                    </div>
                    <div className="grid min-w-[10rem] flex-1 gap-2">
                        <Label htmlFor="board-column">Column</Label>
                        <select
                            id="board-column"
                            value={draftColumn}
                            onChange={(e) => setDraftColumn(e.target.value)}
                            className="border-input bg-background h-9 w-full max-w-md rounded-md border px-2 text-sm"
                        >
                            <option value="">All columns</option>
                            {columns.map((c) => (
                                <option key={c.id} value={String(c.id)}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {assignableUsers.length > 0 && (
                        <div className="grid min-w-[10rem] flex-1 gap-2">
                            <Label htmlFor="board-assignee">Assignee</Label>
                            <select
                                id="board-assignee"
                                value={draftAssignee}
                                onChange={(e) => setDraftAssignee(e.target.value)}
                                className="border-input bg-background h-9 w-full max-w-md rounded-md border px-2 text-sm"
                            >
                                <option value="">Anyone</option>
                                {assignableUsers.map((u) => (
                                    <option key={u.id} value={String(u.id)}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" size="sm" onClick={applyBoardFilters}>
                            Apply filters
                        </Button>
                        <Button type="button" size="sm" variant="outline" onClick={clearBoardFilters}>
                            Clear
                        </Button>
                    </div>
                </section>

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
                                    <BoardFilterHiddenFields />
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
                                    className="flex w-80 shrink-0 flex-col rounded-lg border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
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

                                    <div className="flex flex-1 flex-col border-b border-border">
                                        {col.tasks.length === 0 ? (
                                            <p className="p-3 text-xs text-muted-foreground">
                                                No tasks in this column.
                                            </p>
                                        ) : (
                                            <ul className="max-h-112 space-y-3 overflow-y-auto p-3">
                                                {col.tasks.map((task) => (
                                                    <li
                                                        key={task.id}
                                                        className="rounded-md border border-border/60 bg-muted/40 p-2.5 text-sm dark:bg-muted/20"
                                                    >
                                                        <div className="mb-2 flex justify-end">
                                                            <Link
                                                                href={taskCommentsIndex.url({
                                                                    team: team.id,
                                                                    project: project.id,
                                                                    task: task.id,
                                                                })}
                                                                className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                            >
                                                                Comments
                                                            </Link>
                                                        </div>
                                                        {can.manageTasks ? (
                                                            <div className="flex flex-col gap-3">
                                                                <Form
                                                                    {...TaskController.update.form(
                                                                        {
                                                                            team: team.id,
                                                                            project:
                                                                                project.id,
                                                                            task: task.id,
                                                                        },
                                                                    )}
                                                                    options={{
                                                                        preserveScroll:
                                                                            true,
                                                                    }}
                                                                    className="space-y-2"
                                                                >
                                                                    {({
                                                                        processing,
                                                                        errors: ue,
                                                                    }) => (
                                                                        <>
                                                                            <BoardFilterHiddenFields />
                                                                            <div className="grid gap-1">
                                                                                <Label
                                                                                    className="text-xs"
                                                                                    htmlFor={`task-title-${task.id}`}
                                                                                >
                                                                                    Title
                                                                                </Label>
                                                                                <Input
                                                                                    id={`task-title-${task.id}`}
                                                                                    name="title"
                                                                                    required
                                                                                    maxLength={
                                                                                        255
                                                                                    }
                                                                                    defaultValue={
                                                                                        task.title
                                                                                    }
                                                                                    className="text-sm"
                                                                                />
                                                                                <InputError
                                                                                    message={
                                                                                        ue.title
                                                                                    }
                                                                                />
                                                                            </div>
                                                                            <div className="grid gap-1">
                                                                                <Label
                                                                                    className="text-xs"
                                                                                    htmlFor={`task-desc-${task.id}`}
                                                                                >
                                                                                    Description
                                                                                </Label>
                                                                                <textarea
                                                                                    id={`task-desc-${task.id}`}
                                                                                    name="description"
                                                                                    rows={
                                                                                        2
                                                                                    }
                                                                                    defaultValue={
                                                                                        task.description ??
                                                                                        ''
                                                                                    }
                                                                                    className={
                                                                                        textareaClass
                                                                                    }
                                                                                    placeholder="Optional"
                                                                                />
                                                                                <InputError
                                                                                    message={
                                                                                        ue.description
                                                                                    }
                                                                                />
                                                                            </div>
                                                                            <div className="grid gap-1">
                                                                                <Label
                                                                                    className="text-xs"
                                                                                    htmlFor={`task-assignee-${task.id}`}
                                                                                >
                                                                                    Assignee
                                                                                </Label>
                                                                                <select
                                                                                    id={`task-assignee-${task.id}`}
                                                                                    name="assignee_id"
                                                                                    defaultValue={
                                                                                        task.assignee?.id?.toString() ??
                                                                                        ''
                                                                                    }
                                                                                    className="border-input bg-background h-9 w-full rounded-md border px-2 text-xs"
                                                                                >
                                                                                    <option value="">
                                                                                        Unassigned
                                                                                    </option>
                                                                                    {assignableUsers.map(
                                                                                        (
                                                                                            u,
                                                                                        ) => (
                                                                                            <option
                                                                                                key={
                                                                                                    u.id
                                                                                                }
                                                                                                value={
                                                                                                    u.id
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    u.name
                                                                                                }
                                                                                            </option>
                                                                                        ),
                                                                                    )}
                                                                                </select>
                                                                                <InputError
                                                                                    message={
                                                                                        ue.assignee_id
                                                                                    }
                                                                                />
                                                                            </div>
                                                                            <Button
                                                                                type="submit"
                                                                                size="sm"
                                                                                variant="secondary"
                                                                                disabled={
                                                                                    processing
                                                                                }
                                                                                className="w-full"
                                                                            >
                                                                                {processing && (
                                                                                    <Spinner />
                                                                                )}
                                                                                Save task
                                                                            </Button>
                                                                        </>
                                                                    )}
                                                                </Form>

                                                                {otherColumns(col.id)
                                                                    .length > 0 && (
                                                                    <Form
                                                                        {...TaskController.move.form(
                                                                            {
                                                                                team: team.id,
                                                                                project:
                                                                                    project.id,
                                                                                task: task.id,
                                                                            },
                                                                        )}
                                                                        options={{
                                                                            preserveScroll:
                                                                                true,
                                                                        }}
                                                                        className="flex flex-col gap-1 border-t border-border/60 pt-2"
                                                                    >
                                                                        {({
                                                                            processing,
                                                                            errors: moveErrors,
                                                                        }) => (
                                                                            <>
                                                                                <BoardFilterHiddenFields />
                                                                                <Label
                                                                                    className="text-xs text-muted-foreground"
                                                                                    htmlFor={`move-task-${task.id}`}
                                                                                >
                                                                                    Move column
                                                                                </Label>
                                                                                <select
                                                                                    id={`move-task-${task.id}`}
                                                                                    name="target_column_id"
                                                                                    required
                                                                                    defaultValue=""
                                                                                    className="border-input bg-background h-9 w-full rounded-md border px-2 text-xs"
                                                                                >
                                                                                    <option
                                                                                        value=""
                                                                                        disabled
                                                                                    >
                                                                                        Move to…
                                                                                    </option>
                                                                                    {otherColumns(
                                                                                        col.id,
                                                                                    ).map(
                                                                                        (
                                                                                            c,
                                                                                        ) => (
                                                                                            <option
                                                                                                key={
                                                                                                    c.id
                                                                                                }
                                                                                                value={
                                                                                                    c.id
                                                                                                }
                                                                                            >
                                                                                                {
                                                                                                    c.name
                                                                                                }
                                                                                            </option>
                                                                                        ),
                                                                                    )}
                                                                                </select>
                                                                                <InputError
                                                                                    message={
                                                                                        moveErrors.target_column_id
                                                                                    }
                                                                                />
                                                                                <Button
                                                                                    type="submit"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    disabled={
                                                                                        processing
                                                                                    }
                                                                                >
                                                                                    {processing && (
                                                                                        <Spinner />
                                                                                    )}
                                                                                    Move
                                                                                </Button>
                                                                            </>
                                                                        )}
                                                                    </Form>
                                                                )}
                                                                <div className="border-t border-border/60 pt-2">
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="destructive"
                                                                        className="w-full gap-1"
                                                                        onClick={() =>
                                                                            setTaskPendingDelete(task)
                                                                        }
                                                                    >
                                                                        <Trash2
                                                                            className="size-3.5"
                                                                            aria-hidden
                                                                        />
                                                                        Delete task
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <>
                                                                <p className="font-medium leading-snug">
                                                                    {task.title}
                                                                </p>
                                                                {task.description && (
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {task.description}
                                                                    </p>
                                                                )}
                                                                {task.assignee && (
                                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                                        {task.assignee.name}
                                                                    </p>
                                                                )}
                                                            </>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>

                                    {can.manageTasks && (
                                        <div className="border-b border-border p-3">
                                            <p className="mb-2 text-xs font-medium text-muted-foreground">
                                                New task
                                            </p>
                                            <Form
                                                {...TaskController.store.form({
                                                    team: team.id,
                                                    project: project.id,
                                                    column: col.id,
                                                })}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                resetOnSuccess={[
                                                    'title',
                                                    'description',
                                                ]}
                                                className="space-y-2"
                                            >
                                                {({ processing, errors: te }) => (
                                                    <>
                                                        <BoardFilterHiddenFields />
                                                        <div className="grid gap-1">
                                                            <Label
                                                                className="sr-only"
                                                                htmlFor={`new-task-title-${col.id}`}
                                                            >
                                                                Title
                                                            </Label>
                                                            <Input
                                                                id={`new-task-title-${col.id}`}
                                                                name="title"
                                                                required
                                                                maxLength={255}
                                                                placeholder="Title"
                                                                className="text-sm"
                                                            />
                                                            <InputError
                                                                message={te.title}
                                                            />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                className="sr-only"
                                                                htmlFor={`new-task-desc-${col.id}`}
                                                            >
                                                                Description
                                                            </Label>
                                                            <textarea
                                                                id={`new-task-desc-${col.id}`}
                                                                name="description"
                                                                rows={2}
                                                                className={textareaClass}
                                                                placeholder="Description (optional)"
                                                            />
                                                            <InputError
                                                                message={te.description}
                                                            />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                className="text-xs text-muted-foreground"
                                                                htmlFor={`new-task-assignee-${col.id}`}
                                                            >
                                                                Assignee
                                                            </Label>
                                                            <select
                                                                id={`new-task-assignee-${col.id}`}
                                                                name="assignee_id"
                                                                defaultValue=""
                                                                className="border-input bg-background h-9 w-full rounded-md border px-2 text-xs"
                                                            >
                                                                <option value="">
                                                                    Unassigned
                                                                </option>
                                                                {assignableUsers.map(
                                                                    (u) => (
                                                                        <option
                                                                            key={u.id}
                                                                            value={u.id}
                                                                        >
                                                                            {u.name}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                            <InputError
                                                                message={te.assignee_id}
                                                            />
                                                        </div>
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            disabled={processing}
                                                            className="w-full"
                                                        >
                                                            {processing && <Spinner />}
                                                            Add task
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </div>
                                    )}

                                    {can.manageColumns && (
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
                                                        <BoardFilterHiddenFields />
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
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    className="gap-1"
                                                    onClick={() => setColumnPendingDelete(col)}
                                                >
                                                    <Trash2
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>

            <ConfirmDestructiveDialog
                open={taskPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTaskPendingDelete(null);
                    }
                }}
                title="Delete task"
                description={
                    taskPendingDelete
                        ? `Delete “${taskPendingDelete.title}”? This cannot be undone.`
                        : ''
                }
                confirmLabel="Delete task"
                onConfirm={() => {
                    const t = taskPendingDelete;
                    if (!t) {
                        return;
                    }
                    setTaskPendingDelete(null);
                    router.delete(
                        TaskController.destroy.url({
                            team: team.id,
                            project: project.id,
                            task: t.id,
                        }),
                        { preserveScroll: true },
                    );
                }}
            />

            <ConfirmDestructiveDialog
                open={columnPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setColumnPendingDelete(null);
                    }
                }}
                title="Delete column"
                description={
                    columnPendingDelete
                        ? `Delete column “${columnPendingDelete.name}” and all tasks inside? This cannot be undone.`
                        : ''
                }
                confirmLabel="Delete column"
                onConfirm={() => {
                    const c = columnPendingDelete;
                    if (!c) {
                        return;
                    }
                    setColumnPendingDelete(null);
                    router.delete(
                        ColumnController.destroy.url({
                            team: team.id,
                            project: project.id,
                            column: c.id,
                        }),
                        { preserveScroll: true },
                    );
                }}
            />
        </AppLayout>
    );
}
