import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import {
    Form,
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    GripVertical,
    LayoutGrid,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ColumnController from '@/actions/App/Http/Controllers/ColumnController';
import LabelController from '@/actions/App/Http/Controllers/LabelController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { ConfirmDestructiveDialog } from '@/components/confirm-destructive-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    BoardTasksDnd,
    boardTasksForColumn,
} from '@/components/project-board/BoardTasksDnd';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { BoardFilterHiddenFields } from '@/pages/teams/projects/board-filter-hidden-fields';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { board as projectBoard, index as teamProjectsIndex } from '@/routes/teams/projects';
import { index as projectActivityIndex } from '@/routes/teams/projects/activity/index';
import { show as taskShow } from '@/routes/teams/projects/tasks';
import { index as taskCommentsIndex } from '@/routes/teams/projects/tasks/comments/index';
import type { BreadcrumbItem } from '@/types';

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground flex min-h-[4rem] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

const selectClass = cn(
    'border-input bg-background flex h-9 shrink-0 rounded-md border px-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

type LabelRow = {
    id: number;
    name: string;
    color: string | null;
};

type TaskRow = {
    id: number;
    title: string;
    description: string | null;
    position: number;
    due_date: string | null;
    priority: string;
    completed_at: string | null;
    is_completed: boolean;
    checklist_done: number;
    checklist_total: number;
    labels: LabelRow[];
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
        id: string;
        name: string;
        owner_id: number;
    };
    project: {
        id: string;
        name: string;
        archived_at: string | null;
    };
    labels: LabelRow[];
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
        filter_label: number | null;
        filter_priority: string;
        filter_due: string;
    };
};

function hasActiveBoardFilters(filters: ProjectBoardPageProps['filters']): boolean {
    return (
        filters.search.trim() !== '' ||
        filters.filter_column != null ||
        filters.filter_assignee != null ||
        filters.filter_label != null ||
        filters.filter_priority !== '' ||
        filters.filter_due !== ''
    );
}

function priorityLabel(p: string): string {
    if (p === 'high') {
        return 'Alta';
    }

    if (p === 'low') {
        return 'Baja';
    }

    return 'Media';
}

