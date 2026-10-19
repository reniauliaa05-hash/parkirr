<?php
require_once __DIR__ . '/../koneksi.php';
wajibRole(['admin']);

$judul = 'Dashboard Admin';

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

// Format singkat untuk label sumbu grafik, misalnya "250 rb" atau "1,5 jt".
function dashRupiahRingkas(float $nilai): string
{
    if ($nilai >= 1000000000) {
        $n = $nilai / 1000000000;
        $satuan = ' mi';
    } elseif ($nilai >= 1000000) {
        $n = $nilai / 1000000;
        $satuan = ' jt';
    } elseif ($nilai >= 1000) {
        $n = $nilai / 1000;
        $satuan = ' rb';
    } else {
        return number_format($nilai, 0, ',', '.');
    }

    $teks = number_format($n, 2, ',', '.');
    $teks = rtrim(rtrim($teks, '0'), ',');

    return $teks . $satuan;
}

// Batas atas sumbu Y yang "rapi" agar garis bantu mudah dibaca.
function dashSkalaAtas(float $nilai): float
{
    if ($nilai <= 0) {
        return 1.0;
    }

    $pangkat = pow(10, floor(log10($nilai)));
    $relatif = $nilai / $pangkat;

    foreach ([1, 2, 4, 5, 8, 10] as $langkah) {
        if ($relatif <= $langkah) {
            return (float) ($langkah * $pangkat);
        }
    }

    return (float) (10 * $pangkat);
}

// Angka desimal dengan titik, aman untuk atribut SVG di semua locale.
function dashAngka(float $nilai, int $desimal = 2): string
{
    return number_format($nilai, $desimal, '.', '');
}

$pendapatanPerTanggal = [];
$jenisKendaraan = [];

try {
    // Ringkasan dashboard
    $totalKendaraan = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_kendaraan'
    )->fetchColumn();

    $parkirAktif = (int) $pdo->query(
        "SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'"
    )->fetchColumn();

    $totalArea = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_area_parkir'
    )->fetchColumn();

    $pendapatan = (float) $pdo->query(
        "SELECT COALESCE(SUM(biaya_total), 0)
         FROM tb_transaksi
         WHERE status = 'keluar'"
    )->fetchColumn();

    $totalPengguna = (int) $pdo->query(
        'SELECT COUNT(*) FROM tb_user'
    )->fetchColumn();

    // Lima transaksi terbaru
    $terbaru = $pdo->query(
        "SELECT t.id_parkir, k.plat_nomor, k.jenis_kendaraan,
                t.waktu_masuk, t.waktu_keluar,
                t.status, t.biaya_total
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
             ON k.id_kendaraan = t.id_kendaraan
         ORDER BY t.id_parkir DESC
         LIMIT 5"
    )->fetchAll();

    // Pendapatan 7 hari terakhir (hari ini termasuk)
    $stmt = $pdo->prepare(
        "SELECT DATE(waktu_keluar) AS tgl,
                COALESCE(SUM(biaya_total), 0) AS total
         FROM tb_transaksi
         WHERE status = 'keluar'
           AND waktu_keluar IS NOT NULL
           AND waktu_keluar >= ?
         GROUP BY DATE(waktu_keluar)"
    );
    $stmt->execute([date('Y-m-d', strtotime('-6 days')) . ' 00:00:00']);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $baris) {
        $pendapatanPerTanggal[(string) $baris['tgl']] = (float) $baris['total'];
    }

    // Komposisi kendaraan terdaftar menurut jenis
    $jenisKendaraan = $pdo->query(
        "SELECT jenis_kendaraan, COUNT(*) AS jumlah
         FROM tb_kendaraan
         GROUP BY jenis_kendaraan
         ORDER BY jumlah DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard Admin: ' . $e->getMessage());
    http_response_code(500);
    exit('Data dashboard gagal dimuat. Periksa struktur tabel database.');
}

// Kapasitas dipisah agar dashboard tetap tampil bila kolomnya belum ada.
$kapasitasTotal = 0;

try {
    $kapasitasTotal = (int) $pdo->query(
        'SELECT COALESCE(SUM(kapasitas), 0) FROM tb_area_parkir'
    )->fetchColumn();
} catch (PDOException $e) {
    error_log('Dashboard Admin (kapasitas): ' . $e->getMessage());
}

