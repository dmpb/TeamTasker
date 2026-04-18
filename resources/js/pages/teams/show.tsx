import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import TeamInvitationController from '@/actions/App/Http/Controllers/TeamInvitationController';
import { ConfirmDestructiveDialog } from '@/components/confirm-destructive-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import {
    destroy as destroyTeamMember,
    update as updateTeamMember,
} from '@/routes/teams/members';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
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

type MemberSuggestion = {
    id: number;
    name: string;
    email: string;
};

type TeamShowProps = {
    team: {
        id: string;
        name: string;
        owner_id: number;
    };
    members: MemberRow[];
    invitations: InvitationRow[];
    memberSuggestions: MemberSuggestion[];
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

export default function TeamShow() {
    const page = usePage<TeamShowProps & { errors: PageErrors }>();
    const { team, members, can, invitations, memberSuggestions } = page.props;
    const { errors } = page.props;

    const [memberPendingRemove, setMemberPendingRemove] = useState<MemberRow | null>(null);
    const [invitationPendingCancel, setInvitationPendingCancel] = useState<InvitationRow | null>(null);
    const [userSearchDraft, setUserSearchDraft] = useState('');
    const [copiedInvitationId, setCopiedInvitationId] = useState<string | null>(null);

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

    const runMemberSuggestionSearch = (): void => {
        const q = userSearchDraft.trim();
        if (q.length < 2) {
            return;
        }
        router.get(
            teamsShow.url({ team: team.id }, { query: { user_q: q } }),
            {},
            {
                only: ['memberSuggestions'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const copyInvitationLink = async (row: InvitationRow): Promise<void> => {
        await navigator.clipboard.writeText(row.accept_url);
        setCopiedInvitationId(row.id);
        window.setTimeout(() => setCopiedInvitationId(null), 2000);
    };

    const fillInviteEmail = (email: string): void => {
        const el = document.getElementById(
            'invite-email',
        ) as HTMLInputElement | null;
        if (el) {
            el.value = email;
            el.focus();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={team.name} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        variant="small"
                        title={team.name}
                        description="Members, invitations, and access for this team."
                    />
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <Link
                            href={teamProjectsIndex(team.id)}
                            className="text-sm font-medium text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Projects
                        </Link>
                        <Link
                            href={teamsIndex()}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            Back to teams
                        </Link>
                    </div>
                </div>

                {can.manageMembers && (
                    <section className="max-w-2xl space-y-6">
                        <div className="space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Invite by email
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                We will email a link (when mail is configured) and you
                                can always copy the link from the list below.
                            </p>
                            <Form
                                {...TeamInvitationController.store.form({
                                    team: team.id,
                                })}
                                options={{
                                    preserveScroll: true,
                                }}
                                resetOnSuccess={['email']}
                                className="space-y-4"
                            >
                                {({ processing, errors: formErrors }) => (
                                    <>
                                        <div className="grid gap-2 sm:max-w-md">
                                            <Label htmlFor="invite-email">Email</Label>
                                            <Input
                                                id="invite-email"
                                                name="email"
                                                type="email"
                                                required
                                                autoComplete="email"
                                                placeholder="colleague@example.com"
                                            />
                                            <InputError
                                                className="mt-1"
                                                message={
                                                    formErrors.email ?? errors.email
                                                }
                                            />
                                        </div>
                                        <div className="grid max-w-xs gap-2">
                                            <Label htmlFor="invite-role">Role</Label>
                                            <select
                                                id="invite-role"
                                                name="role"
                                                required
                                                defaultValue="member"
                                                className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:outline-none"
                                            >
                                                <option value="member">Member</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="send-team-invitation"
                                        >
                                            {processing && <Spinner />}
                                            Send invitation
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>

                        <div className="space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Find registered users
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Search by name or email. Pick a row to copy their email
                                into the invite form.
                            </p>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div className="grid max-w-md flex-1 gap-2">
                                    <Label htmlFor="member-user-search">Search</Label>
                                    <Input
                                        id="member-user-search"
                                        value={userSearchDraft}
                                        onChange={(e) =>
                                            setUserSearchDraft(e.target.value)
                                        }
                                        placeholder="At least 2 characters…"
                                        maxLength={255}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={runMemberSuggestionSearch}
                                >
                                    Search
                                </Button>
                            </div>
                            {memberSuggestions.length > 0 && (
                                <ul className="divide-y rounded-md border border-border text-sm">
                                    {memberSuggestions.map((u) => (
                                        <li
                                            key={u.id}
                                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                                        >
                                            <div>
                                                <p className="font-medium">{u.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {u.email}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => fillInviteEmail(u.email)}
                                            >
                                                Use email
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="space-y-3">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                Pending invitations
                            </h2>
                            {errors.invitation && (
                                <p
                                    className="text-sm text-destructive"
                                    role="alert"
                                >
                                    {errors.invitation}
                                </p>
                            )}
                            {invitations.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No open invitations.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                                    {invitations.map((row) => (
                                        <li
                                            key={row.id}
                                            className="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="text-sm font-medium">
                                                    {row.email}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Role {row.role} · Invited by{' '}
                                                    {row.invited_by_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Expires{' '}
                                                    {new Date(
                                                        row.expires_at,
                                                    ).toLocaleString()}
                                                </p>
                                                {row.is_expired && (
                                                    <span className="inline-block rounded border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-200">
                                                        Expired — resend to refresh
                                                    </span>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        copyInvitationLink(row)
                                                    }
                                                >
                                                    {copiedInvitationId === row.id
                                                        ? 'Copied'
                                                        : 'Copy link'}
                                                </Button>
                                                <Form
                                                    {...TeamInvitationController.resend.form(
                                                        {
                                                            team: team.id,
                                                            invitation: row.id,
                                                        },
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={processing}
                                                            data-test={`resend-invitation-${row.id}`}
                                                        >
                                                            {processing && <Spinner />}
                                                            Resend email
                                                        </Button>
                                                    )}
                                                </Form>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        setInvitationPendingCancel(row)
                                                    }
                                                    data-test={`cancel-invitation-${row.id}`}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Members
                    </h2>
                    {(errors.user || errors.role) && can.manageMembers && (
                        <div
                            className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                            role="alert"
                        >
                            {errors.user && <p>{errors.user}</p>}
                            {errors.role && <p>{errors.role}</p>}
                        </div>
                    )}
                    <ul className="divide-y rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        {members.length === 0 ? (
                            <li className="px-3 py-6 text-sm text-muted-foreground">
                                No members yet.
                            </li>
                        ) : (
                            members.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">
                                            {row.user.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.user.email}
                                        </p>
                                        <div className="mt-1 flex flex-wrap items-center gap-2">
                                            <span className="rounded-md border border-sidebar-border/70 px-2 py-0.5 text-xs font-medium capitalize dark:border-sidebar-border">
                                                {row.role}
                                            </span>
                                            {row.user.id === team.owner_id && (
                                                <span className="text-xs text-muted-foreground">
                                                    Team owner
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    {(row.can_update_role || row.can_remove) && (
                                        <div className="flex flex-col gap-3 sm:min-w-[220px] sm:items-end">
                                            {row.can_update_role && (
                                                <Form
                                                    {...updateTeamMember.form({
                                                        team: team.id,
                                                        member: row.id,
                                                    })}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end"
                                                >
                                                    {({
                                                        processing,
                                                        errors: fe,
                                                    }) => (
                                                        <>
                                                            <div className="grid w-full gap-1 sm:w-40">
                                                                <Label
                                                                    className="sr-only"
                                                                    htmlFor={`role-${row.id}`}
                                                                >
                                                                    Role
                                                                </Label>
                                                                <select
                                                                    id={`role-${row.id}`}
                                                                    name="role"
                                                                    required
                                                                    defaultValue={
                                                                        row.role
                                                                    }
                                                                    className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-2 py-1 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:outline-none disabled:opacity-50"
                                                                >
                                                                    <option value="member">
                                                                        Member
                                                                    </option>
                                                                    <option value="admin">
                                                                        Admin
                                                                    </option>
                                                                </select>
                                                                <InputError
                                                                    message={fe.role}
                                                                />
                                                            </div>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="secondary"
                                                                disabled={processing}
                                                                data-test={`update-member-role-${row.user.id}`}
                                                            >
                                                                {processing && (
                                                                    <Spinner />
                                                                )}
                                                                Save role
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                            {row.can_remove && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    data-test={`remove-member-${row.user.id}`}
                                                    onClick={() =>
                                                        setMemberPendingRemove(row)
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>

            <ConfirmDestructiveDialog
                open={memberPendingRemove !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMemberPendingRemove(null);
                    }
                }}
                title="Remove member"
                description={
                    memberPendingRemove
                        ? `Remove ${memberPendingRemove.user.name} from this team? They will lose access to projects in this team.`
                        : ''
                }
                confirmLabel="Remove"
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
                title="Cancel invitation"
                description={
                    invitationPendingCancel
                        ? `Cancel the invitation sent to ${invitationPendingCancel.email}?`
                        : ''
                }
                confirmLabel="Cancel invitation"
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
