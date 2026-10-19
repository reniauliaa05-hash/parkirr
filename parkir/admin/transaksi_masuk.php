<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['admin']);

$judul = 'Kendaraan Masuk';
$error = '';

/*
|--------------------------------------------------------------------------
| Proses kendaraan masuk
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idKendaraan = filter_var(
        $_POST['id_kendaraan'] ?? null,
        FILTER_VALIDATE_INT
    );

    try {
        if (!$idKendaraan || $idKendaraan < 1) {
            throw new Exception('Silakan pilih kendaraan.');
        }

        $pdo->beginTransaction();

        // Kunci data kendaraan.
        $stmt = $pdo->prepare(
            "SELECT id_kendaraan, plat_nomor, jenis_kendaraan
             FROM tb_kendaraan
             WHERE id_kendaraan = ?
             FOR UPDATE"
        );
        $stmt->execute([$idKendaraan]);
        $kendaraan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$kendaraan) {
            throw new Exception('Kendaraan tidak ditemukan.');
        }

        // Periksa apakah kendaraan masih parkir.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE id_kendaraan = ?
               AND status = 'masuk'"
        );
        $stmt->execute([$idKendaraan]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception(
                'Kendaraan masih tercatat sedang parkir.'
            );
        }

        // Ambil satu-satunya area parkir.
        $stmt = $pdo->query(
            "SELECT id_area, nama_area, kapasitas, terisi
             FROM tb_area_parkir
             ORDER BY id_area ASC
             LIMIT 2
             FOR UPDATE"
        );

        $daftarArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($daftarArea) === 0) {
            throw new Exception(
                'Data area parkir belum tersedia.'
            );
        }

        if (count($daftarArea) > 1) {
            throw new Exception(
                'Database memiliki lebih dari satu area. Sisakan satu area parkir.'
            );
        }

        $area = $daftarArea[0];
        $idArea = (int) $area['id_area'];
        $kapasitas = (int) $area['kapasitas'];
        $terisi = (int) $area['terisi'];

        if ($kapasitas < 1) {
            throw new Exception(
                'Kapasitas area parkir belum diatur.'
            );
        }

        if ($terisi >= $kapasitas) {
            throw new Exception(
                'Parkir penuh. Tidak ada slot tersedia.'
            );
        }

        // Ambil tarif berdasarkan jenis kendaraan.
        $stmt = $pdo->prepare(
            "SELECT id_tarif
             FROM tb_tarif
             WHERE jenis_kendaraan = ?
             LIMIT 1"
        );
        $stmt->execute([$kendaraan['jenis_kendaraan']]);
        $idTarif = $stmt->fetchColumn();

        if (!$idTarif) {
            throw new Exception(
                'Tarif kendaraan belum tersedia. Tambahkan tarif terlebih dahulu.'
            );
        }

        // Simpan transaksi masuk.
        $stmt = $pdo->prepare(
            "INSERT INTO tb_transaksi
                (id_kendaraan, waktu_masuk, id_tarif, durasi_jam,
                 biaya_total, status, id_user, id_area)
             VALUES (?, NOW(), ?, 0, 0, 'masuk', ?, ?)"
        );

        $stmt->execute([
            $idKendaraan,
            $idTarif,
            (int) $_SESSION['user']['id_user'],
            $idArea
        ]);

        // Perbarui jumlah kendaraan yang terisi.
        $stmt = $pdo->prepare(
            "UPDATE tb_area_parkir
             SET terisi = terisi + 1
             WHERE id_area = ?
               AND terisi < kapasitas"
        );
        $stmt->execute([$idArea]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Slot parkir tidak tersedia. Silakan coba lagi.'
            );
        }

        // Catat aktivitas.
        catatAktivitas(
            $pdo,
            'Kendaraan masuk: ' . $kendaraan['plat_nomor']
        );

        $pdo->commit();

        header('Location: transaksi_masuk.php?sukses=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log(
                'Transaksi masuk gagal: ' . $e->getMessage()
            );
            $error = 'Transaksi gagal disimpan. Periksa struktur tabel database.';
        } else {
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data untuk tampilan
|--------------------------------------------------------------------------
*/
$kendaraan = [];
$area = null;
$aktif = [];
$gagalMemuat = false;

