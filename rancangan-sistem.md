# Rancangan Sistem: Cold Storage Fish Stock Management

Versi 1 · 3 Oktober 2026 · Disusun dari spesifikasi awal + semua default di [review-spesifikasi.md](review-spesifikasi.md) yang sudah disetujui.

---

## 1. Ringkasan

Web app (Laravel + Inertia.js + React + Tailwind) untuk melacak setiap dus ikan di cold storage dari masuk sampai keluar, dengan tujuan utama mencegah kecolongan. Setiap dus punya stiker QR berisi ID unik. Semua detail dus disimpan di database.

Prinsip anti-kecolongan yang dipegang di seluruh sistem:

1. **Tidak ada dus keluar tanpa order.** Staf hanya bisa scan keluar terhadap Order Keluar yang dibuat Admin.
2. **Tidak ada perubahan tanpa jejak.** Setiap kejadian (masuk, keluar, revisi, pindah lokasi, adjustment, pembatalan) tercatat di log yang tidak bisa diedit atau dihapus siapa pun.
3. **Stok hanya berkurang lewat dua jalan:** scan keluar terhadap order, atau adjustment yang di-approve Owner.
4. **Hanya QR yang dikeluarkan sistem yang bisa dipakai**, dan setiap QR hanya bisa dipakai sekali.

Asumsi: satu gudang, berat per dus flat per produk (`kg_per_carton`, contoh data 10 kg), sehingga KG = jumlah dus (MC) x berat per dus. Jenis, grade, dan size digabung dalam satu tabel `products`: satu kombinasi = satu produk (MB A 3-5 dan MB A 6-10 adalah dua produk). Di dalam cold storage tidak ada jaringan: scan Inbound dan Outbound dilakukan di area bongkar muat di luar cold storage (online), sedangkan di dalam cold storage staf hanya mengambil dus berdasarkan daftar ambil FEFO yang tersimpan di perangkat atau dicetak. Tidak ada fitur Stock Opname berbasis scan: pengecekan fisik dilakukan manual dengan laporan stok per lokasi.

---

## 2. Role dan Hak Akses

| Fitur | Staff | Admin | Owner |
|---|:-:|:-:|:-:|
| Scan Inbound (batch) | ✓ | ✓ | |
| Scan Outbound (terhadap order) | ✓ | ✓ | |
| Generate & cetak stiker QR | | ✓ | |
| Kelola produk ikan | | ✓ | ✓ |
| Kelola lokasi | | ✓ | |
| Buat / tutup Order Keluar | | ✓ | |
| Revisi data dus (produk, expired) | | ✓ | |
| Pindah lokasi dus | | ✓ | |
| Batalkan scan yang salah | | ✓ | |
| Ajukan adjustment (hilang / rusak) | | ✓ | |
| Approve / reject adjustment | | | ✓ |
| Dashboard & laporan | | lihat stok | ✓ penuh |
| Log aktivitas | | ✓ | ✓ |
| Kelola akun user | | | ✓ |

Staff tidak punya hak edit atau hapus apa pun. Admin tidak bisa mengurangi stok sendiri tanpa approval Owner. Owner tidak melakukan operasional gudang, hanya memantau, memutuskan, serta mengelola user dan produk ikan.

---

## 3. Siklus Hidup

### 3.1 Stiker QR (`qr_labels.status`)

```
available ──(scan inbound)──► used
    │
    └──(dibatalkan Admin, misal stiker rusak)──► void
```

Format kode: `DUS-YYMMDD-NNNN` (tanggal = tanggal generate, nomor urut per hari). Scan inbound untuk kode yang tidak ada di tabel, berstatus `used`, atau `void` langsung ditolak.

### 3.2 Dus (`boxes.status`)

```
                 ┌──────────(scan keluar terhadap order)──────────► outbound
                 │                                                     │
in_warehouse ────┤                                       (Admin batalkan scan keluar,
     ▲           │                                        selama order masih open)
     │           │                                                     │
     │           └──(Admin ajukan adjustment)──► pending_adjustment     │
     │                                               │                 │
     ├──────────(Owner reject)───────────────────────┤                 │
     │                                               ├─(Owner approve, hilang)─► lost
     │                                               └─(Owner approve, rusak)──► damaged
     └─────────────────────────────────────────────────────────────────┘
```

Pembatalan scan inbound oleh Admin: dus dihapus secara soft delete dan stiker QR kembali `available`. Tetap tercatat di log.

