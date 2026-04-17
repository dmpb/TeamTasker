import { Form, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ToastKind = 'success' | 'error';

type FlashUndo = {
    method: string;
    url: string;
    label: string;
};

export function FlashToasts() {
    const page = usePage<{ flash: { success?: string | null; error?: string | null; undo?: FlashUndo | null } }>();
    const { flash } = page.props;
    const lastFlashRef = useRef<string>('');
    const [toast, setToast] = useState<{
        kind: ToastKind;
        message: string;
        undo?: FlashUndo | null;
    } | null>(null);

    useEffect(() => {
        const key = `${flash.success ?? ''}|${flash.error ?? ''}|${flash.undo?.url ?? ''}`;
        if (key === '|' || key === lastFlashRef.current) {
            return;
        }
        lastFlashRef.current = key;

        if (flash.error) {
            setToast({ kind: 'error', message: flash.error, undo: null });
        } else if (flash.success) {
            setToast({
                kind: 'success',
                message: flash.success,
                undo: flash.undo ?? null,
            });
        }

        const t = window.setTimeout(() => setToast(null), 8000);

        return () => window.clearTimeout(t);
    }, [flash.success, flash.error, flash.undo]);

    if (!toast) {
        return null;
    }

    const undoPost =
        toast.undo &&
        toast.undo.method.toLowerCase() === 'post' &&
        toast.undo.url
            ? toast.undo
            : null;

    return (
        <div
            className={cn(
                'animate-in slide-in-from-top-2 fixed top-4 right-4 z-[100] flex max-w-md flex-col gap-2 rounded-lg border p-4 shadow-lg md:top-6 md:right-6',
                toast.kind === 'success' &&
                    'border-emerald-500/40 bg-emerald-950/90 text-emerald-50 dark:bg-emerald-950/95',
                toast.kind === 'error' &&
                    'border-destructive/50 bg-destructive/95 text-destructive-foreground',
            )}
            role="status"
        >
            <p className="text-sm font-medium">{toast.message}</p>
            <div className="flex flex-wrap items-center gap-2">
                {undoPost && (
                        <Form
                            action={undoPost.url}
                            method="post"
                            options={{ preserveScroll: true }}
                            className="inline"
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    disabled={processing}
                                    className="h-8"
                                >
                                    {undoPost.label}
                                </Button>
                            )}
                        </Form>
                    )}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="h-8 text-inherit hover:bg-white/10"
                    onClick={() => setToast(null)}
                >
                    Cerrar
                </Button>
            </div>
        </div>
    );
}
