import { Link, usePage } from '@inertiajs/react';
import { Bell, BookOpen, FolderGit2, LayoutGrid, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavTeamProjects } from '@/components/nav-team-projects';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as teamsIndex } from '@/routes/teams';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Teams',
        href: teamsIndex(),
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

type PageWithNotificationCount = {
    notificationUnreadCount?: number;
};

export function AppSidebar() {
    const page = usePage<PageWithNotificationCount>();
    const unread = page.props.notificationUnreadCount ?? 0;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="sr-only">Cuenta</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip={{ children: 'Notificaciones' }}>
                                <Link href={notificationsIndex()} prefetch className="gap-2">
                                    <Bell className="size-4" aria-hidden />
                                    <span>Notificaciones</span>
                                    {unread > 0 ? (
                                        <Badge variant="secondary" className="ml-auto min-w-6 justify-center px-1 text-[10px]">
                                            {unread > 99 ? '99+' : unread}
                                        </Badge>
                                    ) : null}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <NavTeamProjects />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
