import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Order {
    id: string;
    order_number: string;
    destination: string;
    order_date: string;
    notes: string | null;
    items: { product_id: string; quantity_requested: number }[];
}

interface Props {
    order: Order | null;
    products: { id: string; display_name: string }[];
    /** product id => boxes in the warehouse minus what other open orders still need */
    available: Record<string, number>;
}

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
const today = () => new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });

export default function OrderForm({ order, products, available }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Order Keluar', href: '/orders' },
        order ? { title: order.order_number, href: route('orders.edit', order.id) } : { title: 'Buat order', href: '/orders/create' },
    ];
    const form = useForm({
        destination: order?.destination ?? '',
        order_date: order?.order_date ?? today(),
        notes: order?.notes ?? '',
        items: order?.items.map((item) => ({ product_id: item.product_id, quantity: String(item.quantity_requested) })) ?? [
            { product_id: '', quantity: '' },
        ],
        open: false,
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setItem = (index: number, changes: Partial<(typeof form.data.items)[number]>) =>
        form.setData(
            'items',
            form.data.items.map((item, i) => (i === index ? { ...item, ...changes } : item)),
        );

    const submit: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        const open = (e.nativeEvent as SubmitEvent).submitter?.getAttribute('value') === 'open';
        form.transform((data) => ({ ...data, open }));
        if (order) {
            form.put(route('orders.update', order.id));
        } else {
            form.post(route('orders.store'));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={order ? `Ubah ${order.order_number}` : 'Buat order'} />
            <form onSubmit={submit} className="mx-auto grid w-full max-w-3xl gap-6 p-4">
                <Heading
                    title={order ? `Ubah ${order.order_number}` : 'Buat Order Keluar'}
                    description="Order disimpan sebagai draft. Stok baru dipesan dan order muncul di layar staf setelah dibuka."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="destination">Tujuan / customer</Label>
                        <Input
                            id="destination"
                            value={form.data.destination}
                            onChange={(e) => form.setData('destination', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.destination} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="order_date">Tanggal</Label>
                        <Input
                            id="order_date"
                            type="date"
                            value={form.data.order_date}
                            onChange={(e) => form.setData('order_date', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.order_date} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="notes">Catatan (opsional)</Label>
                        <Input id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>

                <section className="grid gap-3">
                    <h2 className="font-semibold">Item</h2>
                    {form.data.items.map((item, index) => (
                        <div key={index} className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_8rem_auto] sm:items-start">
                            <div className="grid gap-1">
                                <select
                                    className={selectClass}
                                    value={item.product_id}
                                    onChange={(e) => setItem(index, { product_id: e.target.value })}
                                    aria-label="Produk"
                                    required
                                >
                                    <option value="">Pilih produk</option>
                                    {products.map((product) => (
                                        <option key={product.id} value={product.id}>
                                            {product.display_name} (tersedia {available[product.id] ?? 0})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors[`items.${index}.product_id`]} />
                            </div>
                            <div className="grid gap-1">
                                <Input
                                    type="number"
                                    min="1"
                                    max={item.product_id ? Math.max(available[item.product_id] ?? 0, 1) : undefined}
                                    placeholder="Jumlah dus"
                                    value={item.quantity}
                                    onChange={(e) => setItem(index, { quantity: e.target.value })}
                                    aria-label="Jumlah dus"
                                    required
                                />
                                <InputError message={errors[`items.${index}.quantity`]} />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Hapus item"
                                disabled={form.data.items.length === 1}
                                onClick={() =>
                                    form.setData(
                                        'items',
                                        form.data.items.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <InputError message={form.errors.items} />
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-self-start"
                        onClick={() => form.setData('items', [...form.data.items, { product_id: '', quantity: '' }])}
                    >
                        <Plus className="size-4" /> Tambah item
                    </Button>
                </section>

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" value="draft" variant="outline" disabled={form.processing}>
                        Simpan draft
                    </Button>
                    <Button type="submit" value="open" disabled={form.processing}>
                        Simpan dan buka order
                    </Button>
                    {form.processing && (
                        <span className="text-muted-foreground flex items-center gap-2 text-sm">
                            <LoaderCircle className="size-4 animate-spin" /> Menyimpan…
                        </span>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
