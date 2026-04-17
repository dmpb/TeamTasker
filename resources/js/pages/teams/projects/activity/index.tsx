import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CircleCheck, CircleDashed, MessageSquare, MoveRight, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { index as projectActivityIndex } from '@/routes/teams/projects/activity';
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
    actors: { id: number; name: string }[];
    filters: {
        event: string;
        actor_id: number | null;
        date_from: string;
        date_to: string;
        q: string;
    };
};

const EVENT_FILTER_OPTIONS: { value: string; label: string }[] = [
    { value: '', label: 'Any event' },
    { value: 'task.created', label: 'Task created' },
    { value: 'task.updated', label: 'Task updated' },
    { value: 'task.moved', label: 'Task moved' },
    { value: 'task.deleted', label: 'Task deleted' },
    { value: 'comment.created', label: 'Comment created' },
    { value: 'comment.updated', label: 'Comment updated' },
    { value: 'comment.deleted', label: 'Comment deleted' },
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
    const { team, project, activityLogs, actors, filters } = page.props;

    const [draftEvent, setDraftEvent] = useState(filters.event);
    const [draftActorId, setDraftActorId] = useState(
        filters.actor_id != null ? String(filters.actor_id) : '',
    );
    const [draftDateFrom, setDraftDateFrom] = useState(filters.date_from);
    const [draftDateTo, setDraftDateTo] = useState(filters.date_to);
    const [draftQ, setDraftQ] = useState(filters.q);

    useEffect(() => {
        setDraftEvent(filters.event);
        setDraftActorId(filters.actor_id != null ? String(filters.actor_id) : '');
        setDraftDateFrom(filters.date_from);
        setDraftDateTo(filters.date_to);
        setDraftQ(filters.q);
    }, [filters.event, filters.actor_id, filters.date_from, filters.date_to, filters.q]);

    const applyActivityFilters = (): void => {
        const query: Record<string, string> = {};
        if (draftEvent !== '') {
            query.event = draftEvent;
        }
        if (draftActorId !== '') {
            query.actor_id = draftActorId;
        }
        if (draftDateFrom !== '') {
            query.date_from = draftDateFrom;
        }
        if (draftDateTo !== '') {
            query.date_to = draftDateTo;
        }
        if (draftQ.trim() !== '') {
            query.q = draftQ.trim();
        }
        router.get(
            projectActivityIndex.url(
                { team: team.id, project: project.id },
                { query: Object.keys(query).length ? query : undefined },
            ),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const clearActivityFilters = (): void => {
        setDraftEvent('');
        setDraftActorId('');
        setDraftDateFrom('');
        setDraftDateTo('');
        setDraftQ('');
        router.get(
            projectActivityIndex.url({ team: team.id, project: project.id }),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        { title: 'Projects', href: teamProjectsIndex.url({ team: team.id }) },
        { title: project.name, href: projectBoard.url({ team: team.id, project: project.id }) },
        { title: 'Activity', href: projectActivityIndex.url({ team: team.id, project: project.id }) },
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

                <section className="grid gap-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border sm:grid-cols-2 lg:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="activity-event">Event</Label>
                        <select
                            id="activity-event"
                            value={draftEvent}
                            onChange={(e) => setDraftEvent(e.target.value)}
                            className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-2 py-1 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:outline-none"
                        >
                            {EVENT_FILTER_OPTIONS.map((opt) => (
                                <option key={opt.value || 'any'} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="activity-actor">Actor</Label>
                        <select
                            id="activity-actor"
                            value={draftActorId}
                            onChange={(e) => setDraftActorId(e.target.value)}
                            className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-2 py-1 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:outline-none"
                        >
                            <option value="">Anyone</option>
                            {actors.map((a) => (
                                <option key={a.id} value={String(a.id)}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2 sm:col-span-2 lg:col-span-1">
                        <Label htmlFor="activity-q">Search</Label>
                        <Input
                            id="activity-q"
                            value={draftQ}
                            onChange={(e) => setDraftQ(e.target.value)}
                            placeholder="Metadata / description…"
                            maxLength={255}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="activity-from">From date</Label>
                        <Input
                            id="activity-from"
                            type="date"
                            value={draftDateFrom}
                            onChange={(e) => setDraftDateFrom(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="activity-to">To date</Label>
                        <Input
                            id="activity-to"
                            type="date"
                            value={draftDateTo}
                            onChange={(e) => setDraftDateTo(e.target.value)}
                        />
                    </div>
                    <div className="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-3">
                        <Button type="button" size="sm" onClick={applyActivityFilters}>
                            Apply filters
                        </Button>
                        <Button type="button" size="sm" variant="outline" onClick={clearActivityFilters}>
                            Clear
                        </Button>
                    </div>
                </section>

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
