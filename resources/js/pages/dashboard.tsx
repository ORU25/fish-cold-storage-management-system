import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Role, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Boxes, type LucideIcon, PackagePlus, QrCode } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// ponytail: quick links only; the Owner dashboard (PRD 5.11) comes in stage 5.
const ACTIONS: { title: string; description: string; href: string; icon: LucideIcon; roles: Role[] }[] = [
    { title: 'Barang Masuk', description: 'Scan dus dari truk', href: '/inbound', icon: PackagePlus, roles: ['staff', 'admin'] },
    { title: 'Stok', description: 'Rekap dan daftar dus', href: '/stock', icon: Boxes, roles: ['owner', 'admin'] },
    { title: 'Stiker QR', description: 'Buat dan cetak stiker', href: '/qr-labels', icon: QrCode, roles: ['admin'] },
];

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const actions = ACTIONS.filter((action) => action.roles.includes(auth.user.role));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                {actions.map((action) => (
                    <Link
                        key={action.href}
                        href={action.href}
                        className="hover:bg-muted/50 flex min-h-32 items-center gap-4 rounded-xl border p-6 transition-colors"
                    >
                        <action.icon className="size-10 shrink-0" />
                        <div>
                            <div className="text-xl font-semibold">{action.title}</div>
                            <div className="text-muted-foreground text-sm">{action.description}</div>
                        </div>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
