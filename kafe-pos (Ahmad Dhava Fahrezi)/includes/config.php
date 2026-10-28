<?php
// Konfigurasi aplikasi. Sesuaikan dengan pengaturan MySQL pada XAMPP.
define('DB_HOST', 'localhost');
define('DB_NAME', 'kafe_pos');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Titik Temu Kopi');
define('APP_ADDRESS', 'Pariaman Tengah, Kota Pariaman');
define('APP_PHONE', '(0751) 555-0123');
define('PB1_RATE', 0.10);     // Pajak restoran (PB1) 10%
define('JUMLAH_MEJA', 20);

date_default_timezone_set('Asia/Jakarta');
