import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    ClipboardList,
    FileSpreadsheet,
    Fish,
    History,
    LayoutGrid,
    MapPin,
    PackageMinus,
    PackagePlus,
    QrCode,
    ShieldAlert,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const navGroups: { label?: string; items: NavItem[] }[] = [
    {
        items: [
            {
                title: 'Dashboard',
                url: '/dashboard',
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Operasional',
        items: [
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
                title: 'Pindah Lokasi',
                url: '/box-moves',
                icon: ArrowLeftRight,
                roles: ['admin'],
            },
        ],
    },
    {
        label: 'Stok & Laporan',
        items: [
            {
                title: 'Stok',
                url: '/stock',
                icon: Boxes,
                roles: ['owner', 'admin'],
            },
            {
                title: 'Adjustment',
                url: '/adjustments',
                icon: ShieldAlert,
                roles: ['owner', 'admin'],
            },
            {
                title: 'Laporan',
                url: '/reports',
                icon: FileSpreadsheet,
                roles: ['owner', 'admin'],
            },
            {
                title: 'Log Aktivitas',
                url: '/activity-logs',
                icon: History,
                roles: ['owner', 'admin'],
            },
        ],
    },
    {
        label: 'Master Data',
        items: [
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
                title: 'Stiker QR',
                url: '/qr-labels',
                icon: QrCode,
                roles: ['admin'],
            },
            {
                title: 'Pengguna',
                url: '/users',
                icon: Users,
                roles: ['owner'],
            },
        ],
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const groups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.roles || auth.user.role === 'owner' || item.roles.includes(auth.user.role)),
        }))
        .filter((group) => group.items.length > 0);

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
                {groups.map((group) => (
                    <NavMain key={group.label ?? 'main'} label={group.label} items={group.items} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
