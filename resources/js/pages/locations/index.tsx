import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Location {
    id: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Lokasi', href: '/locations' }];

const emptyForm = { name: '', description: '', is_active: true as boolean };

export default function LocationsIndex({ locations }: { locations: Location[] }) {
    const [editing, setEditing] = useState<Location | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    const openForm = (location: Location | null) => {
        form.clearErrors();
        form.setData(location ? { name: location.name, description: location.description ?? '', is_active: location.is_active } : emptyForm);
        setEditing(location);
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(route('locations.update', editing.id), options);
        } else {
            form.post(route('locations.store'), options);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Lokasi" />
            <div className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Lokasi" description="Lokasi tidak bisa dihapus, hanya dinonaktifkan." />
                    <Button onClick={() => openForm(null)}>Tambah</Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Nama</th>
                                <th className="p-3">Keterangan</th>
                                <th className="p-3">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {locations.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="text-muted-foreground p-6 text-center">
                                        Belum ada lokasi.
                                    </td>
                                </tr>
                            )}
                            {locations.map((location) => (
                                <tr key={location.id} className="border-t">
                                    <td className="p-3 font-medium">{location.name}</td>
                                    <td className="p-3">{location.description ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={location.is_active ? 'default' : 'secondary'}>
                                            {location.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button variant="outline" size="sm" onClick={() => openForm(location)}>
                                            Ubah
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editing ? `Ubah ${editing.name}` : 'Tambah lokasi'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama</Label>
                            <Input
                                id="name"
                                placeholder="Blok A"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Keterangan</Label>
                            <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            <InputError message={form.errors.description} />
                        </div>
                        {editing && (
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                Aktif (muncul di pilihan)
                            </label>
                        )}
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
