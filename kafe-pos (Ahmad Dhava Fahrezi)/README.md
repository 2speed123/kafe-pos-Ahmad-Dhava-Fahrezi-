
## Tech Stack

- PHP 8 (native, PDO, tanpa framework dan tanpa library tambahan)
- MySQL / MariaDB (bawaan XAMPP), engine InnoDB
- HTML, CSS, dan JavaScript murni 

## Instalasi (XAMPP)

1. Pasang dan jalankan **XAMPP**, lalu aktifkan modul **Apache** dan **MySQL** pada XAMPP Control Panel.
2. Salin folder `kafe-pos` ke `C:\xampp\htdocs\` (hasilnya `C:\xampp\htdocs\kafe-pos`).
3. Buka `http://localhost/phpmyadmin`, pilih tab **Import**, pilih file `database.sql`, lalu klik **Import**.
   Database `kafe_pos` beserta data awal akan dibuat otomatis.
4. Jika MySQL Anda memakai password atau port berbeda, sesuaikan `includes/config.php`
   (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Pengaturan bawaan XAMPP: user `root`, password kosong.
5. Buka `http://localhost/kafe-pos/` pada browser.

Persyaratan: PHP 8.0 atau lebih baru dan ekstensi `pdo_mysql` (aktif secara bawaan pada XAMPP).

## Akun Uji Coba

| Role | Username | Password |
|---|---|---|
| Administrator / Manager | `admin` | `admin123` |
| Kasir / Waiter | `kasir1` | `kasir123` |
| Kasir / Waiter | `kasir2` | `kasir123` |

Segera ubah password pada menu **Pengguna** sebelum aplikasi dipakai di luar keperluan uji.

## Fitur per Modul

**1. Autentikasi & Hak Akses**
- Form login dengan validasi input dan pesan kesalahan yang jelas.
- Password disimpan dengan hash bcrypt (`password_hash`), tidak ada plaintext.
- Proteksi halaman (guard) pada setiap halaman: tanpa login akan diarahkan ke halaman login.
- Logout menghancurkan sesi dan cookie sesi. ID sesi diperbarui saat login.
- Administrator: seluruh fitur (menu, stok, harga, pengguna, laporan, ekspor, pembatalan transaksi).
  Kasir: membuat pesanan, menerima pembayaran, mencetak struk, dan melihat riwayat transaksinya sendiri.

**2. Dashboard**
- Empat KPI: total omset hari ini, jumlah pesanan, menu terlaris, dan peringatan stok rendah.
- Grafik omset 7 hari terakhir, distribusi penjualan per kategori, tabel transaksi terbaru, dan tombol aksi cepat "Buat Transaksi Baru".
- Dashboard kasir hanya menampilkan transaksi miliknya; peringatan stok bersifat umum.

**3. Transaksi Inti (master-detail)**
- Nomor faktur otomatis `INV-POS-YYYYMMDD-XXXX`, unik, dan berurutan per hari.
- Tipe pesanan Dine-In (wajib memilih meja) atau Takeaway, nama pemesan, item dinamis dengan kuantiti dan catatan khusus.
- Kalkulasi otomatis: subtotal per baris, diskon (nominal atau persen), pajak PB1 10%, dan grand total.
- Pembayaran Tunai (kembalian otomatis), QRIS, Transfer Bank, atau Kartu Debit. Status: Diproses, Selesai, Dibatalkan.
- Validasi: stok diperiksa sebelum disimpan, pembayaran tunai tidak boleh kurang dari tagihan, dan seluruh nilai dihitung ulang di server.
- Penyimpanan memakai **database transaction (ACID)**: header, detail, dan pengurangan stok tersimpan bersamaan atau di-rollback. Baris produk dikunci (`FOR UPDATE`) agar stok konsisten saat ada transaksi bersamaan.
- Token formulir sekali pakai mencegah transaksi ganda akibat klik berulang atau refresh.
- Struk siap cetak (ukuran thermal 80 mm).
- Pembatalan transaksi (Administrator) mengembalikan stok secara otomatis.

**4. Laporan & Ekspor**
- Filter rentang tanggal, kategori menu, dan metode pembayaran.
- Ringkasan: omset bruto, total diskon, total pajak, omset bersih, dan rata-rata nilai transaksi.
- Tabel: No. Faktur, Tanggal & Jam, Kasir, Tipe Pesanan, Item Terjual, Subtotal, Pajak, Grand Total, Status.
- Ekspor ke CSV (dapat dibuka di Excel).

**Menu pendukung (Administrator):** Menu & Stok dan Pengguna.

## Catatan Perhitungan

- Pajak PB1 = 10% dari (subtotal - diskon). Grand total = subtotal - diskon + pajak.
- Pesanan dibayar saat dibuat, sehingga transaksi berstatus **Diproses** dan **Selesai** dihitung sebagai penjualan. Transaksi **Dibatalkan** tidak dihitung pada dashboard maupun laporan.
- Omset bersih = omset bruto - diskon (di luar pajak restoran).
- Stok rendah: item aktif dengan `stok < stok_minimum`.

## Struktur Folder

```
kafe-pos/
|-- index.php, login.php, logout.php
|-- dashboard.php
|-- transaksi.php, struk.php, riwayat.php
|-- laporan.php, laporan_export.php
|-- produk.php, pengguna.php
|-- includes/       config, bootstrap (helper, sesi, CSRF, guard), header, footer, report
|-- assets/css/     style.css
|-- assets/js/      app.js, transaksi.js
|-- database.sql    skema 5 tabel + data awal
`-- README.md
```

## Skema Database

Lima tabel: `users`, `categories`, `products`, `orders`, `order_details`, dilengkapi relasi foreign key, indeks pada tanggal dan status, serta constraint stok tidak negatif. Kolom tambahan dari rekomendasi minimal: `products.stok_minimum` dan `products.status`, `orders.nama_pemesan`.

## Keamanan

Prepared statement PDO (anti SQL injection), escaping output (anti XSS), token CSRF pada seluruh form POST, cookie sesi `HttpOnly` dan `SameSite`, pengecekan role di sisi server, dan folder `includes/` ditutup dari akses langsung melalui `.htaccess`.
