import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardList, FolderKanban, LayoutGrid, ListTodo, Sparkles } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as teamsIndex } from '@/routes/teams';
import {
    board as projectBoard,
    index as teamProjectsIndex,
} from '@/routes/teams/projects';
import { index as projectActivityIndex } from '@/routes/teams/projects/activity/index';
import { index as taskCommentsIndex } from '@/routes/teams/projects/tasks/comments/index';
import type { BreadcrumbItem } from '@/types';

type TaskRow = {
    id: number;
    title: string;
    updated_at: string | null;
    project: { id: number; name: string };
    team: { id: number; name: string };
    column: { id: number; name: string };
    assignee: { id: number; name: string } | null;
};

type ProjectRow = {
    id: number;
    name: string;
    team: { id: number; name: string };
};

type ActivityRow = {
    id: number;
    event: string;
    created_at: string | null;
    actor: { id: number; name: string } | null;
    project: { id: number; name: string };
    team: { id: number; name: string };
};

type DashboardPageProps = {
    stats: {
        my_assigned_tasks: number;
        active_projects: number;
    };
    myTasks: TaskRow[];
    recentTasks: TaskRow[];
    activeProjects: ProjectRow[];
    recentActivity: ActivityRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

function eventLabel(event: string): string {
    const labels: Record<string, string> = {
        'task.created': 'Task created',
        'task.updated': 'Task updated',
        'task.moved': 'Task moved',
        'task.deleted': 'Task deleted',
        'comment.created': 'Comment created',
        'comment.updated': 'Comment updated',
        'comment.deleted': 'Comment deleted',
    };

    return labels[event] ?? event;
}

export default function Dashboard() {
    const page = usePage<DashboardPageProps>();
    const { stats, myTasks, recentTasks, activeProjects, recentActivity } = page.props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-8 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Your teams, projects, and tasks at a glance.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={teamsIndex()}>All teams</Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">My assigned tasks</CardTitle>
                            <ListTodo className="text-muted-foreground size-4" aria-hidden />
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold tabular-nums">
                                {stats.my_assigned_tasks}
                            </p>
                            <CardDescription className="mt-1">
                                Across all teams you belong to
                            </CardDescription>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active projects</CardTitle>
                            <FolderKanban className="text-muted-foreground size-4" aria-hidden />
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold tabular-nums">
                                {stats.active_projects}
                            </p>
                            <CardDescription className="mt-1">
                                Non-archived projects
                            </CardDescription>
                        </CardContent>
                    </Card>
                    <Card className="sm:col-span-2 lg:col-span-1">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Quick actions</CardTitle>
                            <Sparkles className="text-muted-foreground size-4" aria-hidden />
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Button variant="secondary" size="sm" asChild className="w-full justify-start">
                                <Link href={teamsIndex()}>
                                    <LayoutGrid className="mr-2 size-4" aria-hidden />
                                    Manage teams
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">My tasks</CardTitle>
                            <CardDescription>Assigned to you, most recently updated first</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {myTasks.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No assigned tasks yet. Open a board and assign yourself.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {myTasks.map((t) => (
                                        <li
                                            key={t.id}
                                            className="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{t.title}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {t.team.name} · {t.project.name} · {t.column.name}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 flex-wrap gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link
                                                        href={projectBoard.url({
                                                            team: t.team.id,
                                                            project: t.project.id,
                                                        })}
                                                    >
                                                        Board
                                                    </Link>
                                                </Button>
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link
                                                        href={taskCommentsIndex.url({
                                                            team: t.team.id,
                                                            project: t.project.id,
                                                            task: t.id,
                                                        })}
                                                    >
                                                        <ClipboardList className="mr-1 size-3.5" aria-hidden />
                                                        Comments
                                                    </Link>
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Recent activity</CardTitle>
                            <CardDescription>Latest events across your projects</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentActivity.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No activity yet. Create tasks or comments in a project.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {recentActivity.map((log) => (
                                        <li
                                            key={log.id}
                                            className="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">{eventLabel(log.event)}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {log.actor?.name ?? 'System'} · {log.team.name} ·{' '}
                                                    {log.project.name}
                                                </p>
                                            </div>
                                            <Button variant="outline" size="sm" asChild className="shrink-0">
                                                <Link
                                                    href={projectActivityIndex.url({
                                                        team: log.team.id,
                                                        project: log.project.id,
                                                    })}
                                                >
                                                    Activity
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Recently updated tasks</CardTitle>
                            <CardDescription>Any assignee in your accessible projects</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentTasks.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No tasks yet.</p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {recentTasks.map((t) => (
                                        <li
                                            key={t.id}
                                            className="flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{t.title}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {t.team.name} · {t.project.name}
                                                    {t.assignee ? ` · ${t.assignee.name}` : ''}
                                                </p>
                                            </div>
                                            <Button variant="link" size="sm" className="h-auto shrink-0 px-0" asChild>
                                                <Link
                                                    href={projectBoard.url({
                                                        team: t.team.id,
                                                        project: t.project.id,
                                                    })}
                                                >
                                                    Open board
                                                </Link>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Active projects</CardTitle>
                            <CardDescription>Jump into a project</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {activeProjects.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No projects yet. Create a team, then add a project.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {activeProjects.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex items-center justify-between gap-2 px-3 py-2.5"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{p.name}</p>
                                                <p className="text-muted-foreground text-xs">{p.team.name}</p>
                                            </div>
                                            <div className="flex shrink-0 gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link
                                                        href={teamProjectsIndex.url({ team: p.team.id })}
                                                    >
                                                        Projects
                                                    </Link>
                                                </Button>
                                                <Button size="sm" asChild>
                                                    <Link
                                                        href={projectBoard.url({
                                                            team: p.team.id,
                                                            project: p.id,
                                                        })}
                                                    >
                                                        Board
                                                    </Link>
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
