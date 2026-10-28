<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($aksi === 'simpan') {
            $d = [
                'category_id' => (int)($_POST['category_id'] ?? 0),
                'kode_produk' => strtoupper(trim($_POST['kode_produk'] ?? '')),
                'nama_produk' => trim($_POST['nama_produk'] ?? ''),
                'harga_jual' => (float)($_POST['harga_jual'] ?? 0),
                'harga_pokok' => (float)($_POST['harga_pokok'] ?? 0),
                'stok' => (int)($_POST['stok'] ?? 0),
                'stok_minimum' => (int)($_POST['stok_minimum'] ?? 0),
                'unit' => trim($_POST['unit'] ?? 'porsi') ?: 'porsi',
                'status' => isset($_POST['status']) ? 1 : 0,
            ];
            if ($d['category_id'] <= 0 || $d['kode_produk'] === '' || $d['nama_produk'] === '') throw new RuntimeException('Kategori, kode, dan nama menu wajib diisi.');
            if ($d['harga_jual'] < 0 || $d['harga_pokok'] < 0 || $d['stok'] < 0 || $d['stok_minimum'] < 0) throw new RuntimeException('Harga dan stok tidak boleh bernilai negatif.');
            if ($id) {
                $sql = 'UPDATE products SET category_id=:category_id, kode_produk=:kode_produk, nama_produk=:nama_produk, harga_jual=:harga_jual, harga_pokok=:harga_pokok, stok=:stok, stok_minimum=:stok_minimum, unit=:unit, status=:status WHERE id=:id';
                $d['id'] = $id;
            } else {
                $sql = 'INSERT INTO products (category_id, kode_produk, nama_produk, harga_jual, harga_pokok, stok, stok_minimum, unit, status, created_at) VALUES (:category_id,:kode_produk,:nama_produk,:harga_jual,:harga_pokok,:stok,:stok_minimum,:unit,:status,NOW())';
            }
            $pdo->prepare($sql)->execute($d);
            flash('success', 'Data menu berhasil disimpan.');
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            flash('success', 'Menu dihapus.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() === '23000' ? 'Kode menu sudah digunakan, atau menu sudah tercatat pada transaksi (nonaktifkan saja).' : 'Data gagal disimpan.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('produk.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$kategori = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori')->fetchAll();
$list = $pdo->query('SELECT p.*, c.nama_kategori FROM products p JOIN categories c ON c.id = p.category_id ORDER BY c.nama_kategori, p.nama_produk')->fetchAll();

$judul = 'Menu & Stok';
$menu_aktif = 'produk';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <section class="panel">
    <div class="table-wrap"><table>
      <thead><tr><th>Kode</th><th>Menu</th><th>Kategori</th><th class="num">Harga jual</th><th class="num">Stok</th><th>Status</th><th class="aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($list as $p): $low = $p['stok'] < $p['stok_minimum']; ?>
        <tr>
          <td><?= e($p['kode_produk']) ?></td><td><?= e($p['nama_produk']) ?></td><td><?= e($p['nama_kategori']) ?></td>
          <td class="num"><?= rupiah($p['harga_jual']) ?></td>
          <td class="num <?= $low ? 'text-bad' : '' ?>"><?= (int)$p['stok'] ?> <?= e($p['unit']) ?><?= $low ? ' (rendah)' : '' ?></td>
          <td><?= $p['status'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-bad">Nonaktif</span>' ?></td>
          <td class="aksi">
            <a class="btn btn-light btn-sm" href="?edit=<?= (int)$p['id'] ?>">Ubah</a>
            <form method="post" class="inline-form" data-confirm="Hapus menu <?= e($p['nama_produk']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger btn-sm">Hapus</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <aside><section class="panel">
    <h2><?= $edit ? 'Ubah menu' : 'Tambah menu' ?></h2>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label for="kp">Kode menu</label><input id="kp" name="kode_produk" required maxlength="20" value="<?= e($edit['kode_produk'] ?? '') ?>"></div>
      <div class="field"><label for="np">Nama menu</label><input id="np" name="nama_produk" required maxlength="100" value="<?= e($edit['nama_produk'] ?? '') ?>"></div>
      <div class="field"><label for="ct">Kategori</label>
        <select id="ct" name="category_id" required><option value="">Pilih kategori</option>
          <?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>" <?= (int)($edit['category_id'] ?? 0) === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-row">
        <div class="field"><label for="hj">Harga jual</label><input id="hj" type="number" min="0" name="harga_jual" required value="<?= e($edit['harga_jual'] ?? '') ?>"></div>
        <div class="field"><label for="hp">Harga pokok</label><input id="hp" type="number" min="0" name="harga_pokok" required value="<?= e($edit['harga_pokok'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="field"><label for="st">Stok</label><input id="st" type="number" min="0" name="stok" required value="<?= e($edit['stok'] ?? 0) ?>"></div>
        <div class="field"><label for="sm">Stok minimum</label><input id="sm" type="number" min="0" name="stok_minimum" required value="<?= e($edit['stok_minimum'] ?? 5) ?>"></div>
      </div>
      <div class="field"><label for="un">Satuan</label><input id="un" name="unit" maxlength="20" value="<?= e($edit['unit'] ?? 'porsi') ?>"></div>
      <label class="check"><input type="checkbox" name="status" <?= ($edit['status'] ?? 1) ? 'checked' : '' ?>> Aktif (tampil di kasir)</label>
      <button class="btn btn-primary btn-block">Simpan</button>
      <?php if ($edit): ?><a href="produk.php" class="btn btn-light btn-block">Batal</a><?php endif; ?>
    </form>
  </section></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
