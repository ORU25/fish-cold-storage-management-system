# PRD: Cold Storage Fish Stock Management System

Oct 3, 2026 · @Kennard Benedict

## 1. Ringkasan

Sistem ini adalah web app untuk melacak setiap dus ikan di cold storage dari masuk sampai keluar, supaya tidak ada dus yang hilang tanpa jejak. Setiap dus diberi stiker QR berisi ID unik. Produk ikan (jenis, grade, size), lokasi, dan tanggal expired disimpan di database dan diikat ke ID tersebut.

**Masalah yang diselesaikan.** Saat ini pergerakan barang di gudang sulit diaudit. Dus bisa keluar tanpa dokumen, data bisa diubah tanpa jejak, dan selisih stok baru ketahuan lama setelah kejadian. Akibatnya pemilik tidak bisa membedakan salah catat, barang rusak, dan pencurian.

**Pendekatan.** Sistem menegakkan empat aturan:

1. Tidak ada dus keluar tanpa Order Keluar yang dibuat Admin.
2. Setiap perubahan tercatat di log yang tidak bisa diedit atau dihapus siapa pun.
3. Stok hanya berkurang lewat scan keluar terhadap order, atau adjustment yang di-approve Owner.
4. Hanya QR yang dikeluarkan sistem yang bisa dipakai, dan setiap QR hanya sekali.

Dokumen pendukung: rancangan sistem teknis di `notes/rancangan-sistem.md` dan review spesifikasi di `notes/review-spesifikasi.md` pada folder project.

## 2. Tujuan dan Metrik Keberhasilan

Tujuan utamanya: setiap selisih stok bisa dijelaskan sampai ke dus, user, dan waktu kejadiannya.

| Tujuan | Metrik | Usulan target |
| --- | --- | --- |
| Semua pergerakan dus tercatat | Dus keluar tanpa order | 0 |
| Selisih stok cepat ketahuan | Frekuensi pengecekan fisik manual | Minimal 1 kali per bulan |
| Selisih bisa ditelusuri | Selisih stok yang punya riwayat lengkap di log | 100% |
| FEFO dijalankan | Scan keluar yang melanggar FEFO | Di bawah 5%, semuanya dengan alasan |
| Operasional tidak melambat | Waktu scan inbound per dus dalam mode batch | Di bawah 3 detik |
| Adjustment tidak menggantung | Waktu dari pengajuan sampai keputusan Owner | Di bawah 2 hari kerja |

Angka target di atas adalah usulan awal dan perlu dikonfirmasi pemilik.

**Bukan tujuan rilis ini:** penimbangan per dus, harga dan nilai stok, penagihan atau invoice, integrasi dengan sistem akuntansi, dan multi-gudang.

## 3. Pengguna

Ada tiga role tetap. Pemisahan tugas Staff dan Admin sengaja dibuat supaya tidak ada satu orang yang bisa mengeluarkan barang sekaligus menghapus jejaknya. Owner sebagai pemilik usaha punya semua hak Admin dan Staff tanpa batasan, tapi tetap tidak bisa menghapus jejak karena log aktivitas tidak bisa diubah.

| Role | Siapa | Konteks kerja | Kebutuhan utama | Batasan |
| --- | --- | --- | --- | --- |
| Staff | Operator lapangan di gudang | Scan masuk dan keluar di area bongkar muat di luar cold storage (ada jaringan). Memakai scanner fisik, HP, atau tablet | Scan cepat dengan umpan balik jelas (bunyi dan warna), tombol besar | Hanya scan Inbound dan Outbound. Tidak bisa edit atau hapus apa pun |
| Admin | Kepala gudang atau staf administrasi | Di kantor gudang, PC atau laptop | Membuat order keluar, memperbaiki data, memantau stok, mengajukan adjustment | Tidak bisa mengurangi stok tanpa approval Owner. Setiap perubahan wajib alasan |
| Owner | Pemilik usaha | Dari mana saja, sering lewat HP | Melihat kondisi stok dan kejanggalan sekilas, memutuskan adjustment, mengelola akun user | Tanpa batasan: bisa melakukan semua yang bisa dilakukan Admin dan Staff. Semua aksinya tetap tercatat di log |

