<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pdo = db();
$hariIni = date('Y-m-d');
$uid = (int)user()['id'];
// Kasir hanya melihat data transaksinya sendiri; admin melihat seluruhnya.
$scope = is_admin() ? '' : " AND o.user_id = $uid";
$valid = "o.status <> 'Dibatalkan'";

// KPI 1 & 2
$st = $pdo->prepare("SELECT COALESCE(SUM(o.grand_total),0) omset, COUNT(*) jml FROM orders o WHERE DATE(o.created_at) = ? AND $valid $scope");
$st->execute([$hariIni]);
$kpi = $st->fetch();

// KPI 3
$st = $pdo->prepare("SELECT p.nama_produk, SUM(d.kuantiti) qty
    FROM order_details d JOIN orders o ON o.id = d.order_id JOIN products p ON p.id = d.product_id
    WHERE DATE(o.created_at) = ? AND $valid $scope
    GROUP BY p.id, p.nama_produk ORDER BY qty DESC, p.nama_produk LIMIT 5");
$st->execute([$hariIni]);
$terlaris = $st->fetchAll();

// KPI 4
$stokRendah = $pdo->query("SELECT id, nama_produk, stok, stok_minimum, unit FROM products WHERE status = 1 AND stok < stok_minimum ORDER BY stok ASC")->fetchAll();

// Tren 7 hari
$awal = date('Y-m-d', strtotime('-6 days'));
$st = $pdo->prepare("SELECT DATE(o.created_at) tgl, SUM(o.grand_total) total, COUNT(*) jml FROM orders o
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND $valid $scope GROUP BY DATE(o.created_at)");
$st->execute([$awal, $hariIni]);
$map = [];
foreach ($st->fetchAll() as $r) $map[$r['tgl']] = $r;
$tren = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $tren[] = ['tgl' => $d, 'total' => (float)($map[$d]['total'] ?? 0), 'jml' => (int)($map[$d]['jml'] ?? 0)];
}
$maks = max(array_column($tren, 'total')) ?: 1;

// Distribusi kategori 7 hari
$st = $pdo->prepare("SELECT c.nama_kategori, SUM(d.subtotal_baris) total
    FROM order_details d JOIN orders o ON o.id = d.order_id JOIN products p ON p.id = d.product_id JOIN categories c ON c.id = p.category_id
    WHERE DATE(o.created_at) BETWEEN ? AND ? AND $valid $scope GROUP BY c.id, c.nama_kategori ORDER BY total DESC");
$st->execute([$awal, $hariIni]);
$kat = $st->fetchAll();
$katTotal = array_sum(array_column($kat, 'total')) ?: 1;

// Riwayat cepat
$recent = $pdo->query("SELECT o.id, o.invoice_number, o.created_at, o.tipe_pesanan, o.nomor_meja, o.grand_total, o.status, u.nama_lengkap
    FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1 $scope ORDER BY o.id DESC LIMIT 8")->fetchAll();

$hariNama = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$judul = 'Dashboard';
$menu_aktif = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="toolbar">
  <p class="muted">Ringkasan operasional tanggal <?= date('d/m/Y') ?><?= is_admin() ? '' : ' (transaksi Anda)' ?></p>
  <a href="transaksi.php" class="btn btn-primary">Buat Transaksi Baru</a>
</div>

<div class="kpi-grid">
  <div class="kpi"><span>Total omset hari ini</span><strong><?= rupiah($kpi['omset']) ?></strong></div>
  <div class="kpi"><span>Jumlah pesanan</span><strong><?= (int)$kpi['jml'] ?></strong></div>
  <div class="kpi"><span>Menu terlaris</span>
    <strong class="kpi-text"><?= $terlaris ? e($terlaris[0]['nama_produk']) : '-' ?></strong>
    <small><?= $terlaris ? (int)$terlaris[0]['qty'] . ' porsi terjual' : 'Belum ada penjualan' ?></small></div>
  <div class="kpi <?= $stokRendah ? 'kpi-alert' : '' ?>"><span>Peringatan stok rendah</span><strong><?= count($stokRendah) ?></strong>
    <small><?= $stokRendah ? 'item di bawah batas minimum' : 'Semua stok aman' ?></small></div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Omset 7 hari terakhir</h2>
    <div class="bars">
      <?php foreach ($tren as $t): $h = round($t['total'] / $maks * 100); ?>
        <div class="bar-col" title="<?= e(rupiah($t['total'])) ?> - <?= $t['jml'] ?> pesanan">
          <span class="bar-val"><?= $t['total'] > 0 ? number_format($t['total'] / 1000, 0, ',', '.') . 'k' : '0' ?></span>
          <div class="bar" style="height: <?= max($h, 2) ?>%"></div>
          <span class="bar-lbl"><?= $hariNama[(int)date('w', strtotime($t['tgl']))] ?><br><?= date('d/m', strtotime($t['tgl'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted small">Nilai dalam ribuan rupiah (k).</p>
  </section>

  <section class="panel">
    <h2>Distribusi penjualan per kategori (7 hari)</h2>
    <?php if (!$kat): ?><p class="muted">Belum ada data penjualan.</p><?php endif; ?>
    <?php foreach ($kat as $k): $pct = round($k['total'] / $katTotal * 100); ?>
      <div class="hbar">
        <div class="hbar-head"><span><?= e($k['nama_kategori']) ?></span><span><?= $pct ?>% &nbsp; <?= rupiah($k['total']) ?></span></div>
        <div class="hbar-track"><div class="hbar-fill" style="width: <?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<div class="grid-2">
  <section class="panel">
    <div class="panel-head"><h2>Transaksi terbaru</h2><a href="riwayat.php">Lihat semua</a></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice</th><th>Waktu</th><th>Tipe</th><th class="num">Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="struk.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
            <td><?= e(tanggal_id($r['created_at'])) ?></td>
            <td><?= e($r['tipe_pesanan']) ?><?= $r['nomor_meja'] ? ' / Meja ' . (int)$r['nomor_meja'] : '' ?></td>
            <td class="num"><?= rupiah($r['grand_total']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="empty">Belum ada transaksi.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <h2>Stok di bawah batas minimum</h2>
    <?php if (!$stokRendah): ?>
      <p class="muted">Tidak ada item dengan stok rendah.</p>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Item</th><th class="num">Stok</th><th class="num">Minimum</th></tr></thead>
        <tbody>
        <?php foreach ($stokRendah as $s): ?>
          <tr><td><?= e($s['nama_produk']) ?></td><td class="num"><strong><?= (int)$s['stok'] ?></strong> <?= e($s['unit']) ?></td><td class="num"><?= (int)$s['stok_minimum'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php if (is_admin()): ?><p><a href="produk.php">Kelola stok</a></p><?php endif; ?>
    <?php endif; ?>
    <?php if (count($terlaris) > 1): ?>
      <h2 class="mt">Menu terlaris hari ini</h2>
      <ol class="plain-list">
        <?php foreach ($terlaris as $t): ?><li><?= e($t['nama_produk']) ?> <span class="muted">(<?= (int)$t['qty'] ?>)</span></li><?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
