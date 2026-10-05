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

Asumsi: satu gudang, berat per dus flat per produk (`kg_per_carton`, contoh data 10 kg), sehingga KG = jumlah dus (MC) x berat per dus. Jenis, grade, dan size digabung dalam satu tabel `products`: satu kombinasi = satu produk (MB A 3-5 dan MB A 6-10 adalah dua produk). Di dalam cold storage tidak ada jaringan: scan Inbound dan Outbound dilakukan di area bongkar muat di luar cold storage (online), sedangkan di dalam cold storage staf hanya mengambil dus berdasarkan daftar ambil FEFO yang sudah dibuka di HP sebelum masuk (halaman dibiarkan terbuka, tanpa cetak). Tidak ada fitur Stock Opname berbasis scan: pengecekan fisik dilakukan manual dengan laporan stok per lokasi.

---

## 2. Role dan Hak Akses

| Fitur | Staff | Admin | Owner |
|---|:-:|:-:|:-:|
| Scan Inbound (batch) | ✓ | ✓ | ✓ |
| Scan Outbound (terhadap order) | ✓ | ✓ | ✓ |
| Generate & cetak stiker QR | | ✓ | ✓ |
| Kelola produk ikan | | ✓ | ✓ |
| Kelola lokasi | | ✓ | ✓ |
| Buat / batalkan Order Keluar | | ✓ | ✓ |
| Revisi data dus (produk, expired) | | ✓ | ✓ |
| Pindah lokasi dus | | ✓ | ✓ |
| Batalkan scan yang salah | | ✓ | ✓ |
| Ajukan adjustment (hilang / rusak) | | ✓ | ✓ |
| Approve / reject adjustment | | | ✓ |
| Dashboard & laporan | | dashboard ringkas, semua laporan | ✓ penuh |
| Log aktivitas | | ✓ | ✓ |
| Kelola akun user | | | ✓ |

Staff tidak punya hak edit atau hapus apa pun. Admin tidak bisa mengurangi stok sendiri tanpa approval Owner. Owner tanpa batasan: punya semua hak Admin dan Staff, ditambah approve adjustment dan kelola user. Di server, Owner lolos semua pengecekan role.

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
in_warehouse ────┤                                       (Admin batalkan scan keluar atau
     ▲           │                                        batalkan order, selama open)
     │           │                                                     │
     │           └──(Admin ajukan adjustment)──► pending_adjustment     │
     │                                               │                 │
     ├──────────(Owner reject)───────────────────────┤                 │
     │                                               ├─(Owner approve, hilang)─► lost
     │                                               └─(Owner approve, rusak)──► damaged
     └─────────────────────────────────────────────────────────────────┘
