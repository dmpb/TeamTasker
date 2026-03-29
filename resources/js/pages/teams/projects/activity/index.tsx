import { Head, Link, usePage } from '@inertiajs/react';
import { CircleCheck, CircleDashed, MessageSquare, MoveRight, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { board as projectBoard, index as teamProjectsIndex } from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type ActivityLogRow = {
    id: number;
    event: string;
    created_at: string | null;
    actor: { id: number; name: string } | null;
    subject: { type: string; id: number | null };
    metadata: Record<string, unknown> | null;
};

type ProjectActivityPageProps = {
    team: { id: number; name: string };
    project: { id: number; name: string };
    activityLogs: ActivityLogRow[];
};

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

function eventDescription(log: ActivityLogRow): string {
    if (log.event === 'task.moved') {
        const fromColumnId = log.metadata?.from_column_id;
        const toColumnId = log.metadata?.to_column_id;

        return `Column ${String(fromColumnId)} -> ${String(toColumnId)}`;
    }

    if (log.event === 'task.created') {
        const columnId = log.metadata?.column_id;
        const assigneeId = log.metadata?.assignee_id;

        return `Column ${String(columnId)}${assigneeId ? ` · Assignee ${String(assigneeId)}` : ''}`;
    }

    if (log.event.startsWith('comment.')) {
        const taskId = log.metadata?.task_id;

        return `Task ${String(taskId)}`;
    }

    return `${log.subject.type} #${String(log.subject.id)}`;
}

function eventIcon(event: string) {
    if (event.includes('created')) {
        return <CircleCheck className="size-4 text-emerald-500" aria-hidden />;
    }

    if (event.includes('updated')) {
        return <Pencil className="size-4 text-amber-500" aria-hidden />;
    }

    if (event.includes('moved')) {
        return <MoveRight className="size-4 text-blue-500" aria-hidden />;
    }

    if (event.includes('deleted')) {
        return <Trash2 className="size-4 text-rose-500" aria-hidden />;
    }

    if (event.startsWith('comment.')) {
        return <MessageSquare className="size-4 text-indigo-500" aria-hidden />;
    }

    return <CircleDashed className="size-4 text-muted-foreground" aria-hidden />;
}

function formatDateTime(iso: string | null): string {
    if (! iso) {
        return 'Unknown date';
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function ProjectActivityIndex() {
    const page = usePage<ProjectActivityPageProps>();
    const { team, project, activityLogs } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        { title: 'Projects', href: teamProjectsIndex.url({ team: team.id }) },
        { title: project.name, href: projectBoard.url({ team: team.id, project: project.id }) },
        { title: 'Activity' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${project.name} — Activity`} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold tracking-tight">Project activity</h1>
                    <Link
                        href={projectBoard.url({ team: team.id, project: project.id })}
                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Back to board
                    </Link>
                </div>

                <p className="text-sm text-muted-foreground">
                    {activityLogs.length} events loaded.
                </p>

                {activityLogs.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        No activity yet.
                    </div>
                ) : (
                    <ol className="space-y-3">
                        {activityLogs.map((log) => (
                            <li
                                key={log.id}
                                className="rounded-md border border-border bg-card p-4 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        {eventIcon(log.event)}
                                        <p className="text-sm font-medium">{eventLabel(log.event)}</p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDateTime(log.created_at)}
                                    </p>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {log.actor ? log.actor.name : 'System'} · {eventDescription(log)}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </AppLayout>
    );
}
