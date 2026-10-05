import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export interface Confirmation {
    title: string;
    description?: string;
    confirmLabel: string;
    variant?: 'default' | 'destructive' | 'success';
    onConfirm: () => void;
}

/** Confirmation modal used instead of the browser's confirm(); open while `confirmation` is set. */
export function ConfirmDialog({ confirmation, onClose }: { confirmation: Confirmation | null; onClose: () => void }) {
    return (
        <Dialog open={confirmation !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{confirmation?.title}</DialogTitle>
                    {confirmation?.description && <DialogDescription>{confirmation.description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        variant={confirmation?.variant ?? 'default'}
                        onClick={() => {
                            confirmation?.onConfirm();
                            onClose();
                        }}
                    >
                        {confirmation?.confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
