<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/report.php';
require_admin();

$f = report_filters();
[$rows, $sum] = report_data(db(), $f);
$format = $_GET['format'] ?? 'csv';
$nama = 'laporan-penjualan-' . $f['dari'] . '_sd_' . $f['sampai'];
$head = ['No. Faktur', 'Tanggal & Jam', 'Kasir', 'Tipe Pesanan', 'Item Terjual', 'Subtotal', 'Pajak', 'Grand Total', 'Status'];
$line = fn($r) => [$r['invoice_number'], tanggal_id($r['created_at']), $r['kasir'], $r['tipe_pesanan'] . ($r['nomor_meja'] ? ' (Meja ' . $r['nomor_meja'] . ')' : ''), $r['item_terjual'], (int)$r['total_subtotal'], (int)$r['pajak_nominal'], (int)$r['grand_total'], $r['status']];
$ringkas = [
    'Total Omset Bruto' => $sum['bruto'], 'Total Diskon Diberikan' => $sum['diskon'], 'Total Pajak Restoran' => $sum['pajak'],
    'Omset Bersih' => $sum['bersih'], 'Rata-rata Nilai Transaksi' => round($sum['rata']),
];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8
    fputcsv($out, [APP_NAME . ' - Laporan Penjualan']);
    fputcsv($out, ['Periode', $f['dari'] . ' s/d ' . $f['sampai']]);
    foreach ($ringkas as $k => $v) fputcsv($out, [$k, (int)$v]);
    fputcsv($out, []);
    fputcsv($out, $head);
    foreach ($rows as $r) fputcsv($out, $line($r));
    fclose($out);
    exit;
}