function dueHint(dueDate: string | null, isCompleted: boolean): { text: string; className: string } | null {
    if (!dueDate || isCompleted) {
        return null;
    }

    const [y, m, d] = dueDate.split('-').map((n) => Number.parseInt(n, 10));
    const due = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);
    const diffDays = Math.round((due.getTime() - today.getTime()) / 86400000);

    if (diffDays < 0) {
        return { text: 'Vencida', className: 'text-destructive' };
    }

    if (diffDays === 0) {
        return { text: 'Vence hoy', className: 'text-amber-600 dark:text-amber-400' };
    }

    if (diffDays <= 7) {
        return { text: 'Pronto', className: 'text-muted-foreground' };
    }

    return { text: `Vence ${dueDate}`, className: 'text-muted-foreground' };
}

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
    const { team, project, labels, columns, assignableUsers, can, filters } = page.props;

    const boardTaskDnDActive =
        can.manageTasks && project.archived_at === null && !hasActiveBoardFilters(filters);

    const taskById = useMemo(() => {
        const m = new Map<number, TaskRow>();

        for (const c of columns) {
            for (const t of c.tasks) {
                m.set(t.id, t);
            }
        }

        return m;
    }, [columns]);

    const [taskPendingDelete, setTaskPendingDelete] = useState<TaskRow | null>(null);
    const [columnPendingDelete, setColumnPendingDelete] = useState<ColumnRow | null>(null);

    const [draftSearch, setDraftSearch] = useState(filters.search);
    const [draftColumn, setDraftColumn] = useState<string>(
        filters.filter_column != null ? String(filters.filter_column) : '',
    );
    const [draftAssignee, setDraftAssignee] = useState<string>(
        filters.filter_assignee != null ? String(filters.filter_assignee) : '',
    );
    const [draftLabel, setDraftLabel] = useState<string>(
        filters.filter_label != null ? String(filters.filter_label) : '',
    );
    const [draftPriority, setDraftPriority] = useState<string>(filters.filter_priority ?? '');
    const [draftDue, setDraftDue] = useState<string>(filters.filter_due ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        {
            title: 'Proyectos',
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

        if (filters.filter_label != null) {
            payload.filter_label = filters.filter_label;
        }

        if (filters.filter_priority !== '') {
            payload.filter_priority = filters.filter_priority;
        }

        if (filters.filter_due !== '') {
            payload.filter_due = filters.filter_due;
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

    const navigateBoardWithSearch = (searchText: string): void => {
        const query: Record<string, string> = {};

        if (searchText.trim() !== '') {
            query.search = searchText.trim();
        }

        if (draftColumn !== '') {
            query.filter_column = draftColumn;
        }

        if (draftAssignee !== '') {
            query.filter_assignee = draftAssignee;
        }

        if (draftLabel !== '') {
            query.filter_label = draftLabel;
        }

        if (draftPriority !== '') {
            query.filter_priority = draftPriority;
        }

        if (draftDue !== '') {
            query.filter_due = draftDue;
        }

        router.get(
            projectBoard.url(
                { team: team.id, project: project.id },
                Object.keys(query).length > 0 ? { query } : {},
            ),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const applyBoardFilters = (): void => {
        navigateBoardWithSearch(draftSearch);
    };

    const clearBoardFilters = (): void => {
        setDraftSearch('');
        setDraftColumn('');
        setDraftAssignee('');
        setDraftLabel('');
        setDraftPriority('');
        setDraftDue('');
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
            <Head title={`Tablero — ${project.name}`} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title={project.name}
                        description={
                            project.archived_at
                                ? 'Este proyecto está archivado. Las columnas son solo lectura para los miembros.'
                                : 'Tablero Kanban de este proyecto.'
                        }
                    />
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={teamProjectsIndex.url({ team: team.id })}
                                prefetch
                            >
                                Proyectos
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={projectActivityIndex.url({
                                    team: team.id,
                                    project: project.id,
                                })}
                                prefetch
                            >
                                Actividad
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex min-w-0 flex-nowrap items-center justify-between gap-3 overflow-x-auto border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border">
                    <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto py-0.5">
                        <div className="grid shrink-0 gap-1">
                            <Label htmlFor="board-column" className="sr-only">
                                Columna
                            </Label>
                            <select
                                id="board-column"
                                value={draftColumn}
                                onChange={(e) => setDraftColumn(e.target.value)}
                                className={cn(selectClass, 'w-40')}
                            >
                                <option value="">Todas</option>
                                {columns.map((c) => (
                                    <option key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {assignableUsers.length > 0 ? (
                            <div className="grid shrink-0 gap-1">
                                <Label htmlFor="board-assignee" className="sr-only">
                                    Asignado
                                </Label>
                                <select
                                    id="board-assignee"
                                    value={draftAssignee}
                                    onChange={(e) =>
                                        setDraftAssignee(e.target.value)
                                    }
                                    className={cn(selectClass, 'w-40')}
                                >
                                    <option value="">Cualquiera</option>
                                    {assignableUsers.map((u) => (
                                        <option key={u.id} value={String(u.id)}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}
                        <div className="grid shrink-0 gap-1">
                            <Label htmlFor="board-label" className="sr-only">
                                Etiqueta
                            </Label>
                            <select
                                id="board-label"
                                value={draftLabel}
                                onChange={(e) => setDraftLabel(e.target.value)}
                                className={cn(selectClass, 'w-36')}
                            >
                                <option value="">Cualquiera</option>
                                {labels.map((l) => (
                                    <option key={l.id} value={String(l.id)}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid shrink-0 gap-1">
                            <Label htmlFor="board-priority" className="sr-only">
                                Prioridad
                            </Label>
                            <select
                                id="board-priority"
                                value={draftPriority}
                                onChange={(e) =>
                                    setDraftPriority(e.target.value)
                                }
                                className={cn(selectClass, 'w-32')}
                            >
                                <option value="">Cualquiera</option>
                                <option value="low">Baja</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                            </select>
                        </div>
                        <div className="grid shrink-0 gap-1">
                            <Label htmlFor="board-due" className="sr-only">
                                Vencimiento
                            </Label>
                            <select
                                id="board-due"
                                value={draftDue}
                                onChange={(e) => setDraftDue(e.target.value)}
                                className={cn(selectClass, 'w-40')}
                            >
                                <option value="">Cualquiera</option>
                                <option value="overdue">Vencidas</option>
                                <option value="today">Hoy</option>
                                <option value="this_week">Esta semana</option>
                                <option value="no_due">Sin fecha</option>
                            </select>
                        </div>
                    </div>
                    <div className="ml-auto flex w-full max-w-xs shrink-0 items-center gap-2">
                        <div className="relative min-w-0 flex-1">
                            <Label htmlFor="board-search" className="sr-only">
                                Buscar en títulos
                            </Label>
                            <Search
                                className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                id="board-search"
                                value={draftSearch}
                                onChange={(e) => setDraftSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        applyBoardFilters();
                                    }
                                }}
                                placeholder="Buscar…"
                                maxLength={255}
                                className={
                                    draftSearch.trim() !== ''
                                        ? 'h-9 pl-9 pr-9'
                                        : 'h-9 pl-9 pr-2.5'
                                }
                            />
                            {draftSearch.trim() !== '' ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="absolute right-1 top-1/2 size-7 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    onClick={() => {
                                        setDraftSearch('');
                                        navigateBoardWithSearch('');
                                    }}
                                    aria-label="Limpiar búsqueda"
                                >
                                    <X className="size-4" aria-hidden />
                                </Button>
                            ) : null}
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            className="shrink-0"
                            onClick={applyBoardFilters}
                        >
                            Buscar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="shrink-0"
                            onClick={clearBoardFilters}
                        >
                            Limpiar
                        </Button>
                    </div>
                </div>

                {(can.manageTasks && project.archived_at === null) || can.manageColumns ? (
                    <section className="flex flex-wrap items-center gap-2 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border">
                        {can.manageColumns && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button type="button" size="sm" className="gap-1.5">
                                        <Plus className="size-3.5" aria-hidden />
                                        Nueva columna
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Crear columna</DialogTitle>
                                        <DialogDescription>
                                            Organiza el flujo de trabajo agregando una nueva columna al tablero.
                                        </DialogDescription>
                                    </DialogHeader>
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
                                                    <Label htmlFor="modal-column-name">Nombre</Label>
                                                    <Input
                                                        id="modal-column-name"
                                                        name="name"
                                                        required
                                                        maxLength={255}
                                                        placeholder="Ej. Pendiente"
                                                    />
                                                    <InputError message={errors.name} />
                                                </div>
                                                <Button type="submit" disabled={processing}>
                                                    {processing && <Spinner />}
                                                    Crear columna
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}

                        {can.manageTasks && project.archived_at === null && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button type="button" size="sm" variant="outline" className="gap-1.5">
                                        <Plus className="size-3.5" aria-hidden />
                                        Nueva etiqueta
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Crear etiqueta</DialogTitle>
                                        <DialogDescription>
                                            Las etiquetas te ayudan a clasificar tareas y filtrar el tablero.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...LabelController.store.form({
                                            team: team.id,
                                            project: project.id,
                                        })}
                                        options={{ preserveScroll: true }}
                                        resetOnSuccess={['name', 'color']}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors: le }) => (
                                            <>
                                                <BoardFilterHiddenFields />
                                                <div className="grid gap-2">
                                                    <Label htmlFor="modal-new-label-name">Nombre</Label>
                                                    <Input
                                                        id="modal-new-label-name"
                                                        name="name"
                                                        required
                                                        maxLength={100}
                                                        placeholder="Ej. Bug"
                                                    />
                                                    <InputError message={le.name} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="modal-new-label-color">Color (opcional)</Label>
                                                    <Input
                                                        id="modal-new-label-color"
                                                        name="color"
                                                        maxLength={32}
                                                        placeholder="#ef4444"
                                                    />
                                                    <InputError message={le.color} />
                                                </div>
                                                <Button type="submit" disabled={processing}>
                                                    {processing && <Spinner />}
                                                    Crear etiqueta
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </section>
                ) : null}

                {can.manageTasks && project.archived_at === null && (
                    <section className="space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">Etiquetas del proyecto</h2>
                        {labels.length > 0 ? (
                            <ul className="divide-y rounded-md border text-sm">
                                {labels.map((l) => (
                                    <li
                                        key={l.id}
                                        className="flex flex-wrap items-center justify-between gap-2 p-3"
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <p className="truncate font-medium">{l.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {l.color || 'Sin color'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button type="button" size="sm" variant="secondary" className="gap-1.5">
                                                        <Pencil className="size-3.5" aria-hidden />
                                                        Editar
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>Editar etiqueta</DialogTitle>
                                                        <DialogDescription>
                                                            Cambia el nombre o color de la etiqueta.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <Form
                                                        {...LabelController.update.form({
                                                            team: team.id,
                                                            project: project.id,
                                                            label: l.id,
                                                        })}
                                                        options={{ preserveScroll: true }}
                                                        className="space-y-4"
                                                    >
                                                        {({ processing, errors: ue }) => (
                                                            <>
                                                                <BoardFilterHiddenFields />
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor={`label-name-${l.id}`}>Nombre</Label>
                                                                    <Input
                                                                        id={`label-name-${l.id}`}
                                                                        name="name"
                                                                        required
                                                                        maxLength={100}
                                                                        defaultValue={l.name}
                                                                    />
                                                                    <InputError message={ue.name} />
                                                                </div>
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor={`label-color-${l.id}`}>
                                                                        Color (opcional)
                                                                    </Label>
                                                                    <Input
                                                                        id={`label-color-${l.id}`}
                                                                        name="color"
                                                                        maxLength={32}
                                                                        defaultValue={l.color ?? ''}
                                                                        placeholder="#ef4444"
                                                                    />
                                                                    <InputError message={ue.color} />
                                                                </div>
                                                                <Button type="submit" disabled={processing}>
                                                                    {processing && <Spinner />}
                                                                    Guardar cambios
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    if (
                                                        !window.confirm(
                                                            `Eliminar etiqueta “${l.name}”? Se quitará de todas las tareas.`,
                                                        )
                                                    ) {
                                                        return;
                                                    }

                                                    router.delete(
                                                        LabelController.destroy.url({
                                                            team: team.id,
                                                            project: project.id,
                                                            label: l.id,
                                                        }),
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                            >
                                                Eliminar
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Aun no hay etiquetas. Crea una para organizar mejor el tablero.
                            </p>
                        )}
                    </section>
                )}

                <section className="space-y-3">
                    <div className="space-y-1">
                        <h2 className="text-sm font-medium text-muted-foreground">Columnas</h2>
                        {can.manageTasks && hasActiveBoardFilters(filters) ? (
                            <p className="text-xs text-amber-700 dark:text-amber-400">
                                Con filtros activos no se puede reordenar por arrastre; limpia los filtros o usa mover
                                columna / menú de tarea.
                            </p>
                        ) : null}
                    </div>
                    {columns.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 py-12 text-center dark:border-sidebar-border">
                            <LayoutGrid
                                className="size-10 text-muted-foreground"
                                strokeWidth={1.25}
                                aria-hidden
                            />
                            <p className="text-sm text-muted-foreground">
                                {can.manageColumns
                                    ? 'Crea una columna para estructurar mejor el tablero.'
                                    : 'Aun no hay columnas. Un owner o admin puede crearlas.'}
                            </p>
                        </div>
                    ) : (
                        <BoardTasksDnd
                            teamId={team.id}
                            projectId={project.id}
                            filters={filters}
                            disabled={!boardTaskDnDActive}
                            columns={columns}
                        >
                            {({ taskIdsByColumn, SortableTask, ColumnDropZone }) => (
                                <div className="flex gap-4 overflow-x-auto pb-2">
                                    {columns.map((col, index) => {
                                        const orderedTasks = boardTasksForColumn(col, taskIdsByColumn, taskById);

                                        return (
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
                                        {orderedTasks.length === 0 ? (
                                            <div className="flex flex-col gap-2 p-3">
                                                <p className="text-xs text-muted-foreground">
                                                    No hay tareas en esta columna.
                                                </p>
                                                {boardTaskDnDActive ? (
                                                    <ColumnDropZone columnId={col.id} disabled={false} />
                                                ) : null}
                                            </div>
                                        ) : (
                                            (() => {
                                                const taskList = (
                                                    <ul className="max-h-112 space-y-3 overflow-y-auto p-3">
                                                        {orderedTasks.map((task) => (
                                                            <SortableTask
                                                                key={task.id}
                                                                taskId={task.id}
                                                                disabled={!boardTaskDnDActive}
                                                            >
                                                                {(bag) => (
                                                                    <li
                                                                        ref={bag.setNodeRef}
                                                                        style={bag.style}
                                                                        className={cn(
                                                                            'rounded-md border border-border/60 bg-muted/40 p-2.5 text-sm dark:bg-muted/20',
                                                                            boardTaskDnDActive &&
                                                                                bag.isDragging &&
                                                                                'opacity-70 ring-2 ring-ring/40',
                                                                        )}
                                                                    >
                                                                        <div className="mb-2 flex flex-wrap items-start justify-between gap-2">
                                                                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 text-xs">
                                                                                {boardTaskDnDActive ? (
                                                                                    <button
                                                                                        type="button"
                                                                                        className="text-muted-foreground hover:text-foreground touch-none rounded p-0.5"
                                                                                        ref={bag.setActivatorNodeRef}
                                                                                        aria-label="Arrastrar tarea"
                                                                                        {...bag.listeners}
                                                                                        {...bag.attributes}
                                                                                    >
                                                                                        <GripVertical
                                                                                            className="size-4 shrink-0"
                                                                                            aria-hidden
                                                                                        />
                                                                                    </button>
                                                                                ) : null}
                                                                {task.labels.map((lb) => (
                                                                    <span
                                                                        key={lb.id}
                                                                        className="inline-flex max-w-full items-center truncate rounded-full border px-2 py-0.5 font-medium text-muted-foreground"
                                                                        style={
                                                                            lb.color
                                                                                ? {
                                                                                      borderColor: lb.color,
                                                                                  }
                                                                                : undefined
                                                                        }
                                                                    >
                                                                        {lb.name}
                                                                    </span>
                                                                ))}
                                                                {task.checklist_total > 0 && (
                                                                    <span className="tabular-nums text-muted-foreground">
                                                                        Checklist {task.checklist_done}/
                                                                        {task.checklist_total}
                                                                    </span>
                                                                )}
                                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                                                    {priorityLabel(task.priority)}
                                                                </span>
                                                                {(() => {
                                                                    const hint = dueHint(
                                                                        task.due_date,
                                                                        task.is_completed,
                                                                    );

                                                                    return hint ? (
                                                                        <span className={hint.className}>{hint.text}</span>
                                                                    ) : null;
                                                                })()}
                                                            </div>
                                                            <div className="flex shrink-0 flex-col items-end gap-1">
                                                                <Link
                                                                    href={taskShow.url({
                                                                        team: team.id,
                                                                        project: project.id,
                                                                        task: task.id,
                                                                    })}
                                                                    className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                                >
                                                                    Detalle
                                                                </Link>
                                                                <Link
                                                                    href={taskCommentsIndex.url({
                                                                        team: team.id,
                                                                        project: project.id,
                                                                        task: task.id,
                                                                    })}
                                                                    className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                                >
                                                                    Comentarios
                                                                </Link>
                                                            </div>
                                                        </div>
                                                        <p className="font-medium leading-snug">
                                                            {task.title}
                                                        </p>
                                                        {task.is_completed && (
                                                            <p className="mt-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                                Completada
                                                            </p>
                                                        )}
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
                                                        {task.checklist_total > 0 && (
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Checklist {task.checklist_done}/
                                                                {task.checklist_total}
                                                            </p>
                                                        )}
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {task.labels.map((lb) => (
                                                                <span
                                                                    key={lb.id}
                                                                    className="inline-flex max-w-full truncate rounded-full border px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                                                    style={
                                                                        lb.color
                                                                            ? {
                                                                                  borderColor: lb.color,
                                                                              }
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {lb.name}
                                                                </span>
                                                            ))}
                                                        </div>
                                                        {can.manageTasks ? (
                                                            <div className="mt-2 flex flex-wrap gap-2 border-t border-border/60 pt-2">
                                                                <Dialog>
                                                                    <DialogTrigger asChild>
                                                                        <Button type="button" size="sm" variant="secondary">
                                                                            Editar
                                                                        </Button>
                                                                    </DialogTrigger>
                                                                    <DialogContent className="sm:max-w-2xl">
                                                                        <DialogHeader>
                                                                            <DialogTitle>Editar tarea</DialogTitle>
                                                                            <DialogDescription>
                                                                                Actualiza los datos principales de la tarea.
                                                                            </DialogDescription>
                                                                        </DialogHeader>
                                                                        <Form
                                                                            {...TaskController.update.form({
                                                                                team: team.id,
                                                                                project: project.id,
                                                                                task: task.id,
                                                                            })}
                                                                            options={{ preserveScroll: true }}
                                                                            className="space-y-3"
                                                                        >
                                                                            {({ processing, errors: ue }) => (
                                                                                <>
                                                                                    <BoardFilterHiddenFields />
                                                                                    <div className="grid gap-1">
                                                                                        <Label htmlFor={`task-title-${task.id}`}>
                                                                                            Titulo
                                                                                        </Label>
                                                                                        <Input
                                                                                            id={`task-title-${task.id}`}
                                                                                            name="title"
                                                                                            required
                                                                                            maxLength={255}
                                                                                            defaultValue={task.title}
                                                                                        />
                                                                                        <InputError message={ue.title} />
                                                                                    </div>
                                                                                    <div className="grid gap-1">
                                                                                        <Label htmlFor={`task-desc-${task.id}`}>
                                                                                            Descripcion
                                                                                        </Label>
                                                                                        <textarea
                                                                                            id={`task-desc-${task.id}`}
                                                                                            name="description"
                                                                                            rows={3}
                                                                                            defaultValue={task.description ?? ''}
                                                                                            className={textareaClass}
                                                                                            placeholder="Opcional"
                                                                                        />
                                                                                        <InputError message={ue.description} />
                                                                                    </div>
                                                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                                                        <div className="grid gap-1">
                                                                                            <Label htmlFor={`task-assignee-${task.id}`}>
                                                                                                Asignado a
                                                                                            </Label>
                                                                                            <select
                                                                                                id={`task-assignee-${task.id}`}
                                                                                                name="assignee_id"
                                                                                                defaultValue={task.assignee?.id?.toString() ?? ''}
                                                                                                className={selectClass}
                                                                                            >
                                                                                                <option value="">
                                                                                                    Sin asignar
                                                                                                </option>
                                                                                                {assignableUsers.map((u) => (
                                                                                                    <option key={u.id} value={u.id}>
                                                                                                        {u.name}
                                                                                                    </option>
                                                                                                ))}
                                                                                            </select>
                                                                                            <InputError message={ue.assignee_id} />
                                                                                        </div>
                                                                                        <div className="grid gap-1">
                                                                                            <Label htmlFor={`task-due-${task.id}`}>
                                                                                                Fecha limite
                                                                                            </Label>
                                                                                            <Input
                                                                                                id={`task-due-${task.id}`}
                                                                                                type="date"
                                                                                                name="due_date"
                                                                                                defaultValue={task.due_date ?? ''}
                                                                                            />
                                                                                            <InputError message={ue.due_date} />
                                                                                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                                                <input
                                                                                                    type="checkbox"
                                                                                                    name="clear_due_date"
                                                                                                    value="1"
                                                                                                    className="size-3.5 rounded border"
                                                                                                />
                                                                                                Limpiar fecha limite
                                                                                            </label>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div className="grid gap-1">
                                                                                        <Label htmlFor={`task-priority-${task.id}`}>
                                                                                            Prioridad
                                                                                        </Label>
                                                                                        <select
                                                                                            id={`task-priority-${task.id}`}
                                                                                            name="priority"
                                                                                            defaultValue={task.priority}
                                                                                            className={selectClass}
                                                                                        >
                                                                                            <option value="low">Baja</option>
                                                                                            <option value="medium">Media</option>
                                                                                            <option value="high">Alta</option>
                                                                                        </select>
                                                                                        <InputError message={ue.priority} />
                                                                                    </div>
                                                                                    {labels.length > 0 && (
                                                                                        <div className="grid gap-1">
                                                                                            <input type="hidden" name="sync_label_ids" value="1" />
                                                                                            <Label htmlFor={`task-labels-${task.id}`}>
                                                                                                Etiquetas
                                                                                            </Label>
                                                                                            <select
                                                                                                id={`task-labels-${task.id}`}
                                                                                                name="label_ids[]"
                                                                                                multiple
                                                                                                defaultValue={task.labels.map((lb) => String(lb.id))}
                                                                                                className="border-input bg-background min-h-18 w-full rounded-md border px-2 py-1 text-xs"
                                                                                                size={Math.min(5, labels.length)}
                                                                                            >
                                                                                                {labels.map((lb) => (
                                                                                                    <option key={lb.id} value={lb.id}>
                                                                                                        {lb.name}
                                                                                                    </option>
                                                                                                ))}
                                                                                            </select>
                                                                                            <InputError message={ue.label_ids} />
                                                                                        </div>
                                                                                    )}
                                                                                    <Button type="submit" disabled={processing} className="w-full">
                                                                                        {processing && <Spinner />}
                                                                                        Guardar tarea
                                                                                    </Button>
                                                                                </>
                                                                            )}
                                                                        </Form>
                                                                    </DialogContent>
                                                                </Dialog>

                                                                {otherColumns(col.id).length > 0 && (
                                                                    <Dialog>
                                                                        <DialogTrigger asChild>
                                                                            <Button type="button" size="sm" variant="outline">
                                                                                Mover
                                                                            </Button>
                                                                        </DialogTrigger>
                                                                        <DialogContent>
                                                                            <DialogHeader>
                                                                                <DialogTitle>Mover tarea</DialogTitle>
                                                                                <DialogDescription>
                                                                                    Selecciona la columna de destino para esta tarea.
                                                                                </DialogDescription>
                                                                            </DialogHeader>
                                                                            <Form
                                                                                {...TaskController.move.form({
                                                                                    team: team.id,
                                                                                    project: project.id,
                                                                                    task: task.id,
                                                                                })}
                                                                                options={{ preserveScroll: true }}
                                                                                className="space-y-3"
                                                                            >
                                                                                {({ processing, errors: moveErrors }) => (
                                                                                    <>
                                                                                        <BoardFilterHiddenFields />
                                                                                        <div className="grid gap-1">
                                                                                            <Label htmlFor={`move-task-${task.id}`}>
                                                                                                Columna
                                                                                            </Label>
                                                                                            <select
                                                                                                id={`move-task-${task.id}`}
                                                                                                name="target_column_id"
                                                                                                required
                                                                                                defaultValue=""
                                                                                                className={selectClass}
                                                                                            >
                                                                                                <option value="" disabled>
                                                                                                    Mover a...
                                                                                                </option>
                                                                                                {otherColumns(col.id).map((c) => (
                                                                                                    <option key={c.id} value={c.id}>
                                                                                                        {c.name}
                                                                                                    </option>
                                                                                                ))}
                                                                                            </select>
                                                                                            <InputError message={moveErrors.target_column_id} />
                                                                                        </div>
                                                                                        <Button type="submit" variant="outline" disabled={processing} className="w-full">
                                                                                            {processing && <Spinner />}
                                                                                            Mover tarea
                                                                                        </Button>
                                                                                    </>
                                                                                )}
                                                                            </Form>
                                                                        </DialogContent>
                                                                    </Dialog>
                                                                )}
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="destructive"
                                                                    className="gap-1"
                                                                    onClick={() => setTaskPendingDelete(task)}
                                                                >
                                                                    <Trash2 className="size-3.5" aria-hidden />
                                                                    Eliminar
                                                                </Button>
                                                            </div>
                                                        ) : (
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {priorityLabel(task.priority)}
                                                                {task.due_date ? ` · Vence ${task.due_date}` : ''}
                                                            </p>
                                                        )}
                                                                    </li>
                                                                )}
                                                            </SortableTask>
                                                        ))}
                                                    </ul>
                                                );

                                                return boardTaskDnDActive ? (
                                                    <SortableContext
                                                        items={orderedTasks.map((t) => String(t.id))}
                                                        strategy={verticalListSortingStrategy}
                                                    >
                                                        {taskList}
                                                        <div className="px-3 pb-3">
                                                            <ColumnDropZone columnId={col.id} disabled={false} />
                                                        </div>
                                                    </SortableContext>
                                                ) : (
                                                    taskList
                                                );
                                            })()
                                        )
                                        }
                                    </div>

                                    {can.manageTasks && (
                                        <div className="border-b border-border p-3">
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button type="button" size="sm" className="w-full gap-1.5">
                                                        <Plus className="size-3.5" aria-hidden />
                                                        Nueva tarea
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent className="sm:max-w-2xl">
                                                    <DialogHeader>
                                                        <DialogTitle>Nueva tarea en {col.name}</DialogTitle>
                                                        <DialogDescription>
                                                            Completa los datos para crear la tarea.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <Form
                                                        {...TaskController.store.form({
                                                            team: team.id,
                                                            project: project.id,
                                                            column: col.id,
                                                        })}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        resetOnSuccess={['title', 'description']}
                                                        className="space-y-3"
                                                    >
                                                        {({ processing, errors: te }) => (
                                                            <>
                                                                <BoardFilterHiddenFields />
                                                                <div className="grid gap-1">
                                                                    <Label htmlFor={`new-task-title-${col.id}`}>
                                                                        Titulo
                                                                    </Label>
                                                                    <Input
                                                                        id={`new-task-title-${col.id}`}
                                                                        name="title"
                                                                        required
                                                                        maxLength={255}
                                                                        placeholder="Titulo de la tarea"
                                                                    />
                                                                    <InputError message={te.title} />
                                                                </div>
                                                                <div className="grid gap-1">
                                                                    <Label htmlFor={`new-task-desc-${col.id}`}>
                                                                        Descripcion
                                                                    </Label>
                                                                    <textarea
                                                                        id={`new-task-desc-${col.id}`}
                                                                        name="description"
                                                                        rows={3}
                                                                        className={textareaClass}
                                                                        placeholder="Opcional"
                                                                    />
                                                                    <InputError message={te.description} />
                                                                </div>
                                                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                                    <div className="grid gap-1">
                                                                        <Label htmlFor={`new-task-assignee-${col.id}`}>
                                                                            Asignado a
                                                                        </Label>
                                                                        <select
                                                                            id={`new-task-assignee-${col.id}`}
                                                                            name="assignee_id"
                                                                            defaultValue=""
                                                                            className={selectClass}
                                                                        >
                                                                            <option value="">Sin asignar</option>
                                                                            {assignableUsers.map((u) => (
                                                                                <option key={u.id} value={u.id}>
                                                                                    {u.name}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                        <InputError message={te.assignee_id} />
                                                                    </div>
                                                                    <div className="grid gap-1">
                                                                        <Label htmlFor={`new-task-due-${col.id}`}>
                                                                            Fecha limite
                                                                        </Label>
                                                                        <Input
                                                                            id={`new-task-due-${col.id}`}
                                                                            type="date"
                                                                            name="due_date"
                                                                        />
                                                                        <InputError message={te.due_date} />
                                                                    </div>
                                                                </div>
                                                                <div className="grid gap-1">
                                                                    <Label htmlFor={`new-task-priority-${col.id}`}>
                                                                        Prioridad
                                                                    </Label>
                                                                    <select
                                                                        id={`new-task-priority-${col.id}`}
                                                                        name="priority"
                                                                        defaultValue="medium"
                                                                        className={selectClass}
                                                                    >
                                                                        <option value="low">Baja</option>
                                                                        <option value="medium">Media</option>
                                                                        <option value="high">Alta</option>
                                                                    </select>
                                                                    <InputError message={te.priority} />
                                                                </div>
                                                                {labels.length > 0 && (
                                                                    <div className="grid gap-1">
                                                                        <Label htmlFor={`new-task-labels-${col.id}`}>
                                                                            Etiquetas
                                                                        </Label>
                                                                        <select
                                                                            id={`new-task-labels-${col.id}`}
                                                                            name="label_ids[]"
                                                                            multiple
                                                                            className="border-input bg-background min-h-18 w-full rounded-md border px-2 py-1 text-xs"
                                                                            size={Math.min(5, labels.length)}
                                                                        >
                                                                            {labels.map((lb) => (
                                                                                <option key={lb.id} value={lb.id}>
                                                                                    {lb.name}
                                                                                </option>
                                                                            ))}
                                                                        </select>
                                                                        <InputError message={te.label_ids} />
                                                                    </div>
                                                                )}
                                                                <Button type="submit" disabled={processing} className="w-full">
                                                                    {processing && <Spinner />}
                                                                    Crear tarea
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    )}

                                    {can.manageColumns && (
                                        <div className="flex flex-col gap-4 p-4">
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button type="button" size="sm" variant="secondary">
                                                        Renombrar columna
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>Renombrar columna</DialogTitle>
                                                        <DialogDescription>
                                                            Cambia el nombre visible de la columna.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <Form
                                                        {...ColumnController.update.form({
                                                            team: team.id,
                                                            project: project.id,
                                                            column: col.id,
                                                        })}
                                                        options={{ preserveScroll: true }}
                                                        className="space-y-3"
                                                    >
                                                        {({ processing, errors: fe }) => (
                                                            <>
                                                                <BoardFilterHiddenFields />
                                                                <div className="grid gap-1">
                                                                    <Label htmlFor={`column-rename-${col.id}`}>
                                                                        Nombre
                                                                    </Label>
                                                                    <Input
                                                                        id={`column-rename-${col.id}`}
                                                                        name="name"
                                                                        required
                                                                        maxLength={255}
                                                                        defaultValue={col.name}
                                                                    />
                                                                    <InputError message={fe.name} />
                                                                </div>
                                                                <Button type="submit" disabled={processing}>
                                                                    {processing && <Spinner />}
                                                                    Guardar nombre
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>

                                            <div className="flex flex-wrap items-center gap-2">
                                                <div className="flex gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="size-8"
                                                        disabled={index === 0}
                                                        aria-label="Mover columna a la izquierda"
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
                                                        aria-label="Mover columna a la derecha"
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
                                                    Eliminar
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </article>
                                        );
                                    })}
                                </div>
                            )}
                        </BoardTasksDnd>
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
                title="Eliminar tarea"
                description={
                    taskPendingDelete
                        ? `Eliminar “${taskPendingDelete.title}”? Esta accion no se puede deshacer.`
                        : ''
                }
                confirmLabel="Eliminar tarea"
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
                title="Eliminar columna"
                description={
                    columnPendingDelete
                        ? `Eliminar columna “${columnPendingDelete.name}” y todas sus tareas? Esta accion no se puede deshacer.`
                        : ''
                }
                confirmLabel="Eliminar columna"
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