### 3.3 Order Keluar (`outbound_orders.status`)

`draft` → `open` → `completed` (semua item terpenuhi) atau `closed` (ditutup Admin sebelum terpenuhi, wajib alasan). `draft` juga bisa `cancelled`.

### 3.4 Adjustment (`adjustments.status`)

`pending` → `approved` atau `rejected`.

---

## 4. Alur Kerja per Modul

### 4.1 Generate Stiker QR (Admin)

1. Admin memasukkan jumlah stiker (misal 500).
2. Sistem membuat 500 baris `qr_labels` berstatus `available` dalam satu `print_batch`.
3. Sistem menampilkan halaman cetak (layout lembar stiker, bisa dicetak ulang per batch).

### 4.2 Inbound, Mode Batch (Staff)

1. Staf membuka **Batch Masuk Baru**, mengisi sekali: nama supplier, nomor surat jalan (opsional), produk ikan, lokasi, dan tanggal.
2. **Tanggal:** staf mengisi tanggal yang tercetak di dus.
   - Jika yang tercetak tanggal produksi, staf isi `production_date`. Sistem mengisi `expired_date` otomatis = tanggal produksi + masa simpan produk (dari master).
   - Jika yang tercetak tanggal expired, staf isi `expired_date` langsung.
3. Staf scan dus satu per satu. Setiap scan:
   - Validasi: QR harus `available`.
   - Membuat baris `boxes` dengan data batch saat ini, mengubah QR menjadi `used`, mencatat log.
   - Layar menampilkan bunyi/warna sukses atau gagal dan penghitung dus.
4. Jika di tengah batch ada dus dengan tanggal, produk, atau lokasi berbeda, staf mengubah field tersebut. Scan berikutnya memakai nilai baru (setiap dus menyimpan nilainya sendiri).
5. Staf menekan **Selesai Batch**. Ringkasan per produk dan tanggal ditampilkan.

### 4.3 Order Keluar (Admin)

1. Admin membuat order: tujuan/customer, tanggal, catatan, dan item (produk + jumlah dus).
2. Untuk setiap item sistem menampilkan **stok tersedia** dan menolak jumlah yang melebihinya.
   - Stok tersedia = jumlah dus `in_warehouse` untuk kombinasi itu − sisa kebutuhan order lain yang masih `open`.
   - Dus `pending_adjustment` otomatis tidak terhitung karena statusnya bukan `in_warehouse`.
3. Admin membuka order (`open`). Order muncul di daftar Outbound milik Staff.

### 4.4 Outbound dengan FEFO (Staff)

1. Staf memilih order yang `open`.
2. Untuk setiap item, sistem menampilkan **daftar rekomendasi FEFO**: dus `in_warehouse` yang cocok, diurutkan dari `expired_date` terdekat, sebanyak sisa kebutuhan, dikelompokkan per tanggal dan lokasi. Jika tanggal terdekat tidak cukup, daftar otomatis lanjut ke tanggal berikutnya. Daftar ini tersimpan di perangkat atau dicetak, karena dus diambil di dalam cold storage tanpa jaringan.
3. Staf scan dus di area bongkar muat. Validasi:
   - Dus harus `in_warehouse`. Jika tidak, ditolak.
   - Produk dus harus cocok dengan salah satu item order yang belum terpenuhi. Jika tidak, ditolak.
   - **Cek FEFO:** jika masih ada dus cocok lain yang belum discan dengan `expired_date` lebih awal dari dus ini, muncul peringatan. Staf boleh lanjut dengan wajib mengisi alasan. Scan ditandai `fefo_violation`.
4. Scan sukses: dus menjadi `outbound`, `scanned_out_by` dan `scanned_out_at` terisi, `outbound_order_id` terisi, log tercatat.
5. Jika semua item terpenuhi, order otomatis `completed`.

Dus dikunci dengan transaksi database (`SELECT ... FOR UPDATE`) supaya dua staf tidak bisa scan dus yang sama bersamaan.

### 4.5 Pengecekan Stok Manual (Admin)

1. Admin membuka laporan stok per lokasi: jumlah dus per produk, beserta daftar kode dus di setiap lokasi.
2. Laporan dicetak atau diekspor ke Excel sebagai lembar hitung, lalu dicocokkan dengan fisik secara manual.
3. Laporan mutasi menampilkan masuk, keluar, dan adjustment per periode, sehingga stok akhir bisa ditelusuri.
4. Jika ada selisih, Admin menelusuri dus lewat daftar kode dan riwayat log, lalu mengajukan adjustment atau memperbarui lokasi.

