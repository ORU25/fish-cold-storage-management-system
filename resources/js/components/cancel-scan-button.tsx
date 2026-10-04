import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Admin and Owner undo of a wrong inbound or outbound scan (PRD 5.9). Renders nothing for Staff. */
export function CancelScanButton({ url, title, description }: { url: string; title: string; description: string }) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    if (auth.user.role === 'staff') {
        return null;
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(url, {
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
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="cancel-scan-reason">Alasan</Label>
                            <Input
                                id="cancel-scan-reason"
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                placeholder="Contoh: salah scan dus"
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
