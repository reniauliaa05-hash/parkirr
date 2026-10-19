<?php
require_once __DIR__ . '/../koneksi.php';
wajibRole(['petugas']);

$judul = 'Dashboard Petugas';

function formatRupiahPetugas($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/* Tanggal berbahasa Indonesia, tidak bergantung pada locale server */
function tanggalIndonesia(): string
{
    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $now = new DateTime();

    return $hari[(int) $now->format('w')] . ', '
        . $now->format('j') . ' '
        . $bulan[(int) $now->format('n')] . ' '
        . $now->format('Y');
}

/* Durasi parkir, mis. "2 j 15 m". Untuk kendaraan aktif dihitung sampai sekarang */
function durasiParkir(?string $masuk, ?string $keluar): string
{
    if (!$masuk) {
        return '-';
    }

    try {
        $awal  = new DateTime($masuk);
        $akhir = $keluar ? new DateTime($keluar) : new DateTime();
        $menit = max(0, (int) floor(($akhir->getTimestamp() - $awal->getTimestamp()) / 60));
    } catch (Throwable $e) {
        return '-';
    }

    if ($menit < 60) {
        return $menit . ' m';
    }

    $jam = intdiv($menit, 60);
    $sisa = $menit % 60;

    if ($jam >= 24) {
        return intdiv($jam, 24) . ' h ' . ($jam % 24) . ' j';
    }

    return $jam . ' j ' . $sisa . ' m';
}

try {
    $totalKendaraan = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_kendaraan'
    )->fetchColumn();

    $parkirAktif = (int) $pdo->query(
        "SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'"
    )->fetchColumn();

    $transaksiMasukHariIni = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM tb_transaksi
         WHERE DATE(waktu_masuk) = CURDATE()"
    )->fetchColumn();

    $transaksiKeluarHariIni = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM tb_transaksi
         WHERE status = 'keluar'
           AND DATE(waktu_keluar) = CURDATE()"
    )->fetchColumn();

    $pendapatanHariIni = (float) $pdo->query(
        "SELECT COALESCE(SUM(biaya_total), 0)
         FROM tb_transaksi
         WHERE status = 'keluar'
           AND DATE(waktu_keluar) = CURDATE()"
    )->fetchColumn();

    $terbaru = $pdo->query(
        "SELECT
            t.id_parkir,
            k.plat_nomor,
            k.jenis_kendaraan,
            t.waktu_masuk,
            t.waktu_keluar,
            t.status,
            t.biaya_total
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         ORDER BY t.id_parkir DESC
         LIMIT 8"
    )->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard Petugas: ' . $e->getMessage());
    http_response_code(500);
    exit('Data dashboard gagal dimuat. Periksa struktur tabel database.');
}

/* Kapasitas bersifat opsional: jika kolom tidak ada, dashboard tetap tampil */
$kapasitas = 0;
try {
    $kapasitas = (int) $pdo->query(
        'SELECT COALESCE(SUM(kapasitas), 0) FROM tb_area_parkir'
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('Dashboard Petugas (kapasitas): ' . $e->getMessage());
}

$slotTersedia = max(0, $kapasitas - $parkirAktif);
$persenTerisi = $kapasitas > 0 ? min(100, ($parkirAktif / $kapasitas) * 100) : 0;
$lebarBar     = number_format($persenTerisi, 1, '.', '');

if ($kapasitas <= 0) {
    $kelasKapasitas = 'pd-cap-unknown';
} elseif ($slotTersedia === 0) {
    $kelasKapasitas = 'pd-cap-full';
} elseif ($slotTersedia <= $kapasitas * 0.2) {
    $kelasKapasitas = 'pd-cap-warn';
} else {
    $kelasKapasitas = 'pd-cap-ok';
}

$namaPetugas = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Petugas Parkir';

$jam = (int) date('G');
if ($jam < 11) {
    $sapaan = 'Selamat pagi';
} elseif ($jam < 15) {
    $sapaan = 'Selamat siang';
} elseif ($jam < 18) {
    $sapaan = 'Selamat sore';
} else {
    $sapaan = 'Selamat malam';
}

require __DIR__ . '/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* Semua kelas berawalan "pd-" agar tidak bentrok dengan style.css */
.pd {
    --pd-green: #0c3b2e;
    --pd-green-2: #14513f;
    --pd-sign: #f0b429;
    --pd-paper: #ffffff;
    --pd-text: #16241e;
    --pd-muted: #62736a;
    --pd-line: #dde6e0;
    --pd-soft: #f3f7f4;
    --pd-display: 'Bricolage Grotesque', 'Segoe UI', system-ui, sans-serif;
    --pd-sans: 'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;

    font-family: var(--pd-sans);
    color: var(--pd-text);
    -webkit-font-smoothing: antialiased;
}

.pd *, .pd *::before, .pd *::after { box-sizing: border-box; }

/* ---------- Banner utama ---------- */
.pd-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 32px;
    align-items: center;
    margin-bottom: 20px;
    padding: 34px 36px;
    overflow: hidden;
    border-radius: 22px;
    background: var(--pd-green);
    color: #eaf3ed;
    animation: pd-masuk 0.5s ease both;
}

