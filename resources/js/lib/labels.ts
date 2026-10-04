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

/** Box history actions; other actions are shown as their raw name. */
export const ACTION_LABELS: Record<string, string> = {
    'box.scanned_in': 'Scan masuk',
    'box.inbound_cancelled': 'Scan masuk dibatalkan',
    'box.scanned_out': 'Scan keluar',
    'box.outbound_cancelled': 'Scan keluar dibatalkan',
    'box.fefo_override': 'Keluar melanggar FEFO',
    'box.updated': 'Revisi data',
    'box.location_changed': 'Pindah lokasi',
};
