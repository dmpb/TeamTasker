import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { FolderKanban, MoreHorizontal, Pencil, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import { ConfirmDestructiveDialog } from '@/components/confirm-destructive-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationSimple } from '@/components/pagination-simple';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { index as projectActivityIndex } from '@/routes/teams/projects/activity';
import {
    board as projectBoard,
    index as teamProjectsIndex,
} from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type ProjectRow = {
    id: string;
    name: string;
    archived_at: string | null;
};

type ArchiveScope = 'active' | 'all' | 'archived';

type TeamProjectsPageProps = {
    team: {
        id: string;
        name: string;
        owner_id: number;
    };
    projects: {
        data: ProjectRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    can: {
        manageProjects: boolean;
    };
    filters: {
        q: string;
        archive_scope: ArchiveScope;
    };
};

const selectClass = cn(
    'border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

function buildProjectsListQuery(
    qDraft: string,
    archiveScope: ArchiveScope,
): Record<string, string> | undefined {
    const query: Record<string, string> = {};
    const trimmed = qDraft.trim();
    if (trimmed !== '') {
        query.q = trimmed;
    }
    if (archiveScope !== 'active') {
        query.archive_scope = archiveScope;
    }
    return Object.keys(query).length > 0 ? query : undefined;
}

function navigateToProjectsList(
    teamId: string,
    qDraft: string,
    archiveScope: ArchiveScope,
): void {
    router.get(
        teamProjectsIndex.url(
            { team: teamId },
            { query: buildProjectsListQuery(qDraft, archiveScope) },
        ),
        {},
        { preserveScroll: true, replace: true },
    );
}

function initialsFromName(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[1][0]).toUpperCase();
}

type ProjectCardProps = {
    teamId: string;
    row: ProjectRow;
    canManageProjects: boolean;
    onRename: (project: ProjectRow) => void;
    onArchive: (project: ProjectRow) => void;
    onRestore: (project: ProjectRow) => void;
    onDelete: (project: ProjectRow) => void;
};

function ProjectCard({
    teamId,
    row,
    canManageProjects,
    onRename,
    onArchive,
    onRestore,
    onDelete,
}: ProjectCardProps) {
    return (
        <Card className="flex h-full flex-col py-0">
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pt-6">
                <div className="min-w-0 flex items-start gap-3">
                    <div
                        className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary/10 text-sm font-semibold text-primary"
                        aria-hidden
                    >
                        {initialsFromName(row.name)}
                    </div>
                    <div className="min-w-0 space-y-2">
                        <CardTitle className="truncate text-base leading-snug m-0">
                            {row.name}
                        </CardTitle>
                        {row.archived_at ? (
                            <Badge variant="secondary">Archivado</Badge>
                        ) : (
                            <Badge variant="outline">Activo</Badge>
                        )}
                    </div>
                </div>
                {canManageProjects ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="size-9 shrink-0"
                                aria-label="Más acciones del proyecto"
                            >
                                <MoreHorizontal className="size-4" aria-hidden />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {!row.archived_at ? (
                                <DropdownMenuItem
                                    onSelect={() => onRename(row)}
                                >
                                    <Pencil
                                        className="mr-2 size-4"
                                        aria-hidden
                                    />
                                    Renombrar
                                </DropdownMenuItem>
                            ) : null}
                            {!row.archived_at ? (
                                <DropdownMenuItem
                                    data-test={`archive-project-${row.id}`}
                                    onSelect={() => onArchive(row)}
                                >
                                    Archivar
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    data-test={`unarchive-project-${row.id}`}
                                    onSelect={() => onRestore(row)}
                                >
                                    Restaurar
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => onDelete(row)}
                                data-test={`delete-project-${row.id}`}
                            >
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </CardHeader>
            <CardContent className="mt-auto space-y-2 pb-6">
                <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    asChild
                >
                    <Link
                        href={projectBoard.url({
                            team: teamId,
                            project: row.id,
                        })}
                        prefetch
                    >
                        Abrir tablero
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    asChild
                >
                    <Link
                        href={projectActivityIndex.url({
                            team: teamId,
                            project: row.id,
                        })}
                        prefetch
                    >
                        Actividad
                    </Link>
                </Button>
            </CardContent>
        </Card>
    );
}

export default function TeamProjectsIndex() {
    const page = usePage<TeamProjectsPageProps>();
    const { team, projects, can, filters } = page.props;
    const list = projects.data;

    const [draftQ, setDraftQ] = useState(filters.q);
    const [createOpen, setCreateOpen] = useState(false);
    const [renameProject, setRenameProject] = useState<ProjectRow | null>(null);
    const [projectPendingDelete, setProjectPendingDelete] =
        useState<ProjectRow | null>(null);

    useEffect(() => {
        setDraftQ(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const trimmedDraft = draftQ.trim();
        const trimmedFilter = (filters.q ?? '').trim();
        if (trimmedDraft === trimmedFilter) {
            return;
        }

        const handle = window.setTimeout(() => {
            navigateToProjectsList(team.id, draftQ, filters.archive_scope);
        }, 400);

        return () => window.clearTimeout(handle);
    }, [draftQ, team.id, filters.archive_scope, filters.q]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        {
            title: 'Proyectos',
            href: teamProjectsIndex(team.id),
        },
    ];

    const clearProjectSearch = (): void => {
        setDraftQ('');
        navigateToProjectsList(team.id, '', filters.archive_scope);
    };

    const applySearchImmediate = (): void => {
        navigateToProjectsList(team.id, draftQ, filters.archive_scope);
    };

    const setArchiveScope = (scope: ArchiveScope): void => {
        navigateToProjectsList(team.id, draftQ, scope);
    };

    const archiveProject = (project: ProjectRow): void => {
        router.post(
            ProjectController.archive.url({
                team: team.id,
                project: project.id,
            }),
            {},
            { preserveScroll: true },
        );
    };

    const restoreProject = (project: ProjectRow): void => {
        router.post(
            ProjectController.unarchive.url({
                team: team.id,
                project: project.id,
            }),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Proyectos — ${team.name}`} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title="Proyectos"
                        description={`Tableros Kanban del team ${team.name}.`}
                    />
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {can.manageProjects ? (
                            <Button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                            >
                                Nuevo proyecto
                            </Button>
                        ) : null}
                    </div>
                </div>

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Nuevo proyecto</DialogTitle>
                            <DialogDescription>
                                Crea un tablero Kanban para organizar tareas en
                                este team.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...ProjectController.store.form({
                                team: team.id,
                            })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['name']}
                            onSuccess={() => setCreateOpen(false)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="project-name-create">
                                            Nombre
                                        </Label>
                                        <Input
                                            id="project-name-create"
                                            name="name"
                                            required
                                            maxLength={255}
                                            placeholder="Ej. Producto Q2"
                                            autoComplete="off"
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={errors.name}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setCreateOpen(false)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="create-project-button"
                                        >
                                            {processing && <Spinner />}
                                            Crear proyecto
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={renameProject !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setRenameProject(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Renombrar proyecto</DialogTitle>
                            <DialogDescription>
                                Actualiza el nombre visible del proyecto en este
                                team.
                            </DialogDescription>
                        </DialogHeader>
                        {renameProject ? (
                            <Form
                                key={renameProject.id}
                                {...ProjectController.update.form({
                                    team: team.id,
                                    project: renameProject.id,
                                })}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setRenameProject(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="project-name-rename">
                                                Nombre
                                            </Label>
                                            <Input
                                                id="project-name-rename"
                                                name="name"
                                                required
                                                maxLength={255}
                                                defaultValue={renameProject.name}
                                                autoComplete="off"
                                            />
                                            <InputError
                                                className="mt-1"
                                                message={errors.name}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setRenameProject(null)
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test={`update-project-${renameProject.id}`}
                                            >
                                                {processing && <Spinner />}
                                                Guardar
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </DialogContent>
                </Dialog>

                <div className="flex min-w-0 flex-nowrap items-center justify-between gap-3 overflow-visible border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border">
                    <div className="flex shrink-0 items-center gap-2">
                        {can.manageProjects ? (
                            <>
                                <Label
                                    htmlFor="archive-scope"
                                    className="sr-only whitespace-nowrap"
                                >
                                    Estado de proyectos
                                </Label>
                                <select
                                    id="archive-scope"
                                    value={filters.archive_scope}
                                    onChange={(e) =>
                                        setArchiveScope(e.target.value as ArchiveScope)
                                    }
                                    className={cn(selectClass, 'w-32 shrink-0')}
                                >
                                    <option value="all">Todos</option>
                                    <option value="active">Activos</option>
                                    <option value="archived">Archivados</option>
                                </select>
                            </>
                        ) : null}
                    </div>

                    <div className="ml-auto flex w-full max-w-xs shrink-0 items-center gap-2">
                        <div className="relative min-w-0 flex-1">
                            <Label htmlFor="project-search" className="sr-only">
                                Buscar proyecto
                            </Label>
                            <Search
                                className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                id="project-search"
                                value={draftQ}
                                onChange={(e) => setDraftQ(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        applySearchImmediate();
                                    }
                                }}
                                placeholder="Buscar proyecto…"
                                maxLength={255}
                                className={
                                    draftQ.trim() !== ''
                                        ? 'h-9 pl-9 pr-9'
                                        : 'h-9 pl-9 pr-2.5'
                                }
                            />
                            {draftQ.trim() !== '' ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="absolute right-1 top-1/2 size-7 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    onClick={clearProjectSearch}
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
                            onClick={applySearchImmediate}
                        >
                            Buscar
                        </Button>
                    </div>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Todos los proyectos
                    </h2>
                    {list.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                            <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                                <FolderKanban
                                    className="size-7 text-muted-foreground"
                                    aria-hidden
                                />
                            </div>
                            <div className="max-w-md space-y-2 px-4">
                                <p className="text-base font-medium">
                                    Aún no hay proyectos
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {can.manageProjects
                                        ? 'Crea el primero para planificar el trabajo de este team en tableros Kanban.'
                                        : 'Un propietario o admin del team puede crear proyectos.'}
                                </p>
                            </div>
                            {can.manageProjects ? (
                                <Button
                                    type="button"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    Crear proyecto
                                </Button>
                            ) : null}
                        </div>
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {list.map((row) => (
                                <li key={row.id}>
                                    <ProjectCard
                                        teamId={team.id}
                                        row={row}
                                        canManageProjects={can.manageProjects}
                                        onRename={setRenameProject}
                                        onArchive={archiveProject}
                                        onRestore={restoreProject}
                                        onDelete={setProjectPendingDelete}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    <PaginationSimple
                        currentPage={projects.current_page}
                        lastPage={projects.last_page}
                        prevPageUrl={projects.prev_page_url}
                        nextPageUrl={projects.next_page_url}
                    />
                </section>
            </div>

            <ConfirmDestructiveDialog
                open={projectPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setProjectPendingDelete(null);
                    }
                }}
                title="Eliminar proyecto"
                description={
                    projectPendingDelete
                        ? `¿Eliminar permanentemente «${projectPendingDelete.name}»? Esta acción no se puede deshacer.`
                        : ''
                }
                confirmLabel="Eliminar proyecto"
                onConfirm={() => {
                    const p = projectPendingDelete;
                    if (!p) {
                        return;
                    }
                    setProjectPendingDelete(null);
                    router.delete(
                        ProjectController.destroy.url({
                            team: team.id,
                            project: p.id,
                        }),
                        { preserveScroll: true },
                    );
                }}
            />
        </AppLayout>
    );
}
