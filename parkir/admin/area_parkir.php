<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$pesan = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Simpan perubahan kapasitas area
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kapasitas = filter_input(INPUT_POST, 'kapasitas', FILTER_VALIDATE_INT);

    try {
        if ($kapasitas === false || $kapasitas === null || $kapasitas < 1) {
            throw new Exception('Kapasitas harus berupa angka minimal 1.');
        }

        $pdo->beginTransaction();

        // Pastikan hanya menggunakan satu area parkir.
        $stmt = $pdo->query(
            "SELECT id_area, kapasitas
             FROM tb_area_parkir
             ORDER BY id_area ASC
             LIMIT 2
             FOR UPDATE"
        );
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($areas) !== 1) {
            throw new Exception(
                'Database harus memiliki tepat satu area parkir. Periksa data tb_area_parkir.'
            );
        }

        $area = $areas[0];

        // Hitung kendaraan yang sedang berada di area parkir.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE status = 'masuk'
               AND id_area = ?"
        );
        $stmt->execute([$area['id_area']]);
        $terisi = (int) $stmt->fetchColumn();

        if ($kapasitas < $terisi) {
            throw new Exception(
                "Kapasitas tidak boleh kurang dari jumlah kendaraan yang sedang parkir ($terisi kendaraan)."
            );
        }

        $stmt = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET nama_area = 'Parkir Utama',
                 kapasitas = ?,
                 terisi = ?
             WHERE id_area = ?"
        );
        $stmt->execute([
            $kapasitas,
            $terisi,
            $area['id_area']
        ]);

        if (function_exists('catatAktivitas')) {
            catatAktivitas($pdo, 'Mengubah kapasitas Area Parkir Utama');
        }

        $pdo->commit();
        $pesan = 'Data area parkir berhasil diperbarui.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e instanceof PDOException
            ? 'Gagal menyimpan data. Periksa struktur tabel dan koneksi database.'
            : $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data area parkir
|--------------------------------------------------------------------------
*/
try {
    $stmt = $pdo->query(
        "SELECT
            a.id_area,
            a.nama_area,
            a.kapasitas,
            (
                SELECT COUNT(*)
                FROM tb_transaksi t
                WHERE t.id_area = a.id_area
                  AND t.status = 'masuk'
            ) AS terisi
         FROM tb_area_parkir a
         ORDER BY a.id_area ASC"
    );

    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($areas) !== 1) {
        $error = 'Pastikan tabel tb_area_parkir berisi tepat satu area parkir.';
        $area = null;
    } else {
        $area = $areas[0];
        $area['terisi'] = (int) $area['terisi'];
        $area['kapasitas'] = (int) $area['kapasitas'];
        $area['sisa'] = max(0, $area['kapasitas'] - $area['terisi']);
        $area['persentase'] = $area['kapasitas'] > 0
            ? min(100, round($area['terisi'] / $area['kapasitas'] * 100))
            : 0;
    }
} catch (PDOException $e) {
    $area = null;
    $error = 'Data area gagal ditampilkan. Pastikan tabel dan kolom database sudah sesuai.';
}

// Warna progress bar mengikuti tingkat keterisian (hanya mengubah warna).
$kelasProgress = '';

if ($area !== null) {
    if ($area['persentase'] >= 90) {
        $kelasProgress = 'penuh';
    } elseif ($area['persentase'] >= 70) {
        $kelasProgress = 'ramai';
    }
}

require_once __DIR__ . '/header.php';
?>

<style>
/* Hanya WARNA yang diubah. Ukuran, jarak, dan tata letak sama seperti semula. */

.area-container {
    padding: 24px;
    color: #16241e;
}

.area-heading {
    margin-bottom: 22px;
}

.area-heading h2 {
    margin: 0 0 7px;
    font-size: 25px;
    color: #0c3b2e;
}

.area-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
}

