import {
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
    type DraggableAttributes,
    type DraggableSyntheticListeners,
    DndContext,
    type UniqueIdentifier,
    closestCorners,
    PointerSensor,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import { cn } from '@/lib/utils';

type BoardFilters = {
    filter_column: number | null;
    filter_assignee: number | null;
    search: string;
    filter_label: number | null;
    filter_priority: string;
    filter_due: string;
};

function buildItemsFromColumns(columns: { id: number; tasks: { id: number }[] }[]): Record<number, number[]> {
    const next: Record<number, number[]> = {};
    for (const col of columns) {
        next[col.id] = col.tasks.map((t) => t.id);
    }

    return next;
}

function cloneItems(items: Record<number, number[]>): Record<number, number[]> {
    const next: Record<number, number[]> = {};
    for (const [k, v] of Object.entries(items)) {
        next[Number(k)] = [...v];
    }

    return next;
}

function serializeItems(items: Record<number, number[]>): string {
    return Object.keys(items)
        .sort((a, b) => Number(a) - Number(b))
        .map((k) => `${k}:${items[Number(k)].join(',')}`)
        .join('|');
}

function findColumnForTask(taskId: number, items: Record<number, number[]>): number | undefined {
    for (const [cid, list] of Object.entries(items)) {
        if (list.includes(taskId)) {
            return Number(cid);
        }
    }

    return undefined;
}

function parseDroppableColumnId(overId: UniqueIdentifier): number | null {
    const s = String(overId);
    if (!s.startsWith('droppable-')) {
        return null;
    }

    const n = Number.parseInt(s.slice('droppable-'.length), 10);

    return Number.isFinite(n) ? n : null;
}

function filterPayloadFromBoard(filters: BoardFilters): Record<string, string | number> {
    const payload: Record<string, string | number> = {};

    if (filters.search.trim() !== '') {
        payload.search = filters.search.trim();
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

    return payload;
}

export type BoardSortableTaskBag = {
    setNodeRef: (node: HTMLElement | null) => void;
    style: CSSProperties;
    setActivatorNodeRef: (node: HTMLElement | null) => void;
    attributes: DraggableAttributes;
    listeners: DraggableSyntheticListeners;
    isDragging: boolean;
};

export function BoardSortableTaskShell({
    taskId,
    disabled,
    children,
}: {
    taskId: number;
    disabled: boolean;
    children: (bag: BoardSortableTaskBag) => ReactNode;
}): ReactNode {
    if (disabled) {
        return children({
            setNodeRef: () => {},
            style: {},
            setActivatorNodeRef: () => {},
            attributes: {} as DraggableAttributes,
            listeners: undefined,
            isDragging: false,
        });
    }

    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
        id: String(taskId),
    });

    const style: CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return children({
        setNodeRef,
        style,
        setActivatorNodeRef,
        attributes,
        listeners,
        isDragging,
    });
}

export function BoardColumnDropZone({ columnId, disabled }: { columnId: number; disabled: boolean }): ReactNode {
    const { setNodeRef, isOver } = useDroppable({
        id: `droppable-${columnId}`,
        disabled,
    });

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'min-h-8 shrink-0 rounded-md border border-dashed px-2 py-2 text-center text-[10px] text-muted-foreground',
                isOver ? 'border-primary/60 bg-primary/5' : 'border-transparent',
            )}
        >
            {disabled ? null : 'Soltar aquí'}
        </div>
    );
}

type BoardTasksDndProps = {
    teamId: number;
    projectId: number;
    filters: BoardFilters;
    disabled: boolean;
    columns: { id: number; tasks: { id: number }[] }[];
    children: (ctx: {
        taskIdsByColumn: Record<number, number[]>;
        SortableTask: typeof BoardSortableTaskShell;
        ColumnDropZone: typeof BoardColumnDropZone;
    }) => ReactNode;
};

function applySameColumnReorder(prev: Record<number, number[]>, event: DragEndEvent): Record<number, number[]> {
    const { active, over } = event;
    if (!over) {
        return prev;
    }

    const activeTaskId = Number.parseInt(String(active.id), 10);
    if (!Number.isFinite(activeTaskId)) {
        return prev;
    }

    const overRaw = String(over.id);
    if (overRaw.startsWith('droppable-')) {
        return prev;
    }

    const overTaskId = Number.parseInt(overRaw, 10);
    if (!Number.isFinite(overTaskId)) {
        return prev;
    }

    const activeContainer = findColumnForTask(activeTaskId, prev);
    const overContainer = findColumnForTask(overTaskId, prev);

    if (activeContainer === undefined || overContainer === undefined || activeContainer !== overContainer) {
        return prev;
    }

    const list = [...(prev[activeContainer] ?? [])];
    const oldIndex = list.indexOf(activeTaskId);
    const newIndex = list.indexOf(overTaskId);

    if (oldIndex === -1 || newIndex === -1 || oldIndex === newIndex) {
        return prev;
    }

    return {
        ...prev,
        [activeContainer]: arrayMove(list, oldIndex, newIndex),
    };
}

