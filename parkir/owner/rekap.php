<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['owner']);

$judul = 'Rekap Transaksi';

$tanggalAwal = $_GET['awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['akhir'] ?? date('Y-m-d');

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir) ||
    $tanggalAwal > $tanggalAkhir
) {
    $tanggalAwal = date('Y-m-01');
    $tanggalAkhir = date('Y-m-d');
}

try {
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            k.plat_nomor,
            k.jenis_kendaraan,
            u.nama_lengkap,
            a.nama_area
        FROM tb_transaksi t
        JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
        JOIN tb_user u
            ON u.id_user = t.id_user
        JOIN tb_area_parkir a
            ON a.id_area = t.id_area
        WHERE t.waktu_masuk >= ?
          AND t.waktu_masuk < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY t.id_parkir DESC
    ");

    $stmt->execute([$tanggalAwal, $tanggalAkhir]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $data = [];
    $pesanError = 'Data transaksi gagal dimuat. Periksa struktur tabel database.';
}

$totalPendapatan = 0;
$totalSelesai = 0;

foreach ($data as $transaksi) {
    if ($transaksi['status'] === 'keluar') {
        $totalPendapatan += (float) ($transaksi['biaya_total'] ?? 0);
        $totalSelesai++;
    }
}

function rupiahRekap($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}

require __DIR__ . '/header.php';
?>

<style>
/* ===== HALAMAN REKAP ===== */
.rekap-page {
    --rekap-green: #15803d;
    --rekap-dark: #14532d;
    --rekap-light: #f0fdf4;
    --rekap-yellow: #facc15;
    color: #1f2937;
}

.rekap-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.rekap-heading h1 {
    font-size: 25px;
    font-weight: 750;
    color: #14532d;
    margin: 0 0 7px;
}

.rekap-heading p {
    color: #64748b;
    font-size: 13px;
    margin: 0;
}

.rekap-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde68a;
    border-radius: 20px;
    padding: 8px 13px;
    font-size: 12px;
    font-weight: 700;
}

.rekap-filter {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
}

.rekap-section-title {
    font-size: 16px;
    font-weight: 750;
    color: #1f2937;
    margin: 0 0 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rekap-section-title::before {
    content: "";
    display: block;
    width: 4px;
    height: 20px;
    background: #16a34a;
    border-radius: 4px;
}

.rekap-filter-grid {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) auto;
    align-items: end;
    gap: 16px;
}

.rekap-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
}

.rekap-field input {
    width: 100%;
    min-height: 43px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    padding: 10px 12px;
    color: #334155;
    background: #fff;
    outline: none;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.rekap-field input:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, .12);
}

.rekap-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.rekap-btn {
    min-height: 43px;
    border: 0;
    border-radius: 9px;
    padding: 11px 15px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: .2s ease;
    white-space: nowrap;
}

.rekap-btn:hover {
    transform: translateY(-1px);
    filter: brightness(.97);
}

.rekap-btn-primary {
    background: #15803d;
    color: white;
}

.rekap-btn-print {
    background: #fef08a;
    color: #713f12;
    border: 1px solid #fde047;
}

.rekap-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.rekap-stat {
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
}

.rekap-stat::after {
    content: "";
    position: absolute;
    width: 85px;
    height: 85px;
    border-radius: 50%;
    right: -28px;
    top: -30px;
    background: rgba(22, 163, 74, .07);
}

.rekap-stat.yellow::after {
    background: rgba(250, 204, 21, .15);
}

.rekap-stat.blue::after {
    background: rgba(59, 130, 246, .08);
}

.rekap-stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 17px;
}

.rekap-stat-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 650;
}

.rekap-stat-icon {
    width: 39px;
    height: 39px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dcfce7;
    color: #15803d;
    font-size: 19px;
    font-weight: 800;
}

.rekap-stat.yellow .rekap-stat-icon {
    background: #fef9c3;
    color: #a16207;
}

.rekap-stat.blue .rekap-stat-icon {
    background: #dbeafe;
    color: #1d4ed8;
}

.rekap-stat-value {
    font-size: clamp(21px, 2vw, 28px);
    line-height: 1.3;
    font-weight: 800;
    color: #14532d;
    overflow-wrap: anywhere;
}

.rekap-stat.yellow .rekap-stat-value {
    color: #854d0e;
}

.rekap-stat.blue .rekap-stat-value {
    color: #1e40af;
}

.rekap-stat-note {
    color: #94a3b8;
    font-size: 11px;
    margin-top: 7px;
}

.rekap-report {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .035);
    margin-bottom: 22px;
}

.rekap-report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 22px;
    border-bottom: 1px solid #edf0f3;
    flex-wrap: wrap;
}

.rekap-report-header h2 {
    font-size: 17px;
    font-weight: 750;
    color: #14532d;
    margin: 0 0 6px;
}

