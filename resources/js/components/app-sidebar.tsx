import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Boxes, ClipboardList, Fish, History, LayoutGrid, MapPin, PackageMinus, PackagePlus, QrCode, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Barang Masuk',
        url: '/inbound',
        icon: PackagePlus,
        roles: ['staff', 'admin'],
    },
    {
        title: 'Barang Keluar',
        url: '/outbound',
        icon: PackageMinus,
        roles: ['staff', 'admin'],
    },
    {
        title: 'Order Keluar',
        url: '/orders',
        icon: ClipboardList,
        roles: ['admin'],
    },
    {
        title: 'Stok',
        url: '/stock',
        icon: Boxes,
        roles: ['owner', 'admin'],
    },
    {
        title: 'Pindah Lokasi',
        url: '/box-moves',
        icon: ArrowLeftRight,
        roles: ['admin'],
    },
    {
        title: 'Stiker QR',
        url: '/qr-labels',
        icon: QrCode,
        roles: ['admin'],
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
    const items = mainNavItems.filter((item) => !item.roles || auth.user.role === 'owner' || item.roles.includes(auth.user.role));

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
