import { Form, Head, Link, usePage } from '@inertiajs/react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import type { BreadcrumbItem } from '@/types';

type MemberRow = {
    id: number;
    role: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
    can_update_role: boolean;
    can_remove: boolean;
};

type TeamShowProps = {
    team: {
        id: number;
        name: string;
        owner_id: number;
    };
    members: MemberRow[];
    can: {
        manageMembers: boolean;
    };
};

type PageErrors = {
    user_id?: string;
    role?: string;
    user?: string;
};

export default function TeamShow() {
    const page = usePage<TeamShowProps & { errors: PageErrors }>();
    const { team, members, can } = page.props;
    const { errors } = page.props;

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={team.name} />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        variant="small"
                        title={team.name}
                        description="Members, roles, and access for this team."
                    />
                    <Link
                        href={teamsIndex()}
                        className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Back to teams
                    </Link>
                </div>

                {can.manageMembers && (
                    <section className="max-w-lg space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Add member
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Enter the user ID of an existing account. They must
                            already be registered in the app.
                        </p>
                        <Form
                            {...TeamController.storeMember.form({
                                team: team.id,
                            })}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnSuccess={['user_id']}
                            className="space-y-4"
                        >
                            {({ processing, errors: formErrors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="member-user-id">
                                            User ID
                                        </Label>
                                        <Input
                                            id="member-user-id"
                                            name="user_id"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            required
                                            className="mt-1 block w-full"
                                            placeholder="e.g. 2"
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={
                                                formErrors.user_id ??
                                                errors.user_id
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="member-role">
                                            Role
                                        </Label>
                                        <select
                                            id="member-role"
                                            name="role"
                                            required
                                            defaultValue="member"
                                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="member">
                                                Member
                                            </option>
                                            <option value="admin">
                                                Admin
                                            </option>
                                        </select>
                                        <InputError
                                            className="mt-1"
                                            message={formErrors.role}
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="add-team-member-button"
                                    >
                                        {processing && <Spinner />}
                                        Add to team
                                    </Button>
                                </>
                            )}
                        </Form>
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
                                    {(row.can_update_role ||
                                        row.can_remove) && (
                                        <div className="flex flex-col gap-3 sm:min-w-[220px] sm:items-end">
                                            {row.can_update_role && (
                                                <Form
                                                    {...TeamController.updateMember.form(
                                                        {
                                                            team: team.id,
                                                            user: row.user.id,
                                                        },
                                                    )}
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
                                                                    message={
                                                                        fe.role
                                                                    }
                                                                />
                                                            </div>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="secondary"
                                                                disabled={
                                                                    processing
                                                                }
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
                                                <Form
                                                    {...TeamController.destroyMember.form(
                                                        {
                                                            team: team.id,
                                                            user: row.user.id,
                                                        },
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    onBefore={() =>
                                                        window.confirm(
                                                            `Remove ${row.user.name} from this team?`,
                                                        )
                                                    }
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                            data-test={`remove-member-${row.user.id}`}
                                                        >
                                                            {processing && (
                                                                <Spinner />
                                                            )}
                                                            Remove
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    )}
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}
