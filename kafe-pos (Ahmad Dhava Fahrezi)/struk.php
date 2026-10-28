<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT o.*, u.nama_lengkap FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o || (!is_admin() && (int)$o['user_id'] !== (int)user()['id'])) {
    flash('error', 'Data transaksi tidak ditemukan.');
    redirect('riwayat.php');
}
$st = $pdo->prepare('SELECT d.*, p.nama_produk FROM order_details d JOIN products p ON p.id = d.product_id WHERE d.order_id = ? ORDER BY d.id');
$st->execute([$id]);
$items = $st->fetchAll();

$size = $_GET['size'] ?? '80';
if (!in_array($size, ['80', '58', 'a4'], true)) $size = '80';
$baru = isset($_GET['baru']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk <?= e($o['invoice_number']) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  @page { size: <?= $size === 'a4' ? 'A4' : $size . 'mm auto' ?>; margin: <?= $size === 'a4' ? '15mm' : '4mm' ?>; }
</style>
</head>
<body class="struk-page">
<div class="struk-toolbar no-print">
  <div>
    <a href="transaksi.php" class="btn <?= $baru ? 'btn-primary' : 'btn-light' ?> btn-sm">Transaksi baru</a>
    <a href="riwayat.php" class="btn btn-light btn-sm">Riwayat</a>
  </div>
  <div>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Cetak</button>
  </div>
</div>

<div class="struk struk-<?= e($size) ?>">
  <div class="struk-head">
    <strong><?= e(APP_NAME) ?></strong>
    <div><?= e(APP_ADDRESS) ?></div>
    <div>Telp. <?= e(APP_PHONE) ?></div>
  </div>
  <hr>
  <table class="struk-meta">
    <tr><td>No. Faktur</td><td><?= e($o['invoice_number']) ?></td></tr>
    <tr><td>Tanggal</td><td><?= e(tanggal_id($o['created_at'])) ?></td></tr>
    <tr><td>Kasir</td><td><?= e($o['nama_lengkap']) ?></td></tr>
    <tr><td>Tipe</td><td><?= e($o['tipe_pesanan']) ?><?= $o['nomor_meja'] ? ' - Meja ' . (int)$o['nomor_meja'] : '' ?></td></tr>
    <?php if ($o['nama_pemesan']): ?><tr><td>Pemesan</td><td><?= e($o['nama_pemesan']) ?></td></tr><?php endif; ?>
    <tr><td>Status</td><td><?= e($o['status']) ?></td></tr>
  </table>
  <hr>
  <table class="struk-items">
    <?php foreach ($items as $it): ?>
      <tr><td colspan="2"><?= e($it['nama_produk']) ?></td></tr>
      <?php if ($it['catatan_khusus']): ?><tr><td colspan="2" class="note">* <?= e($it['catatan_khusus']) ?></td></tr><?php endif; ?>
      <tr><td><?= (int)$it['kuantiti'] ?> x <?= number_format($it['harga_satuan'], 0, ',', '.') ?></td><td class="r"><?= number_format($it['subtotal_baris'], 0, ',', '.') ?></td></tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table class="struk-total">
    <tr><td>Subtotal</td><td class="r"><?= number_format($o['total_subtotal'], 0, ',', '.') ?></td></tr>
    <?php if ($o['diskon_nominal'] > 0): ?><tr><td>Diskon</td><td class="r">-<?= number_format($o['diskon_nominal'], 0, ',', '.') ?></td></tr><?php endif; ?>
    <tr><td>PB1 (<?= (int)(PB1_RATE * 100) ?>%)</td><td class="r"><?= number_format($o['pajak_nominal'], 0, ',', '.') ?></td></tr>
    <tr class="gt"><td>Grand Total</td><td class="r"><?= number_format($o['grand_total'], 0, ',', '.') ?></td></tr>
    <tr><td>Bayar (<?= e($o['metode_bayar']) ?>)</td><td class="r"><?= number_format($o['uang_dibayar'], 0, ',', '.') ?></td></tr>
    <tr><td>Kembali</td><td class="r"><?= number_format($o['uang_kembali'], 0, ',', '.') ?></td></tr>
  </table>
  <hr>
  <p class="struk-foot">Terima kasih atas kunjungan Anda.<br>Barang yang sudah dibeli tidak dapat ditukar.</p>
  <p class="struk-foot struk-by">By : Ahmad Dhava</p>
</div>
<?php if ($baru): ?><script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
</body>
</html>
