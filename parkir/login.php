<?php
require_once __DIR__ . '/koneksi.php';

/*
|--------------------------------------------------------------------------
| Pastikan sesi aktif & fungsi bantu tersedia
|--------------------------------------------------------------------------
| Jika koneksi.php sudah memanggil session_start() atau mendefinisikan e(),
| bagian ini otomatis dilewati.
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e($nilai): string
    {
        return htmlspecialchars((string) $nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Halaman login tidak boleh disimpan di cache browser.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| Arahkan pengguna ke dashboard sesuai role
|--------------------------------------------------------------------------
*/
function arahkanDashboardLogin(string $role): void
{
    $halaman = [
        'admin'   => 'admin/dashboard.php',
        'petugas' => 'petugas/dashboard.php',
        'owner'   => 'owner/dashboard.php',
    ];

    $role = strtolower(trim($role));

    if (isset($halaman[$role])) {
        header('Location: ' . $halaman[$role]);
        exit;
    }

    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Periksa sesi login
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['user']['id_user'])) {
    arahkanDashboardLogin((string) ($_SESSION['user']['role'] ?? ''));
}

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Pembatasan percobaan login (per sesi)
|--------------------------------------------------------------------------
| Catatan: ini perlindungan dasar. Untuk perlindungan penuh, catat
| percobaan gagal per IP / username di database.
*/
const BATAS_GAGAL   = 5;
const DURASI_BLOKIR = 300; // detik