$persenOkupansi = $kapasitasTotal > 0
    ? min(100, ($parkirAktif / $kapasitasTotal) * 100)
    : null;

$kelasOkupansi = '';

if ($persenOkupansi !== null) {
    if ($persenOkupansi >= 90) {
        $kelasOkupansi = 'is-penuh';
    } elseif ($persenOkupansi >= 70) {
        $kelasOkupansi = 'is-ramai';
    }
}

$namaAdmin = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Administrator';

/*
|--------------------------------------------------------------------------
| Data tampilan: tanggal dan grafik
|--------------------------------------------------------------------------
*/
$namaHari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$namaHariPanjang = [
    'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
];
$namaBulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$tanggalHariIni = $namaHariPanjang[(int) date('w')] . ', '
    . date('j') . ' ' . $namaBulan[(int) date('n')] . ' ' . date('Y');

// Grafik batang: pendapatan 7 hari terakhir
$seriPendapatan = [];

for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days");
    $tgl = date('Y-m-d', $ts);

    $seriPendapatan[] = [
        'label' => $namaHari[(int) date('w', $ts)],
        'tanggal' => date('j', $ts) . ' ' . $namaBulan[(int) date('n', $ts)],
        'nilai' => $pendapatanPerTanggal[$tgl] ?? 0.0,
    ];
}

$nilaiTertinggi = max(array_column($seriPendapatan, 'nilai'));
$adaPendapatan = $nilaiTertinggi > 0;
$skalaMaks = dashSkalaAtas((float) $nilaiTertinggi);
$totalTujuhHari = array_sum(array_column($seriPendapatan, 'nilai'));

$svgLebar = 640;
$svgTinggi = 280;
$margKiri = 58;
$margKanan = 12;
$margAtas = 24;
$margBawah = 36;
$areaLebar = $svgLebar - $margKiri - $margKanan;
$areaTinggi = $svgTinggi - $margAtas - $margBawah;
$garisDasar = $margAtas + $areaTinggi;
$lebarSlot = $areaLebar / 7;
$lebarBar = $lebarSlot * 0.5;

// Grafik donat: komposisi jenis kendaraan (maksimal 5 bagian)
$warnaDonut = ['#0c3b2e', '#f0b429', '#2f8f6b', '#8fb9a5', '#c8d5ce'];
$segmenJenis = [];
$totalJenis = 0;

foreach ($jenisKendaraan as $baris) {
    $totalJenis += (int) $baris['jumlah'];
}

if ($totalJenis > 0) {
    $lainnya = 0;

    foreach ($jenisKendaraan as $idx => $baris) {
        $nama = trim((string) $baris['jenis_kendaraan']);
        $nama = $nama === '' ? 'Tidak diketahui' : $nama;
        $jumlah = (int) $baris['jumlah'];

        if ($idx < 4) {
            $segmenJenis[] = ['nama' => $nama, 'jumlah' => $jumlah];
        } else {
            $lainnya += $jumlah;
        }
    }

    if ($lainnya > 0) {
        $segmenJenis[] = ['nama' => 'Lainnya', 'jumlah' => $lainnya];
    }

    foreach ($segmenJenis as $idx => $sg) {
        $segmenJenis[$idx]['persen'] = ($sg['jumlah'] / $totalJenis) * 100;
        $segmenJenis[$idx]['warna'] = $warnaDonut[$idx];
    }
}

require __DIR__ . '/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&display=swap');

.admin-dashboard {
    --ad-green: #0c3b2e;
    --ad-green-2: #14513f;
    --ad-sign: #f0b429;
    --ad-text: #16241e;
    --ad-muted: #62736a;
    --ad-line: #d9e2dc;
    --ad-soft: #f3f7f4;
    --ad-display: 'Bricolage Grotesque', 'Segoe UI', system-ui, sans-serif;
    --ad-sans: 'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;

    font-family: var(--ad-sans);
    color: var(--ad-text);
    -webkit-font-smoothing: antialiased;
}

.admin-dashboard *,
.admin-dashboard *::before,
.admin-dashboard *::after {
    box-sizing: border-box;
}