try {
    $stmt = $pdo->query(
        "SELECT k.id_kendaraan, k.plat_nomor, k.jenis_kendaraan
         FROM tb_kendaraan k
         WHERE NOT EXISTS (
             SELECT 1
             FROM tb_transaksi t
             WHERE t.id_kendaraan = k.id_kendaraan
               AND t.status = 'masuk'
         )
         ORDER BY k.plat_nomor ASC"
    );
    $kendaraan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query(
        "SELECT id_area, nama_area, kapasitas, terisi
         FROM tb_area_parkir
         ORDER BY id_area ASC
         LIMIT 2"
    );
    $daftarArea = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($daftarArea) === 1) {
        $area = $daftarArea[0];
    }

    $stmt = $pdo->query(
        "SELECT t.waktu_masuk,
                k.plat_nomor,
                k.jenis_kendaraan,
                a.nama_area
         FROM tb_transaksi t
         INNER JOIN tb_kendaraan k
             ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
             ON a.id_area = t.id_area
         WHERE t.status = 'masuk'
         ORDER BY t.id_parkir DESC"
    );
    $aktif = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Gagal memuat kendaraan masuk: ' . $e->getMessage()
    );
    $error = 'Data gagal dimuat. Periksa struktur tabel database.';
    $gagalMemuat = true;
}

$kapasitas = (int) ($area['kapasitas'] ?? 0);
$terisi = (int) ($area['terisi'] ?? 0);
$tersedia = max(0, $kapasitas - $terisi);

require_once __DIR__ . '/header.php';
?>

<style>
/* Hanya WARNA yang diubah. Ukuran, jarak, dan tata letak sama seperti semula. */

.masuk-page {
    padding: 24px;
    color: #16241e;
}

.masuk-heading {
    margin-bottom: 22px;
}

.masuk-heading h2 {
    margin: 0 0 8px;
    font-size: 24px;
    color: #0c3b2e;
}

.masuk-heading p {
    margin: 0;
    color: #62736a;
    font-size: 14px;
}

.masuk-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.masuk-stat,
.masuk-panel {
    background: #fff;
    border: 1px solid #d9e2dc;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(12, 59, 46, .05);
}

.masuk-stat {
    padding: 20px;
}

.masuk-stat span {
    color: #62736a;
    font-size: 13px;
}

.masuk-stat strong {
    display: block;
    margin-top: 9px;
    font-size: 26px;
    color: #0c3b2e;
}

.masuk-panel {
    margin-bottom: 22px;
    padding: 24px;
}

.masuk-panel h3 {
    margin: 0 0 20px;
    font-size: 17px;
    color: #0c3b2e;
}

.masuk-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #16241e;
}

.masuk-field select {
    width: 100%;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid #d9e2dc;
    border-radius: 7px;
    background: #f8faf8;
    color: #16241e;
    box-sizing: border-box;
}

.masuk-field select:focus {
    outline: 2px solid rgba(20, 81, 63, .3);
    border-color: #14513f;
    background: #fff;
}

.masuk-btn {
    margin-top: 20px;
    min-height: 43px;
    padding: 0 18px;
    border: none;
    border-radius: 7px;
    background: #f0b429;
    color: #0c3b2e;
    font-weight: 600;
    cursor: pointer;
}

.masuk-btn:hover {
    background: #f5c24a;
}

.masuk-btn:focus-visible {
    outline: 2px solid #0c3b2e;
    outline-offset: 2px;
}

.masuk-alert {
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 8px;
    background: #fdeeee;
    color: #a12b2b;
    font-size: 14px;
    line-height: 1.5;
}

.masuk-alert.success {
    background: #e9f7ef;
    color: #14653f;
}

.masuk-area-info {
    margin-bottom: 18px;
    padding: 14px;
    border-radius: 8px;
    background: #e9f2ec;
    color: #14513f;
    font-size: 14px;
}

.masuk-table-wrap {
    overflow-x: auto;
}

