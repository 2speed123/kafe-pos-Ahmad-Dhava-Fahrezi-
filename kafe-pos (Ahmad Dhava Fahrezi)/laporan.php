<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/report.php';
require_admin();

$pdo = db();
$f = report_filters();
[$rows, $sum] = report_data($pdo, $f);
$kategori = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori')->fetchAll();
$qs = http_build_query($f);

$judul = 'Laporan Penjualan';
$menu_aktif = 'laporan';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="filter">
  <div class="field"><label for="dari">Dari tanggal</label><input type="date" id="dari" name="dari" value="<?= e($f['dari']) ?>"></div>
  <div class="field"><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" value="<?= e($f['sampai']) ?>"></div>
  <div class="field"><label for="kategori">Kategori menu</label>
    <select id="kategori" name="kategori"><option value="0">Semua kategori</option>
      <?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>" <?= $f['kategori'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><label for="metode">Metode pembayaran</label>
    <select id="metode" name="metode"><option value="">Semua metode</option>
      <?php foreach (METODE_BAYAR as $m): ?><option <?= $f['metode'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
    </select></div>
  <div class="field"><button class="btn btn-primary">Terapkan filter</button></div>
</form>

<div class="kpi-grid kpi-5">
  <div class="kpi"><span>Total omset bruto</span><strong><?= rupiah($sum['bruto']) ?></strong></div>
  <div class="kpi"><span>Total diskon diberikan</span><strong><?= rupiah($sum['diskon']) ?></strong></div>
  <div class="kpi"><span>Total pajak restoran</span><strong><?= rupiah($sum['pajak']) ?></strong></div>
  <div class="kpi"><span>Omset bersih</span><strong><?= rupiah($sum['bersih']) ?></strong></div>
  <div class="kpi"><span>Rata-rata nilai transaksi</span><strong><?= rupiah($sum['rata']) ?></strong><small><?= (int)$sum['jumlah'] ?> transaksi</small></div>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Rincian transaksi</h2>
    <div>
      <a class="btn btn-light btn-sm" href="laporan_export.php?format=csv&amp;<?= $qs ?>">Ekspor CSV (Excel)</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>No. Faktur</th><th>Tanggal &amp; Jam</th><th>Kasir</th><th>Tipe</th><th>Item terjual</th><th class="num">Subtotal</th><th class="num">Pajak</th><th class="num">Grand total</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="struk.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
          <td><?= e(tanggal_id($r['created_at'])) ?></td>
          <td><?= e($r['kasir']) ?></td>
          <td><?= e($r['tipe_pesanan']) ?></td>
          <td class="wrap"><?= e($r['item_terjual']) ?></td>
          <td class="num"><?= rupiah($r['total_subtotal']) ?></td>
          <td class="num"><?= rupiah($r['pajak_nominal']) ?></td>
          <td class="num"><?= rupiah($r['grand_total']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="empty">Tidak ada transaksi pada periode ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small">Transaksi berstatus Dibatalkan tidak dihitung. Jika filter kategori dipilih, ditampilkan seluruh transaksi yang memuat menu pada kategori tersebut.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