/* ---------- Sambutan ---------- */
.admin-welcome {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}

.admin-welcome h1 {
    margin: 0 0 8px;
    font-family: var(--ad-display);
    font-size: 32px;
    font-weight: 600;
    letter-spacing: -0.02em;
    color: var(--ad-green);
}

.admin-welcome p {
    margin: 0;
    font-size: 15px;
    line-height: 1.6;
    color: var(--ad-muted);
}

.admin-date {
    padding: 9px 16px;
    border: 1px solid var(--ad-line);
    border-radius: 999px;
    background: #fff;
    font-size: 13px;
    font-weight: 600;
    color: var(--ad-green-2);
}

/* ---------- Kartu ringkasan ---------- */
.admin-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.admin-stat {
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--ad-line);
    border-radius: 18px;
    background: #fff;
}

.admin-stat-label {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 500;
    color: var(--ad-muted);
}

.admin-stat-value {
    font-family: var(--ad-display);
    font-size: 34px;
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: -0.02em;
    color: var(--ad-green);
    overflow-wrap: anywhere;
}

.admin-stat-note {
    margin-top: 10px;
    font-size: 13px;
    color: var(--ad-muted);
}

/* Kartu utama: pendapatan */
.admin-stat-hero {
    border-color: var(--ad-green);
    background: var(--ad-green);
}

.admin-stat-hero .admin-stat-label,
.admin-stat-hero .admin-stat-note {
    color: rgba(234, 243, 237, 0.7);
}

.admin-stat-hero .admin-stat-value {
    font-size: 30px;
    color: var(--ad-sign);
}

/* Indikator okupansi */
.admin-meter {
    height: 6px;
    margin-top: 14px;
    overflow: hidden;
    border-radius: 999px;
    background: #e3ebe6;
}

.admin-meter div {
    height: 100%;
    border-radius: 999px;
    background: var(--ad-green-2);
}

.admin-meter.is-ramai div { background: var(--ad-sign); }
.admin-meter.is-penuh div { background: #d9534f; }

/* ---------- Panel ---------- */
.admin-panel {
    min-width: 0;
    margin-bottom: 24px;
    padding: 24px;
    border: 1px solid var(--ad-line);
    border-radius: 18px;
    background: #fff;
}

.admin-panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.admin-panel-header h2 {
    margin: 0;
    font-family: var(--ad-display);
    font-size: 20px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--ad-green);
}

.admin-panel-header p {
    margin: 6px 0 0;
    font-size: 14px;
    color: var(--ad-muted);
}

.admin-chip {
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--ad-soft);
    font-size: 13px;
    font-weight: 600;
    color: var(--ad-green-2);
}

