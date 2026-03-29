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
import { index as teamProjectsIndex } from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type Team = {
    id: number | string;
    name?: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Teams',
        href: teamsIndex(),
    },
];

export default function TeamsIndex() {
    const { teams } = usePage().props as { teams?: Team[] };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams" />

            <div className="space-y-8 p-4">
                <Heading
                    variant="small"
                    title="Teams"
                    description="Create a team and switch context for your work."
                />

                <section className="max-w-md space-y-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        New team
                    </h2>
                    <Form
                        {...TeamController.store.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        resetOnSuccess={['name']}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="team-name">Name</Label>
                                    <Input
                                        id="team-name"
                                        className="mt-1 block w-full"
                                        name="name"
                                        required
                                        maxLength={255}
                                        placeholder="Team name"
                                        autoComplete="organization"
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="create-team-button"
                                >
                                    {processing && <Spinner />}
                                    Create team
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Your teams
                    </h2>
                    <ul className="divide-y rounded-md border border-sidebar-border/70 dark:border-sidebar-border">
                        {(teams ?? []).length === 0 ? (
                            <li className="px-3 py-6 text-sm text-muted-foreground">
                                You are not part of any team yet.
                            </li>
                        ) : (
                            (teams ?? []).map((team) => (
                                <li key={team.id} className="px-3 py-2">
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <Link
                                            href={teamsShow(Number(team.id))}
                                            className="text-sm font-medium hover:underline"
                                        >
                                            {team.name ?? team.id}
                                        </Link>
                                        <span
                                            className="text-xs text-muted-foreground"
                                            aria-hidden
                                        >
                                            ·
                                        </span>
                                        <Link
                                            href={teamProjectsIndex(
                                                Number(team.id),
                                            )}
                                            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                        >
                                            Projects
                                        </Link>
                                    </div>
                                </li>
                            ))
                        )}
                    </ul>
                </section>
            </div>
        </AppLayout>
    );
}
