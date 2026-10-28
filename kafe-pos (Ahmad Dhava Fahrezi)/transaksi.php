<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pdo = db();
$errors = [];
$old = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = $_POST;

    // Token sekali pakai: mencegah transaksi ganda akibat klik berulang / refresh.
    $tokenOk = isset($_POST['form_token'], $_SESSION['form_token']) && hash_equals($_SESSION['form_token'], (string)$_POST['form_token']);
    if (!$tokenOk) {
        flash('error', 'Formulir sudah diproses atau kedaluwarsa. Periksa Riwayat Transaksi sebelum membuat ulang.');
        redirect('transaksi.php');
    }

    $tipe = $_POST['tipe_pesanan'] ?? '';
    $meja = (int)($_POST['nomor_meja'] ?? 0);
    $nama = trim($_POST['nama_pemesan'] ?? '');
    $metode = $_POST['metode_bayar'] ?? '';
    $status = $_POST['status'] ?? 'Diproses';
    $diskonTipe = ($_POST['diskon_tipe'] ?? 'nominal') === 'persen' ? 'persen' : 'nominal';
    $diskonNilai = max(0, (float)($_POST['diskon_nilai'] ?? 0));
    $dibayar = max(0, (float)($_POST['uang_dibayar'] ?? 0));

    if (!in_array($tipe, ['Dine-In', 'Takeaway'], true)) $errors[] = 'Tipe pesanan tidak valid.';
    if ($tipe === 'Dine-In' && ($meja < 1 || $meja > JUMLAH_MEJA)) $errors[] = 'Nomor meja wajib dipilih untuk pesanan Dine-In.';
    if ($tipe === 'Takeaway') $meja = 0;
    if (!in_array($metode, METODE_BAYAR, true)) $errors[] = 'Metode pembayaran tidak valid.';
    if (!in_array($status, ['Diproses', 'Selesai'], true)) $status = 'Diproses';
    if (str_len($nama) > 100) $errors[] = 'Nama pemesan maksimal 100 karakter.';

    // Susun baris pesanan
    $rows = [];
    $pids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $notes = $_POST['catatan'] ?? [];
    foreach ($pids as $i => $pid) {
        $pid = (int)$pid;
        $qty = (int)($qtys[$i] ?? 0);
        if ($pid <= 0) continue;
        if ($qty < 1) { $errors[] = 'Kuantiti setiap item minimal 1.'; break; }
        $rows[] = ['pid' => $pid, 'qty' => $qty, 'catatan' => str_cut(trim($notes[$i] ?? ''), 150)];
    }
    if (!$rows) $errors[] = 'Tambahkan minimal satu item pesanan.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Kunci baris produk agar pengecekan stok konsisten saat ada transaksi bersamaan.
            $ids = array_values(array_unique(array_column($rows, 'pid')));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT id, nama_produk, harga_jual, stok, status FROM products WHERE id IN ($in) FOR UPDATE");
            $st->execute($ids);
            $prod = [];
            foreach ($st->fetchAll() as $p) $prod[(int)$p['id']] = $p;

            $need = [];
            foreach ($rows as $r) $need[$r['pid']] = ($need[$r['pid']] ?? 0) + $r['qty'];
            foreach ($need as $pid => $q) {
                if (!isset($prod[$pid]) || (int)$prod[$pid]['status'] !== 1) {
                    throw new RuntimeException('Terdapat menu yang tidak tersedia. Muat ulang halaman.');
                }
                if ((int)$prod[$pid]['stok'] < $q) {
                    throw new RuntimeException('Stok ' . $prod[$pid]['nama_produk'] . ' tidak mencukupi (tersisa ' . (int)$prod[$pid]['stok'] . ', diminta ' . $q . ').');
                }
            }

            // Hitung ulang seluruh nilai di server (nilai dari browser tidak dipercaya).
            $subtotal = 0;
            foreach ($rows as &$r) {
                $r['harga'] = (float)$prod[$r['pid']]['harga_jual'];
                $r['sub'] = $r['harga'] * $r['qty'];
                $subtotal += $r['sub'];
            }
            unset($r);
            $diskon = $diskonTipe === 'persen' ? round($subtotal * min($diskonNilai, 100) / 100) : min($diskonNilai, $subtotal);
            $pajak = round(($subtotal - $diskon) * PB1_RATE);
            $grand = $subtotal - $diskon + $pajak;

            if ($metode === 'Tunai') {
                if ($dibayar < $grand) throw new RuntimeException('Uang yang dibayarkan (' . rupiah($dibayar) . ') kurang dari total tagihan (' . rupiah($grand) . ').');
                $kembali = $dibayar - $grand;
            } else {
                $dibayar = $grand;
                $kembali = 0;
            }

            $now = date('Y-m-d H:i:s');
            $invoice = next_invoice($pdo, $now, true);
            $ins = $pdo->prepare('INSERT INTO orders (invoice_number, user_id, tipe_pesanan, nomor_meja, nama_pemesan, total_subtotal, pajak_nominal, diskon_nominal, grand_total, metode_bayar, uang_dibayar, uang_kembali, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$invoice, user()['id'], $tipe, $meja ?: null, $nama ?: null, $subtotal, $pajak, $diskon, $grand, $metode, $dibayar, $kembali, $status, $now]);
            $orderId = (int)$pdo->lastInsertId();

            $det = $pdo->prepare('INSERT INTO order_details (order_id, product_id, harga_satuan, kuantiti, subtotal_baris, catatan_khusus) VALUES (?,?,?,?,?,?)');
            foreach ($rows as $r) {
                $det->execute([$orderId, $r['pid'], $r['harga'], $r['qty'], $r['sub'], $r['catatan'] ?: null]);
            }
            $upd = $pdo->prepare('UPDATE products SET stok = stok - ? WHERE id = ?');
            foreach ($need as $pid => $q) $upd->execute([$q, $pid]);

            $pdo->commit();
            unset($_SESSION['form_token']);
            flash('success', 'Transaksi ' . $invoice . ' berhasil disimpan.');
            redirect('struk.php?id=' . $orderId . '&baru=1');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Transaksi gagal disimpan. Tidak ada data yang berubah. Silakan coba lagi.';
        }
    }
}

