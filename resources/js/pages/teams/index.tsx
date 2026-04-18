import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationSimple } from '@/components/pagination-simple';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
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
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as teamsIndex, show as teamsShow } from '@/routes/teams';
import { index as teamProjectsIndex } from '@/routes/teams/projects';
import type { BreadcrumbItem } from '@/types';

type TeamRow = {
    id: string;
    name: string;
    description: string | null;
    projects_count: number;
    members_count: number;
    is_owner: boolean;
};

type PaginatedTeams = {
    data: TeamRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Teams',
        href: teamsIndex(),
    },
];

const textareaClass = cn(
    'border-input placeholder:text-muted-foreground flex min-h-[5rem] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none',
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

export default function TeamsIndex() {
    const { teams } = usePage().props as { teams?: PaginatedTeams };
    const list = teams?.data ?? [];
    const totalTeams = teams?.total ?? 0;
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams" />

            <div className="space-y-8 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title="Teams"
                        description="Crea un team y organiza proyectos con tu gente."
                    />
                    <Button
                        type="button"
                        className="shrink-0"
                        onClick={() => setCreateOpen(true)}
                    >
                        Crear team
                    </Button>
                </div>

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Nuevo team</DialogTitle>
                            <DialogDescription>
                                Asigna un nombre claro. Puedes añadir una
                                descripción opcional para contextualizar el
                                team.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...TeamController.store.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onSuccess={() => setCreateOpen(false)}
                            resetOnSuccess={['name', 'description']}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="team-name">Nombre</Label>
                                        <Input
                                            id="team-name"
                                            name="name"
                                            required
                                            maxLength={255}
                                            placeholder="Ej. Producto"
                                            autoComplete="organization"
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={errors.name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="team-description">
                                            Descripción{' '}
                                            <span className="font-normal text-muted-foreground">
                                                (opcional)
                                            </span>
                                        </Label>
                                        <textarea
                                            id="team-description"
                                            name="description"
                                            rows={4}
                                            maxLength={5000}
                                            placeholder="Objetivo del team, ámbito, notas internas…"
                                            className={textareaClass}
                                        />
                                        <InputError
                                            className="mt-1"
                                            message={errors.description}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setCreateOpen(false)}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="create-team-button"
                                        >
                                            {processing && <Spinner />}
                                            Guardar team
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>

                {totalTeams === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-sidebar-border/70 py-16 text-center dark:border-sidebar-border">
                        <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                            <Users
                                className="size-7 text-muted-foreground"
                                aria-hidden
                            />
                        </div>
                        <div className="max-w-md space-y-2 px-4">
                            <p className="text-base font-medium">
                                Aún no perteneces a ningún team
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Crea el primero para invitar a compañeros y
                                empezar proyectos Kanban en un espacio común.
                            </p>
                        </div>
                        <Button
                            type="button"
                            onClick={() => setCreateOpen(true)}
                        >
                            Crear tu primer team
                        </Button>
                    </div>
                ) : (
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Tus Teams
                        </h2>
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {list.map((team) => (
                                <li key={team.id}>
                                    <Card className="h-full py-0 gap-1">
                                        <CardHeader className="flex flex-row items-center gap-3 space-y-0 pt-6">
                                            <div
                                                className="flex size-11 shrink-0 items-center justify-center rounded-md bg-primary/10 text-sm font-semibold text-primary"
                                                aria-hidden
                                            >
                                                {initialsFromName(team.name)}
                                            </div>
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <CardTitle className="text-base leading-snug">
                                                    <Link
                                                        href={teamsShow(team.id)}
                                                        prefetch
                                                        className="hover:underline"
                                                    >
                                                        {team.name}
                                                    </Link>
                                                </CardTitle>
                                                {team.description?.trim() ? (
                                                    <p className="line-clamp-2 text-sm text-muted-foreground">
                                                        {team.description}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pb-2">
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {team.projects_count}{' '}
                                                    {team.projects_count === 1
                                                        ? 'proyecto'
                                                        : 'proyectos'}
                                                </span>
                                                <span aria-hidden>·</span>
                                                <span>
                                                    {team.members_count}{' '}
                                                    {team.members_count === 1
                                                        ? 'miembro'
                                                        : 'miembros'}
                                                </span>
                                                {team.is_owner ? (
                                                    <>
                                                        <span aria-hidden>
                                                            ·
                                                        </span>
                                                        <Badge variant="secondary">
                                                            Eres propietario
                                                        </Badge>
                                                    </>
                                                ) : null}
                                            </div>
                                        </CardContent>
                                        <CardFooter className="mt-auto flex-wrap gap-2 border-t py-4">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link
                                                    href={teamsShow(team.id)}
                                                    prefetch
                                                >
                                                    Ver team
                                                </Link>
                                            </Button>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link
                                                    href={teamProjectsIndex(
                                                        team.id,
                                                    )}
                                                    prefetch
                                                >
                                                    Proyectos
                                                </Link>
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                        {teams ? (
                            <PaginationSimple
                                currentPage={teams.current_page}
                                lastPage={teams.last_page}
                                prevPageUrl={teams.prev_page_url}
                                nextPageUrl={teams.next_page_url}
                            />
                        ) : null}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
