<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pdo = db();
$uid = (int)user()['id'];

// Aksi ubah status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $oid = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $st->execute([$oid]);
        $o = $st->fetch();
        if (!$o || (!is_admin() && (int)$o['user_id'] !== $uid)) throw new RuntimeException('Transaksi tidak ditemukan.');
        if ($o['status'] === 'Dibatalkan') throw new RuntimeException('Transaksi yang dibatalkan tidak dapat diubah.');

        if ($aksi === 'selesai' && $o['status'] === 'Diproses') {
            $pdo->prepare("UPDATE orders SET status = 'Selesai' WHERE id = ?")->execute([$oid]);
            $msg = 'Pesanan ' . $o['invoice_number'] . ' ditandai selesai.';
        } elseif ($aksi === 'batal' && is_admin()) {
            // Kembalikan stok bahan/menu yang sebelumnya dikurangi.
            $pdo->prepare('UPDATE products p JOIN order_details d ON d.product_id = p.id SET p.stok = p.stok + d.kuantiti WHERE d.order_id = ?')->execute([$oid]);
            $pdo->prepare("UPDATE orders SET status = 'Dibatalkan' WHERE id = ?")->execute([$oid]);
            $msg = 'Pesanan ' . $o['invoice_number'] . ' dibatalkan dan stok dikembalikan.';
        } else {
            throw new RuntimeException('Aksi tidak dapat dilakukan.');
        }
        $pdo->commit();
        flash('success', $msg);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'Perubahan status gagal diproses.');
    }
    redirect('riwayat.php?' . http_build_query(array_filter(['tgl' => $_POST['tgl'] ?? '', 'q' => $_POST['q'] ?? ''])));
}

$tgl = $_GET['tgl'] ?? date('Y-m-d');
if ($tgl !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) $tgl = date('Y-m-d');
$q = trim($_GET['q'] ?? '');

$where = ['1=1'];
$par = [];
if (!is_admin()) { $where[] = 'o.user_id = ?'; $par[] = $uid; }
if ($tgl !== '') { $where[] = 'DATE(o.created_at) = ?'; $par[] = $tgl; }
if ($q !== '') { $where[] = '(o.invoice_number LIKE ? OR o.nama_pemesan LIKE ?)'; $par[] = "%$q%"; $par[] = "%$q%"; }

$st = $pdo->prepare('SELECT o.*, u.nama_lengkap FROM orders o JOIN users u ON u.id = o.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.id DESC LIMIT 200');
$st->execute($par);
$rows = $st->fetchAll();

$judul = is_admin() ? 'Riwayat Transaksi' : 'Riwayat Transaksi Saya';
$menu_aktif = 'riwayat';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="filter">
  <div class="field"><label for="tgl">Tanggal</label><input type="date" id="tgl" name="tgl" value="<?= e($tgl) ?>"></div>
  <div class="field grow"><label for="q">Cari invoice / nama pemesan</label><input type="text" id="q" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><button class="btn btn-primary">Tampilkan</button> <a class="btn btn-light" href="riwayat.php?tgl=">Semua tanggal</a></div>
</form>

<section class="panel">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Invoice</th><th>Waktu</th><?php if (is_admin()): ?><th>Kasir</th><?php endif; ?><th>Tipe</th><th>Pembayaran</th><th class="num">Grand total</th><th>Status</th><th class="aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="struk.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
          <td><?= e(tanggal_id($r['created_at'])) ?></td>
          <?php if (is_admin()): ?><td><?= e($r['nama_lengkap']) ?></td><?php endif; ?>
          <td><?= e($r['tipe_pesanan']) ?><?= $r['nomor_meja'] ? ' / Meja ' . (int)$r['nomor_meja'] : '' ?></td>
          <td><?= e($r['metode_bayar']) ?></td>
          <td class="num"><?= rupiah($r['grand_total']) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="aksi">
            <a href="struk.php?id=<?= (int)$r['id'] ?>" class="btn btn-light btn-sm">Struk</a>
            <?php if ($r['status'] === 'Diproses'): ?>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="aksi" value="selesai"><input type="hidden" name="tgl" value="<?= e($tgl) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><button class="btn btn-light btn-sm">Selesaikan</button></form>
            <?php endif; ?>
            <?php if (is_admin() && $r['status'] !== 'Dibatalkan'): ?>
              <form method="post" class="inline-form" data-confirm="Batalkan transaksi <?= e($r['invoice_number']) ?>? Stok akan dikembalikan."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="aksi" value="batal"><input type="hidden" name="tgl" value="<?= e($tgl) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><button class="btn btn-danger btn-sm">Batalkan</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada transaksi untuk filter ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