/* ---------- Grafik ---------- */
.admin-charts {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.admin-charts .admin-panel { margin-bottom: 0; }

.admin-chart-scroll { overflow-x: auto; }

.admin-chart {
    display: block;
    width: 100%;
    min-width: 520px;
    height: auto;
}

.admin-chart text {
    font-family: var(--ad-sans);
    fill: var(--ad-muted);
    font-size: 12px;
}

.admin-chart .grid-line { stroke: #e6ede8; stroke-width: 1; }
.admin-chart .base-line { stroke: #c5d2ca; stroke-width: 1; }
.admin-chart .bar { fill: #14513f; }
.admin-chart .bar.is-today { fill: #f0b429; }
.admin-chart .bar-value { fill: var(--ad-text); font-size: 11px; font-weight: 600; }
.admin-chart .label-today { fill: var(--ad-text); font-weight: 700; }

.admin-donut-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.admin-donut {
    display: block;
    width: 100%;
    max-width: 200px;
    height: auto;
}

.admin-donut .track { fill: none; stroke: #e6ede8; stroke-width: 26; }
.admin-donut .segment { fill: none; stroke-width: 26; }

.admin-donut .donut-angka {
    font-family: var(--ad-display);
    font-size: 30px;
    font-weight: 600;
    fill: var(--ad-green);
}

.admin-donut .donut-label {
    font-family: var(--ad-sans);
    font-size: 11px;
    fill: var(--ad-muted);
}

.admin-legend {
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}

.admin-legend li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid #edf2ee;
    font-size: 14px;
}

.admin-legend li:last-child { border-bottom: 0; }

.admin-legend-dot {
    width: 10px;
    height: 10px;
    flex-shrink: 0;
    border-radius: 3px;
}

.admin-legend-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.admin-legend-count { font-weight: 700; }

.admin-legend-persen {
    min-width: 46px;
    text-align: right;
    color: var(--ad-muted);
}

.admin-chart-empty {
    padding: 48px 16px;
    text-align: center;
    font-size: 14px;
    line-height: 1.6;
    color: var(--ad-muted);
}

/* ---------- Tabel ---------- */
.admin-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.admin-table th {
    background: var(--ad-soft);
    font-size: 13px;
    font-weight: 600;
    color: var(--ad-muted);
}

.admin-table th:first-child { border-radius: 10px 0 0 10px; }
.admin-table th:last-child  { border-radius: 0 10px 10px 0; }

.admin-table th,
.admin-table td {
    padding: 14px;
    border-bottom: 1px solid #edf2ee;
    text-align: left;
    font-size: 14px;
}

.admin-table tbody tr:last-child td { border-bottom: 0; }
.admin-table tbody tr:hover { background: #f8fbf9; }

.admin-plate {
    display: inline-block;
    padding: 3px 9px;
    border: 1.5px solid var(--ad-green);
    border-radius: 6px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--ad-green);
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.admin-badge::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.admin-badge-active { background: #fdf1cf; color: #8a5a00; }
.admin-badge-done   { background: #dcf3e6; color: #14653f; }

.admin-empty {
    padding: 28px !important;
    text-align: center !important;
    color: var(--ad-muted);
}

/* ---------- Menu cepat ---------- */
.admin-shortcuts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.admin-shortcut {
    display: block;
    padding: 20px;
    border: 1px solid var(--ad-line);
    border-radius: 14px;
    text-decoration: none;
    color: var(--ad-text);
    transition: background 0.2s, border-color 0.2s;
}

.admin-shortcut:hover {
    border-color: var(--ad-green-2);
    background: var(--ad-soft);
}

.admin-shortcut:focus-visible {
    outline: 3px solid var(--ad-sign);
    outline-offset: 2px;
}

.admin-shortcut strong {
    display: block;
    margin-bottom: 6px;
    font-family: var(--ad-display);
    font-size: 16px;
    font-weight: 600;
    color: var(--ad-green);
}

.admin-shortcut span {
    font-size: 13px;
    line-height: 1.6;
    color: var(--ad-muted);
}

/* ---------- Responsif ---------- */
@media (max-width: 1100px) {
    .admin-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-charts { grid-template-columns: minmax(0, 1fr); }
    .admin-charts .admin-panel { margin-bottom: 0; }
}

@media (max-width: 900px) {
    .admin-shortcuts { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 520px) {
    .admin-cards { grid-template-columns: minmax(0, 1fr); }
    .admin-welcome h1 { font-size: 26px; }
    .admin-stat, .admin-panel { padding: 18px; }
}
</style>

<div class="admin-dashboard">

    <section class="admin-welcome">
        <div>
            <h1>Dashboard Admin</h1>
            <p>
                Selamat datang, <?= e($namaAdmin) ?>.
                Berikut ringkasan sistem parkir hari ini.
            </p>
        </div>

        <div class="admin-date"><?= e($tanggalHariIni) ?></div>
    </section>

    <section class="admin-cards">

        <div class="admin-stat admin-stat-hero">
            <div class="admin-stat-label">Total pendapatan</div>
            <div class="admin-stat-value">
                <?= rupiah($pendapatan) ?>
            </div>
            <div class="admin-stat-note">Dari transaksi yang sudah selesai</div>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Parkir aktif</div>
            <div class="admin-stat-value">
                <?= number_format($parkirAktif, 0, ',', '.') ?>
            </div>
            <?php if ($persenOkupansi !== null): ?>
                <div class="admin-meter <?= e($kelasOkupansi) ?>"
                     role="progressbar"
                     aria-label="Okupansi area parkir"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-valuenow="<?= (int) round($persenOkupansi) ?>">
                    <div style="width: <?= dashAngka($persenOkupansi, 1) ?>%"></div>
                </div>
                <div class="admin-stat-note">
                    <?= number_format($persenOkupansi, 0, ',', '.') ?>%
                    dari <?= number_format($kapasitasTotal, 0, ',', '.') ?> slot terisi
                </div>
            <?php else: ?>
                <div class="admin-stat-note">Masih berada di area parkir</div>
            <?php endif; ?>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Total kendaraan</div>
            <div class="admin-stat-value">
                <?= number_format($totalKendaraan, 0, ',', '.') ?>
            </div>
            <div class="admin-stat-note">Kendaraan terdaftar</div>
        </div>

        <div class="admin-stat">
            <div class="admin-stat-label">Area parkir</div>
            <div class="admin-stat-value">
                <?= number_format($totalArea, 0, ',', '.') ?>
            </div>
            <div class="admin-stat-note">Area yang terdaftar</div>
        </div>

    </section>

    <section class="admin-charts">

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2>Pendapatan 7 hari terakhir</h2>
                    <p>Dihitung dari transaksi yang selesai pada tiap tanggal.</p>
                </div>
                <div class="admin-chip">
                    Total <?= rupiah($totalTujuhHari) ?>
                </div>
            </div>

            <?php if (!$adaPendapatan): ?>
                <div class="admin-chart-empty">
                    Belum ada pendapatan dalam 7 hari terakhir.<br>
                    Grafik akan muncul setelah ada kendaraan yang keluar.
                </div>
            <?php else: ?>
                <div class="admin-chart-scroll">
                    <svg class="admin-chart"
                         viewBox="0 0 <?= $svgLebar ?> <?= $svgTinggi ?>"
                         role="img"
                         aria-label="Grafik batang pendapatan parkir 7 hari terakhir, total <?= e(rupiah($totalTujuhHari)) ?>">

                        <?php for ($k = 0; $k <= 4; $k++):
                            $yGaris = $garisDasar - ($areaTinggi * $k / 4);
                            $nilaiGaris = $skalaMaks * $k / 4;
                        ?>
                            <line class="<?= $k === 0 ? 'base-line' : 'grid-line' ?>"
                                  x1="<?= $margKiri ?>"
                                  x2="<?= $svgLebar - $margKanan ?>"
                                  y1="<?= dashAngka($yGaris) ?>"
                                  y2="<?= dashAngka($yGaris) ?>"/>
                            <text x="<?= $margKiri - 10 ?>"
                                  y="<?= dashAngka($yGaris + 4) ?>"
                                  text-anchor="end"><?= e(dashRupiahRingkas($nilaiGaris)) ?></text>
                        <?php endfor; ?>

                        <?php foreach ($seriPendapatan as $i => $s):
                            $hariIniBar = ($i === count($seriPendapatan) - 1);
                            $tinggiBar = $s['nilai'] > 0
                                ? max(3, ($s['nilai'] / $skalaMaks) * $areaTinggi)
                                : 0;
                            $x = $margKiri + ($lebarSlot * $i) + (($lebarSlot - $lebarBar) / 2);
                            $y = $garisDasar - $tinggiBar;
                            $r = min(7, $tinggiBar / 2);
                            $tengah = $x + ($lebarBar / 2);
                        ?>
                            <?php if ($tinggiBar > 0): ?>
                                <path class="bar<?= $hariIniBar ? ' is-today' : '' ?>"
                                      d="M<?= dashAngka($x) ?>,<?= dashAngka($garisDasar) ?>
                                         V<?= dashAngka($y + $r) ?>
                                         Q<?= dashAngka($x) ?>,<?= dashAngka($y) ?> <?= dashAngka($x + $r) ?>,<?= dashAngka($y) ?>
                                         H<?= dashAngka($x + $lebarBar - $r) ?>
                                         Q<?= dashAngka($x + $lebarBar) ?>,<?= dashAngka($y) ?> <?= dashAngka($x + $lebarBar) ?>,<?= dashAngka($y + $r) ?>
                                         V<?= dashAngka($garisDasar) ?>Z">
                                    <title><?= e($s['label'] . ', ' . $s['tanggal'] . ': ' . rupiah($s['nilai'])) ?></title>
                                </path>
                                <text class="bar-value"
                                      x="<?= dashAngka($tengah) ?>"
                                      y="<?= dashAngka($y - 7) ?>"
                                      text-anchor="middle"><?= e(dashRupiahRingkas($s['nilai'])) ?></text>
                            <?php endif; ?>

                            <text class="<?= $hariIniBar ? 'label-today' : '' ?>"
                                  x="<?= dashAngka($tengah) ?>"
                                  y="<?= $svgTinggi - 12 ?>"
                                  text-anchor="middle"><?= e($s['label']) ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2>Jenis kendaraan</h2>
                    <p>Komposisi kendaraan terdaftar.</p>
                </div>
            </div>

            <?php if ($totalJenis === 0): ?>
                <div class="admin-chart-empty">
                    Belum ada data kendaraan terdaftar.
                </div>
            <?php else: ?>
                <div class="admin-donut-wrap">
                    <svg class="admin-donut"
                         viewBox="0 0 200 200"
                         role="img"
                         aria-label="Grafik donat komposisi jenis kendaraan, total <?= $totalJenis ?> kendaraan">
                        <circle class="track" cx="100" cy="100" r="70"/>

                        <?php
                        $akum = 0.0;
                        $banyakSegmen = count($segmenJenis);

                        foreach ($segmenJenis as $sg):
                            $panjang = max(0, $sg['persen'] - ($banyakSegmen > 1 ? 0.8 : 0));
                        ?>
                            <circle class="segment"
                                    cx="100" cy="100" r="70"
                                    pathLength="100"
                                    stroke="<?= e($sg['warna']) ?>"
                                    stroke-dasharray="<?= dashAngka($panjang, 3) ?> <?= dashAngka(100 - $panjang, 3) ?>"
                                    stroke-dashoffset="<?= dashAngka(-$akum, 3) ?>"
                                    transform="rotate(-90 100 100)">
                                <title><?= e($sg['nama'] . ': ' . $sg['jumlah'] . ' kendaraan') ?></title>
                            </circle>
                        <?php
                            $akum += $sg['persen'];
                        endforeach;
                        ?>

                        <text class="donut-angka" x="100" y="102"
                              text-anchor="middle"><?= number_format($totalJenis, 0, ',', '.') ?></text>
                        <text class="donut-label" x="100" y="120"
                              text-anchor="middle">kendaraan</text>
                    </svg>

                    <ul class="admin-legend">
                        <?php foreach ($segmenJenis as $sg): ?>
                            <li>
                                <span class="admin-legend-dot"
                                      style="background: <?= e($sg['warna']) ?>"></span>
                                <span class="admin-legend-name"><?= e($sg['nama']) ?></span>
                                <span class="admin-legend-count"><?= number_format($sg['jumlah'], 0, ',', '.') ?></span>
                                <span class="admin-legend-persen"><?= number_format($sg['persen'], 1, ',', '.') ?>%</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

    </section>

    <section class="admin-panel">
        <div class="admin-panel-header">
            <div>
                <h2>Transaksi terbaru</h2>
                <p>Lima transaksi parkir terakhir yang tercatat.</p>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat nomor</th>
                        <th>Jenis kendaraan</th>
                        <th>Waktu masuk</th>
                        <th>Waktu keluar</th>
                        <th>Status</th>
                        <th>Biaya</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($terbaru)): ?>
                    <tr>
                        <td colspan="7" class="admin-empty">
                            Belum ada transaksi parkir.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($terbaru as $i => $t): ?>
                        <?php $sudahKeluar = $t['status'] === 'keluar'; ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <span class="admin-plate">
                                    <?= e($t['plat_nomor']) ?>
                                </span>
                            </td>
                            <td><?= e($t['jenis_kendaraan']) ?></td>
                            <td><?= e($t['waktu_masuk']) ?></td>
                            <td>
                                <?= !empty($t['waktu_keluar'])
                                    ? e($t['waktu_keluar'])
                                    : '-' ?>
                            </td>
                            <td>
                                <span class="admin-badge <?= $sudahKeluar
                                    ? 'admin-badge-done'
                                    : 'admin-badge-active' ?>">
                                    <?= $sudahKeluar ? 'Selesai' : 'Parkir' ?>
                                </span>
                            </td>
                            <td>
                                <?= $sudahKeluar ? rupiah($t['biaya_total']) : '-' ?>
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