import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Fish, History, LayoutGrid, MapPin, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Produk Ikan',
        url: '/products',
        icon: Fish,
        roles: ['owner', 'admin'],
    },
    {
        title: 'Lokasi',
        url: '/locations',
        icon: MapPin,
        roles: ['admin'],
    },
    {
        title: 'Pengguna',
        url: '/users',
        icon: Users,
        roles: ['owner'],
    },
    {
        title: 'Log Aktivitas',
        url: '/activity-logs',
        icon: History,
        roles: ['owner', 'admin'],
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const items = mainNavItems.filter((item) => !item.roles || item.roles.includes(auth.user.role));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