```

Pembatalan scan inbound oleh Admin, hanya selama batch masih berjalan: dus dihapus secara soft delete dan stiker QR kembali `available`. Tetap tercatat di log. Setelah batch ditutup, scan masuk tidak bisa dibatalkan.

### 3.3 Order Keluar (`outbound_orders.status`)

`draft` → `open` → `completed` (semua item terpenuhi dan sudah dicek fisik oleh Admin). `draft` dan `open` bisa `cancelled`:
- Dari `draft`: alasan opsional.
- Dari `open`: wajib alasan. Semua dus yang sudah discan untuk order itu kembali `in_warehouse` dan stok yang dipesan dilepas. Riwayat scan tetap tersimpan.

Tidak ada status "ditutup". Jika pembeli hanya sanggup mengambil sebagian, order dibatalkan lalu dibuat order baru sesuai kesanggupan pembeli. Order `completed` tidak bisa dibatalkan.

### 3.4 Adjustment (`adjustments.status`)

`pending` → `approved` atau `rejected`.

---

## 4. Alur Kerja per Modul

### 4.1 Generate Stiker QR (Admin)

1. Admin memasukkan jumlah stiker (maks. 100 per batch, supaya generate QR tidak membebani server).
2. Sistem membuat 500 baris `qr_labels` berstatus `available` dalam satu `print_batch`.
3. Sistem menampilkan halaman cetak (layout lembar stiker, bisa dicetak ulang per batch).
4. Halaman **detail batch** menampilkan setiap kode dengan statusnya (cari per kode, filter per status, 50 per halaman). Stiker `used` menampilkan dus-nya (produk, lokasi, status, expired) dengan link ke halaman Stok.
5. Void hanya dari detail batch: Admin mencentang stiker `available` (per stiker atau semua di halaman, maks. 50 sekali), lalu mengisi satu alasan untuk semuanya. Prosesnya atomic: jika ada stiker yang sudah tidak `available`, tidak ada yang di-void. Log `qr.voided` tetap dicatat per stiker.
6. Dari detail batch, Admin bisa mencetak ulang satu stiker `available` atau `used` dengan kode yang sama. Stiker `void` tidak bisa dicetak (server menolak).

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
6. Jika belum ada dus yang discan, tombolnya menjadi **Batalkan Batch**: batch dihapus (tidak ada data stok di dalamnya) dan pembatalannya tercatat di log sebagai `inbound.cancelled`. Batch yang sudah pernah berisi dus tidak bisa dibatalkan.

### 4.3 Order Keluar (Admin)

1. Admin membuat order: tujuan/customer, tanggal, catatan, dan item (produk + jumlah dus).
2. Untuk setiap item sistem menampilkan **stok tersedia** dan menolak jumlah yang melebihinya.
   - Stok tersedia = jumlah dus `in_warehouse` untuk kombinasi itu − sisa kebutuhan order lain yang masih `open`.
   - Dus `pending_adjustment` otomatis tidak terhitung karena statusnya bukan `in_warehouse`.
3. Admin membuka order (`open`). Order muncul di daftar Outbound milik Staff.

### 4.4 Outbound dengan FEFO (Staff)

1. Staf memilih order yang `open`.
2. Untuk setiap item, sistem menampilkan **daftar rekomendasi FEFO**: dus `in_warehouse` yang cocok, diurutkan dari `expired_date` terdekat, sebanyak sisa kebutuhan, dikelompokkan per tanggal dan lokasi. Jika tanggal terdekat tidak cukup, daftar otomatis lanjut ke tanggal berikutnya. Daftar ini dibuka di HP sebelum masuk dan tetap terlihat selama halaman tidak dimuat ulang, karena dus diambil di dalam cold storage tanpa jaringan. Tidak ada tombol cetak.
3. Staf scan dus di area bongkar muat. Validasi:
   - Dus harus `in_warehouse`. Jika tidak, ditolak.
   - Produk dus harus cocok dengan salah satu item order yang belum terpenuhi. Jika tidak, ditolak.
   - **Cek FEFO:** jika masih ada dus cocok lain yang belum discan dengan `expired_date` lebih awal dari dus ini, muncul peringatan. Staf boleh lanjut dengan wajib mengisi alasan. Scan ditandai `fefo_violation`.
4. Scan sukses: dus menjadi `outbound`, `scanned_out_by` dan `scanned_out_at` terisi, `outbound_order_id` terisi, log tercatat.
5. Jika semua item terpenuhi, order tetap `open` dengan tanda "menunggu pengecekan" dan tidak menerima scan lagi. Admin mengecek fisik barang, lalu menekan **Selesaikan order** sehingga order menjadi `completed`. Selama masih `open`, scan yang salah masih bisa dibatalkan Admin (lihat 4.7).
6. Jika pembeli hanya sanggup mengambil sebagian, Admin menekan **Batalkan order** dengan alasan: dus yang sudah discan kembali `in_warehouse` (tercatat `box.outbound_cancelled` per dus, dan semua scan-nya ditandai dibatalkan), lalu Admin membuat order baru sesuai jumlah yang sanggup diambil.

Dus dikunci dengan transaksi database (`SELECT ... FOR UPDATE`) supaya dua staf tidak bisa scan dus yang sama bersamaan.

### 4.5 Pengecekan Stok Manual (Admin)

1. Admin membuka **Laporan** (`/reports`), tab **Stok per lokasi**: jumlah dus per produk, beserta daftar kode dus (dengan expired, dus `pending_adjustment` ditandai) di setiap lokasi.
2. Laporan diunduh sebagai file Excel (.xlsx) sebagai lembar hitung: satu baris per dus berisi lokasi, produk, kode, expired, status, dan kolom kosong "Hitung fisik". Lalu dicocokkan dengan fisik secara manual.
3. Tab **Mutasi** (periode dari–sampai, default awal bulan sampai hari ini) menampilkan per produk: stok awal, masuk, keluar, adjustment, stok akhir, dengan stok awal + masuk − keluar − adjustment = stok akhir. Stok akhir sekaligus menjadi "stok per tanggal" di tanggal akhir periode.
   - Masuk = dus yang scan masuknya tidak dibatalkan; keluar = dus yang masih berstatus `outbound` (scan keluar yang dibatalkan tidak dihitung); adjustment = adjustment `approved` menurut `decided_at`.
   - Dihitung menurut produk dus saat ini: revisi produk ikut mengubah angka periode lampau.
   - Tab **Adjustment** menampilkan pengajuan dalam periode beserta keputusannya. Semua tab bisa diunduh sebagai file Excel (.xlsx).
4. Jika ada selisih, Admin menelusuri dus lewat daftar kode dan riwayat log, lalu mengajukan adjustment atau memperbarui lokasi.

### 4.6 Adjustment (Admin ajukan, Owner putuskan)

1. Dari detail dus, Admin menekan **Ajukan hilang/rusak**, lalu mengisi jenis adjustment (`lost` / `damaged`), alasan (wajib), foto (opsional, gambar maks. 5 MB, bisa langsung dari kamera HP). Hanya dus `in_warehouse` yang bisa diajukan, sehingga satu dus tidak pernah punya dua pengajuan `pending`.
2. Dus menjadi `pending_adjustment` dan stok tersedia berkurang. Stok riil di laporan Owner tetap dihitung sampai diputuskan, ditampilkan terpisah sebagai "menunggu approval". Detail dus menampilkan pengajuan yang masih menunggu.
3. Owner membuka halaman **Adjustment** (`/adjustments`, default menampilkan yang `pending`) dan menekan **Approve** atau **Reject** (catatan opsional). Pengajuan yang sudah diputuskan tidak bisa diputuskan ulang.
   - Approve: dus menjadi `lost` atau `damaged`.
   - Reject: dus kembali `in_warehouse`.
4. Owner juga boleh mengajukan adjustment sendiri lalu memutuskannya. Pengajuan dan keputusan tetap tercatat terpisah di log.
5. Foto disimpan di storage privat (bukan folder publik) dan hanya bisa dibuka Admin dan Owner lewat aplikasi.

### 4.7 Revisi Data dan Koreksi (Admin)

- **Detail dus** (`/boxes/{id}`): data dus, batch masuk, order keluar, dan riwayat dari `activity_logs` berdasarkan `box_id`. ID produk/lokasi di nilai lama/baru ditampilkan sebagai nama. Dibuka dari kode dus di Stok dan di detail batch QR.
- **Revisi data dus** (produk, tanggal produksi/expired): dari detail dus, wajib alasan, hanya untuk dus `in_warehouse` atau `pending_adjustment`. Expired kosong dihitung dari tanggal produksi + `shelf_life_days` (logika yang sama dengan inbound, `Product::expiryFrom()`). Hanya kolom yang berubah yang dicatat (`box.updated`, nilai lama dan baru); revisi tanpa perubahan ditolak. Tidak perlu approval.
- **Pindah lokasi**: per dus dari detail dus, atau massal di halaman **Pindah Lokasi** (`/box-moves`): pilih lokasi tujuan sekali, lalu setiap dus yang discan (scanner atau kamera) langsung dipindah. Hanya dus `in_warehouse`/`pending_adjustment`, lokasi tujuan harus aktif dan berbeda dari lokasi sekarang. Tercatat `box.location_changed`, alasan tidak wajib. Lokasi tujuan tampil besar selama scan (tombol **Ganti** untuk memilih ulang), dan setiap scan menampilkan kode, produk, serta lokasi asal → tujuan.
- **Batalkan pindah lokasi**: halaman Pindah Lokasi menampilkan pindahan user itu hari ini (termasuk dari detail dus). Tombol **Batalkan** mengembalikan dus ke lokasi asalnya, hanya jika itu pindahan terakhir dus tersebut dan dus masih di lokasi tujuan dan masih di gudang. Alasan tidak wajib, tercatat `box.move_cancelled`. Pembatalan tidak bisa dibatalkan lagi; jika keliru, scan ulang ke lokasi yang benar.
- **Batalkan scan inbound**: hanya selama batch masih berjalan, wajib alasan, lihat bagian 3.2. Setelah batch ditutup, koreksi lewat revisi data, pindah lokasi, atau adjustment.
- **Batalkan scan outbound**: hanya selama order masih `open`, wajib alasan. Tombol **Batalkan** ada di riwayat scan pada detail order dan di daftar scan terakhir halaman Barang Keluar (hanya untuk Admin dan Owner). Dus kembali `in_warehouse` dan `quantity_scanned` item berkurang satu, sehingga dus itu (atau dus lain) bisa discan lagi. Baris `outbound_scans` tidak dihapus, hanya diisi `cancelled_at`, `cancelled_by`, `cancel_reason`. Tercatat di log sebagai `box.outbound_cancelled`.

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

Master tidak dihapus, hanya dinonaktifkan, supaya data historis tetap valid. Lokasi hanya bisa dinonaktifkan jika tidak ada dus `in_warehouse` atau `pending_adjustment` di dalamnya.

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
| qr_label_id | FK qr_labels, index | |
| qr_code | string, index | duplikat kode untuk pencarian cepat |
| active_qr_code | virtual: `qr_code` jika `deleted_at` kosong, selain itu NULL; unique | menjaga satu kode QR hanya dipakai satu dus aktif |
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

Kode QR unik di level database hanya di antara dus yang tidak dibatalkan. Dus yang scan masuknya dibatalkan (soft delete) tidak menghalangi stiker yang sama discan ulang ke batch yang benar, dan baris lamanya tetap tersimpan untuk audit.

### 5.6 Outbound

**`outbound_orders`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| order_number | string, unique | misal `OUT-261003-001` |
| destination | string | customer / tujuan |
| order_date | date | |
| notes | text, nullable | |
| status | enum: `draft`, `open`, `completed`, `cancelled` | |
| cancel_reason | text, nullable | wajib jika dibatalkan dari `open` |
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
| cancelled_at / cancelled_by / cancel_reason | nullable | pembatalan scan oleh Admin, atau otomatis saat order dibatalkan |

### 5.7 Adjustment

**`adjustments`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| box_id | FK | |
| type | enum: `lost`, `damaged` | |
| reason | text | wajib |
| photo_path | string, nullable | path di storage privat |
| status | enum: `pending`, `approved`, `rejected` | |
| requested_by | FK users | |
| decided_by | FK users, nullable | |
| decided_at | timestamp, nullable | |
| decision_note | text, nullable | |

Aturan: satu dus hanya boleh punya satu adjustment `pending`. Dijaga dengan mengunci dus saat pengajuan: hanya dus `in_warehouse` yang bisa diajukan, dan pengajuan langsung mengubahnya menjadi `pending_adjustment`.

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

Action: `box.scanned_in`, `box.scanned_out`, `box.updated`, `box.location_changed`, `box.move_cancelled`, `box.inbound_cancelled`, `box.outbound_cancelled`, `box.fefo_override`, `qr.generated`, `qr.voided`, `order.created`, `order.updated`, `order.opened`, `order.cancelled`, `order.completed`, `adjustment.requested`, `adjustment.approved`, `adjustment.rejected`, `inbound.started`, `inbound.finished`, `inbound.cancelled`, `product.created`, `product.updated`, `location.created`, `location.updated`, `user.created`, `user.updated`, `user.login`, `user.logout`.

Log `adjustment.*` memakai dus sebagai subject (sehingga `box_id` terisi dan muncul di riwayat dus), dengan status lama/baru dan jenis adjustment di nilai lama/baru. Alasan pengajuan atau catatan keputusan Owner masuk ke `reason`.

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
- **Tampilan PC/laptop** (lebar ≥ 1024 px): halaman Inbound Batch dan Outbound memakai dua kolom. Kolom kiri menempel saat halaman digulir dan berisi header, progress, area scan, dan tombol aksi; kolom kanan berisi daftar dus yang sudah discan, ringkasan, atau rekomendasi FEFO. Daftar batch dan daftar order juga dua kolom. Di HP/tablet tetap satu kolom dengan urutan yang sama.

### Admin
- Dashboard ringkas: pintasan ringkas (ikon dan judul, 2 baris), angka ringkas (stok di gudang dalam dus dan kg, jumlah dus mendekati expired, jumlah dus menunggu approval), rekap stok per produk (MC, KG) dan dus mendekati expired; keduanya dibatasi tingginya dan digulir di dalam panel (baris Total rekap tetap terlihat), supaya produk yang banyak tidak memanjangkan halaman
- Stok: daftar dus dengan filter (produk, jenis, grade, size, lokasi, status, rentang expired) dan detail dus berisi riwayat lengkap dari log, dengan tombol Revisi data, Pindah lokasi, dan Ajukan hilang/rusak
- Pindah Lokasi: pilih lokasi tujuan lalu scan dus (massal), dengan daftar pindahan hari ini dan tombol Batalkan untuk salah scan
- Order Keluar: daftar, buat, detail
- Laporan: stok per lokasi untuk pengecekan manual, mutasi per periode, adjustment, masing-masing bisa diunduh sebagai Excel (.xlsx)
- Adjustment: daftar pengajuan per status (menunggu, disetujui, ditolak) dengan foto; pengajuan dibuat dari detail dus
- Stiker QR: generate, detail batch (kode, status, dus untuk stiker used), void massal dengan centang, cetak ulang per batch atau per stiker
- Master Data: produk ikan, lokasi
- Log Aktivitas
- Semua halaman scan Staff

### Owner
- **Dashboard**:
  - Pintasan ringkas dan angka ringkas yang sama dengan Admin, di bagian paling atas
  - Adjustment menunggu approval (dengan tombol Approve/Reject), tepat di bawah angka ringkas dan hanya tampil jika ada
  - Rekap stok per produk dalam MC dan KG
  - Dus mendekati expired (default ≤ 30 hari, bisa diubah 1–365 hari di dashboard, maks. 50 dus terdekat; yang sudah lewat ditandai merah)
  - 10 pelanggaran FEFO terbaru (yang scan-nya tidak dibatalkan)
  - 10 revisi dan pembatalan terbaru oleh Admin: `box.updated`, `box.location_changed`, `box.move_cancelled`, `box.inbound_cancelled`, `box.outbound_cancelled`, `order.cancelled`, `qr.voided`
  - Masuk/keluar per hari, 14 hari terakhir, sebagai grafik batang (masuk hijau, keluar oranye; angka per hari muncul saat disorot) dengan total di judul panel
- Adjustment: halaman yang sama dengan Admin, ditambah tombol Approve/Reject (sama dengan di dashboard)
- Log Aktivitas (filter per user, aksi, dus, tanggal)
- Master Data: produk ikan
- Detail dus dengan riwayat (dan semua aksi Admin, karena Owner punya semua hak Admin)
- Laporan dengan unduhan Excel (.xlsx): stok per lokasi, mutasi masuk/keluar (termasuk stok per tanggal), adjustment
- Kelola User
- Semua halaman Admin dan Staff

---

## 7. Komponen Scan

Satu komponen React `ScanInput` dipakai di semua halaman scan:
- Input teks auto-focus dan otomatis kembali fokus setelah setiap scan atau saat area lain disentuh, selama kamera mati.
- Scanner fisik bekerja sebagai keyboard: mengetik kode lalu `Enter` memicu submit.
- **Kamera** (komponen `CameraScanner`, memerlukan HTTPS): di HP/tablet (`pointer: coarse`) kamera belakang otomatis menyala; di PC lewat tombol **Kamera**. QR dibaca tiap ~250 ms dengan `BarcodeDetector` bawaan browser (Chrome Android), atau polyfill `barcode-detector` (zxing-wasm) yang hanya dimuat di browser tanpa pembaca bawaan (iPhone, Chrome Windows). Kode yang sama diabaikan selama 2 detik. Kamera berhenti membaca selama input dikunci (request berjalan, peringatan FEFO, order lengkap), ditambah jeda 1,5 detik setelahnya (`SCAN_COOLDOWN_MS`). Pembacaan QR berjalan di perangkat; server hanya menerima satu request per dus, sama seperti scanner fisik. Saat kamera menyala, input teks tidak di-auto-focus supaya keyboard HP tidak muncul, tapi tetap bisa diketik manual.
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
9. Order dibatalkan, bukan ditutup sebagian: membatalkan order `open` wajib alasan dan mengembalikan semua dus yang sudah discan ke gudang. Order `completed` tidak bisa dibatalkan.
10. Revisi data dan pindah lokasi hanya untuk dus yang masih di gudang (`in_warehouse` atau `pending_adjustment`).
11. Pindah lokasi hanya bisa dibatalkan untuk pindahan terakhir sebuah dus, selama dus masih di lokasi tujuan dan masih di gudang.
12. Login wajib lolos Cloudflare Turnstile yang diverifikasi di server sebelum password dicek, bila `TURNSTILE_ENABLED=true`. Token hanya sekali pakai, jadi widget dimuat ulang setelah setiap percobaan gagal. Lima kali gagal per username dan IP mengunci percobaan sementara.

---

## 9. Catatan Teknis

- **Backend**: Laravel versi terbaru, Inertia.js, otorisasi dengan Gate/Policy berdasarkan kolom `role` (tiga role tetap, tidak perlu package permission).
- **Frontend**: React + Tailwind, layout mobile-first untuk halaman scan, layout tabel untuk halaman Admin/Owner.
- **Notifikasi aksi**: controller mengirim pesan lewat session flash `success` (`back()->with('success', …)`), dibagikan ke semua halaman sebagai prop `flash` oleh `HandleInertiaRequests`, lalu ditampilkan sebagai toast `sonner` (komponen `FlashToaster`, kanan atas di PC, lebar penuh di atas pada HP). Toast hanya muncul dari respons server, tidak saat halaman dibuka lagi lewat tombol Back. Aksi scan (masuk, keluar, pindah lokasi) tidak memakai toast karena layar scan punya umpan balik warna dan bunyi sendiri.
- **Database**: MySQL atau PostgreSQL.
- **Library yang disarankan**: generator QR di PHP (misal `chillerlan/php-qrcode`), scan kamera di browser (`BarcodeDetector` bawaan, dengan polyfill `barcode-detector`). Export ke Excel (.xlsx) memakai `openspout/openspout`, yang menulis file baris per baris lewat `streamDownload` sehingga memori tetap kecil berapa pun jumlah barisnya; baris judul tebal dan dibekukan, kolom tanggal tersimpan sebagai tanggal Excel. Hindari PhpSpreadsheet/Laravel Excel karena menahan seluruh workbook di memori. Server butuh ekstensi PHP `zip` dan `xmlwriter`.
- **Cloudflare Turnstile (login)**: widget dimuat dari skrip Cloudflare tanpa paket npm; token diverifikasi server ke API `siteverify` (rule `App\Rules\Turnstile`, batas waktu 5 detik, gagal bila Cloudflare tidak terjangkau). Pengaturan di `.env`: `TURNSTILE_ENABLED` (`false` = widget demo Cloudflare yang selalu lolos tanpa verifikasi server, untuk development; `true` = production), `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`.
- **Hosting**: wajib HTTPS (untuk kamera dan PWA offline). Laravel mempercayai header proxy (`trustProxies`) supaya URL tetap `https` di belakang reverse proxy atau tunnel. Pastikan WiFi atau sinyal memadai di area bongkar muat.
- **Offline**: hanya daftar ambil FEFO, yang sudah termuat di halaman Barang Keluar, dipakai di dalam cold storage. Halaman jangan dimuat ulang sebelum kembali ke area bersinyal.
- **Testing**: feature test untuk setiap aturan bisnis di bagian 8, terutama validasi scan, FEFO, dan alur adjustment.

---

## 10. Tahapan Pembangunan

| Tahap | Isi | Hasil yang bisa dipakai |
|---|---|---|
| 1. Fondasi | Setup project, login (dengan Cloudflare Turnstile), role, kelola user, master data, `activity_logs` | Owner bisa membuat akun, Admin mengisi master |
| 2. Stiker & Inbound | Generate/cetak QR, komponen `ScanInput`, inbound batch, pembatalan scan inbound oleh Admin, daftar stok | Barang masuk bisa dicatat |
| 3. Outbound | Order Keluar, stok tersedia, scan keluar dengan FEFO, pembatalan scan outbound | Barang keluar terkontrol |
| 4. Koreksi & Adjustment | 4a: detail dus dengan riwayat, revisi data, pindah lokasi (per dus dan massal lewat scan, dengan pembatalan salah scan). 4b: adjustment dari detail dus dan halaman Adjustment untuk approval Owner | Admin dan Owner bisa mengoreksi dengan jejak |
| 5. Dashboard & Laporan | Dashboard Owner (termasuk approve adjustment), dashboard ringkas Admin, laporan stok per lokasi, mutasi, dan adjustment, unduhan Excel (.xlsx), peringatan expired | Owner bisa memantau penuh |
| 6. Polishing | Uji di perangkat lapangan, perbaikan dari uji coba (scan kamera HP sudah dikerjakan lebih awal) | Siap dipakai operasional |

Tahap 1–3 sudah cukup untuk mulai uji coba di gudang.

---

## 11. Hal yang Masih Terbuka

- Tanggal apa yang tercetak di dus supplier (produksi atau expired). Rancangan ini mendukung keduanya.
- Batas "mendekati expired" untuk dashboard. Default: 30 hari, bisa diubah langsung di dashboard (tidak disimpan).
- Ukuran dan layout kertas stiker QR yang dipakai untuk cetak.