.rekap-report-header p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.rekap-count {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 8px 11px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.rekap-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.rekap-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    text-align: left;
}

.rekap-table thead {
    background: #f8fafc;
}

.rekap-table th {
    color: #64748b;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .45px;
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.rekap-table td {
    padding: 15px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 12px;
    color: #475569;
    vertical-align: middle;
    white-space: nowrap;
}

.rekap-table tbody tr:hover {
    background: #f8fff9;
}

.rekap-table tbody tr:last-child td {
    border-bottom: 0;
}

.rekap-number {
    color: #94a3b8 !important;
    font-weight: 700;
}

.rekap-plat {
    display: inline-block;
    color: #14532d;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 6px;
    padding: 5px 8px;
    font-weight: 800;
    letter-spacing: .3px;
}

.rekap-biaya {
    font-weight: 750;
    color: #15803d !important;
}

.rekap-status {
    display: inline-block;
    border-radius: 20px;
    padding: 6px 10px;
    font-size: 10px;
    font-weight: 800;
}

.rekap-status-keluar {
    background: #dcfce7;
    color: #166534;
}

.rekap-status-masuk {
    background: #fef9c3;
    color: #854d0e;
}

.rekap-empty {
    text-align: center !important;
    padding: 45px 20px !important;
    color: #94a3b8 !important;
}

.rekap-empty-icon {
    display: block;
    font-size: 27px;
    margin-bottom: 9px;
}

.rekap-footnote {
    padding: 14px 22px;
    border-top: 1px solid #edf0f3;
    background: #fcfdfc;
    color: #94a3b8;
    font-size: 11px;
}

@media (max-width: 900px) {
    .rekap-filter-grid {
        grid-template-columns: 1fr 1fr;
    }

    .rekap-actions {
        grid-column: 1 / -1;
    }

    .rekap-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rekap-stat:last-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 560px) {
    .rekap-heading h1 {
        font-size: 22px;
    }

    .rekap-filter {
        padding: 17px;
    }

    .rekap-filter-grid,
    .rekap-stats {
        grid-template-columns: 1fr;
    }

    .rekap-actions {
        grid-column: auto;
    }

    .rekap-actions .rekap-btn {
        flex: 1;
    }

    .rekap-stat:last-child {
        grid-column: auto;
    }

    .rekap-stat {
        padding: 18px;
    }

    .rekap-report-header {
        padding: 18px;
    }
}

/* ===== TAMPILAN CETAK ===== */
@media print {
    @page {
        size: landscape;
        margin: 12mm;
    }

    body {
        background: white !important;
        color: #111827 !important;
    }

    .no-print,
    aside,
    nav,
    .sidebar,
    .rekap-heading .rekap-label {
        display: none !important;
    }

    .rekap-page {
        margin: 0 !important;
        padding: 0 !important;
    }

    .rekap-heading {
        margin-bottom: 15px;
    }

    .rekap-heading h1 {
        font-size: 20px;
    }

    .rekap-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .rekap-stat,
    .rekap-report {
        box-shadow: none !important;
        break-inside: avoid;
    }

    .rekap-table-wrap {
        overflow: visible !important;
    }

    .rekap-table {
        min-width: 0 !important;
        width: 100% !important;
    }

    .rekap-table th,
    .rekap-table td {
        padding: 7px 8px;
        font-size: 9px;
        color: #111827 !important;
    }

    .rekap-table th {
        background: #f1f5f9 !important;
    }

    .rekap-report-header {
        padding: 12px 15px;
    }

    .rekap-footnote {
        background: white !important;
    }
}
</style>

<div class="rekap-page">

    <div class="rekap-heading">
        <div>
            <h1>Rekap Transaksi</h1>
            <p>Ringkasan aktivitas dan pendapatan parkir berdasarkan periode.</p>
        </div>

        <div class="rekap-label">
            <span>●</span> Laporan Parkir Utama
        </div>
    </div>

    <?php if (!empty($pesanError)): ?>
        <div class="panel" style="border-left:4px solid #dc2626;color:#b91c1c">
            <?= htmlspecialchars($pesanError) ?>
        </div>
    <?php endif; ?>

    <div class="rekap-filter no-print">
        <h2 class="rekap-section-title">Filter Periode Transaksi</h2>

        <form method="GET" class="rekap-filter-grid">
            <div class="rekap-field">
                <label for="awal">Tanggal Awal</label>
                <input
                    type="date"
                    id="awal"
                    name="awal"
                    value="<?= htmlspecialchars($tanggalAwal) ?>"
                    max="<?= htmlspecialchars($tanggalAkhir) ?>"
                    required
                >
            </div>

            <div class="rekap-field">
                <label for="akhir">Tanggal Akhir</label>
                <input
                    type="date"
                    id="akhir"
                    name="akhir"
                    value="<?= htmlspecialchars($tanggalAkhir) ?>"
                    min="<?= htmlspecialchars($tanggalAwal) ?>"
                    required
                >
            </div>

            <div class="rekap-actions">
                <button class="rekap-btn rekap-btn-primary" type="submit">
                    <span>⌕</span> Tampilkan
                </button>

                <button
                    class="rekap-btn rekap-btn-print"
                    type="button"
                    onclick="window.print()"
                >
                    <span>▤</span> Cetak Rekap
                </button>
            </div>
        </form>
    </div>

    <div class="rekap-stats">
        <div class="rekap-stat">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Jumlah Transaksi</div>
                <div class="rekap-stat-icon">▤</div>
            </div>
            <div class="rekap-stat-value">
                <?= number_format(count($data), 0, ',', '.') ?>
            </div>
            <div class="rekap-stat-note">Seluruh transaksi pada periode terpilih</div>
        </div>

        <div class="rekap-stat yellow">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Transaksi Selesai</div>
                <div class="rekap-stat-icon">✓</div>
            </div>
            <div class="rekap-stat-value">
                <?= number_format($totalSelesai, 0, ',', '.') ?>
            </div>
            <div class="rekap-stat-note">Kendaraan yang sudah keluar</div>
        </div>

        <div class="rekap-stat blue">
            <div class="rekap-stat-top">
                <div class="rekap-stat-label">Total Pendapatan</div>
                <div class="rekap-stat-icon">Rp</div>
            </div>
            <div class="rekap-stat-value" style="font-size:clamp(19px, 2vw, 25px)">
                <?= rupiahRekap($totalPendapatan) ?>
            </div>
            <div class="rekap-stat-note">Dari transaksi yang sudah selesai</div>
        </div>
    </div>

    <div class="rekap-report">
        <div class="rekap-report-header">
            <div>
                <h2>Laporan Transaksi Parkir</h2>
                <p>
                    Periode
                    <?= htmlspecialchars(date('d-m-Y', strtotime($tanggalAwal))) ?>
                    sampai
                    <?= htmlspecialchars(date('d-m-Y', strtotime($tanggalAkhir))) ?>
                </p>
            </div>

            <div class="rekap-count">
                <?= number_format(count($data), 0, ',', '.') ?> transaksi
            </div>
        </div>

        <div class="rekap-table-wrap">
            <table class="rekap-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area Parkir</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Keluar</th>
                        <th>Durasi</th>
                        <th>Biaya</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($data): ?>
                    <?php foreach ($data as $i => $t): ?>
                        <tr>
                            <td class="rekap-number">
                                <?= $i + 1 ?>
                            </td>

                            <td>
                                <span class="rekap-plat">
                                    <?= htmlspecialchars($t['plat_nomor'] ?? '-') ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($t['jenis_kendaraan'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($t['nama_area'] ?? '-') ?>
                            </td>

                            <td>
                                <?= !empty($t['waktu_masuk'])
                                    ? htmlspecialchars(date('d-m-Y H:i', strtotime($t['waktu_masuk'])))
                                    : '-' ?>
                            </td>

                            <td>
                                <?= !empty($t['waktu_keluar'])
                                    ? htmlspecialchars(date('d-m-Y H:i', strtotime($t['waktu_keluar'])))
                                    : '-' ?>
                            </td>

                            <td>
                                <?= (int) ($t['durasi_jam'] ?? 0) ?> jam
                            </td>

                            <td class="rekap-biaya">
                                <?= rupiahRekap($t['biaya_total'] ?? 0) ?>
                            </td>

                            <td>
                                <?php
                                $status = $t['status'] ?? '';
                                $kelasStatus = $status === 'keluar'
                                    ? 'rekap-status-keluar'
                                    : 'rekap-status-masuk';
                                ?>
                                <span class="rekap-status <?= $kelasStatus ?>">
                                    <?= htmlspecialchars(ucfirst($status ?: 'Tidak diketahui')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="rekap-empty">
                            <span class="rekap-empty-icon">▤</span>
                            <strong>Tidak ada transaksi</strong>
                            <div style="margin-top:6px">
                                Belum ada transaksi pada periode tanggal yang dipilih.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="rekap-footnote">
            Laporan dibuat pada <?= date('d-m-Y H:i') ?>.
            Pendapatan hanya menghitung transaksi dengan status keluar.
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const awal = document.getElementById('awal');
    const akhir = document.getElementById('akhir');

    if (awal && akhir) {
        awal.addEventListener('change', function () {
            akhir.min = awal.value;

            if (akhir.value && akhir.value < awal.value) {
                akhir.value = awal.value;
            }
        });

        akhir.addEventListener('change', function () {
            awal.max = akhir.value;

            if (awal.value && awal.value > akhir.value) {
                awal.value = akhir.value;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