.masuk-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}

.masuk-table th {
    padding: 14px 12px;
    background: #f3f7f4;
    color: #62736a;
    border-bottom: 1px solid #d9e2dc;
    white-space: nowrap;
}

.masuk-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf2ee;
}

.masuk-table tbody tr:hover {
    background: #f8fbf9;
}

.masuk-empty {
    padding: 24px !important;
    text-align: center;
    color: #62736a;
}

@media (max-width: 800px) {
    .masuk-page {
        padding: 16px;
    }

    .masuk-stats {
        grid-template-columns: 1fr;
    }

    .masuk-btn {
        width: 100%;
    }
}
</style>

<div class="masuk-page">

    <div class="masuk-heading">
        <h2>Kendaraan Masuk</h2>
        <p>Catat kendaraan yang memasuki area parkir.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="masuk-alert" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sukses'])): ?>
        <div class="masuk-alert success" role="status">
            Transaksi kendaraan masuk berhasil disimpan.
        </div>
    <?php endif; ?>

    <?php if ($area !== null): ?>
        <div class="masuk-area-info">
            Area: <strong><?= e($area['nama_area']) ?></strong>
            — Kapasitas <?= $kapasitas ?> kendaraan,
            terisi <?= $terisi ?>,
            tersedia <?= $tersedia ?> slot.
        </div>
    <?php endif; ?>

    <div class="masuk-stats">
        <div class="masuk-stat">
            <span>Total Kapasitas</span>
            <strong><?= number_format($kapasitas) ?></strong>
        </div>

        <div class="masuk-stat">
            <span>Sedang Terisi</span>
            <strong><?= number_format($terisi) ?></strong>
        </div>

        <div class="masuk-stat">
            <span>Slot Tersedia</span>
            <strong><?= number_format($tersedia) ?></strong>
        </div>
    </div>

    <section class="masuk-panel">
        <h3>Form Kendaraan Masuk</h3>

        <?php if ($gagalMemuat): ?>
            <div class="masuk-alert">
                Form tidak dapat digunakan karena data gagal dimuat.
            </div>

        <?php elseif ($area === null): ?>
            <div class="masuk-alert">
                Pastikan database memiliki tepat satu area parkir.
            </div>

        <?php elseif ($tersedia <= 0): ?>
            <div class="masuk-alert">
                Parkir penuh. Tidak ada slot yang tersedia.
            </div>

        <?php elseif (empty($kendaraan)): ?>
            <div class="masuk-alert">
                Tidak ada kendaraan yang tersedia. Tambahkan data kendaraan
                atau periksa transaksi yang masih aktif.
            </div>

        <?php else: ?>
            <form method="POST" action="transaksi_masuk.php">
                <div class="masuk-field">
                    <label for="id_kendaraan">Pilih Kendaraan</label>

                    <select
                        name="id_kendaraan"
                        id="id_kendaraan"
                        required
                    >
                        <option value="">Pilih kendaraan</option>

                        <?php foreach ($kendaraan as $k): ?>
                            <option value="<?= (int) $k['id_kendaraan'] ?>">
                                <?= e($k['plat_nomor']) ?>
                                - <?= e(ucfirst($k['jenis_kendaraan'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="masuk-btn">
                    Simpan Kendaraan Masuk
                </button>
            </form>
        <?php endif; ?>
    </section>

    <section class="masuk-panel">
        <h3>Kendaraan Sedang Parkir</h3>

        <div class="masuk-table-wrap">
            <table class="masuk-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Plat Nomor</th>
                        <th>Jenis Kendaraan</th>
                        <th>Area Parkir</th>
                        <th>Waktu Masuk</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($aktif)): ?>
                        <tr>
                            <td colspan="5" class="masuk-empty">
                                Tidak ada kendaraan yang sedang parkir.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aktif as $i => $t): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($t['plat_nomor']) ?></td>
                                <td><?= e(ucfirst($t['jenis_kendaraan'])) ?></td>
                                <td><?= e($t['nama_area'] ?? '-') ?></td>
                                <td><?= e($t['waktu_masuk']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>