## 4. Ruang Lingkup

Rilis pertama mencakup seluruh siklus dus di satu gudang, dari stiker QR sampai dashboard Owner.

**Termasuk:**

- Login dan tiga role (Staff, Admin, Owner), kelola user oleh Owner
- Master data: produk ikan (satu kombinasi jenis, grade, size) dan lokasi
- Generate dan cetak stiker QR
- Inbound mode batch
- Order Keluar dan scan Outbound dengan FEFO
- Laporan stok per lokasi untuk pengecekan fisik manual
- Revisi data, pindah lokasi, dan pembatalan scan oleh Admin
- Adjustment hilang atau rusak dengan approval Owner
- Log aktivitas yang tidak bisa diubah
- Dashboard Owner, laporan, export Excel (.xlsx)
- Scan lewat scanner fisik dan kamera HP/tablet

**Tidak termasuk:**

- Multi-gudang
- Fitur Stock Opname berbasis scan (pengecekan fisik dilakukan manual) dan mode offline (daftar ambil FEFO cukup dilihat di HP dengan halaman Barang Keluar tetap terbuka)
- Penimbangan per dus, harga, dan nilai stok
- Data customer dan supplier sebagai master (cukup teks bebas di order dan batch)
- Invoice, pembayaran, integrasi akuntansi
- Notifikasi WhatsApp atau email
- Aplikasi mobile native (cukup web responsif)

## 5. Kebutuhan Fungsional

Setiap modul ditulis sebagai user story dengan kriteria penerimaan. Fitur dianggap selesai jika semua kriteria terpenuhi dan teruji.

### 5.1 Autentikasi dan User

**Sebagai Owner**, saya ingin membuat dan menonaktifkan akun, supaya hanya orang yang berwenang yang bisa memakai sistem.

- Login memakai username dan password, dilindungi Cloudflare Turnstile (pengecekan anti-bot) di halaman login. Lima kali gagal berturut-turut mengunci percobaan sementara.
- Owner bisa membuat user, mengganti role, mereset password, dan menonaktifkan user.
- User nonaktif tidak bisa login, tapi riwayatnya tetap tampil di log.
- User tidak bisa dihapus.
- Owner bisa membuka semua halaman dan melakukan semua aksi Admin dan Staff.
- Setiap halaman dan aksi dicek berdasarkan role di server, bukan hanya disembunyikan di tampilan.

### 5.2 Master Data

**Sebagai Admin**, saya ingin mengelola daftar produk ikan dan lokasi, supaya staf cukup memilih satu produk dari dropdown dan tidak ada salah ketik. **Sebagai Owner**, saya juga ingin bisa mengelola produk ikan dan lokasi, supaya daftar produk sesuai dengan yang saya jual.

- Satu produk ikan adalah satu kombinasi jenis, grade, dan size. Contoh: MB A 3-5, MB A 6-10, dan MB B 6-10 adalah tiga produk berbeda.
- Setiap produk punya kode, jenis ikan, grade (boleh kosong), size (boleh kosong), berat per dus dalam kg (default 10), dan masa simpan dalam hari (opsional).
- Kombinasi jenis, grade, dan size tidak boleh dobel.
- Lokasi dikelola sebagai daftar nama lokasi tersendiri.
- Produk dan lokasi tidak bisa dihapus, hanya dinonaktifkan. Data nonaktif tidak muncul di dropdown baru tapi tetap tampil di data lama.
- Lokasi tidak bisa dinonaktifkan selama masih ada dus di dalamnya. Dus harus dipindah dulu.

### 5.3 Stiker QR

**Sebagai Admin**, saya ingin mencetak stiker QR dalam jumlah banyak sebelum barang datang.

