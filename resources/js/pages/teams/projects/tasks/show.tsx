import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import TaskChecklistItemController from '@/actions/App/Http/Controllers/TaskChecklistItemController';
import TaskCompletionController from '@/actions/App/Http/Controllers/TaskCompletionController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import TaskDependencyController from '@/actions/App/Http/Controllers/TaskDependencyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import {
    board as projectBoard,
    index as teamProjectsIndex,
} from '@/routes/teams/projects';
import { index as taskCommentsIndex } from '@/routes/teams/projects/tasks/comments/index';
import type { BreadcrumbItem } from '@/types';

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground flex min-h-[6rem] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

type LabelRow = { id: number; name: string; color: string | null };
type AssignableUser = { id: number; name: string };
type ChecklistItemRow = { id: number; title: string; position: number; is_completed: boolean };
type DependencyRow = { prerequisite_task_id: number; title: string; is_completed: boolean };
type ProjectTaskOption = { id: number; title: string; is_completed: boolean };

type TaskShowPageProps = {
    team: { id: number; name: string; owner_id: number };
    project: { id: number; name: string; archived_at: string | null };
    labels: LabelRow[];
    project_tasks: ProjectTaskOption[];
    assignableUsers: AssignableUser[];
    task: {
        id: number;
        title: string;
        description: string | null;
        due_date: string | null;
        priority: string;
        completed_at: string | null;
        is_completed: boolean;
        column: { id: number; name: string };
        assignee: { id: number; name: string } | null;
        label_ids: number[];
        checklist_items: ChecklistItemRow[];
        dependencies: DependencyRow[];
    };
    can: { manageTasks: boolean };
};

function priorityLabel(p: string): string {
    if (p === 'high') {
        return 'High';
    }
    if (p === 'low') {
        return 'Low';
    }
    return 'Medium';
}

