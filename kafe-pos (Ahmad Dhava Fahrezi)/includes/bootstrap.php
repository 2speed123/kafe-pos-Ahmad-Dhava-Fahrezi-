<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Koneksi database gagal. Periksa includes/config.php dan pastikan database "' . DB_NAME . '" sudah diimpor.');
        }
    }
    return $pdo;
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tanggal_id(string $dt, bool $jam = true): string
{
    return date($jam ? 'd/m/Y H:i' : 'd/m/Y', strtotime($dt));
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Flash message ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="alert alert-' . e($f['type']) . '" role="alert">' . e($f['msg']) . '<button type="button" class="alert-close" aria-label="Tutup">&times;</button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.');
    }
}

/* ---------- Autentikasi ---------- */
function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!user()) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        redirect('dashboard.php');
    }
}

/* ---------- Domain ---------- */
const METODE_BAYAR = ['Tunai', 'QRIS', 'Transfer Bank', 'Kartu Debit'];
const STATUS_ORDER = ['Diproses', 'Selesai', 'Dibatalkan'];

function status_badge(string $s): string
{
    $cls = ['Diproses' => 'warn', 'Selesai' => 'ok', 'Dibatalkan' => 'bad'][$s] ?? '';
    return '<span class="badge badge-' . $cls . '">' . e($s) . '</span>';
}

/** Nomor invoice berikutnya untuk tanggal tertentu. Gunakan $lock=true di dalam transaksi. */
function next_invoice(PDO $pdo, string $date, bool $lock = false): string
{
    $prefix = 'INV-POS-' . date('Ymd', strtotime($date)) . '-';
    $st = $pdo->prepare('SELECT MAX(invoice_number) FROM orders WHERE invoice_number LIKE ?' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $n = $last ? ((int)substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Potong teks maksimal $n karakter (aman untuk UTF-8 meski ekstensi mbstring tidak aktif). */
function str_cut(string $s, int $n): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
}

function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}