- Admin memasukkan jumlah stiker (maks. 100 per batch supaya generate QR tidak membebani server), sistem membuat kode berformat `DUS-YYMMDD-NNNN` yang unik.
- Sistem menyediakan halaman cetak lembar stiker, dan batch yang sama bisa dicetak ulang.
- Admin bisa menandai stiker rusak sebagai void dari halaman detail batch, satu atau beberapa sekaligus dengan satu alasan.
- Stiker hanya bisa berstatus available, used, atau void.
- Setiap batch punya halaman detail: daftar kode dan statusnya, bisa dicari per kode dan difilter per status. Untuk stiker used tampil dus tempat stiker itu tertempel (produk, lokasi, status dus, expired).
- Dari halaman detail, Admin bisa mencentang stiker available (per stiker atau semua di halaman itu) lalu men-void semuanya dengan satu alasan, dan mencetak ulang satu stiker available atau used (misal stiker rusak atau terlewat dicetak). Stiker void tidak bisa dicetak.

### 5.4 Inbound (Barang Masuk)

**Sebagai Staff**, saya ingin mengisi data sekali lalu scan banyak dus berturut-turut, supaya penerimaan satu truk tidak lama.

- Staf membuat batch dengan nama supplier, nomor surat jalan (opsional), produk ikan, lokasi, dan tanggal.
- Staf bisa mengisi tanggal produksi atau tanggal expired. Jika tanggal produksi diisi dan produk punya masa simpan, tanggal expired terisi otomatis dan bisa diubah.
- Setiap scan membuat satu dus dengan data batch saat itu, mencatat staf dan waktu, dan mengubah stiker menjadi used.
- Scan ditolak jika kode tidak dikenal, sudah dipakai, atau void, dengan pesan dan bunyi gagal.
- Staf bisa mengubah field batch di tengah jalan. Scan berikutnya memakai nilai baru, dus yang sudah discan tidak berubah.
- Layar menampilkan jumlah dus yang sudah discan dan daftar scan terakhir.
- Saat batch diselesaikan, tampil ringkasan per produk dan tanggal.
- Batch yang belum berisi dus tidak bisa diselesaikan, tapi bisa dibatalkan. Batch yang dibatalkan dihapus dan hanya tercatat di log.

### 5.5 Order Keluar

**Sebagai Admin**, saya ingin membuat order keluar, supaya staf hanya mengeluarkan barang yang memang diminta.

- Order berisi tujuan atau customer, tanggal, catatan, dan satu atau lebih item (produk ikan dan jumlah dus).
- Untuk setiap item tampil stok tersedia, yaitu dus di gudang dikurangi sisa kebutuhan order lain yang masih terbuka.
- Jumlah item melebihi stok tersedia ditolak.
- Status order: draft, open, completed, cancelled. Hanya order open yang muncul di layar staf.
- Saat semua item terpenuhi, order tetap open dengan tanda menunggu pengecekan. Admin mengecek fisik barang, lalu menyelesaikan order (completed).
- Admin bisa membatalkan order draft atau open. Membatalkan order open wajib alasan, dan semua dus yang sudah discan untuk order itu kembali ke gudang.
- Jika pembeli hanya sanggup mengambil sebagian (misal 2 dari 5 dus), order dibatalkan lalu dibuat order baru sesuai kesanggupan pembeli.

### 5.6 Outbound (Barang Keluar) dengan FEFO

**Sebagai Staff**, saya ingin sistem memberi tahu dus mana yang harus diambil, supaya barang yang expired-nya lebih dulu keluar lebih dulu.

- Staf memilih order open, lalu melihat daftar rekomendasi dus per item, diurutkan dari expired terdekat, dikelompokkan per tanggal dan lokasi. Daftar ini dibuka di HP sebelum masuk dan tetap terlihat selama halaman tidak dimuat ulang, karena dus diambil di dalam cold storage tanpa jaringan lalu discan di area bongkar muat. Daftar ini tidak dicetak.
- Jika stok tanggal terdekat tidak cukup, rekomendasi berlanjut ke tanggal berikutnya.
- Scan ditolak jika dus tidak berada di gudang, atau produknya tidak cocok dengan item yang belum terpenuhi.
- Jika masih ada dus cocok dengan expired lebih awal, muncul peringatan FEFO. Staf boleh lanjut dengan mengisi alasan, dan scan ditandai sebagai pelanggaran FEFO.
- Scan sukses mengubah dus menjadi outbound dan mencatat staf, waktu, dan order.
- Dua staf tidak bisa mengeluarkan dus yang sama pada saat bersamaan.

### 5.7 Pengecekan Stok Manual