/*
|--------------------------------------------------------------------------
| Proses login
|--------------------------------------------------------------------------
*/
$error = '';
$usernameInput = '';
$roleDipilih = '';
$rolesValid = ['admin', 'petugas', 'owner'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim((string) ($_POST['username'] ?? ''));
    $passwordInput = (string) ($_POST['password'] ?? '');
    $roleDipilih   = strtolower(trim((string) ($_POST['role'] ?? '')));
    $tokenKiriman  = (string) ($_POST['csrf'] ?? '');

    $blokirSampai = (int) ($_SESSION['login_blokir_sampai'] ?? 0);

    if (!hash_equals($_SESSION['csrf_login'], $tokenKiriman)) {
        $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($blokirSampai > time()) {
        $sisa = (int) ceil(($blokirSampai - time()) / 60);
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . $sisa . ' menit.';
    } elseif ($usernameInput === '' || $passwordInput === '' || $roleDipilih === '') {
        $error = 'Username, password, dan peran wajib diisi.';
    } elseif (!in_array($roleDipilih, $rolesValid, true)) {
        $error = 'Peran yang dipilih tidak valid.';
    } else {
        $pesanGagal = 'Username, password, atau peran tidak sesuai.';

        try {
            $stmt = $pdo->prepare(
                "SELECT id_user, nama_lengkap, username,
                        password, role, status_aktif
                 FROM tb_user
                 WHERE username = ?
                 LIMIT 1"
            );
            $stmt->execute([$usernameInput]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordCocok = false;
            $perluRehash = false;

            if ($user) {
                $passwordDB = trim((string) $user['password']);

                // Mendukung SHA-256 lama dan password_hash().
                if (preg_match('/^[a-f0-9]{64}$/i', $passwordDB)) {
                    $passwordCocok = hash_equals(
                        strtolower($passwordDB),
                        hash('sha256', $passwordInput)
                    );
                    $perluRehash = $passwordCocok;
                } else {
                    $passwordCocok = password_verify($passwordInput, $passwordDB);
                    $perluRehash = $passwordCocok
                        && password_needs_rehash($passwordDB, PASSWORD_DEFAULT);
                }
            } else {
                // Hash palsu agar waktu respons tidak membocorkan
                // apakah username terdaftar atau tidak.
                password_verify(
                    $passwordInput,
                    '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZ01234'
                );
            }

            $roleDB = $user ? strtolower(trim((string) $user['role'])) : '';

            if (!$user || !$passwordCocok) {
                $error = $pesanGagal;
            } elseif ((int) $user['status_aktif'] !== 1) {
                $error = 'Akun tidak aktif. Hubungi administrator.';
            } elseif (!in_array($roleDB, $rolesValid, true) || $roleDB !== $roleDipilih) {
                $error = $pesanGagal;
            } else {
                // Naikkan hash lama (SHA-256) ke password_hash() secara otomatis.
                if ($perluRehash) {
                    try {
                        $upd = $pdo->prepare(
                            "UPDATE tb_user SET password = ? WHERE id_user = ?"
                        );
                        $upd->execute([
                            password_hash($passwordInput, PASSWORD_DEFAULT),
                            (int) $user['id_user'],
                        ]);
                    } catch (Throwable $e) {
                        error_log('Rehash password gagal: ' . $e->getMessage());
                    }
                }

                session_regenerate_id(true);

                unset(
                    $_SESSION['login_gagal'],
                    $_SESSION['login_blokir_sampai']
                );

                $_SESSION['user'] = [
                    'id_user'      => (int) $user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'username'     => $user['username'],
                    'role'         => $roleDB,
                ];

                try {
                    if (function_exists('catatAktivitas')) {
                        catatAktivitas($pdo, 'Login ke sistem');
                    }
                } catch (Throwable $e) {
                    error_log('Pencatatan login gagal: ' . $e->getMessage());
                }

                arahkanDashboardLogin($roleDB);
            }

            // Hitung kegagalan (kecuali akun nonaktif yang sudah benar kredensialnya).
            if ($error === $pesanGagal) {
                $_SESSION['login_gagal'] = (int) ($_SESSION['login_gagal'] ?? 0) + 1;

                if ($_SESSION['login_gagal'] >= BATAS_GAGAL) {
                    $_SESSION['login_blokir_sampai'] = time() + DURASI_BLOKIR;
                    $_SESSION['login_gagal'] = 0;
                }
            }
        } catch (PDOException $e) {
            error_log('Kesalahan database login: ' . $e->getMessage());
            $error = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Informasi kapasitas parkir
|--------------------------------------------------------------------------
| Asumsi: tb_area_parkir memiliki kolom "kapasitas".
| Kendaraan aktif = transaksi berstatus "masuk".
*/
$kapasitasParkir  = 0;
$kendaraanParkir  = 0;
$slotTersedia     = 0;
$persentaseTerisi = 0.0;
$kodeStatus       = 'unknown'; // unknown | available | warning | full
$labelStatus      = 'Belum ada data kapasitas';

try {
    $kapasitasParkir = (int) $pdo
        ->query("SELECT COALESCE(SUM(kapasitas), 0) FROM tb_area_parkir")
        ->fetchColumn();

    $kendaraanParkir = (int) $pdo
        ->query("SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'")
        ->fetchColumn();

    if ($kapasitasParkir > 0) {
        $slotTersedia = max(0, $kapasitasParkir - $kendaraanParkir);
        $persentaseTerisi = min(100, ($kendaraanParkir / $kapasitasParkir) * 100);

        if ($slotTersedia === 0) {
            $kodeStatus  = 'full';
            $labelStatus = 'Parkir Penuh';
        } elseif ($slotTersedia <= $kapasitasParkir * 0.2) {
            $kodeStatus  = 'warning';
            $labelStatus = 'Hampir Penuh';
        } else {
            $kodeStatus  = 'available';
            $labelStatus = 'Tersedia';
        }
    }
} catch (PDOException $e) {
    error_log('Gagal mengambil informasi parkir: ' . $e->getMessage());
}

$statusClass = 'status-' . $kodeStatus;

// Titik desimal yang aman untuk CSS (tidak terpengaruh locale).
$lebarProgress = number_format($persentaseTerisi, 1, '.', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ParkirKu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Semua kelas berawalan "pk-" agar tidak bentrok dengan style.css lain. */
        :root {
            --pk-bg: #eaf0eb;
            --pk-green: #0c3b2e;
            --pk-green-2: #14513f;
            --pk-sign: #f0b429;
            --pk-paper: #ffffff;
            --pk-text: #16241e;
            --pk-muted: #62736a;
            --pk-line: #d9e2dc;
            --pk-danger: #a12b2b;
            --pk-danger-bg: #fdeeee;
            --pk-display: 'Bricolage Grotesque', 'Segoe UI', system-ui, sans-serif;
            --pk-sans: 'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; }

        /* ---------- Latar: denah parkir dari atas ---------- */
        body.login-page {
            min-height: 100vh;
            margin: 0;
            padding: 32px 20px;
            display: grid;
            place-items: center;
            font-family: var(--pk-sans);
            color: var(--pk-text);
            -webkit-font-smoothing: antialiased;

            background-color: var(--pk-bg);
            background-image:
                radial-gradient(ellipse at center,
                    rgba(234, 240, 235, 0.92) 0%,
                    rgba(234, 240, 235, 0.55) 55%,
                    rgba(234, 240, 235, 0) 100%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='360' height='320' viewBox='0 0 360 320'%3E%3Cg stroke='%23c9d8ce' stroke-width='3' fill='none'%3E%3Cpath d='M0 0V110M60 0V110M120 0V110M180 0V110M240 0V110M300 0V110M360 0V110'/%3E%3Cpath d='M0 210V320M60 210V320M120 210V320M180 210V320M240 210V320M300 210V320M360 210V320'/%3E%3Cpath d='M0 160H360' stroke-dasharray='18 14'/%3E%3C/g%3E%3Cg fill='%23d3e1d8'%3E%3Crect x='12' y='22' width='36' height='72' rx='10'/%3E%3Crect x='132' y='30' width='36' height='72' rx='10'/%3E%3Crect x='192' y='18' width='36' height='72' rx='10'/%3E%3Crect x='312' y='26' width='36' height='72' rx='10'/%3E%3Crect x='72' y='226' width='36' height='72' rx='10'/%3E%3Crect x='252' y='234' width='36' height='72' rx='10'/%3E%3Crect x='312' y='222' width='36' height='72' rx='10'/%3E%3C/g%3E%3Cg fill='%23c3d4c9'%3E%3Crect x='17' y='40' width='26' height='14' rx='4'/%3E%3Crect x='137' y='48' width='26' height='14' rx='4'/%3E%3Crect x='197' y='36' width='26' height='14' rx='4'/%3E%3Crect x='317' y='44' width='26' height='14' rx='4'/%3E%3Crect x='77' y='244' width='26' height='14' rx='4'/%3E%3Crect x='257' y='252' width='26' height='14' rx='4'/%3E%3Crect x='317' y='240' width='26' height='14' rx='4'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 100% 100%, 360px 320px;
            background-repeat: no-repeat, repeat;
        }

        .pk-layout {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.95fr);
            gap: 28px;
            align-items: stretch;
        }

        /* ---------- Kartu formulir ---------- */
        .pk-card {
            min-width: 0;
            padding: 44px 44px 36px;
            border: 1px solid var(--pk-line);
            border-radius: 22px;
            background: var(--pk-paper);
            box-shadow: 0 14px 36px rgba(12, 59, 46, 0.08);
            animation: pk-masuk 0.6s ease both;
        }

        @keyframes pk-masuk {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }

        .pk-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
        }

        .pk-brand-badge {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: var(--pk-green);
            font-family: var(--pk-display);
            font-size: 22px;
            font-weight: 700;
            color: var(--pk-sign);
        }

        .pk-brand-name {
            font-family: var(--pk-display);
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--pk-green);
        }

        .pk-card h1 {
            margin: 0 0 8px;
            font-family: var(--pk-display);
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .pk-lead {
            margin: 0 0 28px;
            font-size: 15px;
            line-height: 1.6;
            color: var(--pk-muted);
        }

        .pk-alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #f3c7c7;
            border-radius: 10px;
            background: var(--pk-danger-bg);
            color: var(--pk-danger);
            font-size: 14px;
            line-height: 1.5;
        }

        .pk-field { margin-bottom: 18px; }

        .pk-field label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
        }

        .pk-input,
        .pk-select {
            width: 100%;
            height: 50px;
            padding: 0 16px;
            border: 1.5px solid var(--pk-line);
            border-radius: 12px;
            background-color: #f8faf8;
            font-family: inherit;
            font-size: 15px;
            color: var(--pk-text);
            transition: border-color 0.2s, background-color 0.2s, box-shadow 0.2s;
        }

        .pk-input::placeholder { color: #9aa9a0; }

        .pk-select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 44px;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2362736a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
        }

        .pk-input:hover,
        .pk-select:hover { border-color: #bccac1; }

        .pk-input:focus-visible,
        .pk-select:focus-visible {
            outline: none;
            border-color: var(--pk-green-2);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(20, 81, 63, 0.14);
        }

        .pk-password { position: relative; }
        .pk-password .pk-input { padding-right: 112px; }

        .pk-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            padding: 8px 10px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--pk-green-2);
            cursor: pointer;
        }

        .pk-toggle:hover { background: #e7efe9; }

        .pk-toggle:focus-visible {
            outline: 2px solid var(--pk-green-2);
            outline-offset: 1px;
        }

        .pk-submit {
            width: 100%;
            height: 52px;
            margin-top: 6px;
            border: 0;
            border-radius: 12px;
            background: var(--pk-sign);
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--pk-green);
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .pk-submit:hover { background: #f5c24a; }
        .pk-submit:active { transform: translateY(1px); }

        .pk-submit:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .pk-submit:focus-visible {
            outline: 3px solid var(--pk-green);
            outline-offset: 3px;
        }

        .pk-foot {
            margin: 24px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: var(--pk-muted);
        }

        /* ---------- Tiket ketersediaan parkir ---------- */
        .parking-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            color: #eaf3ed;
            filter: drop-shadow(0 14px 28px rgba(12, 59, 46, 0.22));
        }

        .pk-ticket-top,
        .pk-ticket-stub {
            position: relative;
            padding: 36px;
            background: var(--pk-green);
        }

        /* Takik tiket dibuat dengan mask (transparan), bukan lingkaran
           berwarna, sehingga cocok di atas latar apa pun. */
        .pk-ticket-top {
            flex: 1;
            border-radius: 22px 22px 0 0;
            -webkit-mask:
                radial-gradient(circle 13px at 0 100%, #0000 97%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 13px at 100% 100%, #0000 97%, #000) right / 51% 100% no-repeat;
            mask:
                radial-gradient(circle 13px at 0 100%, #0000 97%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 13px at 100% 100%, #0000 97%, #000) right / 51% 100% no-repeat;
        }

        .pk-ticket-stub {
            padding-top: 30px;
            border-radius: 0 0 22px 22px;
            background: var(--pk-green-2);
            -webkit-mask:
                radial-gradient(circle 13px at 0 0, #0000 97%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 13px at 100% 0, #0000 97%, #000) right / 51% 100% no-repeat;
            mask:
                radial-gradient(circle 13px at 0 0, #0000 97%, #000) left / 51% 100% no-repeat,
                radial-gradient(circle 13px at 100% 0, #0000 97%, #000) right / 51% 100% no-repeat;
        }

        /* Garis sobekan */
        .pk-ticket-stub::before {
            content: "";
            position: absolute;
            top: 0;
            left: 22px;
            right: 22px;
            border-top: 2px dashed rgba(234, 243, 237, 0.35);
        }

        .parking-info h2 {
            margin: 0 0 6px;
            font-family: var(--pk-display);
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        .parking-subtitle {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: rgba(234, 243, 237, 0.65);
        }

        .parking-status {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin: 22px 0 4px;
            padding: 7px 14px 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            flex-shrink: 0;
            border-radius: 50%;
            background: currentColor;
        }

        .status-available { background: rgba(134, 239, 172, 0.16); color: #a3efbf; }
        .status-warning   { background: rgba(240, 180, 41, 0.20);  color: #f7cf6a; }
        .status-full      { background: rgba(248, 113, 113, 0.20); color: #fda4a4; }
        .status-unknown   { background: rgba(255, 255, 255, 0.12); color: rgba(234, 243, 237, 0.8); }

        .parking-availability { margin-top: 10px; }

        .parking-availability > span {
            display: block;
            font-size: 14px;
            color: rgba(234, 243, 237, 0.65);
        }

        .parking-availability > strong {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 2px;
            font-family: var(--pk-display);
            font-size: clamp(60px, 7vw, 88px);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -0.04em;
            color: var(--pk-sign);
        }

        .parking-availability > strong small {
            font-family: var(--pk-sans);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0;
            color: rgba(234, 243, 237, 0.65);
        }

        .parking-progress {
            height: 8px;
            margin-top: 22px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
        }

        /* Kelas status juga memberi warna latar; di bilah progres
           latar itu harus tetap netral. */
        .parking-progress.status-available,
        .parking-progress.status-warning,
        .parking-progress.status-full,
        .parking-progress.status-unknown {
            background: rgba(255, 255, 255, 0.14);
        }

        .parking-progress div {
            height: 100%;
            border-radius: 999px;
            background: #a3efbf;
            transition: width 0.6s ease;
        }

        .parking-progress.status-warning div { background: #f0b429; }
        .parking-progress.status-full div    { background: #f87171; }

        .parking-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .parking-stat { min-width: 0; }

        .parking-stat span {
            display: block;
            font-size: 13px;
            color: rgba(234, 243, 237, 0.65);
        }

        .parking-stat strong {
            display: block;
            margin: 4px 0 0;
            font-family: var(--pk-display);
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -0.02em;
            overflow-wrap: anywhere;
        }

        .parking-stat small {
            font-size: 13px;
            color: rgba(234, 243, 237, 0.55);
        }

        .parking-note {
            margin: 20px 0 0;
            font-size: 13px;
            line-height: 1.7;
            color: rgba(234, 243, 237, 0.7);
        }

        /* Strip kode batang sebagai ornamen */
        .pk-barcode {
            height: 28px;
            margin-top: 22px;
            opacity: 0.35;
            border-radius: 2px;
            background: repeating-linear-gradient(
                90deg,
                #eaf3ed 0 2px,
                transparent 2px 5px,
                #eaf3ed 5px 6px,
                transparent 6px 11px,
                #eaf3ed 11px 14px,
                transparent 14px 17px
            );
        }

        /* ---------- Responsif ---------- */
        @media (max-width: 820px) {
            body.login-page { align-items: start; padding: 20px 14px 32px; }

            .pk-layout {
                max-width: 480px;
                grid-template-columns: minmax(0, 1fr);
                gap: 20px;
            }

            .pk-card { padding: 30px 24px 28px; }
            .pk-ticket-top, .pk-ticket-stub { padding: 28px 24px; }
            .pk-ticket-stub { padding-top: 26px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .pk-card { animation: none; }
            .pk-input, .pk-select, .pk-submit,
            .parking-progress div { transition: none; }
        }
    </style>
</head>
<body class="login-page">

    <div class="pk-layout">

        <main class="pk-card">
            <div class="pk-brand">
                <span class="pk-brand-badge" aria-hidden="true">P</span>
                <span class="pk-brand-name">ParkirKu</span>
            </div>

            <h1>Selamat datang</h1>
            <p class="pk-lead">
                Sistem Informasi Manajemen Parkir. Pilih peran Anda,
                lalu masuk dengan akun yang terdaftar.
            </p>

            <?php if ($error !== ''): ?>
                <div class="pk-alert" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="formLogin">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_login']) ?>">

                <div class="pk-field">
                    <label for="role">Peran</label>
                    <select name="role" id="role" class="pk-select" required>
                        <option value="">Pilih peran</option>
                        <option value="admin" <?= $roleDipilih === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $roleDipilih === 'petugas' ? 'selected' : '' ?>>Petugas Parkir</option>
                        <option value="owner" <?= $roleDipilih === 'owner' ? 'selected' : '' ?>>Owner</option>
                    </select>
                </div>

                <div class="pk-field">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="pk-input"
                        placeholder="Masukkan username"
                        value="<?= e($usernameInput) ?>"
                        maxlength="100"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="pk-field">
                    <label for="password">Password</label>
                    <div class="pk-password">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="pk-input"
                            placeholder="Masukkan password"
                            maxlength="200"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="pk-toggle"
                            id="togglePassword"
                            aria-controls="password"
                            aria-pressed="false"
                        >Tampilkan</button>
                    </div>
                </div>

                <button type="submit" class="pk-submit" id="tombolMasuk">
                    Masuk ke dashboard
                </button>
            </form>

            <p class="pk-foot">
                Lupa password atau akun tidak aktif? Hubungi administrator.
            </p>
        </main>

        <aside class="parking-info" aria-label="Ketersediaan parkir">
            <div class="pk-ticket-top">
                <h2>Ketersediaan parkir</h2>

                <p class="parking-subtitle">
                    Kapasitas dan slot parkir saat ini.
                </p>

                <div class="parking-status <?= e($statusClass) ?>">
                    <span class="status-dot"></span>
                    <?= e($labelStatus) ?>
                </div>

                <div class="parking-availability">
                    <span>Slot tersedia</span>

                    <strong>
                        <?= number_format($slotTersedia) ?>
                        <small>dari <?= number_format($kapasitasParkir) ?> slot</small>
                    </strong>

                    <div
                        class="parking-progress <?= e($statusClass) ?>"
                        role="progressbar"
                        aria-label="Persentase kapasitas parkir terisi"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="<?= (int) round($persentaseTerisi) ?>"
                    >
                        <div style="width: <?= e($lebarProgress) ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="pk-ticket-stub">
                <div class="parking-stats">
                    <div class="parking-stat">
                        <span>Total kapasitas</span>
                        <strong><?= number_format($kapasitasParkir) ?></strong>
                        <small>slot parkir</small>
                    </div>

                    <div class="parking-stat">
                        <span>Sedang terisi</span>
                        <strong><?= number_format($kendaraanParkir) ?></strong>
                        <small>kendaraan</small>
                    </div>
                </div>

                <p class="parking-note">
                    <?php if ($kodeStatus === 'unknown'): ?>
                        Data kapasitas parkir belum tersedia. Periksa data area parkir.
                    <?php elseif ($kodeStatus === 'full'): ?>
                        Parkir penuh. Seluruh slot parkir sedang terisi.
                    <?php elseif ($kodeStatus === 'warning'): ?>
                        Slot parkir hampir habis. Silakan periksa ketersediaan sebelum masuk.
                    <?php else: ?>
                        Masih tersedia slot parkir untuk kendaraan yang akan masuk.
                    <?php endif; ?>
                </p>

                <div class="pk-barcode" aria-hidden="true"></div>
            </div>
        </aside>

    </div>

    <script>
        (function () {
            // Tampilkan / sembunyikan password
            var tombol = document.getElementById('togglePassword');
            var input = document.getElementById('password');

            if (tombol && input) {
                tombol.addEventListener('click', function () {
                    var tampil = input.type === 'password';
                    input.type = tampil ? 'text' : 'password';
                    tombol.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
                    tombol.setAttribute('aria-pressed', tampil ? 'true' : 'false');
                });
            }

            // Cegah kirim ganda
            var form = document.getElementById('formLogin');
            var submit = document.getElementById('tombolMasuk');

            if (form && submit) {
                form.addEventListener('submit', function () {
                    submit.disabled = true;
                    submit.textContent = 'Memproses...';
                });
            }
        })();
    </script>

</body>
</html>