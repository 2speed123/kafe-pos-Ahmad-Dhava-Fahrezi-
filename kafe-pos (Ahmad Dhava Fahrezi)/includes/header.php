<?php
// Variabel: $judul (judul halaman), $menu_aktif (kunci menu)
$menu_aktif = $menu_aktif ?? '';
$u = user();
$nav = [
    ['dashboard', 'dashboard.php', 'Dashboard', false],
    ['transaksi', 'transaksi.php', 'Transaksi Baru', false],
    ['riwayat', 'riwayat.php', is_admin() ? 'Riwayat Transaksi' : 'Riwayat Saya', false],
    ['laporan', 'laporan.php', 'Laporan', true],
    ['produk', 'produk.php', 'Menu & Stok', true],
    ['pengguna', 'pengguna.php', 'Pengguna', true],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul ?? 'Halaman') ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <strong><?= e(APP_NAME) ?></strong>
      <span>Sistem Kasir</span>
    </div>
    <nav>
      <?php foreach ($nav as [$key, $href, $label, $adminOnly]):
          if ($adminOnly && !is_admin()) continue; ?>
        <a href="<?= $href ?>" class="<?= $menu_aktif === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <div><?= e($u['nama_lengkap']) ?></div>
      <small><?= is_admin() ? 'Administrator' : 'Kasir' ?></small>
      <a href="logout.php" class="btn btn-light btn-sm" data-confirm="Keluar dari aplikasi?">Keluar</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu">Menu</button>
      <h1><?= e($judul ?? '') ?></h1>
      <span class="topbar-date"><?= date('d/m/Y') ?></span>
    </header>
    <main class="content">
      <?= flash_render() ?>
