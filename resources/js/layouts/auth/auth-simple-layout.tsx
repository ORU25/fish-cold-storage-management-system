import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 bg-[radial-gradient(var(--color-border)_1px,transparent_1px)] [background-size:20px_20px] p-4 sm:p-6 md:p-10">
            <div className="grid w-full max-w-sm gap-6">
                <Link href={route('home')} className="flex flex-col items-center gap-3 text-center">
                    <AppLogoIcon className="size-16" />
                    <div>
                        <div className="text-2xl font-semibold tracking-tight">Cold Storage</div>
                        <div className="text-muted-foreground text-sm">Stok ikan beku, dari truk sampai keluar gudang</div>
                    </div>
                </Link>

                <div className="bg-background grid gap-6 rounded-xl border p-6 shadow-sm sm:p-8">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-muted-foreground text-sm">{description}</p>
                    </div>
                    {children}
                </div>

                <p className="text-muted-foreground text-center text-xs">© {new Date().getFullYear()} Cold Storage</p>
            </div>
        </div>
    );
}