.area-alert {
    padding: 12px 16px;
    margin-bottom: 18px;
    border-radius: 8px;
    font-size: 14px;
}

.area-success {
    color: #14653f;
    background: #e9f7ef;
}

.area-error {
    color: #a12b2b;
    background: #fdeeee;
}

.area-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.area-card {
    background: #fff;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(12, 59, 46, 0.05);
}

.area-card p {
    margin: 0 0 10px;
    color: #62736a;
    font-size: 14px;
}

.area-card h3 {
    margin: 0;
    font-size: 27px;
    color: #0c3b2e;
}

.area-panel {
    background: #fff;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;
}

.area-panel h3 {
    margin: 0 0 20px;
    font-size: 18px;
    color: #0c3b2e;
}

.area-progress {
    width: 100%;
    height: 10px;
    margin: 12px 0 8px;
    overflow: hidden;
    border-radius: 20px;
    background: #e3ebe6;
}

.area-progress div {
    height: 100%;
    background: #14513f;
    border-radius: 20px;
}

.area-progress.ramai div {
    background: #f0b429;
}

.area-progress.penuh div {
    background: #d9534f;
}

.area-status {
    color: #62736a;
    font-size: 13px;
}

.area-form {
    max-width: 450px;
}

.area-form label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #16241e;
}

.area-form input {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #d9e2dc;
    border-radius: 8px;
    margin-bottom: 14px;
    background: #f8faf8;
    color: #16241e;
    font-size: 14px;
}

.area-form input:focus {
    outline: 2px solid rgba(20, 81, 63, 0.3);
    border-color: #14513f;
    background: #fff;
}

.area-form button {
    border: 0;
    border-radius: 8px;
    padding: 11px 17px;
    color: #0c3b2e;
    background: #f0b429;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.area-form button:hover {
    background: #f5c24a;
}

.area-form button:focus-visible {
    outline: 2px solid #0c3b2e;
    outline-offset: 2px;
}

@media (max-width: 700px) {
    .area-grid {
        grid-template-columns: 1fr;
    }

    .area-container {
        padding: 16px;
    }
}
</style>

<div class="area-container">
    <div class="area-heading">
        <h2>Area Parkir</h2>
        <p>Informasi kapasitas dan ketersediaan Parkir Utama.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="area-alert area-success">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="area-alert area-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($area !== null): ?>
        <div class="area-grid">
            <div class="area-card">
                <p>Total Kapasitas</p>
                <h3><?= number_format($area['kapasitas']) ?></h3>
                <span class="area-status">Kendaraan maksimal</span>
            </div>

            <div class="area-card">
                <p>Kendaraan Terisi</p>
                <h3><?= number_format($area['terisi']) ?></h3>
                <span class="area-status">Sedang parkir</span>
            </div>

            <div class="area-card">
                <p>Sisa Kapasitas</p>
                <h3><?= number_format($area['sisa']) ?></h3>
                <span class="area-status">Tempat tersedia</span>
            </div>
        </div>

        <div class="area-panel">
            <h3>Informasi Parkir Utama</h3>

            <p>
                <strong>Nama Area:</strong>
                <?= e($area['nama_area']) ?>
            </p>

            <div class="area-progress <?= e($kelasProgress) ?>">
                <div style="width: <?= $area['persentase'] ?>%;"></div>
            </div>

            <p class="area-status">
                <?= $area['persentase'] ?>% kapasitas telah digunakan.
            </p>
        </div>

        <div class="area-panel">
            <h3>Pengaturan Kapasitas</h3>

            <form method="POST" class="area-form">
                <label for="kapasitas">Kapasitas Maksimal Kendaraan</label>

                <input
                    type="number"
                    id="kapasitas"
                    name="kapasitas"
                    min="<?= max(1, $area['terisi']) ?>"
                    value="<?= $area['kapasitas'] ?>"
                    required
                >

                <button type="submit">Simpan Perubahan</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>