import { type BadgeProps } from '@/components/ui/badge';

type BadgeVariant = BadgeProps['variant'];

export const BOX_STATUS_LABELS: Record<string, string> = {
    in_warehouse: 'Di gudang',
    outbound: 'Keluar',
    pending_adjustment: 'Menunggu approval',
    lost: 'Hilang',
    damaged: 'Rusak',
};

export const ORDER_STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    open: 'Open',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

export const QR_STATUS_LABELS: Record<string, string> = {
    available: 'Available',
    used: 'Used',
    void: 'Void',
};

export const ADJUSTMENT_TYPE_LABELS: Record<string, string> = {
    lost: 'Hilang',
    damaged: 'Rusak',
};

export const ADJUSTMENT_STATUS_LABELS: Record<string, string> = {
    pending: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

/** Box history actions; other actions are shown as their raw name. */
export const ACTION_LABELS: Record<string, string> = {
    'box.scanned_in': 'Scan masuk',
    'box.inbound_cancelled': 'Scan masuk dibatalkan',
    'box.scanned_out': 'Scan keluar',
    'box.outbound_cancelled': 'Scan keluar dibatalkan',
    'box.fefo_override': 'Keluar melanggar FEFO',
    'box.updated': 'Revisi data',
    'box.location_changed': 'Pindah lokasi',
    'box.move_cancelled': 'Pindah lokasi dibatalkan',
    'order.cancelled': 'Order dibatalkan',
    'qr.voided': 'Stiker di-void',
    'adjustment.requested': 'Diajukan hilang/rusak',
    'adjustment.approved': 'Adjustment disetujui',
    'adjustment.rejected': 'Adjustment ditolak',
};

/** Box history colors: the status the box ends up in (same colors as BOX_STATUS_BADGE); corrections are red, data changes blue. */
export const ACTION_BADGE: Record<string, BadgeVariant> = {
    'box.scanned_in': 'success',
    'box.inbound_cancelled': 'danger',
    'box.scanned_out': 'neutral',
    'box.outbound_cancelled': 'danger',
    'box.fefo_override': 'warning',
    'box.updated': 'info',
    'box.location_changed': 'info',
    'box.move_cancelled': 'danger',
    'order.cancelled': 'danger',
    'qr.voided': 'danger',
    'adjustment.requested': 'warning',
    'adjustment.approved': 'danger',
    'adjustment.rejected': 'success',
};

export const BOX_STATUS_BADGE: Record<string, BadgeVariant> = {
    in_warehouse: 'success',
    outbound: 'neutral',
    pending_adjustment: 'warning',
    lost: 'danger',
    damaged: 'danger',
};

export const ORDER_STATUS_BADGE: Record<string, BadgeVariant> = {
    draft: 'neutral',
    open: 'info',
    completed: 'success',
    cancelled: 'danger',
};

export const QR_STATUS_BADGE: Record<string, BadgeVariant> = {
    available: 'success',
    used: 'neutral',
    void: 'danger',
};

export const ADJUSTMENT_STATUS_BADGE: Record<string, BadgeVariant> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

export const ADJUSTMENT_TYPE_BADGE: Record<string, BadgeVariant> = {
    lost: 'danger',
    damaged: 'warning',
};