**Sebagai Admin**, saya ingin mencocokkan fisik gudang dengan jumlah di sistem secara manual, supaya selisih cepat ketahuan.

- Halaman Stok menampilkan jumlah dus per produk dan lokasi, beserta daftar kode dus di setiap lokasi.
- Daftar ini ada di halaman Laporan dan bisa diunduh sebagai file Excel (.xlsx) sebagai lembar hitung manual: satu baris per dus dengan kolom kosong untuk hasil hitung fisik.
- Laporan mutasi menampilkan per produk stok awal, masuk, keluar, adjustment, dan stok akhir untuk periode yang dipilih, sehingga stok akhir bisa ditelusuri dari stok awal ditambah masuk dikurangi keluar dan adjustment. Stok akhir sekaligus menunjukkan stok di tanggal tertentu.
- Jika hitungan fisik berbeda, Admin menelusuri dus yang selisih lewat daftar kode per lokasi dan riwayat log, lalu mengajukan adjustment.

### 5.8 Adjustment (Hilang atau Rusak)

**Sebagai Admin**, saya ingin mengajukan dus hilang atau rusak, dan **sebagai Owner** saya ingin memutuskannya, supaya stok tidak berkurang tanpa sepengetahuan pemilik.

- Pengajuan dibuat dari halaman detail dus, berisi jenis (hilang atau rusak), alasan wajib, dan foto opsional (maks. 5 MB, bisa langsung dari kamera HP). Hanya dus yang berstatus di gudang yang bisa diajukan.
- Dus yang diajukan menjadi pending dan tidak bisa dikeluarkan, tapi masih dihitung di stok riil sampai diputuskan.
- Satu dus hanya boleh punya satu pengajuan pending.
- Admin dan Owner melihat semua pengajuan di halaman Adjustment (menunggu, disetujui, ditolak). Foto hanya bisa dibuka lewat aplikasi.
- Owner bisa approve atau reject dengan catatan opsional dari halaman Adjustment. Approve mengubah dus menjadi lost atau damaged. Reject mengembalikan dus ke gudang. Pengajuan yang sudah diputuskan tidak bisa diubah.
- Pengajuan dan keputusan tercatat di log dan tampil di riwayat dus.

### 5.9 Revisi dan Koreksi oleh Admin

**Sebagai Admin**, saya ingin memperbaiki salah input, dengan jejak yang jelas.

- Setiap dus punya halaman detail: data dus, batch masuk, order keluar, dan riwayat lengkap dari log (siapa, kapan, nilai lama dan baru, alasan). Halaman ini dibuka dari kode dus di halaman Stok atau di detail batch stiker QR. Admin dan Owner bisa melihatnya.
- Admin bisa merevisi produk, tanggal produksi, dan tanggal expired dengan alasan wajib, dari halaman detail dus. Revisi hanya untuk dus yang masih di gudang. Expired yang dikosongkan dihitung dari tanggal produksi dan masa simpan produk, sama seperti saat scan masuk.
- Admin bisa memindah lokasi per dus dari halaman detail dus, atau banyak dus sekaligus lewat halaman Pindah Lokasi: pilih lokasi tujuan sekali, lalu scan dus satu per satu dengan scanner atau kamera HP. Lokasi tujuan tampil besar selama scan, dan setiap scan menampilkan kode, produk, serta lokasi asal → tujuan. Salah scan bisa dibatalkan dari daftar pindahan hari ini: dus kembali ke lokasi asalnya, selama dus itu belum dipindah lagi dan masih di gudang.
- Admin bisa membatalkan scan inbound selama batch masih berjalan. Dus dihapus secara soft delete dan stiker kembali available. Setelah batch ditutup, scan masuk tidak bisa dibatalkan; koreksi dilakukan lewat revisi data, pindah lokasi, atau adjustment.
- Admin bisa membatalkan scan outbound yang salah selama order masih open, dengan alasan wajib, dari detail order maupun halaman Barang Keluar. Dus kembali ke gudang dan item order kembali membutuhkan satu dus. Riwayat scan tetap tersimpan dengan tanda dibatalkan. Staff tidak bisa membatalkan scan.
- Setiap revisi dan pembatalan mencatat nilai lama, nilai baru, alasan, user, dan waktu.

