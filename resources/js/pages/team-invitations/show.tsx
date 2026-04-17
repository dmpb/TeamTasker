import { Form, Head, Link, usePage } from '@inertiajs/react';
import TeamInvitationAcceptController from '@/actions/App/Http/Controllers/TeamInvitationAcceptController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { login } from '@/routes';
import { index as teamsIndex } from '@/routes/teams';
import type { Auth, BreadcrumbItem } from '@/types';

type InvitationState = 'open' | 'expired' | 'accepted' | 'cancelled';

type TeamInvitationShowProps = {
    auth: Auth;
    token: string;
    invitation: {
        team_name: string;
        email: string;
        role: string;
        state: InvitationState;
    };
    authEmail: string | null;
};

type PageErrors = {
    accept?: string;
};

export default function TeamInvitationShow() {
    const page = usePage<TeamInvitationShowProps & { errors: PageErrors }>();
    const { token, invitation, authEmail } = page.props;
    const { errors } = page.props;
    const isAuthed = page.props.auth.user !== null;
    const emailMatches =
        isAuthed &&
        authEmail !== null &&
        authEmail.toLowerCase() === invitation.email.toLowerCase();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Teams', href: teamsIndex() },
        { title: 'Invitation', href: '#' },
    ];

    const loginHref =
        typeof window !== 'undefined'
            ? login.url({
                  query: { redirect: window.location.href },
              })
            : login.url();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team invitation" />

            <div className="mx-auto max-w-lg space-y-6 p-4">
                <Heading
                    variant="small"
                    title={`Join ${invitation.team_name}`}
                    description="You have been invited to collaborate on TeamTasker."
                />

                {invitation.state === 'cancelled' && (
                    <p className="text-sm text-muted-foreground">
                        This invitation was cancelled. Ask a team admin for a new
                        link if you still need access.
                    </p>
                )}

                {invitation.state === 'accepted' && (
                    <p className="text-sm text-muted-foreground">
                        This invitation was already accepted. You can open the
                        team from your teams list.
                    </p>
                )}

                {invitation.state === 'expired' && (
                    <p className="text-sm text-muted-foreground">
                        This invitation link has expired. Ask a team admin to
                        resend the invitation.
                    </p>
                )}

                {invitation.state === 'open' && (
                    <div className="space-y-4 rounded-md border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm">
                            <span className="text-muted-foreground">Invited as</span>{' '}
                            <span className="font-medium capitalize">{invitation.role}</span>
                        </p>
                        <p className="text-sm">
                            <span className="text-muted-foreground">Email</span>{' '}
                            <span className="font-medium">{invitation.email}</span>
                        </p>

                        {!isAuthed && (
                            <p className="text-sm text-muted-foreground">
                                Sign in with the invited email address, then return
                                here to accept.
                            </p>
                        )}

                        {isAuthed && !emailMatches && (
                            <p className="text-sm text-destructive">
                                You are signed in as {authEmail}, but this invitation
                                was sent to {invitation.email}. Switch accounts or
                                contact your admin.
                            </p>
                        )}

                        {errors.accept && (
                            <InputError message={errors.accept} className="text-sm" />
                        )}

                        <div className="flex flex-wrap gap-2">
                            {!isAuthed && (
                                <Button asChild>
                                    <Link href={loginHref}>Sign in</Link>
                                </Button>
                            )}
                            {isAuthed && emailMatches && (
                                <Form
                                    {...TeamInvitationAcceptController.accept.form(
                                        token,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button type="submit" disabled={processing}>
                                            {processing && <Spinner />}
                                            Accept invitation
                                        </Button>
                                    )}
                                </Form>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={teamsIndex()}>Back to teams</Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