export function BoardTasksDnd({
    teamId,
    projectId,
    filters,
    disabled,
    columns,
    children,
}: BoardTasksDndProps): ReactNode {
    const [items, setItems] = useState<Record<number, number[]>>(() => buildItemsFromColumns(columns));
    const snapshotRef = useRef<Record<number, number[]> | null>(null);
    const itemsRef = useRef(items);
    itemsRef.current = items;

    const columnSignature = useMemo(() => serializeItems(buildItemsFromColumns(columns)), [columns]);

    useEffect(() => {
        if (snapshotRef.current !== null) {
            return;
        }
        setItems(buildItemsFromColumns(columns));
    }, [columnSignature, columns]);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 8 },
        }),
    );

    const postSync = useCallback(
        (nextItems: Record<number, number[]>) => {
            const columnsPayload = columns.map((c) => ({
                column_id: c.id,
                task_ids: nextItems[c.id] ?? [],
            }));

            router.post(
                TaskController.syncBoard.url({ team: teamId, project: projectId }),
                {
                    columns: columnsPayload,
                    ...filterPayloadFromBoard(filters),
                },
                { preserveScroll: true },
            );
        },
        [columns, filters, projectId, teamId],
    );

    const onDragStart = useCallback(() => {
        if (disabled) {
            return;
        }
        snapshotRef.current = cloneItems(itemsRef.current);
    }, [disabled]);

    const onDragOver = useCallback(
        (event: DragOverEvent) => {
            if (disabled) {
                return;
            }

            const { active, over } = event;
            const overId = over?.id;

            if (overId == null) {
                return;
            }

            const activeTaskId = Number.parseInt(String(active.id), 10);
            if (!Number.isFinite(activeTaskId)) {
                return;
            }

            setItems((prev) => {
                const activeContainer = findColumnForTask(activeTaskId, prev);
                if (activeContainer === undefined) {
                    return prev;
                }

                let overContainer = findColumnForTask(Number.parseInt(String(overId), 10), prev);
                if (overContainer === undefined) {
                    const dropCol = parseDroppableColumnId(overId);
                    if (dropCol !== null) {
                        overContainer = dropCol;
                    }
                }

                if (overContainer === undefined) {
                    return prev;
                }

                if (activeContainer === overContainer) {
                    return prev;
                }

                const activeItems = [...(prev[activeContainer] ?? [])];
                const overItems = [...(prev[overContainer] ?? [])];
                const activeIndex = activeItems.indexOf(activeTaskId);

                if (activeIndex === -1) {
                    return prev;
                }

                const overTaskId = Number.parseInt(String(overId), 10);
                const overIndex = Number.isFinite(overTaskId) ? overItems.indexOf(overTaskId) : -1;

                let newIndex: number;

                if (parseDroppableColumnId(overId) === overContainer) {
                    newIndex = overItems.length;
                } else {
                    const isBelowOverItem =
                        over &&
                        active.rect.current.translated &&
                        active.rect.current.translated.top > over.rect.top + over.rect.height;

                    const modifier = isBelowOverItem ? 1 : 0;
                    newIndex = overIndex >= 0 ? overIndex + modifier : overItems.length;
                }

                activeItems.splice(activeIndex, 1);

                const nextOver = [...overItems];
                nextOver.splice(newIndex, 0, activeTaskId);

                return {
                    ...prev,
                    [activeContainer]: activeItems,
                    [overContainer]: nextOver,
                };
            });
        },
        [disabled],
    );

    const onDragEnd = useCallback(
        (event: DragEndEvent) => {
            if (disabled) {
                return;
            }

            const snap = snapshotRef.current;
            snapshotRef.current = null;

            if (!event.over) {
                if (snap !== null) {
                    setItems(cloneItems(snap));
                }

                return;
            }

            setItems((prev) => {
                const next = applySameColumnReorder(prev, event);
                if (snap !== null && serializeItems(next) !== serializeItems(snap)) {
                    queueMicrotask(() => postSync(next));
                }

                return next;
            });
        },
        [disabled, postSync],
    );

    const onDragCancel = useCallback(() => {
        const snap = snapshotRef.current;
        snapshotRef.current = null;
        if (snap !== null) {
            setItems(cloneItems(snap));
        }
    }, []);

    const ctx = useMemo(
        () => ({
            taskIdsByColumn: items,
            SortableTask: BoardSortableTaskShell,
            ColumnDropZone: BoardColumnDropZone,
        }),
        [items],
    );

    if (disabled) {
        return children({
            ...ctx,
            taskIdsByColumn: buildItemsFromColumns(columns),
        });
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDragCancel={onDragCancel}
        >
            {children(ctx)}
        </DndContext>
    );
}

export function boardTasksForColumn<TTask extends { id: number }>(
    column: { id: number; tasks: TTask[] },
    taskIdsByColumn: Record<number, number[]>,
    taskById: Map<number, TTask>,
): TTask[] {
    const order = taskIdsByColumn[column.id] ?? column.tasks.map((t) => t.id);

    return order.map((id) => taskById.get(id)).filter((t): t is TTask => t !== undefined);
}
