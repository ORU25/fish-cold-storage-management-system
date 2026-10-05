import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ADJUSTMENT_TYPE_LABELS } from '@/lib/labels';
import { useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

/** Owner's Approve / Reject buttons for one pending adjustment, with an optional note (rancangan 4.6). */
export function AdjustmentDecision({ adjustment }: { adjustment: { id: string; type: string; box: { qr_code: string } } }) {
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
    const form = useForm({ decision_note: '' });

    const open = (choice: 'approve' | 'reject') => {
        form.reset();
        form.clearErrors();
        setDecision(choice);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route(`adjustments.${decision}`, adjustment.id), { preserveScroll: true, onSuccess: () => setDecision(null) });
    };

    return (
        <>
            <div className="flex justify-end gap-2">
                <Button size="sm" variant="success" onClick={() => open('approve')}>
                    <Check /> Approve
                </Button>
                <Button size="sm" variant="outline" className="text-red-700 hover:text-red-800" onClick={() => open('reject')}>
                    <X /> Reject
                </Button>
            </div>

            <Dialog open={decision !== null} onOpenChange={(isOpen) => !isOpen && setDecision(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {decision === 'approve' ? 'Approve' : 'Reject'} {adjustment.box.qr_code}
                        </DialogTitle>
                        <DialogDescription>
                            {decision === 'approve'
                                ? `Dus menjadi ${ADJUSTMENT_TYPE_LABELS[adjustment.type].toLowerCase()} dan keluar dari stok.`
                                : 'Dus kembali berstatus di gudang.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor={`decision_note_${adjustment.id}`}>Catatan (opsional)</Label>
                            <Input
                                id={`decision_note_${adjustment.id}`}
                                value={form.data.decision_note}
                                onChange={(e) => form.setData('decision_note', e.target.value)}
                            />
                            <InputError message={form.errors.decision_note} />
                        </div>
                        <DialogFooter>
                            <Button type="submit" variant={decision === 'approve' ? 'success' : 'destructive'} disabled={form.processing}>
                                {decision === 'approve' ? <Check /> : <X />} {decision === 'approve' ? 'Approve' : 'Reject'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