/* Garis marka parkir sebagai ornamen */
.pd-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.07;
    pointer-events: none;
    background-image: repeating-linear-gradient(
        100deg,
        #fff 0 3px,
        transparent 3px 64px
    );
}

.pd-hero > * { position: relative; }

@keyframes pd-masuk {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: none; }
}

.pd-date {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--pd-sign);
}

.pd-hero h1 {
    margin: 0 0 10px;
    font-family: var(--pd-display);
    font-size: clamp(26px, 3.4vw, 36px);
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.02em;
    color: #fff;
}

.pd-hero-lead {
    margin: 0;
    max-width: 420px;
    font-size: 15px;
    line-height: 1.65;
    color: rgba(234, 243, 237, 0.72);
}

.pd-cta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 26px;
}

.pd-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 13px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s, transform 0.1s, border-color 0.2s;
}

.pd-btn svg { flex-shrink: 0; }
.pd-btn:active { transform: translateY(1px); }

.pd-btn-primary {
    background: var(--pd-sign);
    color: var(--pd-green);
}
.pd-btn-primary:hover { background: #f5c24a; }

.pd-btn-ghost {
    border: 1.5px solid rgba(234, 243, 237, 0.35);
    color: #eaf3ed;
}
.pd-btn-ghost:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(234, 243, 237, 0.6);
}

.pd-btn:focus-visible {
    outline: 3px solid #fff;
    outline-offset: 3px;
}

/* Panel kapasitas di dalam banner */
.pd-cap {
    padding: 26px 28px;
    border: 1px solid rgba(234, 243, 237, 0.14);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.06);
}

.pd-cap-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
    color: rgba(234, 243, 237, 0.7);
}