### 5.10 Log Aktivitas

**Sebagai Owner**, saya ingin melihat siapa melakukan apa dan kapan, supaya setiap kejanggalan bisa ditelusuri.

- Setiap kejadian penting tercatat: scan masuk dan keluar, revisi, pindah lokasi, pembatalan, pelanggaran FEFO, order, adjustment, perubahan user, dan login.
- Log tidak bisa diedit atau dihapus lewat aplikasi oleh siapa pun.
- Log bisa difilter per user, jenis aksi, dus, dan rentang tanggal.
- Halaman detail dus menampilkan riwayat lengkap dus tersebut dari log.

### 5.11 Dashboard dan Laporan

**Sebagai Owner**, saya ingin melihat kondisi gudang dan kejanggalan dalam satu layar.

- Dashboard menampilkan: angka ringkas di bagian atas (stok di gudang, dus mendekati expired, dus menunggu approval); rekap stok per produk dalam MC dan KG, mengikuti format rekap yang sudah dipakai; dus mendekati expired (default 30 hari, bisa diubah di dashboard); adjustment menunggu keputusan; pelanggaran FEFO terbaru; revisi dan pembatalan terbaru oleh Admin; grafik batang masuk dan keluar per hari selama 14 hari terakhir.
- Owner bisa approve atau reject adjustment langsung dari dashboard.
- Admin melihat dashboard ringkas: angka ringkas, rekap stok per produk, dan dus mendekati expired. Staff hanya melihat tombol pintasan.
- Laporan stok per lokasi, mutasi, dan adjustment bisa diunduh sebagai file Excel (.xlsx).

### 5.12 Layar Scan

**Sebagai Staff**, saya ingin bisa scan dengan alat apa pun yang tersedia.

- Input scan otomatis fokus dan kembali fokus setelah setiap scan (saat kamera mati).
- Scanner fisik bekerja tanpa klik apa pun: kode diketik scanner lalu Enter memproses scan.
- Di HP dan tablet, kamera belakang otomatis menyala saat halaman Barang Masuk atau Barang Keluar dibuka; cukup arahkan ke stiker QR. Di PC kamera bisa dinyalakan lewat tombol Kamera.
- Satu dus yang masih di depan kamera hanya terbaca sekali (kode yang sama diabaikan selama 2 detik), dan kamera berhenti membaca selama scan diproses atau peringatan FEFO terbuka.
- Setelah setiap scan kamera ada jeda 1,5 detik (tampil "Siap scan berikutnya…") supaya stiker dus sebelahnya tidak ikut terscan sebelum pekerja siap.
- Kode tetap bisa diketik manual jika stiker rusak.
- Sukses, peringatan, dan gagal punya warna dan bunyi berbeda.
- Input dikunci sebentar saat memproses supaya satu dus tidak tercatat dua kali.

## 6. Kebutuhan Non-Fungsional

| Aspek | Kebutuhan |
| --- | --- |
| Kecepatan | Satu scan diproses dan memberi umpan balik dalam waktu di bawah 1 detik pada jaringan gudang normal |
| Perangkat | Berjalan di Chrome terbaru pada PC, tablet Android, dan HP Android. Layar scan nyaman dipakai di lebar 360 px. Di PC/laptop layar scan memakai dua kolom supaya area scan dan hasil scan selalu terlihat di samping daftar dus |
| Scanner | Kompatibel dengan scanner USB atau Bluetooth mode keyboard yang mengirim Enter |
| Kamera | Scan kamera berjalan lewat HTTPS |
| Umpan balik aksi | Setiap aksi yang berhasil (simpan, ubah, buka, selesaikan, batalkan, void, setujui/tolak) menampilkan notifikasi singkat (toast) yang hilang sendiri: di kanan atas pada PC, lebar penuh di atas layar pada HP. Layar scan tetap memakai warna dan bunyi |
| Keamanan | Password di-hash, sesi login berakhir setelah tidak aktif, semua aksi dicek role di server, HTTPS wajib. Login dilindungi Cloudflare Turnstile yang diverifikasi di server; saklar `TURNSTILE_ENABLED` di `.env` memakai widget demo yang selalu lolos untuk development |
| Integritas data | Semua operasi scan dan perubahan status berjalan dalam transaksi database dengan row lock. Kode QR dan status dus dijaga unik di level database |
| Jejak audit | Log tidak bisa diubah lewat aplikasi. Untuk produksi, user database aplikasi hanya diberi hak tambah dan baca pada tabel log |
| Ketersediaan | Inbound dan Outbound wajib online karena dilakukan di luar cold storage. Jika koneksi putus, layar scan menampilkan peringatan dan tidak menerima scan. Daftar ambil FEFO dibuka di HP sebelum masuk cold storage dan tetap terlihat selama halaman tidak dimuat ulang |
| Cadangan | Backup database otomatis harian, disimpan di luar server aplikasi |
| Bahasa | Antarmuka dalam Bahasa Indonesia, tanggal format DD/MM/YYYY, zona waktu WIB |