$_SESSION['form_token'] = bin2hex(random_bytes(16));

$produk = $pdo->query("SELECT p.id, p.nama_produk, p.harga_jual, p.stok, p.unit, c.nama_kategori
    FROM products p JOIN categories c ON c.id = p.category_id
    WHERE p.status = 1 AND c.status = 1 ORDER BY c.nama_kategori, p.nama_produk")->fetchAll();
$grup = [];
foreach ($produk as $p) $grup[$p['nama_kategori']][] = $p;

$oldItems = [];
if ($old) {
    foreach (($old['product_id'] ?? []) as $i => $pid) {
        $oldItems[] = ['pid' => (int)$pid, 'qty' => (int)($old['qty'][$i] ?? 1), 'catatan' => $old['catatan'][$i] ?? ''];
    }
}
$v = fn($k, $d = '') => e($old[$k] ?? $d);

$judul = 'Transaksi Baru';
$menu_aktif = 'transaksi';
require __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $er): ?>
  <div class="alert alert-error" role="alert"><?= e($er) ?></div>
<?php endforeach; ?>

<form method="post" id="formTransaksi" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="form_token" value="<?= e($_SESSION['form_token']) ?>">

  <div class="grid-main">
    <div>
      <section class="panel">
        <div class="panel-head">
          <h2>Data pesanan</h2>
          <span class="invoice-no">No. Faktur: <strong><?= e(next_invoice($pdo, date('Y-m-d H:i:s'))) ?></strong></span>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="tipe">Tipe pesanan</label>
            <select name="tipe_pesanan" id="tipe">
              <?php foreach (['Dine-In', 'Takeaway'] as $t): ?>
                <option <?= ($old['tipe_pesanan'] ?? 'Dine-In') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" id="boxMeja">
            <label for="meja">Nomor meja</label>
            <select name="nomor_meja" id="meja">
              <option value="">Pilih meja</option>
              <?php for ($i = 1; $i <= JUMLAH_MEJA; $i++): ?>
                <option value="<?= $i ?>" <?= (int)($old['nomor_meja'] ?? 0) === $i ? 'selected' : '' ?>>Meja <?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="field grow">
            <label for="nama">Nama pemesan / catatan meja</label>
            <input type="text" name="nama_pemesan" id="nama" maxlength="100" value="<?= $v('nama_pemesan') ?>" placeholder="Contoh: Bapak Andi, meja dekat jendela">
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2>Item pesanan</h2>
          <button type="button" class="btn btn-light btn-sm" id="btnTambah">Tambah item</button>
        </div>
        <div class="table-wrap">
          <table class="items" id="tblItems">
            <thead><tr><th>Menu</th><th class="num w-qty">Qty</th><th>Catatan khusus</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody id="itemRows"></tbody>
          </table>
        </div>
      </section>
    </div>

    <aside>
      <section class="panel sticky">
        <h2>Pembayaran</h2>
        <dl class="sum">
          <dt>Subtotal</dt><dd id="sumSubtotal">Rp 0</dd>
          <dt>Diskon</dt><dd id="sumDiskon">Rp 0</dd>
          <dt>PB1 (<?= (int)(PB1_RATE * 100) ?>%)</dt><dd id="sumPajak">Rp 0</dd>
          <dt class="total">Grand total</dt><dd class="total" id="sumGrand">Rp 0</dd>
        </dl>

        <div class="field">
          <label for="diskon_nilai">Diskon / voucher</label>
          <div class="inline">
            <input type="number" min="0" step="any" name="diskon_nilai" id="diskonNilai" value="<?= $v('diskon_nilai', '0') ?>">
            <select name="diskon_tipe" id="diskonTipe">
              <option value="nominal" <?= ($old['diskon_tipe'] ?? '') === 'nominal' ? 'selected' : '' ?>>Rp</option>
              <option value="persen" <?= ($old['diskon_tipe'] ?? '') === 'persen' ? 'selected' : '' ?>>%</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="metode">Metode pembayaran</label>
          <select name="metode_bayar" id="metode">
            <?php foreach (METODE_BAYAR as $m): ?><option <?= ($old['metode_bayar'] ?? 'Tunai') === $m ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="boxBayar">
          <label for="bayar">Uang dibayarkan</label>
          <input type="number" min="0" step="any" name="uang_dibayar" id="bayar" value="<?= $v('uang_dibayar', '') ?>">
          <div class="quick-cash" id="quickCash"></div>
          <p class="kembalian">Kembalian: <strong id="sumKembali">Rp 0</strong></p>
          <p class="field-error" id="errBayar" hidden>Uang yang dibayarkan kurang dari total tagihan.</p>
        </div>
        <div class="field">
          <label for="status">Status pesanan</label>
          <select name="status" id="status">
            <option value="Diproses" <?= ($old['status'] ?? '') !== 'Selesai' ? 'selected' : '' ?>>Diproses</option>
            <option value="Selesai" <?= ($old['status'] ?? '') === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btnSimpan">Simpan dan cetak struk</button>
      </section>
    </aside>
  </div>
</form>

<?php
$cfg = [
    'produk' => array_map(fn($p) => ['id' => (int)$p['id'], 'nama' => $p['nama_produk'], 'harga' => (float)$p['harga_jual'], 'stok' => (int)$p['stok'], 'unit' => $p['unit'], 'kat' => $p['nama_kategori']], $produk),
    'pb1' => PB1_RATE,
    'old' => $oldItems,
];
$extra_js = '<script>window.POS = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script><script src="assets/js/transaksi.js"></script>';
require __DIR__ . '/includes/footer.php';