.pd-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.pd-pill::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.pd-cap-ok .pd-pill      { background: rgba(134, 239, 172, 0.16); color: #a3efbf; }
.pd-cap-warn .pd-pill    { background: rgba(240, 180, 41, 0.20);  color: #f7cf6a; }
.pd-cap-full .pd-pill    { background: rgba(248, 113, 113, 0.20); color: #fda4a4; }
.pd-cap-unknown .pd-pill { background: rgba(255, 255, 255, 0.12); color: rgba(234, 243, 237, 0.8); }

.pd-cap-number {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
    font-family: var(--pd-display);
    font-size: clamp(52px, 6vw, 72px);
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.04em;
    color: var(--pd-sign);
}

.pd-cap-number small {
    font-family: var(--pd-sans);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0;
    color: rgba(234, 243, 237, 0.65);
}

.pd-cap-sub {
    margin: 6px 0 0;
    font-size: 13px;
    color: rgba(234, 243, 237, 0.65);
}

.pd-bar {
    height: 8px;
    margin-top: 18px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
}

.pd-bar div {
    height: 100%;
    border-radius: 999px;
    background: #a3efbf;
    transition: width 0.6s ease;
}

.pd-cap-warn .pd-bar div { background: #f0b429; }
.pd-cap-full .pd-bar div { background: #f87171; }

/* ---------- Kartu statistik ---------- */
.pd-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.pd-stat {
    min-width: 0;
    padding: 20px 20px 18px;
    border: 1px solid var(--pd-line);
    border-radius: 16px;
    background: var(--pd-paper);
    animation: pd-masuk 0.5s ease both;
}

.pd-stat:nth-child(2) { animation-delay: 0.05s; }
.pd-stat:nth-child(3) { animation-delay: 0.1s; }
.pd-stat:nth-child(4) { animation-delay: 0.15s; }

.pd-stat-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pd-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--pd-soft);
    color: var(--pd-green-2);
}

.pd-icon-gold {
    background: #fdf3d8;
    color: #946200;
}

.pd-stat-label {
    font-size: 13px;
    font-weight: 600;
    line-height: 1.35;
    color: var(--pd-muted);
}

.pd-stat-value {
    margin-top: 16px;
    font-family: var(--pd-display);
    font-size: 30px;
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: -0.02em;
    overflow-wrap: anywhere;
    color: var(--pd-text);
}

.pd-stat-value.pd-money { font-size: 24px; }

.pd-stat-note {
    margin-top: 7px;
    font-size: 12px;
    line-height: 1.5;
    color: #8a9b92;
}

/* ---------- Panel tabel ---------- */
.pd-panel {
    padding: 26px;
    border: 1px solid var(--pd-line);
    border-radius: 18px;
    background: var(--pd-paper);
}

.pd-panel-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.pd-panel-head h2 {
    margin: 0;
    font-family: var(--pd-display);
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.pd-panel-head p {
    margin: 5px 0 0;
    font-size: 13px;
    color: var(--pd-muted);
}

.pd-panel-count {
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--pd-soft);
    font-size: 12px;
    font-weight: 700;
    color: var(--pd-green-2);
}

.pd-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid var(--pd-line);
    border-radius: 12px;
}

.pd-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.pd-table th,
.pd-table td {
    padding: 14px 16px;
    text-align: left;
    font-size: 13.5px;
}

.pd-table th {
    background: var(--pd-soft);
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pd-muted);
}

.pd-table td { border-top: 1px solid #edf2ee; }
.pd-table tbody tr:hover td { background: #fafcfb; }

.pd-num { color: #8a9b92; font-variant-numeric: tabular-nums; }
.pd-right { text-align: right !important; }

/* Plat nomor bergaya pelat kendaraan */
.pd-plat {
    display: inline-block;
    padding: 4px 11px;
    border: 2px solid var(--pd-text);
    border-radius: 7px;
    background: #fffdf4;
    font-family: 'Courier New', ui-monospace, monospace;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--pd-text);
}

.pd-jenis { color: var(--pd-muted); }

.pd-time { font-variant-numeric: tabular-nums; }
.pd-time small {
    display: block;
    margin-top: 2px;
    font-size: 11.5px;
    color: #8a9b92;
}

.pd-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.pd-badge::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.pd-badge-masuk  { background: #fdf3d8; color: #946200; }
.pd-badge-keluar { background: #def5e6; color: #1c6b3a; }

.pd-biaya {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pd-biaya-kosong { color: #aab7b0; font-weight: 500; }

.pd-empty {
    padding: 44px 20px !important;
    text-align: center !important;
    color: var(--pd-muted);
}

.pd-empty strong {
    display: block;
    margin-bottom: 4px;
    font-size: 15px;
    color: var(--pd-text);
}

/* ---------- Responsif ---------- */
@media (max-width: 1000px) {
    .pd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 860px) {
    .pd-hero {
        grid-template-columns: minmax(0, 1fr);
        gap: 24px;
        padding: 28px 24px;
    }
}

@media (max-width: 520px) {
    .pd-stats { grid-template-columns: minmax(0, 1fr); }
    .pd-panel { padding: 18px 14px; }
    .pd-btn { flex: 1 1 100%; justify-content: center; }
    .pd-cap { padding: 22px 20px; }
}

@media (prefers-reduced-motion: reduce) {
    .pd-hero, .pd-stat { animation: none; }
    .pd-bar div, .pd-btn { transition: none; }
}
</style>

<div class="pd">

    <section class="pd-hero">
        <div>
            <p class="pd-date"><?= e(tanggalIndonesia()) ?></p>
            <h1><?= e($sapaan) ?>, <?= e($namaPetugas) ?></h1>
            <p class="pd-hero-lead">
                Pantau kendaraan di area parkir dan catat transaksi
                masuk maupun keluar dari satu tempat.
            </p>

            <div class="pd-cta">
                <a class="pd-btn pd-btn-primary" href="transaksi_masuk.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    Kendaraan masuk
                </a>
                <a class="pd-btn pd-btn-ghost" href="transaksi_keluar.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                    Kendaraan keluar
                </a>
            </div>
        </div>

        <div class="pd-cap <?= e($kelasKapasitas) ?>">
            <div class="pd-cap-head">
                <span>Sedang parkir</span>
                <span class="pd-pill">
                    <?php if ($kapasitas <= 0): ?>
                        Kapasitas belum diatur
                    <?php elseif ($slotTersedia === 0): ?>
                        Penuh
                    <?php elseif ($kelasKapasitas === 'pd-cap-warn'): ?>
                        Hampir penuh
                    <?php else: ?>
                        Tersedia
                    <?php endif; ?>
                </span>
            </div>

            <div class="pd-cap-number">
                <?= number_format($parkirAktif, 0, ',', '.') ?>
                <small>kendaraan</small>
            </div>

            <?php if ($kapasitas > 0): ?>
                <p class="pd-cap-sub">
                    <?= number_format($slotTersedia, 0, ',', '.') ?> slot kosong
                    dari <?= number_format($kapasitas, 0, ',', '.') ?> slot
                </p>

                <div
                    class="pd-bar"
                    role="progressbar"
                    aria-label="Persentase kapasitas parkir terisi"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="<?= (int) round($persenTerisi) ?>"
                >
                    <div style="width: <?= e($lebarBar) ?>%"></div>
                </div>
            <?php else: ?>
                <p class="pd-cap-sub">Kendaraan masih berada di area parkir.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="pd-stats" aria-label="Ringkasan hari ini">

        <div class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
                <span class="pd-stat-label">Kendaraan masuk hari ini</span>
            </div>
            <div class="pd-stat-value"><?= number_format($transaksiMasukHariIni, 0, ',', '.') ?></div>
            <div class="pd-stat-note">Total pencatatan kendaraan masuk</div>
        </div>

        <div class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                </span>
                <span class="pd-stat-label">Kendaraan keluar hari ini</span>
            </div>
            <div class="pd-stat-value"><?= number_format($transaksiKeluarHariIni, 0, ',', '.') ?></div>
            <div class="pd-stat-note">Transaksi selesai hari ini</div>
        </div>

        <div class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17l1.5-6h9L18 17M7.5 11l1-3.5h7l1 3.5"/><circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/></svg>
                </span>
                <span class="pd-stat-label">Kendaraan terdaftar</span>
            </div>
            <div class="pd-stat-value"><?= number_format($totalKendaraan, 0, ',', '.') ?></div>
            <div class="pd-stat-note">Data kendaraan dalam sistem</div>
        </div>

        <div class="pd-stat">
            <div class="pd-stat-top">
                <span class="pd-icon pd-icon-gold" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/></svg>
                </span>
                <span class="pd-stat-label">Pendapatan hari ini</span>
            </div>
            <div class="pd-stat-value pd-money"><?= e(formatRupiahPetugas($pendapatanHariIni)) ?></div>
            <div class="pd-stat-note">Dari transaksi keluar hari ini</div>
        </div>

    </section>

    <section class="pd-panel">
        <div class="pd-panel-head">
            <div>
                <h2>Transaksi terbaru</h2>
                <p>Riwayat kendaraan masuk dan keluar.</p>
            </div>
            <?php if (!empty($terbaru)): ?>
                <span class="pd-panel-count"><?= count($terbaru) ?> transaksi terakhir</span>
            <?php endif; ?>
        </div>

        <div class="pd-table-wrap">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat nomor</th>
                        <th>Jenis</th>
                        <th>Waktu masuk</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th class="pd-right">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($terbaru)): ?>
                    <tr>
                        <td colspan="7" class="pd-empty">
                            <strong>Belum ada transaksi parkir</strong>
                            Transaksi akan muncul di sini setelah kendaraan pertama dicatat.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($terbaru as $i => $t): ?>
                        <?php
                        $sudahKeluar = $t['status'] === 'keluar';
                        $tsMasuk = strtotime((string) $t['waktu_masuk']);
                        ?>
                        <tr>
                            <td class="pd-num"><?= $i + 1 ?></td>
                            <td><span class="pd-plat"><?= e($t['plat_nomor']) ?></span></td>
                            <td class="pd-jenis"><?= e($t['jenis_kendaraan']) ?></td>
                            <td class="pd-time">
                                <?= $tsMasuk ? e(date('H:i', $tsMasuk)) : '-' ?>
                                <small><?= $tsMasuk ? e(date('d/m/Y', $tsMasuk)) : '' ?></small>
                            </td>
                            <td class="pd-time">
                                <?= e(durasiParkir(
                                    $t['waktu_masuk'] ?? null,
                                    $sudahKeluar ? ($t['waktu_keluar'] ?? null) : null
                                )) ?>
                            </td>
                            <td>
                                <span class="pd-badge <?= $sudahKeluar ? 'pd-badge-keluar' : 'pd-badge-masuk' ?>">
                                    <?= $sudahKeluar ? 'Selesai' : 'Parkir' ?>
                                </span>
                            </td>
                            <td class="pd-right">
                                <?php if ($sudahKeluar): ?>
                                    <span class="pd-biaya"><?= e(formatRupiahPetugas($t['biaya_total'])) ?></span>
                                <?php else: ?>
                                    <span class="pd-biaya pd-biaya-kosong">Belum dihitung</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require __DIR__ . '/footer.php'; ?>