## 7. Model Data dan Teknologi

Sistem dibangun dengan Laravel, Inertia.js, React, dan Tailwind CSS, memakai MySQL atau PostgreSQL. Skema lengkap per kolom ada di `notes/rancangan-sistem.md`. Entitas utamanya:

| Entitas | Isi | Status |
| --- | --- | --- |
| User | Nama, username, role, aktif | aktif, nonaktif |
| Master | Produk ikan (jenis, grade, size, berat per dus, masa simpan) dan lokasi | aktif, nonaktif |
| Stiker QR | Kode `DUS-YYMMDD-NNNN`, batch cetak | available, used, void |
| Batch Masuk | Supplier, nomor surat jalan, staf, waktu |  |
| Dus | Kode QR, produk, lokasi, tanggal produksi, tanggal expired, siapa dan kapan masuk/keluar | in\_warehouse, outbound, pending\_adjustment, lost, damaged |
| Order Keluar | Tujuan, tanggal, item (produk, jumlah) | draft, open, completed, cancelled |
| Scan Keluar | Dus, item order, staf, tanda pelanggaran FEFO dan alasannya, pembatalan (waktu, oleh siapa, alasan) |  |
| Adjustment | Dus, hilang atau rusak, alasan, foto, pengaju, pemutus | pending, approved, rejected |
| Log Aktivitas | User, aksi, objek, nilai lama dan baru, alasan, waktu | tidak bisa diubah |

&#91;embedded content: siklus status dus · 6 status\]

Dus hanya bisa meninggalkan status Di gudang lewat scan keluar terhadap order, atau lewat pengajuan Admin yang diputuskan Owner.

## 8. Tahapan Rilis

Tahap 1 sampai 3 sudah cukup untuk uji coba di gudang. Tahap berikutnya ditambahkan sambil uji coba berjalan.

1. **Fondasi:** login (dengan Cloudflare Turnstile), role, kelola user, master data, log aktivitas. Selesai jika Owner bisa membuat akun dan Admin bisa mengisi master.
2. **Stiker dan Inbound:** generate dan cetak QR, layar scan, inbound batch, pembatalan scan inbound oleh Admin, daftar stok. Selesai jika satu truk bisa diterima dan stok tampil benar.
3. **Outbound:** order keluar, stok tersedia, scan keluar dengan FEFO, pembatalan scan outbound yang salah. Selesai jika barang hanya bisa keluar lewat order. **Mulai uji coba di gudang.**
4. **Koreksi dan Adjustment:** dibagi dua. 4a: detail dus dengan riwayat, revisi data, pindah lokasi (per dus dan massal lewat scan, dengan pembatalan salah scan). 4b: adjustment dari detail dus dengan approval Owner di halaman Adjustment.
5. **Dashboard dan Laporan:** dashboard Owner (termasuk approve adjustment), dashboard ringkas Admin, laporan stok per lokasi untuk pengecekan manual, laporan mutasi dan adjustment, peringatan expired, export Excel (.xlsx).
6. **Penyempurnaan:** uji di perangkat lapangan, perbaikan dari hasil uji coba. Scan kamera HP sudah dikerjakan lebih awal.

## 9. Asumsi, Risiko, dan Pertanyaan Terbuka

