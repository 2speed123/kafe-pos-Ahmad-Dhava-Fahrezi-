<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$meId = (int)user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($aksi === 'simpan') {
            $username = trim($_POST['username'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'kasir';
            $status = isset($_POST['status']) ? 1 : 0;
            $pass = $_POST['password'] ?? '';
            if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) throw new RuntimeException('Username 3-30 karakter (huruf, angka, titik, garis bawah, strip).');
            if ($nama === '') throw new RuntimeException('Nama lengkap wajib diisi.');
            if (!$id && strlen($pass) < 6) throw new RuntimeException('Password minimal 6 karakter.');
            if ($id && $pass !== '' && strlen($pass) < 6) throw new RuntimeException('Password baru minimal 6 karakter.');
            if ($id === $meId && (!$status || $role !== 'admin')) throw new RuntimeException('Anda tidak dapat menonaktifkan atau menurunkan role akun sendiri.');

            if ($id) {
                $pdo->prepare('UPDATE users SET username=?, nama_lengkap=?, role=?, status=? WHERE id=?')->execute([$username, $nama, $role, $status, $id]);
                if ($pass !== '') $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
            } else {
                $pdo->prepare('INSERT INTO users (username, password_hash, nama_lengkap, role, status, created_at) VALUES (?,?,?,?,?,NOW())')
                    ->execute([$username, password_hash($pass, PASSWORD_BCRYPT), $nama, $role, $status]);
            }
            flash('success', 'Data pengguna berhasil disimpan.');
        } elseif ($aksi === 'hapus') {
            if ($id === $meId) throw new RuntimeException('Akun yang sedang digunakan tidak dapat dihapus.');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            flash('success', 'Pengguna dihapus.');
        }
    } catch (PDOException $ex) {
        flash('error', $ex->getCode() === '23000' ? 'Username sudah digunakan, atau pengguna sudah memiliki transaksi (nonaktifkan saja).' : 'Data gagal disimpan.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('pengguna.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$list = $pdo->query('SELECT * FROM users ORDER BY role, nama_lengkap')->fetchAll();

$judul = 'Manajemen Pengguna';
$menu_aktif = 'pengguna';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-main">
  <section class="panel">
    <div class="table-wrap"><table>
      <thead><tr><th>Username</th><th>Nama lengkap</th><th>Role</th><th>Status</th><th class="aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($list as $u): ?>
        <tr>
          <td><?= e($u['username']) ?></td><td><?= e($u['nama_lengkap']) ?></td><td><?= $u['role'] === 'admin' ? 'Administrator' : 'Kasir' ?></td>
          <td><?= $u['status'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-bad">Nonaktif</span>' ?></td>
          <td class="aksi">
            <a class="btn btn-light btn-sm" href="?edit=<?= (int)$u['id'] ?>">Ubah</a>
            <?php if ((int)$u['id'] !== $meId): ?>
            <form method="post" class="inline-form" data-confirm="Hapus pengguna <?= e($u['username']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-danger btn-sm">Hapus</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <aside><section class="panel">
    <h2><?= $edit ? 'Ubah pengguna' : 'Tambah pengguna' ?></h2>
    <form method="post" autocomplete="off"><?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label for="un">Username</label><input id="un" name="username" required maxlength="30" value="<?= e($edit['username'] ?? '') ?>"></div>
      <div class="field"><label for="nl">Nama lengkap</label><input id="nl" name="nama_lengkap" required maxlength="100" value="<?= e($edit['nama_lengkap'] ?? '') ?>"></div>
      <div class="field"><label for="rl">Role</label>
        <select id="rl" name="role"><option value="kasir" <?= ($edit['role'] ?? '') === 'kasir' ? 'selected' : '' ?>>Kasir / Waiter</option><option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator / Manager</option></select></div>
      <div class="field"><label for="pw">Password<?= $edit ? ' (kosongkan jika tidak diubah)' : '' ?></label><input id="pw" type="password" name="password" <?= $edit ? '' : 'required' ?> autocomplete="new-password"></div>
      <label class="check"><input type="checkbox" name="status" <?= ($edit['status'] ?? 1) ? 'checked' : '' ?>> Akun aktif</label>
      <button class="btn btn-primary btn-block">Simpan</button>
      <?php if ($edit): ?><a href="pengguna.php" class="btn btn-light btn-block">Batal</a><?php endif; ?>
    </form>
  </section></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
