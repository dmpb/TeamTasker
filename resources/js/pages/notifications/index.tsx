import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type NotificationRow = {
    id: string;
    read_at: string | null;
    created_at: string | null;
    title: string;
    body: string;
    url: string;
};

type NotificationsPageProps = {
    notifications: NotificationRow[];
};

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function NotificationsIndex() {
    const page = usePage<NotificationsPageProps>();
    const { notifications } = page.props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Panel', href: dashboard() },
        { title: 'Notificaciones', href: NotificationController.index.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notificaciones" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        variant="small"
                        title="Notificaciones"
                        description="Tareas asignadas, comentarios e invitaciones a teams. Abre una fila para ir al enlace."
                    />
                    <Form {...NotificationController.markAllRead.form()}>
                        {({ processing }) => (
                            <Button type="submit" variant="outline" size="sm" disabled={processing}>
                                Marcar todas como leídas
                            </Button>
                        )}
                    </Form>
                </div>

                {notifications.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                        <Bell className="size-10 text-muted-foreground" strokeWidth={1.25} aria-hidden />
                        <p className="text-sm text-muted-foreground">No hay notificaciones.</p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {notifications.map((n) => (
                            <li
                                key={n.id}
                                className="rounded-lg border border-border bg-card p-4 shadow-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="text-sm font-medium leading-snug">
                                            {n.title !== '' ? n.title : 'Notificación'}
                                        </p>
                                        {n.body !== '' ? (
                                            <p className="text-sm text-muted-foreground">{n.body}</p>
                                        ) : null}
                                        <p className="text-xs text-muted-foreground">{formatWhen(n.created_at)}</p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-2">
                                        {n.read_at === null ? (
                                            <Form {...NotificationController.markRead.form({ notification: n.id })}>
                                                {({ processing }) => (
                                                    <Button type="submit" size="sm" variant="secondary" disabled={processing}>
                                                        Marcar leída
                                                    </Button>
                                                )}
                                            </Form>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">Leída</span>
                                        )}
                                        {n.url !== '' ? (
                                            <Link
                                                href={n.url}
                                                className="text-xs text-primary underline-offset-4 hover:underline"
                                            >
                                                Abrir
                                            </Link>
                                        ) : null}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