export default function TaskShow() {
    const page = usePage<TaskShowPageProps & { errors: Record<string, string> }>();
    const { team, project, labels, project_tasks, assignableUsers, task, can } = page.props;
    const errors = page.props.errors ?? {};

    const [orderedChecklistIds, setOrderedChecklistIds] = useState<number[]>(() =>
        [...task.checklist_items].sort((a, b) => a.position - b.position).map((i) => i.id),
    );

    const checklistById = useMemo(() => {
        const m = new Map<number, ChecklistItemRow>();
        task.checklist_items.forEach((i) => m.set(i.id, i));

        return m;
    }, [task.checklist_items]);

    useEffect(() => {
        setOrderedChecklistIds(
            [...task.checklist_items].sort((a, b) => a.position - b.position).map((i) => i.id),
        );
    }, [task.checklist_items]);

    const depIds = new Set(task.dependencies.map((d) => d.prerequisite_task_id));
    const prerequisiteOptions = project_tasks.filter((t) => !depIds.has(t.id));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: team.name, href: teamsShow(team.id) },
        { title: 'Projects', href: teamProjectsIndex(team.id) },
        {
            title: project.name,
            href: projectBoard.url({ team: team.id, project: project.id }),
        },
        { title: task.title, href: '#' },
    ];

    const moveChecklist = (id: number, dir: -1 | 1): void => {
        setOrderedChecklistIds((prev) => {
            const idx = prev.indexOf(id);
            if (idx < 0) {
                return prev;
            }
            const j = idx + dir;
            if (j < 0 || j >= prev.length) {
                return prev;
            }
            const next = [...prev];
            [next[idx], next[j]] = [next[j], next[idx]];

            return next;
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${task.title} — Task`} />

            <div className="space-y-8 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title={task.title}
                        description={`Column: ${task.column.name}`}
                    />
                    <div className="flex flex-col items-start gap-2 sm:items-end">
                        <Link
                            href={projectBoard.url({ team: team.id, project: project.id })}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Back to board
                        </Link>
                        <Link
                            href={taskCommentsIndex.url({
                                team: team.id,
                                project: project.id,
                                task: task.id,
                            })}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Comments
                        </Link>
                    </div>
                </div>

                {project.archived_at && (
                    <p className="text-sm text-muted-foreground">
                        This project is archived. Editing may be limited.
                    </p>
                )}

                <InputError className="text-sm" message={errors.complete} />
                <InputError className="text-sm" message={errors.dependency} />

                {task.is_completed && (
                    <p className="text-sm font-medium text-emerald-600 dark:text-emerald-400">Marked as done.</p>
                )}

                {can.manageTasks && project.archived_at === null && (
                    <div className="flex flex-wrap gap-2">
                        {!task.is_completed ? (
                            <Form
                                {...TaskCompletionController.store.form({
                                    team: team.id,
                                    project: project.id,
                                    task: task.id,
                                })}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" size="sm" disabled={processing}>
                                        {processing && <Spinner />}
                                        Mark complete
                                    </Button>
                                )}
                            </Form>
                        ) : (
                            <Form
                                {...TaskCompletionController.destroy.form({
                                    team: team.id,
                                    project: project.id,
                                    task: task.id,
                                })}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" size="sm" variant="outline" disabled={processing}>
                                        {processing && <Spinner />}
                                        Reopen task
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}

                {can.manageTasks && project.archived_at === null ? (
                    <section className="max-w-2xl space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">Task details</h2>
                        <Form
                            {...TaskController.update.form({
                                team: team.id,
                                project: project.id,
                                task: task.id,
                            })}
                            options={{ preserveScroll: true }}
                            className="space-y-3"
                        >
                            {({ processing, errors: fe }) => (
                                <>
                                    <input type="hidden" name="return_to_task" value="1" />
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-title">Title</Label>
                                        <Input
                                            id="task-title"
                                            name="title"
                                            required
                                            maxLength={255}
                                            defaultValue={task.title}
                                        />
                                        <InputError message={fe.title} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-desc">Description</Label>
                                        <textarea
                                            id="task-desc"
                                            name="description"
                                            rows={4}
                                            defaultValue={task.description ?? ''}
                                            className={textareaClass}
                                            placeholder="Optional"
                                        />
                                        <InputError message={fe.description} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-assignee">Assignee</Label>
                                        <select
                                            id="task-assignee"
                                            name="assignee_id"
                                            defaultValue={task.assignee?.id?.toString() ?? ''}
                                            className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                        >
                                            <option value="">Unassigned</option>
                                            {assignableUsers.map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={fe.assignee_id} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-due">Due date</Label>
                                        <Input
                                            id="task-due"
                                            type="date"
                                            name="due_date"
                                            defaultValue={task.due_date ?? ''}
                                        />
                                        <InputError message={fe.due_date} />
                                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <input
                                                type="checkbox"
                                                name="clear_due_date"
                                                value="1"
                                                className="size-3.5 rounded border"
                                            />
                                            Clear due date
                                        </label>
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-priority">Priority</Label>
                                        <select
                                            id="task-priority"
                                            name="priority"
                                            defaultValue={task.priority}
                                            className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                        >
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                        <InputError message={fe.priority} />
                                    </div>
                                    {labels.length > 0 && (
                                        <div className="grid gap-1">
                                            <input type="hidden" name="sync_label_ids" value="1" />
                                            <Label htmlFor="task-labels">Labels</Label>
                                            <select
                                                id="task-labels"
                                                name="label_ids[]"
                                                multiple
                                                defaultValue={task.label_ids.map(String)}
                                                className="border-input bg-background min-h-[5rem] w-full rounded-md border px-2 py-1 text-sm"
                                                size={Math.min(6, labels.length)}
                                            >
                                                {labels.map((lb) => (
                                                    <option key={lb.id} value={lb.id}>
                                                        {lb.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={fe.label_ids} />
                                        </div>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save changes
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                ) : (
                    <section className="max-w-2xl space-y-2 rounded-md border p-4 text-sm">
                        <p>{task.description || 'No description.'}</p>
                        <p className="text-muted-foreground">
                            Assignee: {task.assignee?.name ?? 'Unassigned'} · Priority:{' '}
                            {priorityLabel(task.priority)}
                            {task.due_date ? ` · Due ${task.due_date}` : ''}
                        </p>
                    </section>
                )}

                {can.manageTasks && project.archived_at === null && (
                    <>
                        <section className="max-w-2xl space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium text-muted-foreground">Checklist</h2>
                            <Form
                                {...TaskChecklistItemController.store.form({
                                    team: team.id,
                                    project: project.id,
                                    task: task.id,
                                })}
                                options={{ preserveScroll: true }}
                                resetOnSuccess={['title']}
                                className="flex flex-col gap-2 sm:flex-row sm:items-end"
                            >
                                {({ processing, errors: ce }) => (
                                    <>
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <Label htmlFor="new-checklist-title" className="text-xs">
                                                New item
                                            </Label>
                                            <Input
                                                id="new-checklist-title"
                                                name="title"
                                                required
                                                maxLength={500}
                                                placeholder="Checklist item title"
                                            />
                                            <InputError message={ce.title} />
                                        </div>
                                        <Button type="submit" size="sm" disabled={processing}>
                                            {processing && <Spinner />}
                                            Add
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {orderedChecklistIds.length > 0 && (
                                <Form
                                    {...TaskChecklistItemController.reorder.form({
                                        team: team.id,
                                        project: project.id,
                                        task: task.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                    className="space-y-2"
                                >
                                    {({ processing }) => (
                                        <>
                                            {orderedChecklistIds.map((id) => (
                                                <input key={id} type="hidden" name="item_ids[]" value={id} />
                                            ))}
                                            <Button type="submit" size="sm" variant="secondary" disabled={processing}>
                                                {processing && <Spinner />}
                                                Save checklist order
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}

                            <ul className="space-y-4">
                                {orderedChecklistIds.map((id) => {
                                    const item = checklistById.get(id);
                                    if (!item) {
                                        return null;
                                    }

                                    return (
                                        <li
                                            key={id}
                                            className="rounded-md border border-border/70 p-3 dark:border-border"
                                        >
                                            <div className="mb-2 flex gap-1">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="size-8"
                                                    aria-label="Move up"
                                                    onClick={() => moveChecklist(id, -1)}
                                                >
                                                    <ChevronUp className="size-4" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="size-8"
                                                    aria-label="Move down"
                                                    onClick={() => moveChecklist(id, 1)}
                                                >
                                                    <ChevronDown className="size-4" />
                                                </Button>
                                            </div>
                                            <Form
                                                {...TaskChecklistItemController.update.form({
                                                    team: team.id,
                                                    project: project.id,
                                                    task: task.id,
                                                    checklistItem: id,
                                                })}
                                                options={{ preserveScroll: true }}
                                                className="space-y-2"
                                            >
                                                {({ processing, errors: ie }) => (
                                                    <>
                                                        <div className="grid gap-1">
                                                            <Label className="text-xs" htmlFor={`cl-title-${id}`}>
                                                                Title
                                                            </Label>
                                                            <Input
                                                                id={`cl-title-${id}`}
                                                                name="title"
                                                                required
                                                                maxLength={500}
                                                                defaultValue={item.title}
                                                            />
                                                            <InputError message={ie.title} />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <Label className="text-xs" htmlFor={`cl-done-${id}`}>
                                                                Done
                                                            </Label>
                                                            <select
                                                                id={`cl-done-${id}`}
                                                                name="is_completed"
                                                                defaultValue={item.is_completed ? '1' : '0'}
                                                                className="border-input bg-background h-9 max-w-xs rounded-md border px-2 text-sm"
                                                            >
                                                                <option value="0">No</option>
                                                                <option value="1">Yes</option>
                                                            </select>
                                                            <InputError message={ie.is_completed} />
                                                        </div>
                                                        <Button type="submit" size="sm" disabled={processing}>
                                                            {processing && <Spinner />}
                                                            Update item
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                            <Form
                                                {...TaskChecklistItemController.destroy.form({
                                                    team: team.id,
                                                    project: project.id,
                                                    task: task.id,
                                                    checklistItem: id,
                                                })}
                                                options={{ preserveScroll: true }}
                                                className="mt-2"
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={processing}
                                                    >
                                                        {processing && <Spinner />}
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>

                        <section className="max-w-2xl space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium text-muted-foreground">Dependencies</h2>
                            <p className="text-xs text-muted-foreground">
                                This task cannot be marked complete until all prerequisites below are done. You can
                                still move or edit the task.
                            </p>
                            {prerequisiteOptions.length > 0 ? (
                                <Form
                                    {...TaskDependencyController.store.form({
                                        team: team.id,
                                        project: project.id,
                                        task: task.id,
                                    })}
                                    options={{ preserveScroll: true }}
                                    className="flex flex-col gap-2 sm:flex-row sm:items-end"
                                >
                                    {({ processing, errors: de }) => (
                                        <>
                                            <div className="grid min-w-0 flex-1 gap-1">
                                                <Label className="text-xs" htmlFor="prereq">
                                                    Prerequisite task
                                                </Label>
                                                <select
                                                    id="prereq"
                                                    name="prerequisite_task_id"
                                                    required
                                                    defaultValue=""
                                                    className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm"
                                                >
                                                    <option value="" disabled>
                                                        Select task…
                                                    </option>
                                                    {prerequisiteOptions.map((t) => (
                                                        <option key={t.id} value={t.id}>
                                                            {t.title}
                                                            {t.is_completed ? ' (done)' : ''}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={de.prerequisite_task_id} />
                                            </div>
                                            <Button type="submit" size="sm" disabled={processing}>
                                                {processing && <Spinner />}
                                                Add dependency
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    No other tasks in this project to depend on.
                                </p>
                            )}
                            {task.dependencies.length > 0 && (
                                <ul className="divide-y rounded-md border text-sm">
                                    {task.dependencies.map((d) => (
                                        <li
                                            key={d.prerequisite_task_id}
                                            className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p className="font-medium">{d.title}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {d.is_completed ? 'Completed' : 'Pending'}
                                                </p>
                                            </div>
                                            <Form
                                                {...TaskDependencyController.destroy.form({
                                                    team: team.id,
                                                    project: project.id,
                                                    task: task.id,
                                                    prerequisiteTask: d.prerequisite_task_id,
                                                })}
                                                options={{ preserveScroll: true }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        {processing && <Spinner />}
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
