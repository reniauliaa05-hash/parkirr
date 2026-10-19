<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Rekap Transaksi';
$tanggalAwal = $_GET['awal'] ?? date('Y-m-01');
$tanggalAkhir = $_GET['akhir'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAkhir) ||
    $tanggalAwal > $tanggalAkhir) {
    $tanggalAwal = date('Y-m-01');
    $tanggalAkhir = date('Y-m-d');
}

$stmt = $pdo->prepare(
    "SELECT t.*, k.plat_nomor, k.jenis_kendaraan,
            u.nama_lengkap, a.nama_area
     FROM tb_transaksi t
     JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
     JOIN tb_user u ON u.id_user = t.id_user
     JOIN tb_area_parkir a ON a.id_area = t.id_area
     WHERE t.waktu_masuk >= ?
       AND t.waktu_masuk < DATE_ADD(?, INTERVAL 1 DAY)
     ORDER BY t.id_parkir DESC"
);
$stmt->execute([$tanggalAwal, $tanggalAkhir]);
$data = $stmt->fetchAll();

$total = 0;
foreach ($data as $t) {
    if ($t['status'] === 'keluar') {
        $total += (float)$t['biaya_total'];
    }
}

require 'header.php';
?>

<style>
/* Hanya mengubah WARNA. Ukuran, jarak, dan tata letak tetap
   mengikuti style.css seperti semula. */

.admin-content .panel,
.admin-content .card {
    background-color: #ffffff !important;
    border-color: #d9e2dc !important;
}

.admin-content .panel-title,
.admin-content .panel-header h2 {
    color: #0c3b2e !important;
}

.admin-content .card-label,
.admin-content .subtitle {
    color: #62736a !important;
}

.admin-content .card-value {
    color: #0c3b2e !important;
}

.admin-content .form-group label {
    color: #16241e !important;
}

.admin-content .form-group input,
.admin-content .form-group select {
    background-color: #f8faf8 !important;
    border-color: #d9e2dc !important;
    color: #16241e !important;
}

.admin-content .form-group input:focus,
.admin-content .form-group select:focus {
    border-color: #14513f !important;
    box-shadow: 0 0 0 3px rgba(20, 81, 63, 0.14) !important;
}

.admin-content .btn-primary {
    background-color: #f0b429 !important;
    border-color: #f0b429 !important;
    color: #0c3b2e !important;
}

.admin-content .btn-primary:hover {
    background-color: #f5c24a !important;
    border-color: #f5c24a !important;
}

.admin-content .btn-light {
    background-color: #e9f2ec !important;
    border-color: #d9e2dc !important;
    color: #14513f !important;
}

.admin-content .btn-light:hover {
    background-color: #dcebe2 !important;
}

.admin-content table th {
    background-color: #f3f7f4 !important;
    border-bottom-color: #d9e2dc !important;
    color: #62736a !important;
}

.admin-content table td {
    border-bottom-color: #edf2ee !important;
    color: #16241e !important;
}

.admin-content table tbody tr:hover {
    background-color: #f8fbf9 !important;
}
</style>

<div class="panel no-print">
    <h2 class="panel-title" style="margin-bottom:20px">
        Filter Periode Transaksi
    </h2>

    <form method="GET" class="filter-form">
        <div class="form-group">
            <label>Tanggal Awal</label>
            <input type="date" name="awal"
                   value="<?= htmlspecialchars($tanggalAwal) ?>" required>
        </div>

        <div class="form-group">
            <label>Tanggal Akhir</label>
            <input type="date" name="akhir"
                   value="<?= htmlspecialchars($tanggalAkhir) ?>" required>
        </div>

        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <button class="btn btn-light" type="button"
                onclick="window.print()">Cetak Rekap</button>
    </form>
</div>

<div class="cards">
    <div class="card">
        <div class="card-label">Jumlah Transaksi</div>
        <div class="card-value"><?= count($data) ?></div>
    </div>

    <div class="card">
        <div class="card-label">Pendapatan Selesai</div>
        <div class="card-value" style="font-size:23px">
            Rp <?= number_format($total, 0, ',', '.') ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Laporan Transaksi Parkir</h2>
    </div>

    <p class="subtitle" style="margin-bottom:18px">
        Periode <?= htmlspecialchars($tanggalAwal) ?> sampai
        <?= htmlspecialchars($tanggalAkhir) ?>
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Durasi</th>
                    <th>Biaya</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($t['plat_nomor']) ?></td>
                    <td><?= htmlspecialchars($t['jenis_kendaraan']) ?></td>
                    <td><?= htmlspecialchars($t['nama_area']) ?></td>
                    <td><?= htmlspecialchars($t['waktu_masuk']) ?></td>
                    <td><?= htmlspecialchars($t['waktu_keluar'] ?? '-') ?></td>
                    <td><?= (int)$t['durasi_jam'] ?> jam</td>
                    <td>Rp <?= number_format((float)$t['biaya_total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars(ucfirst($t['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$data): ?>
                <tr><td colspan="9">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>