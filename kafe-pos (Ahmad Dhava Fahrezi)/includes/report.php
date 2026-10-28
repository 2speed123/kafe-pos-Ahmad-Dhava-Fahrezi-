<?php
/** Membaca parameter filter laporan dari query string. */
function report_filters(): array
{
    $valid = fn($d, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : $def;
    $dari = $valid($_GET['dari'] ?? '', date('Y-m-01'));
    $sampai = $valid($_GET['sampai'] ?? '', date('Y-m-d'));
    if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
    $metode = in_array($_GET['metode'] ?? '', METODE_BAYAR, true) ? $_GET['metode'] : '';
    return ['dari' => $dari, 'sampai' => $sampai, 'kategori' => (int)($_GET['kategori'] ?? 0), 'metode' => $metode];
}

/** Mengambil data laporan (transaksi tidak dibatalkan) beserta ringkasannya. */
function report_data(PDO $pdo, array $f): array
{
    $where = ["o.status <> 'Dibatalkan'", 'DATE(o.created_at) BETWEEN ? AND ?'];
    $par = [$f['dari'], $f['sampai']];
    if ($f['metode'] !== '') { $where[] = 'o.metode_bayar = ?'; $par[] = $f['metode']; }
    if ($f['kategori'] > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM order_details d2 JOIN products p2 ON p2.id = d2.product_id WHERE d2.order_id = o.id AND p2.category_id = ?)';
        $par[] = $f['kategori'];
    }
    $sql = "SELECT o.id, o.invoice_number, o.created_at, o.tipe_pesanan, o.nomor_meja, o.total_subtotal, o.diskon_nominal, o.pajak_nominal, o.grand_total, o.metode_bayar, o.status,
                   u.nama_lengkap AS kasir,
                   (SELECT GROUP_CONCAT(CONCAT(p.nama_produk, ' x', d.kuantiti) ORDER BY d.id SEPARATOR ', ')
                      FROM order_details d JOIN products p ON p.id = d.product_id WHERE d.order_id = o.id) AS item_terjual
            FROM orders o JOIN users u ON u.id = o.user_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY o.created_at DESC, o.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($par);
    $rows = $st->fetchAll();

    $sum = ['bruto' => 0, 'diskon' => 0, 'pajak' => 0, 'bersih' => 0, 'grand' => 0, 'jumlah' => count($rows), 'rata' => 0];
    foreach ($rows as $r) {
        $sum['bruto'] += $r['total_subtotal'];
        $sum['diskon'] += $r['diskon_nominal'];
        $sum['pajak'] += $r['pajak_nominal'];
        $sum['grand'] += $r['grand_total'];
    }
    $sum['bersih'] = $sum['bruto'] - $sum['diskon'];   // omset bersih = bruto - diskon (di luar pajak)
    $sum['rata'] = $sum['jumlah'] ? $sum['grand'] / $sum['jumlah'] : 0;
    return [$rows, $sum];
}
