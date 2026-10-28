-- Database Sistem Kasir & Pemesanan Kafe: Titik Temu Kopi
-- Impor melalui phpMyAdmin (tab Import) atau: mysql -u root < database.sql
CREATE DATABASE IF NOT EXISTS kafe_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kafe_pos;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_details, orders, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(50) NOT NULL UNIQUE,
  slug VARCHAR(60) NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  kode_produk VARCHAR(20) NOT NULL UNIQUE,
  nama_produk VARCHAR(100) NOT NULL,
  harga_jual DECIMAL(12,0) NOT NULL DEFAULT 0,
  harga_pokok DECIMAL(12,0) NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  stok_minimum INT NOT NULL DEFAULT 5,
  unit VARCHAR(20) NOT NULL DEFAULT 'porsi',
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT chk_stok CHECK (stok >= 0)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(25) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  tipe_pesanan ENUM('Dine-In','Takeaway') NOT NULL,
  nomor_meja TINYINT UNSIGNED NULL,
  nama_pemesan VARCHAR(100) NULL,
  total_subtotal DECIMAL(12,0) NOT NULL,
  pajak_nominal DECIMAL(12,0) NOT NULL DEFAULT 0,
  diskon_nominal DECIMAL(12,0) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,0) NOT NULL,
  metode_bayar ENUM('Tunai','QRIS','Transfer Bank','Kartu Debit') NOT NULL,
  uang_dibayar DECIMAL(12,0) NOT NULL,
  uang_kembali DECIMAL(12,0) NOT NULL DEFAULT 0,
  status ENUM('Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Diproses',
  created_at DATETIME NOT NULL,
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_status (status),
  CONSTRAINT fk_ord_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_details (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  harga_satuan DECIMAL(12,0) NOT NULL,
  kuantiti INT NOT NULL,
  subtotal_baris DECIMAL(12,0) NOT NULL,
  catatan_khusus VARCHAR(150) NULL,
  CONSTRAINT fk_det_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_det_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Data awal: akun uji (password di-hash dengan bcrypt)
INSERT INTO users (username, password_hash, nama_lengkap, role, status, created_at) VALUES
('admin','$2b$10$1LsJBEZKsbIq2Te5fH6Q3OYJAjJlckt.KRtaARkl4emtOXNKqPmcC','Rina Kusuma (Manager)','admin',1,NOW()),
('kasir1','$2b$10$xrtWS7rctvyJ6KeW8Hz9KuYP7kivjK2HSpTT591ZqE2gswWar8eoe','Dewi Lestari','kasir',1,NOW()),
('kasir2','$2b$10$E8DY.s0e5O4VCdNoxIYr5OqMQ0t89GQiuIKHVwOHrs5fUQZ4O2Ula','Budi Santoso','kasir',1,NOW());

INSERT INTO categories (nama_kategori, slug, status) VALUES
('Coffee','coffee',1),
('Non-Coffee','non-coffee',1),
('Food','food',1),
('Pastry','pastry',1);

INSERT INTO products (category_id, kode_produk, nama_produk, harga_jual, harga_pokok, stok, stok_minimum, unit, status, created_at) VALUES
(1,'CF-001','Americano',22000,7000,50,10,'porsi',1,NOW()),
(1,'CF-002','Cafe Latte',28000,10000,40,10,'porsi',1,NOW()),
(2,'NC-001','Matcha Latte',30000,12000,30,8,'porsi',1,NOW()),
(3,'FD-001','Nasi Goreng Kampung',35000,15000,25,6,'porsi',1,NOW()),
(4,'PS-001','Croissant Butter',24000,10000,4,5,'pcs',1,NOW());

-- Contoh transaksi (tanggal relatif terhadap hari impor)
SET @d = DATE_SUB(CURDATE(), INTERVAL 4 DAY);
INSERT INTO orders (invoice_number,user_id,tipe_pesanan,nomor_meja,nama_pemesan,total_subtotal,pajak_nominal,diskon_nominal,grand_total,metode_bayar,uang_dibayar,uang_kembali,status,created_at) VALUES (CONCAT('INV-POS-',DATE_FORMAT(@d,'%Y%m%d'),'-0001'),2,'Dine-In',3,'Pak Andi',44000,4400,0,48400,'Tunai',50000,1600,'Selesai',TIMESTAMP(@d,'09:15:00'));
INSERT INTO order_details (order_id,product_id,harga_satuan,kuantiti,subtotal_baris,catatan_khusus) VALUES (LAST_INSERT_ID(),1,22000,2,44000,'Less sugar');
SET @d = DATE_SUB(CURDATE(), INTERVAL 3 DAY);
INSERT INTO orders (invoice_number,user_id,tipe_pesanan,nomor_meja,nama_pemesan,total_subtotal,pajak_nominal,diskon_nominal,grand_total,metode_bayar,uang_dibayar,uang_kembali,status,created_at) VALUES (CONCAT('INV-POS-',DATE_FORMAT(@d,'%Y%m%d'),'-0001'),3,'Takeaway',NULL,NULL,30000,3000,0,33000,'QRIS',33000,0,'Selesai',TIMESTAMP(@d,'13:40:00'));
INSERT INTO order_details (order_id,product_id,harga_satuan,kuantiti,subtotal_baris,catatan_khusus) VALUES (LAST_INSERT_ID(),3,30000,1,30000,NULL);
SET @d = DATE_SUB(CURDATE(), INTERVAL 2 DAY);
INSERT INTO orders (invoice_number,user_id,tipe_pesanan,nomor_meja,nama_pemesan,total_subtotal,pajak_nominal,diskon_nominal,grand_total,metode_bayar,uang_dibayar,uang_kembali,status,created_at) VALUES (CONCAT('INV-POS-',DATE_FORMAT(@d,'%Y%m%d'),'-0001'),2,'Dine-In',7,'Ibu Sari',56000,5600,0,61600,'Kartu Debit',61600,0,'Selesai',TIMESTAMP(@d,'16:05:00'));
INSERT INTO order_details (order_id,product_id,harga_satuan,kuantiti,subtotal_baris,catatan_khusus) VALUES (LAST_INSERT_ID(),2,28000,2,56000,'Extra ice');
SET @d = DATE_SUB(CURDATE(), INTERVAL 0 DAY);
INSERT INTO orders (invoice_number,user_id,tipe_pesanan,nomor_meja,nama_pemesan,total_subtotal,pajak_nominal,diskon_nominal,grand_total,metode_bayar,uang_dibayar,uang_kembali,status,created_at) VALUES (CONCAT('INV-POS-',DATE_FORMAT(@d,'%Y%m%d'),'-0001'),2,'Dine-In',5,NULL,105000,10500,0,115500,'Tunai',150000,34500,'Selesai',TIMESTAMP(@d,'10:20:00'));
INSERT INTO order_details (order_id,product_id,harga_satuan,kuantiti,subtotal_baris,catatan_khusus) VALUES (LAST_INSERT_ID(),4,35000,3,105000,NULL);
SET @d = DATE_SUB(CURDATE(), INTERVAL 0 DAY);
INSERT INTO orders (invoice_number,user_id,tipe_pesanan,nomor_meja,nama_pemesan,total_subtotal,pajak_nominal,diskon_nominal,grand_total,metode_bayar,uang_dibayar,uang_kembali,status,created_at) VALUES (CONCAT('INV-POS-',DATE_FORMAT(@d,'%Y%m%d'),'-0002'),3,'Takeaway',NULL,'Mas Rizki',22000,2200,0,24200,'QRIS',24200,0,'Diproses',TIMESTAMP(@d,'11:30:00'));
INSERT INTO order_details (order_id,product_id,harga_satuan,kuantiti,subtotal_baris,catatan_khusus) VALUES (LAST_INSERT_ID(),1,22000,1,22000,NULL);
