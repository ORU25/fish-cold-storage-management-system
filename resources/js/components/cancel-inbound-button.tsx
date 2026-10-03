import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Admin-only undo of a wrong inbound scan (PRD 5.9). Renders nothing for other roles. */
export function CancelInboundButton({ box }: { box: { id: string; qr_code: string } }) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (auth.user.role !== 'admin') {
        return null;
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('boxes.cancel-inbound', box.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <>
            <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
                Batalkan
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Batalkan scan masuk {box.qr_code}?</DialogTitle>
                        <DialogDescription>
                            Dus dikeluarkan dari stok dan stikernya kembali bisa discan, misalnya ke batch yang benar. Pembatalan tercatat di log.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor={`reason-${box.id}`}>Alasan</Label>
                            <Input
                                id={`reason-${box.id}`}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                placeholder="Contoh: salah masuk batch"
                                required
                            />
                            <InputError message={form.errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" variant="destructive" disabled={form.processing}>
                                Batalkan scan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