### 4.6 Adjustment (Admin ajukan, Owner putuskan)

1. Admin memilih dus, jenis adjustment (`lost` / `damaged`), alasan (wajib), foto (opsional).
2. Dus menjadi `pending_adjustment` dan stok tersedia berkurang. Stok riil di laporan Owner tetap dihitung sampai diputuskan, ditampilkan terpisah sebagai "menunggu approval".
3. Owner melihat daftar pengajuan dan menekan **Approve** atau **Reject** (catatan opsional).
   - Approve: dus menjadi `lost` atau `damaged`.
   - Reject: dus kembali `in_warehouse`.

### 4.7 Revisi Data dan Koreksi (Admin)

- **Revisi data dus** (produk, tanggal produksi/expired): wajib alasan. Nilai lama dan baru tercatat di log dan muncul di feed aktivitas Owner. Tidak perlu approval.
- **Pindah lokasi**: per dus atau massal (scan beberapa dus lalu pilih lokasi tujuan). Tercatat di log.
- **Batalkan scan inbound**: wajib alasan, lihat bagian 3.2.
- **Batalkan scan outbound**: hanya selama order masih `open`. Dus kembali `in_warehouse`, wajib alasan.

---

## 5. Skema Database

Semua tabel memakai `id` UUID sebagai primary key dan `timestamps`, kecuali disebutkan lain.

### 5.1 Pengguna

**`users`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| name | string | |
| username | string, unique | login dengan username (lebih mudah di lapangan) |
| email | string, nullable, unique | |
| password | string | |
| role | enum: `staff`, `admin`, `owner` | |
| is_active | boolean, default true | user nonaktif tidak bisa login, data historis tetap |

### 5.2 Master Data

**`products`** (produk ikan: satu baris = satu kombinasi jenis, grade, size)
| Kolom | Tipe | Keterangan |
|---|---|---|
| code | string, unique | misal `MB-A-3-5` |
| fish_name | string | jenis ikan, misal "MB", "SARDEN" |
| grade | string, default kosong | misal "A", "B", "PP"; kosong jika tidak ada |
| size | string, default kosong | misal "3-5", "15-20"; kosong jika tidak ada |
| display_name | string | otomatis: gabungan jenis, grade, size, misal "MB A 3-5" |
| kg_per_carton | decimal, default 10 | untuk menghitung KG di laporan |
| shelf_life_days | integer, nullable | untuk menghitung expired dari tanggal produksi |
| is_active | boolean | |

Unique: `(fish_name, grade, size)`. Grade dan size disimpan sebagai string kosong (bukan NULL) supaya constraint unique tetap berlaku. Contoh: MB A 3-5, MB A 6-10, dan MB B 6-10 adalah tiga produk berbeda.

**`locations`**: `name` (unique, misal "Blok A"), `description`, `is_active`

Master tidak dihapus, hanya dinonaktifkan, supaya data historis tetap valid.

### 5.3 Stiker QR

**`qr_print_batches`**: `quantity`, `generated_by` (FK users)

**`qr_labels`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| code | string, unique | `DUS-YYMMDD-NNNN` |
| qr_print_batch_id | FK | |
| status | enum: `available`, `used`, `void` | |
| used_at | timestamp, nullable | |

### 5.4 Inbound

**`inbound_batches`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| supplier_name | string | |
| delivery_note_number | string, nullable | nomor surat jalan |
| notes | text, nullable | |
| created_by | FK users | |
| started_at / finished_at | timestamp | |

### 5.5 Dus

**`boxes`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| qr_label_id | FK qr_labels, unique | |
| qr_code | string, unique | duplikat kode untuk pencarian cepat |
| inbound_batch_id | FK | |
| product_id | FK products | |
| location_id | FK, nullable | |
| production_date | date, nullable | |
| expired_date | date | dasar FEFO, index |
| status | enum: `in_warehouse`, `outbound`, `pending_adjustment`, `lost`, `damaged` | index |
| scanned_in_by | FK users | |
| scanned_in_at | timestamp | |
| outbound_order_id | FK, nullable | |
| scanned_out_by | FK users, nullable | |
| scanned_out_at | timestamp, nullable | |
| deleted_at | timestamp, nullable | soft delete untuk pembatalan inbound |

