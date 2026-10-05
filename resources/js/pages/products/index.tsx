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
import { Pencil, Plus, Save } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Product {
    id: string;
    code: string;
    fish_name: string;
    grade: string;
    size: string;
    display_name: string;
    kg_per_carton: string;
    shelf_life_days: number | null;
    is_active: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Produk Ikan', href: '/products' }];

const emptyForm = {
    code: '',
    fish_name: '',
    grade: '',
    size: '',
    kg_per_carton: '10',
    shelf_life_days: '',
    is_active: true as boolean,
};

export default function ProductsIndex({ products }: { products: Product[] }) {
    const [editing, setEditing] = useState<Product | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    const openForm = (product: Product | null) => {
        form.clearErrors();
        form.setData(
            product
                ? {
                      code: product.code,
                      fish_name: product.fish_name,
                      grade: product.grade,
                      size: product.size,
                      kg_per_carton: product.kg_per_carton,
                      shelf_life_days: product.shelf_life_days?.toString() ?? '',
                      is_active: product.is_active,
                  }
                : emptyForm,
        );
        setEditing(product);
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(route('products.update', editing.id), options);
        } else {
            form.post(route('products.store'), options);
        }
    };

    const preview = [form.data.fish_name, form.data.grade, form.data.size]
        .map((part) => part.trim().toUpperCase())
        .filter(Boolean)
        .join(' ');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Produk Ikan" />
            <div className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Produk Ikan"
                        description="Satu produk = satu kombinasi jenis, grade, dan size. Produk tidak bisa dihapus, hanya dinonaktifkan."
                    />
                    <Button onClick={() => openForm(null)}>
                        <Plus /> Tambah
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3">Kode</th>
                                <th className="p-3">Produk</th>
                                <th className="p-3">Jenis</th>
                                <th className="p-3">Grade</th>
                                <th className="p-3">Size</th>
                                <th className="p-3 text-right">Kg/dus</th>
                                <th className="p-3 text-right">Masa simpan</th>
                                <th className="p-3">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {products.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="text-muted-foreground p-6 text-center">
                                        Belum ada produk.
                                    </td>
                                </tr>
                            )}
                            {products.map((product) => (
                                <tr key={product.id} className="border-t">
                                    <td className="p-3 font-mono text-xs">{product.code}</td>
                                    <td className="p-3 font-medium">{product.display_name}</td>
                                    <td className="p-3">{product.fish_name}</td>
                                    <td className="p-3">{product.grade || '-'}</td>
                                    <td className="p-3">{product.size || '-'}</td>
                                    <td className="p-3 text-right">{Number(product.kg_per_carton)}</td>
                                    <td className="p-3 text-right">{product.shelf_life_days ? `${product.shelf_life_days} hari` : '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={product.is_active ? 'success' : 'neutral'}>{product.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button variant="outline" size="sm" onClick={() => openForm(product)}>
                                            <Pencil /> Ubah
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
                        <DialogTitle>{editing ? `Ubah ${editing.display_name}` : 'Tambah produk'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid grid-cols-3 gap-2">
                            <div className="grid gap-2">
                                <Label htmlFor="fish_name">Jenis ikan</Label>
                                <Input
                                    id="fish_name"
                                    placeholder="MB"
                                    value={form.data.fish_name}
                                    onChange={(e) => form.setData('fish_name', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="grade">Grade</Label>
                                <Input id="grade" placeholder="A" value={form.data.grade} onChange={(e) => form.setData('grade', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="size">Size</Label>
                                <Input id="size" placeholder="3-5" value={form.data.size} onChange={(e) => form.setData('size', e.target.value)} />
                            </div>
                        </div>
                        <InputError message={form.errors.fish_name || form.errors.grade || form.errors.size} />
                        <p className="text-muted-foreground -mt-2 text-xs">Grade dan size boleh kosong. Nama produk: {preview || '-'}</p>

                        <div className="grid gap-2">
                            <Label htmlFor="code">Kode (kosongkan untuk dibuat otomatis)</Label>
                            <Input id="code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                            <InputError message={form.errors.code} />
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-2">
                                <Label htmlFor="kg_per_carton">Kg per dus</Label>
                                <Input
                                    id="kg_per_carton"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={form.data.kg_per_carton}
                                    onChange={(e) => form.setData('kg_per_carton', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.kg_per_carton} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="shelf_life_days">Masa simpan (hari)</Label>
                                <Input
                                    id="shelf_life_days"
                                    type="number"
                                    min="1"
                                    value={form.data.shelf_life_days}
                                    onChange={(e) => form.setData('shelf_life_days', e.target.value)}
                                />
                                <InputError message={form.errors.shelf_life_days} />
                            </div>
                        </div>

                        {editing && (
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                Aktif (muncul di pilihan)
                            </label>
                        )}
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                <Save /> Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
