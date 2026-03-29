import { Form, Head, Link, usePage } from '@inertiajs/react';
import { MessageSquare, PencilLine, Trash2 } from 'lucide-react';
import CommentController from '@/actions/App/Http/Controllers/CommentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import {
    board as projectBoard,
    index as teamProjectsIndex,
} from '@/routes/teams/projects';
import type { Auth, BreadcrumbItem } from '@/types';

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground flex min-h-[6rem] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

type CommentRow = {
    id: number;
    body: string;
    created_at: string | null;
    user: {
        id: number;
        name: string;
    };
    can: {
        update: boolean;
        delete: boolean;
    };
};

type TaskCommentsPageProps = {
    auth: Auth;
    team: {
        id: number;
        name: string;
    };
    project: {
        id: number;
        name: string;
    };
    task: {
        id: number;
        title: string;
    };
    can: {
        createComments: boolean;
    };
    comments: CommentRow[];
};

function formatDateTime(iso: string | null): string {
    if (! iso) {
        return 'Unknown date';
    }

    const date = new Date(iso);

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export default function TaskComments() {
    const page = usePage<TaskCommentsPageProps>();
    const { auth, team, project, task, can, comments } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        {
            title: 'Projects',
            href: teamProjectsIndex.url({ team: team.id }),
        },
        {
            title: project.name,
            href: projectBoard.url({ team: team.id, project: project.id }),
        },
        {
            title: task.title,
            href: CommentController.index.url({
                team: team.id,
                project: project.id,
                task: task.id,
            }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${task.title} — Comments`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <h1 className="text-lg font-semibold tracking-tight">
                        {task.title}
                    </h1>
                    <div className="flex items-center gap-3">
                        <p className="text-muted-foreground text-sm">
                            Comments ({comments.length})
                        </p>
                        <Link
                            href={projectBoard.url({ team: team.id, project: project.id })}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Back to board
                        </Link>
                    </div>
                </div>

                {can.createComments && (
                    <section className="space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Add comment
                        </h2>
                        <Form
                            {...CommentController.store.form({
                                team: team.id,
                                project: project.id,
                                task: task.id,
                            })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['body']}
                            className="space-y-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="comment-body">Comment</Label>
                                        <textarea
                                            id="comment-body"
                                            name="body"
                                            required
                                            maxLength={10000}
                                            className={textareaClass}
                                            placeholder="Write your comment..."
                                        />
                                        <InputError
                                            message={errors.body}
                                            className="mt-1"
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Publish
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <ul className="space-y-4">
                    {comments.length === 0 && (
                        <li className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-sidebar-border/70 py-10 text-center dark:border-sidebar-border">
                            <MessageSquare className="size-8 text-muted-foreground" aria-hidden />
                            <p className="text-sm text-muted-foreground">
                                No comments yet.
                            </p>
                        </li>
                    )}
                    {comments.map((comment) => (
                        <li
                            key={comment.id}
                            className="border-border rounded-md border p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm font-medium">{comment.user.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDateTime(comment.created_at)}
                                    </p>
                                </div>
                                {comment.user.id === auth.user.id && (
                                    <span className="rounded bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
                                        You
                                    </span>
                                )}
                            </div>
                            <p className="text-muted-foreground mt-1 whitespace-pre-wrap text-sm">
                                {comment.body}
                            </p>

                            {(comment.can.update || comment.can.delete) && (
                                <div className="mt-4 flex flex-col gap-3 border-t border-border pt-3">
                                    {comment.can.update && (
                                        <Form
                                            {...CommentController.update.form({
                                                team: team.id,
                                                project: project.id,
                                                task: task.id,
                                                comment: comment.id,
                                            })}
                                            options={{ preserveScroll: true }}
                                            className="space-y-2"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <Label
                                                        htmlFor={`comment-edit-${comment.id}`}
                                                        className="text-xs text-muted-foreground"
                                                    >
                                                        <span className="inline-flex items-center gap-1">
                                                            <PencilLine className="size-3.5" aria-hidden />
                                                            Edit comment
                                                        </span>
                                                    </Label>
                                                    <textarea
                                                        id={`comment-edit-${comment.id}`}
                                                        name="body"
                                                        required
                                                        maxLength={10000}
                                                        defaultValue={comment.body}
                                                        className={textareaClass}
                                                    />
                                                    <InputError
                                                        message={errors.body}
                                                        className="mt-1"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        {processing && <Spinner />}
                                                        Save
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}

                                    {comment.can.delete && (
                                        <Form
                                            {...CommentController.destroy.form({
                                                team: team.id,
                                                project: project.id,
                                                task: task.id,
                                                comment: comment.id,
                                            })}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing && <Spinner />}
                                                    <Trash2 className="size-4" aria-hidden />
                                                    Delete
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </div>
        </AppLayout>
    );
}