Index gabungan: `(product_id, status, expired_date)` untuk query FEFO dan stok.

### 5.6 Outbound

**`outbound_orders`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| order_number | string, unique | misal `OUT-261003-001` |
| destination | string | customer / tujuan |
| order_date | date | |
| notes | text, nullable | |
| status | enum: `draft`, `open`, `completed`, `closed`, `cancelled` | |
| close_reason | text, nullable | |
| created_by | FK users | |

**`outbound_order_items`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| outbound_order_id | FK | |
| product_id | FK products | |
| quantity_requested | integer | |
| quantity_scanned | integer, default 0 | |

**`outbound_scans`** (satu baris per dus yang discan keluar)
| Kolom | Tipe | Keterangan |
|---|---|---|
| outbound_order_item_id | FK | |
| box_id | FK | |
| scanned_by | FK users | |
| fefo_violation | boolean | |
| fefo_reason | text, nullable | |
| cancelled_at / cancelled_by / cancel_reason | nullable | pembatalan oleh Admin |

### 5.7 Adjustment

**`adjustments`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| box_id | FK | |
| type | enum: `lost`, `damaged` | |
| reason | text | wajib |
| photo_path | string, nullable | |
| status | enum: `pending`, `approved`, `rejected` | |
| requested_by | FK users | |
| decided_by | FK users, nullable | |
| decided_at | timestamp, nullable | |
| decision_note | text, nullable | |

Aturan: satu dus hanya boleh punya satu adjustment `pending`.

### 5.8 Log Aktivitas (immutable)

**`activity_logs`** (id bigint auto-increment, hanya `created_at`)
| Kolom | Tipe | Keterangan |
|---|---|---|
| user_id | FK users | |
| action | string | lihat daftar di bawah |
| subject_type / subject_id | morph | entitas utama (box, order, adjustment, dst) |
| box_id | FK, nullable | untuk riwayat per dus |
| old_values / new_values | json, nullable | |
| reason | text, nullable | |
| ip_address / user_agent | string, nullable | |
| created_at | timestamp | |

Action: `box.scanned_in`, `box.scanned_out`, `box.updated`, `box.location_changed`, `box.inbound_cancelled`, `box.outbound_cancelled`, `box.fefo_override`, `qr.generated`, `qr.voided`, `order.created`, `order.opened`, `order.closed`, `adjustment.requested`, `adjustment.approved`, `adjustment.rejected`, `user.created`, `user.updated`, `user.login`.

Cara menjaga log tidak bisa diubah:
- Tidak ada route atau UI untuk edit/hapus log.
- Model `ActivityLog` melempar exception pada `updating` dan `deleting`.
- Opsional (disarankan untuk produksi): user database aplikasi hanya diberi hak `INSERT` dan `SELECT` pada tabel ini, atau dipasang trigger database yang menolak `UPDATE`/`DELETE`.

### 5.9 Relasi Ringkas

```
users ─┬─< inbound_batches ─< boxes >─ qr_labels >─ qr_print_batches
       │                       │ │
       │      products / locations ──< boxes
       │                       │ │
       ├─< outbound_orders ─< outbound_order_items ─< outbound_scans >─ boxes
       ├─< adjustments >─ boxes
       └─< activity_logs
```

---

## 6. Halaman per Role

### Staff (dioptimalkan untuk HP/Tablet dan scanner fisik)
- **Beranda**: dua tombol besar: Masuk dan Keluar.
- **Inbound Batch**: form header batch + area scan + daftar dus yang sudah discan.
- **Outbound**: daftar order `open` → halaman scan per order dengan daftar rekomendasi FEFO dan progress per item.

### Admin
- Dashboard stok (ringkas)
- Stok: daftar dus dengan filter (produk, jenis, grade, size, lokasi, status, rentang expired) dan detail dus berisi riwayat lengkap dari log
- Order Keluar: daftar, buat, detail
- Laporan stok per lokasi untuk pengecekan manual
- Adjustment: daftar pengajuan dan form pengajuan
- Stiker QR: generate dan cetak ulang
- Master Data: produk ikan, lokasi
- Log Aktivitas
- Semua halaman scan Staff