**Asumsi:**

- Berat setiap dus sama untuk satu produk (di contoh rekap 10 kg per dus), sehingga stok dihitung per dus (MC) dan KG dihitung otomatis.
- Satu gudang. Area bongkar muat di luar cold storage punya WiFi atau sinyal, sedangkan di dalam cold storage tidak ada jaringan.
- Dus selalu keluar utuh, tidak pernah dibongkar sebagian.
- Stiker QR tahan suhu beku dan lembap.

**Risiko:**

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Tidak ada jaringan di dalam cold storage | Pengambilan dus tidak bisa memakai data online | Daftar ambil FEFO dibuka di HP sebelum masuk dan halaman dibiarkan terbuka, scan masuk dan keluar di area bongkar muat |
| Stiker lepas atau rusak karena es | Dus tidak bisa dilacak | Pakai stiker khusus freezer, Admin bisa membuat stiker pengganti lewat revisi |
| Staf menganggap scan memperlambat kerja | Scan dilewati | Mode batch, umpan balik bunyi, uji coba waktu scan per dus |
| Admin dan staf bekerja sama mengeluarkan barang | Kecolongan tetap terjadi | Semua aksi Admin tampil di dashboard Owner, pengecekan fisik manual berkala, pelanggaran FEFO dipantau |
| Owner terlambat memutuskan adjustment | Data stok menggantung | Pengajuan pending tampil menonjol di dashboard Owner |

**Pertanyaan terbuka:**

- [x] Tanggal apa yang tercetak di dus supplier, tanggal produksi atau tanggal expired? Diputuskan: staf menginput manual sesuai yang tertera di dus, lewat tanggal produksi (expired dihitung otomatis) atau tanggal expired langsung.
- [x] Berapa hari batas "mendekati expired" di dashboard? Default 30 hari, bisa diubah di dashboard.
- [x] Export laporan dalam format Excel (.xlsx) atau CSV? Diputuskan: Excel (.xlsx) lewat `openspout/openspout` yang menulis baris per baris, sehingga tetap ringan di server (memori tetap kecil). Sebelumnya CSV; diganti supaya tanggal dan angka langsung terbaca benar di Excel tanpa masalah pemisah kolom.
- [x] Siapa yang melihat dashboard lengkap? Diputuskan: Owner. Admin melihat versi ringkas (rekap stok dan dus mendekati expired).
- [x] Ukuran kertas dan layout stiker QR yang akan dipakai? Usulan: label thermal tahan beku 50 x 30 mm, QR 25 x 25 mm dengan kode teks di bawahnya, bisa dicetak printer label thermal.
- [x] Apakah target metrik di bagian 2 sudah sesuai?
- [x] Apakah order yang hanya terpenuhi sebagian perlu status sendiri (ditutup)? Diputuskan: tidak. Status ditutup dihapus; order dibatalkan (dus kembali ke gudang) lalu dibuat order baru sesuai kesanggupan pembeli.
- [x] Apakah pembatalan scan outbound perlu tersedia sejak uji coba? Diputuskan: ya, dipindah dari tahap 4 ke tahap 3, sama seperti pembatalan scan inbound.
- [x] Apakah scan lewat kamera HP perlu tersedia sejak uji coba? Diputuskan: ya, dipindah dari tahap 6. Kamera otomatis menyala di HP, berjalan di Android dan iPhone.
- [x] Apakah tahap 4 dikerjakan sekaligus? Diputuskan: dibagi. 4a (detail dus, revisi, pindah lokasi) lebih dulu, adjustment menyusul di 4b.
- [x] Apakah Owner boleh ikut operasional gudang? Diputuskan: ya, Owner punya semua hak Admin dan Staff tanpa batasan.

**Dari contoh rekap stok:**

- [ ] Apakah A, B, dan PP adalah grade? Saat ini dicatat sebagai grade di dalam data produk (MB A 3-5: jenis MB, grade A, size 3-5).
- [ ] Apakah semua jenis ikan 10 kg per dus, atau ada yang berbeda?
- [ ] Kolom EKOR kosong di semua baris. Apakah ada ikan yang dihitung per ekor? Saat ini tidak dicatat.
