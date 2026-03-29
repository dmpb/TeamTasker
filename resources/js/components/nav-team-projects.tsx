import { Link, usePage } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { index as teamProjectsIndex } from '@/routes/teams/projects';

type TeamNavEntry = {
    id: number;
    name: string;
};

export function NavTeamProjects() {
    const page = usePage<{ teamsForNav?: TeamNavEntry[] }>();
    const teamsForNav = page.props.teamsForNav ?? [];
    const { isCurrentOrParentUrl } = useCurrentUrl();

    if (teamsForNav.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Team projects</SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu>
                    {teamsForNav.map((team) => {
                        const href = teamProjectsIndex(team.id);

                        return (
                            <SidebarMenuItem key={team.id}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(href)}
                                    tooltip={{ children: team.name }}
                                >
                                    <Link href={href} prefetch>
                                        <FolderKanban />
                                        <span className="truncate">
                                            {team.name}
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
