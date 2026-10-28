
## Tech Stack

- PHP 8
- MySQL / MariaDB, engine InnoDB
- HTML, CSS, dan JavaScript murni 

## Instalasi (XAMPP)

1. Pasang dan jalankan **XAMPP**, lalu aktifkan modul **Apache** dan **MySQL** pada XAMPP Control Panel.
2. Salin folder `kafe-pos` ke `C:\xampp\htdocs\` (hasilnya `C:\xampp\htdocs\(Ahmad Dhava Fahrezi)`).
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
