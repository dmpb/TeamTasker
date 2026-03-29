import { Form, Head, Link, usePage } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import {
    board as projectBoard,
    index as teamProjectsIndex,
} from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type ProjectRow = {
    id: number;
    name: string;
    archived_at: string | null;
};

type TeamProjectsPageProps = {
    team: {
        id: number;
        name: string;
        owner_id: number;
    };
    projects: ProjectRow[];
    can: {
        manageProjects: boolean;
        showArchived: boolean;
    };
};

export default function TeamProjectsIndex() {
    const page = usePage<TeamProjectsPageProps>();
    const { team, projects, can } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        {
            title: 'Projects',
            href: teamProjectsIndex(team.id),
        },
    ];

    const archivedListUrl = teamProjectsIndex.url(
        { team: team.id },
        { query: { include_archived: '1' } },
    );
    const activeListUrl = teamProjectsIndex.url({ team: team.id });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${team.name} — Projects`} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title="Projects"
                        description={`Kanban projects for ${team.name}.`}
                    />
                    <div className="flex flex-col gap-2 sm:items-end">
                        <Link
                            href={teamsShow(team.id)}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Team settings
                        </Link>
                        {can.manageProjects && (
                            <>
                                {can.showArchived ? (
                                    <Link
                                        href={activeListUrl}
                                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                    >
                                        Hide archived
                                    </Link>
                                ) : (
                                    <Link
                                        href={archivedListUrl}
                                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                    >
                                        Show archived
                                    </Link>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {can.manageProjects && (
                    <section className="max-w-md space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            New project
                        </h2>
                        <Form
                            {...ProjectController.store.form({ team: team.id })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['name']}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="project-name">Name</Label>
                                        <Input
                                            id="project-name"
                                            name="name"
                                            required
                                            maxLength={255}
                                            className="mt-1 block w-full"
                                            placeholder="Project name"
                                        />
                                        <InputError
                                            className="mt-2"
                                            message={errors.name}
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="create-project-button"
                                    >
                                        {processing && <Spinner />}
                                        Create project
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        All projects
                    </h2>
                    <ul className="divide-y rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        {projects.length === 0 ? (
                            <li className="flex flex-col items-center gap-3 px-3 py-12 text-center">
                                <FolderKanban
                                    className="size-10 text-muted-foreground"
                                    strokeWidth={1.25}
                                    aria-hidden
                                />
                                <div className="space-y-1">
                                    <p className="text-sm font-medium text-foreground">
                                        No projects yet
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {can.manageProjects
                                            ? 'Create one above to start planning work in this team.'
                                            : 'An owner or admin can add projects for this team.'}
                                    </p>
                                </div>
                            </li>
                        ) : (
                            projects.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-col gap-3 px-3 py-3 lg:flex-row lg:items-start lg:justify-between"
                                >
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <Link
                                            href={projectBoard.url({
                                                team: team.id,
                                                project: row.id,
                                            })}
                                            className="text-sm font-medium text-foreground underline-offset-4 hover:underline"
                                        >
                                            {row.name}
                                        </Link>
                                        {row.archived_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Archived
                                            </p>
                                        )}
                                    </div>
                                    {can.manageProjects && (
                                        <div className="flex flex-col gap-3 lg:min-w-[280px] lg:items-end">
                                            {!row.archived_at && (
                                                <Form
                                                    {...ProjectController.update.form(
                                                        {
                                                            team: team.id,
                                                            project: row.id,
                                                        },
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    className="flex w-full flex-col gap-2 sm:flex-row sm:items-end"
                                                >
                                                    {({
                                                        processing,
                                                        errors: fe,
                                                    }) => (
                                                        <>
                                                            <div className="grid flex-1 gap-1">
                                                                <Label
                                                                    className="sr-only"
                                                                    htmlFor={`project-name-${row.id}`}
                                                                >
                                                                    Rename
                                                                </Label>
                                                                <Input
                                                                    id={`project-name-${row.id}`}
                                                                    name="name"
                                                                    required
                                                                    maxLength={
                                                                        255
                                                                    }
                                                                    defaultValue={
                                                                        row.name
                                                                    }
                                                                    className="block w-full"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        fe.name
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
                                                                data-test={`update-project-${row.id}`}
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                Save
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                            <div className="flex flex-wrap gap-2">
                                                {!row.archived_at && (
                                                    <Form
                                                        {...ProjectController.archive.form(
                                                            {
                                                                team: team.id,
                                                                project:
                                                                    row.id,
                                                            },
                                                        )}
                                                        options={{
                                                            preserveScroll:
                                                                true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`archive-project-${row.id}`}
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                Archive
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                {row.archived_at && (
                                                    <Form
                                                        {...ProjectController.unarchive.form(
                                                            {
                                                                team: team.id,
                                                                project:
                                                                    row.id,
                                                            },
                                                        )}
                                                        options={{
                                                            preserveScroll:
                                                                true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`unarchive-project-${row.id}`}
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                Restore
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                <Form
                                                    {...ProjectController.destroy.form(
                                                        {
                                                            team: team.id,
                                                            project: row.id,
                                                        },
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Permanently delete “${row.name}”?`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                            data-test={`delete-project-${row.id}`}
                                                        >
                                                            {processing && (
                                                                <Spinner />
                                                            )}
                                                            Delete
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                    )}
                                </li>
                            ))
                        )}
                    </ul>
                </section>

                <p className="text-xs text-muted-foreground">
                    <Link
                        href={teamsIndex()}
                        className="underline-offset-4 hover:underline"
                    >
                        Back to all teams
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