### Owner
- **Dashboard**:
  - Rekap stok per produk dalam MC dan KG
  - Dus mendekati expired (misal ≤ 30 hari, bisa diatur)
  - Adjustment menunggu approval (dengan tombol Approve/Reject)
  - Pelanggaran FEFO terbaru
  - Revisi data dan pembatalan scan terbaru oleh Admin
  - Ringkasan masuk/keluar per hari atau minggu
- Log Aktivitas (filter per user, aksi, dus, tanggal)
- Master Data: produk ikan
- Laporan dengan export Excel: stok per tanggal, mutasi masuk/keluar, adjustment, stok per lokasi
- Kelola User

---

## 7. Komponen Scan

Satu komponen React `ScanInput` dipakai di semua halaman scan:
- Input teks auto-focus dan otomatis kembali fokus setelah setiap scan atau saat area lain disentuh.
- Scanner fisik bekerja sebagai keyboard: mengetik kode lalu `Enter` memicu submit.
- Tombol **Scan dengan Kamera** membuka kamera perangkat (memerlukan HTTPS).
- Umpan balik langsung: bunyi dan warna berbeda untuk sukses, peringatan (FEFO), dan gagal, karena staf di lapangan sering tidak melihat layar.
- Input dinonaktifkan sebentar saat request berjalan untuk mencegah scan ganda.

---

## 8. Aturan Bisnis Penting

1. Scan inbound hanya menerima QR `available`.
2. Scan outbound hanya untuk dus `in_warehouse` yang cocok dengan item order `open` yang belum terpenuhi.
3. Jumlah item order tidak boleh melebihi stok tersedia saat order dibuat atau dibuka.
4. Pelanggaran FEFO boleh dilanjutkan dengan alasan wajib, dan selalu tercatat.
5. Stok hanya berkurang lewat scan keluar atau adjustment yang di-approve Owner.
6. Setiap perubahan oleh Admin wajib alasan dan tercatat beserta nilai lama/baru.
7. Data master dan user tidak dihapus, hanya dinonaktifkan.
8. Semua operasi scan berjalan dalam transaksi database dengan row lock.

---

## 9. Catatan Teknis

- **Backend**: Laravel versi terbaru, Inertia.js, otorisasi dengan Gate/Policy berdasarkan kolom `role` (tiga role tetap, tidak perlu package permission).
- **Frontend**: React + Tailwind, layout mobile-first untuk halaman scan, layout tabel untuk halaman Admin/Owner.
- **Database**: MySQL atau PostgreSQL.
- **Library yang disarankan**: generator QR di PHP (misal `chillerlan/php-qrcode`), scan kamera di browser (misal `html5-qrcode` atau `@zxing/browser`), export Excel (misal `maatwebsite/excel`).
- **Hosting**: wajib HTTPS (untuk kamera dan PWA offline). Pastikan WiFi atau sinyal memadai di area bongkar muat.
- **Offline**: hanya daftar ambil FEFO yang tersimpan di perangkat (atau dicetak) untuk dipakai di dalam cold storage.
- **Testing**: feature test untuk setiap aturan bisnis di bagian 8, terutama validasi scan, FEFO, dan alur adjustment.

---

## 10. Tahapan Pembangunan

| Tahap | Isi | Hasil yang bisa dipakai |
|---|---|---|
| 1. Fondasi | Setup project, login, role, kelola user, master data, `activity_logs` | Owner bisa membuat akun, Admin mengisi master |
| 2. Stiker & Inbound | Generate/cetak QR, komponen `ScanInput`, inbound batch, daftar stok | Barang masuk bisa dicatat |
| 3. Outbound | Order Keluar, stok tersedia, scan keluar dengan FEFO | Barang keluar terkontrol |
| 4. Koreksi & Adjustment | Revisi data, pindah lokasi, pembatalan scan, adjustment dan approval | Admin dan Owner bisa mengoreksi dengan jejak |
| 5. Dashboard & Laporan | Dashboard Owner, laporan stok per lokasi dan mutasi, export Excel, peringatan expired | Owner bisa memantau penuh |
| 6. Kamera & Polishing | Scan kamera, bunyi, uji di perangkat lapangan | Siap dipakai operasional |

Tahap 1–3 sudah cukup untuk mulai uji coba di gudang.

---

## 11. Hal yang Masih Terbuka

- Tanggal apa yang tercetak di dus supplier (produksi atau expired). Rancangan ini mendukung keduanya.
- Batas "mendekati expired" untuk dashboard. Default: 30 hari.
- Ukuran dan layout kertas stiker QR yang dipakai untuk cetak.
