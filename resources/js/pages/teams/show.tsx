import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Copy, Mail, Trash2, UserCog } from 'lucide-react';
import { useState } from 'react';
import TeamInvitationController from '@/actions/App/Http/Controllers/TeamInvitationController';
import { ConfirmDestructiveDialog } from '@/components/confirm-destructive-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import {
    destroy as destroyTeamMember,
    update as updateTeamMember,
} from '@/routes/teams/members';
import { index as teamProjectsIndex } from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type MemberRow = {
    id: string;
    role: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    can_update_role: boolean;
    can_remove: boolean;
};

type InvitationRow = {
    id: string;
    email: string;
    role: string;
    expires_at: string;
    is_expired: boolean;
    invited_by_name: string;
    accept_url: string;
};

type TeamShowProps = {
    team: {
        id: string;
        name: string;
        description: string | null;
        owner_id: number;
        projects_count: number;
        members_count: number;
    };
    members: MemberRow[];
    invitations: InvitationRow[];
    can: {
        manageMembers: boolean;
    };
};

type PageErrors = {
    user_id?: string;
    role?: string;
    user?: string;
    email?: string;
    invitation?: string;
};

const selectClass = cn(
    'border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
);

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

export default function TeamShow() {
    const page = usePage<TeamShowProps & { errors: PageErrors }>();
    const { team, members, can, invitations } = page.props;
    const { errors } = page.props;

    const [memberPendingRemove, setMemberPendingRemove] =
        useState<MemberRow | null>(null);
    const [invitationPendingCancel, setInvitationPendingCancel] =
        useState<InvitationRow | null>(null);
    const [copiedInvitationId, setCopiedInvitationId] = useState<string | null>(
        null,
    );
    const [inviteOpen, setInviteOpen] = useState(false);
    const [roleEditMember, setRoleEditMember] = useState<MemberRow | null>(
        null,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Teams',
            href: teamsIndex(),
        },
        {
            title: team.name,
            href: teamsShow(team.id),
        },
    ];

    const copyInvitationLink = async (row: InvitationRow): Promise<void> => {
        await navigator.clipboard.writeText(row.accept_url);
        setCopiedInvitationId(row.id);
        window.setTimeout(() => setCopiedInvitationId(null), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={team.name} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-2">
                        <Heading
                            variant="small"
                            title={team.name}
                            description={
                                team.description?.trim()
                                    ? team.description
                                    : 'Miembros, invitaciones y acceso para este team.'
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {team.projects_count}{' '}
                            {team.projects_count === 1
                                ? 'proyecto'
                                : 'proyectos'}
                            <span aria-hidden> · </span>
                            {team.members_count}{' '}
                            {team.members_count === 1
                                ? 'miembro'
                                : 'miembros'}
                            {can.manageMembers && invitations.length > 0 ? (
                                <>
                                    <span aria-hidden> · </span>
                                    {invitations.length}{' '}
                                    {invitations.length === 1
                                        ? 'invitación pendiente'
                                        : 'invitaciones pendientes'}
                                </>
                            ) : null}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {can.manageMembers ? (
                            <Button
                                type="button"
                                onClick={() => setInviteOpen(true)}
                            >
                                Invitar por correo
                            </Button>
                        ) : null}
                        <Button variant="outline" asChild>
                            <Link
                                href={teamProjectsIndex(team.id)}
                                prefetch
                            >
                                Proyectos
                            </Link>
                        </Button>
                    </div>
                </div>

                <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Invitar por correo</DialogTitle>
                            <DialogDescription>
                                Enviaremos un enlace de invitación al correo
                                indicado (si el correo está configurado).
                                También puedes copiar el enlace desde la lista
                                de invitaciones pendientes.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...TeamInvitationController.store.form({
                                team: team.id,
                            })}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnSuccess={['email']}
                            onSuccess={() => setInviteOpen(false)}
                            className="space-y-4"
                        >
                            {({ processing, errors: formErrors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-email">
                                            Correo
                                        </Label>
                                        <Input
                                            id="invite-email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="email"
                                            placeholder="compañero@ejemplo.com"
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={
                                                formErrors.email ??
                                                errors.email
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-role">
                                            Rol
                                        </Label>
                                        <select
                                            id="invite-role"
                                            name="role"
                                            required
                                            defaultValue="member"
                                            className={selectClass}
                                        >
                                            <option value="member">
                                                Miembro
                                            </option>
                                            <option value="admin">
                                                Admin
                                            </option>
                                        </select>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setInviteOpen(false)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="send-team-invitation"
                                        >
                                            {processing && <Spinner />}
                                            Enviar invitación
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={roleEditMember !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setRoleEditMember(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Cambiar rol</DialogTitle>
                            <DialogDescription>
                                {roleEditMember
                                    ? `${roleEditMember.user.name} · ${roleEditMember.user.email}`
                                    : ''}
                            </DialogDescription>
                        </DialogHeader>
                        {roleEditMember ? (
                            <Form
                                key={roleEditMember.id}
                                {...updateTeamMember.form({
                                    team: team.id,
                                    member: roleEditMember.id,
                                })}
                                options={{
                                    preserveScroll: true,
                                }}
                                onSuccess={() => setRoleEditMember(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors: fe }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-member-role">
                                                Rol
                                            </Label>
                                            <select
                                                id="edit-member-role"
                                                name="role"
                                                required
                                                defaultValue={
                                                    roleEditMember.role
                                                }
                                                className={selectClass}
                                            >
                                                <option value="member">
                                                    Miembro
                                                </option>
                                                <option value="admin">
                                                    Admin
                                                </option>
                                            </select>
                                            <InputError
                                                message={
                                                    fe.role ?? errors.role
                                                }
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setRoleEditMember(null)
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test={`update-member-role-${roleEditMember.user.id}`}
                                            >
                                                {processing && <Spinner />}
                                                Guardar rol
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </DialogContent>
                </Dialog>

                {can.manageMembers ? (
                    <section className="space-y-3">
                        <Card className="py-0 gap-1">
                            <CardHeader className="pb-0 pt-6">
                                <CardTitle className="text-base font-medium">
                                    Invitaciones pendientes
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Invitaciones abiertas para unirse a este
                                    team.
                                </p>
                            </CardHeader>
                            <CardContent className="pb-6 pt-4">
                                {errors.invitation ? (
                                    <p
                                        className="mb-4 text-sm text-destructive"
                                        role="alert"
                                    >
                                        {errors.invitation}
                                    </p>
                                ) : null}
                                {invitations.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No hay invitaciones abiertas.
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                                        {invitations.map((row) => (
                                            <li
                                                key={row.id}
                                                className="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <p className="text-sm font-medium">
                                                        {row.email}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Rol {row.role} ·
                                                        Invitado por{' '}
                                                        {row.invited_by_name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Caduca{' '}
                                                        {new Date(
                                                            row.expires_at,
                                                        ).toLocaleString()}
                                                    </p>
                                                    {row.is_expired ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200"
                                                        >
                                                            Caducada — reenvía
                                                            para renovar
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1 sm:justify-end">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="secondary"
                                                                className="size-9 shrink-0"
                                                                onClick={() =>
                                                                    copyInvitationLink(
                                                                        row,
                                                                    )
                                                                }
                                                                aria-label="Copiar enlace de invitación"
                                                            >
                                                                <Copy
                                                                    className="size-4"
                                                                    aria-hidden
                                                                />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {copiedInvitationId ===
                                                            row.id
                                                                ? 'Copiado'
                                                                : 'Copiar enlace'}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <Form
                                                        {...TeamInvitationController.resend.form(
                                                            {
                                                                team: team.id,
                                                                invitation:
                                                                    row.id,
                                                            },
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        type="submit"
                                                                        size="icon"
                                                                        variant="outline"
                                                                        className="size-9 shrink-0"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                        data-test={`resend-invitation-${row.id}`}
                                                                        aria-label="Reenviar correo de invitación"
                                                                    >
                                                                        {processing ? (
                                                                            <Spinner />
                                                                        ) : (
                                                                            <Mail
                                                                                className="size-4"
                                                                                aria-hidden
                                                                            />
                                                                        )}
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Reenviar
                                                                    correo
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </Form>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="destructive"
                                                                className="size-9 shrink-0"
                                                                onClick={() =>
                                                                    setInvitationPendingCancel(
                                                                        row,
                                                                    )
                                                                }
                                                                data-test={`cancel-invitation-${row.id}`}
                                                                aria-label="Cancelar invitación"
                                                            >
                                                                <Trash2
                                                                    className="size-4"
                                                                    aria-hidden
                                                                />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Cancelar invitación
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                ) : null}

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Miembros
                    </h2>
                    {(errors.user || errors.role) && can.manageMembers ? (
                        <div
                            className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                            role="alert"
                        >
                            {errors.user ? <p>{errors.user}</p> : null}
                            {errors.role && !roleEditMember ? (
                                <p>{errors.role}</p>
                            ) : null}
                        </div>
                    ) : null}
                    {members.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 py-12 text-center dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Aún no hay miembros en este team.
                            </p>
                        </div>
                    ) : (
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {members.map((row) => (
                                <li key={row.id}>
                                    <Card className="h-full py-0 gap-0">
                                        <CardHeader className="flex flex-row items-start gap-3 space-y-0 pt-6 pb-6">
                                            <div
                                                className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                                aria-hidden
                                            >
                                                {initialsFromName(
                                                    row.user.name,
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <CardTitle className="text-base leading-snug">
                                                    {row.user.name}
                                                </CardTitle>
                                                <p className="break-all text-sm text-muted-foreground">
                                                    {row.user.email}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-2 pt-1">
                                                    <Badge
                                                        variant="secondary"
                                                        className="capitalize"
                                                    >
                                                        {row.role === 'member'
                                                            ? 'Miembro'
                                                            : row.role ===
                                                                'admin'
                                                              ? 'Admin'
                                                              : row.role ===
                                                                  'owner'
                                                                ? 'Propietario'
                                                                : row.role}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        {row.can_update_role ||
                                        row.can_remove ? (
                                            <CardContent className="flex flex-wrap gap-2 pb-6">
                                                {row.can_update_role ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setRoleEditMember(
                                                                row,
                                                            )
                                                        }
                                                    >
                                                        <UserCog
                                                            className="mr-1.5 size-4"
                                                            aria-hidden
                                                        />
                                                        Cambiar rol
                                                    </Button>
                                                ) : null}
                                                {row.can_remove ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        data-test={`remove-member-${row.user.id}`}
                                                        onClick={() =>
                                                            setMemberPendingRemove(
                                                                row,
                                                            )
                                                        }
                                                    >
                                                        Quitar
                                                    </Button>
                                                ) : null}
                                            </CardContent>
                                        ) : null}
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ConfirmDestructiveDialog
                open={memberPendingRemove !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMemberPendingRemove(null);
                    }
                }}
                title="Quitar miembro"
                description={
                    memberPendingRemove
                        ? `¿Quitar a ${memberPendingRemove.user.name} de este team? Perderá el acceso a los proyectos del team.`
                        : ''
                }
                confirmLabel="Quitar"
                onConfirm={() => {
                    const m = memberPendingRemove;

                    if (!m) {
                        return;
                    }

                    setMemberPendingRemove(null);
                    router.delete(
                        destroyTeamMember.url({
                            team: team.id,
                            member: m.id,
                        }),
                        { preserveScroll: true },
                    );
                }}
            />

            <ConfirmDestructiveDialog
                open={invitationPendingCancel !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setInvitationPendingCancel(null);
                    }
                }}
                title="Cancelar invitación"
                description={
                    invitationPendingCancel
                        ? `¿Cancelar la invitación enviada a ${invitationPendingCancel.email}?`
                        : ''
                }
                confirmLabel="Cancelar invitación"
                onConfirm={() => {
                    const inv = invitationPendingCancel;

                    if (!inv) {
                        return;
                    }

                    setInvitationPendingCancel(null);
                    router.delete(
                        TeamInvitationController.destroy.url({
                            team: team.id,
                            invitation: inv.id,
                        }),
                        { preserveScroll: true },
                    );
                }}
            />
        </AppLayout>
    );
}
