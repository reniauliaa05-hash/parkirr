
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['petugas']);

$judul = 'Kendaraan Masuk';
$error = '';
$sukses = isset($_GET['sukses']);

// ========================================
// PROSES TRANSAKSI KENDARAAN MASUK
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idKendaraan = (int) ($_POST['id_kendaraan'] ?? 0);
    $idArea = (int) ($_POST['id_area'] ?? 0);

    try {
        if ($idKendaraan <= 0 || $idArea <= 0) {
            throw new Exception('Silakan pilih kendaraan dan area parkir.');
        }

        $pdo->beginTransaction();

        // Periksa kendaraan
        $stmt = $pdo->prepare(
            'SELECT * FROM tb_kendaraan
             WHERE id_kendaraan = ?
             FOR UPDATE'
        );
        $stmt->execute([$idKendaraan]);
        $dataKendaraan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dataKendaraan) {
            throw new Exception('Kendaraan tidak ditemukan.');
        }

        // Pastikan kendaraan belum masuk
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM tb_transaksi
             WHERE id_kendaraan = ?
             AND status = 'masuk'"
        );
        $stmt->execute([$idKendaraan]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception(
                'Kendaraan ini masih tercatat sedang parkir.'
            );
        }

        // Periksa area parkir tanpa role area
        $stmt = $pdo->prepare(
            'SELECT *
             FROM tb_area_parkir
             WHERE id_area = ?
             FOR UPDATE'
        );
        $stmt->execute([$idArea]);
        $dataArea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dataArea) {
            throw new Exception('Area parkir tidak ditemukan.');
        }

        if ((int) $dataArea['terisi'] >= (int) $dataArea['kapasitas']) {
            throw new Exception('Area parkir sudah penuh.');
        }

        // Ambil tarif berdasarkan jenis kendaraan
        $stmt = $pdo->prepare(
            'SELECT id_tarif
             FROM tb_tarif
             WHERE jenis_kendaraan = ?
             LIMIT 1'
        );
        $stmt->execute([$dataKendaraan['jenis_kendaraan']]);
        $idTarif = $stmt->fetchColumn();

        if (!$idTarif) {
            throw new Exception(
                'Tarif untuk jenis kendaraan ini belum tersedia.'
            );
        }

        // Pastikan user petugas tersedia di session
        $idUser = (int) ($_SESSION['user']['id_user'] ?? 0);

        if ($idUser <= 0) {
            throw new Exception('Data login petugas tidak ditemukan.');
        }

        // Simpan transaksi masuk
        $stmt = $pdo->prepare(
            "INSERT INTO tb_transaksi
             (
                id_kendaraan,
                waktu_masuk,
                id_tarif,
                durasi_jam,
                biaya_total,
                status,
                id_user,
                id_area
             )
             VALUES (?, NOW(), ?, 0, 0, 'masuk', ?, ?)"
        );

        $stmt->execute([
            $idKendaraan,
            $idTarif,
            $idUser,
            $idArea
        ]);

        // Tambahkan jumlah kendaraan di area
        $stmt = $pdo->prepare(
            'UPDATE tb_area_parkir
             SET terisi = terisi + 1
             WHERE id_area = ?
             AND terisi < kapasitas'
        );
        $stmt->execute([$idArea]);

        if ($stmt->rowCount() === 0) {
            throw new Exception(
                'Area parkir sudah penuh atau gagal diperbarui.'
            );
        }

        // Catat aktivitas petugas
        catatAktivitas(
            $pdo,
            'Kendaraan masuk: ' . $dataKendaraan['plat_nomor']
        );

        $pdo->commit();

        header('Location: transaksi_masuk.php?sukses=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
}

// ========================================
// AMBIL DATA KENDARAAN YANG BELUM PARKIR
// ========================================
$kendaraan = [];

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

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $kendaraan = is_array($hasil) ? $hasil : [];

} catch (Throwable $e) {
    $error = 'Gagal mengambil data kendaraan. Periksa tabel tb_kendaraan.';
}

// ========================================
// AMBIL DATA AREA PARKIR TANPA ROLE
// ========================================
$area = [];

try {
    $stmt = $pdo->query(
        'SELECT id_area, nama_area, kapasitas, terisi
         FROM tb_area_parkir
         ORDER BY nama_area ASC'
    );

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $area = is_array($hasil) ? $hasil : [];

} catch (Throwable $e) {
    $error = 'Gagal mengambil data area parkir. Periksa tabel tb_area_parkir.';
}

// ========================================
// AMBIL KENDARAAN YANG SEDANG PARKIR
// ========================================
$aktif = [];

try {
    $stmt = $pdo->query(
        "SELECT
            t.waktu_masuk,
            k.plat_nomor,
            k.jenis_kendaraan,
            a.nama_area
         FROM tb_transaksi t
         JOIN tb_kendaraan k
            ON k.id_kendaraan = t.id_kendaraan
         LEFT JOIN tb_area_parkir a
            ON a.id_area = t.id_area
         WHERE t.status = 'masuk'
         ORDER BY t.id_parkir DESC"
    );

    $hasil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $aktif = is_array($hasil) ? $hasil : [];

} catch (Throwable $e) {
    $error = 'Gagal mengambil data kendaraan yang sedang parkir.';
}

require __DIR__ . '/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($sukses): ?>
    <div class="alert success">
        Transaksi kendaraan masuk berhasil disimpan.
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Catat Kendaraan Masuk</h2>
    </div>

    <?php if (empty($area)): ?>
        <div class="alert">
            Belum ada data area parkir. Tambahkan area parkir terlebih dahulu.
        </div>
    <?php endif; ?>

    <?php if (empty($kendaraan)): ?>
        <div class="alert">
            Tidak ada kendaraan yang tersedia untuk dicatat masuk.
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="id_kendaraan">Kendaraan</label>

                <select
                    name="id_kendaraan"
                    id="id_kendaraan"
                    required
                >
                    <option value="">Pilih kendaraan</option>

                    <?php foreach ($kendaraan as $k): ?>
                        <option value="<?= (int) $k['id_kendaraan'] ?>">
                            <?= htmlspecialchars(
                                $k['plat_nomor'] . ' - ' . $k['jenis_kendaraan'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_area">Area Parkir</label>

                <select
                    name="id_area"
                    id="id_area"
                    required
                >
                    <option value="">Pilih area parkir</option>

                    <?php foreach ($area as $a): ?>
                        <?php
                        $kapasitas = (int) $a['kapasitas'];
                        $terisi = (int) $a['terisi'];
                        $penuh = $terisi >= $kapasitas;
                        ?>

                        <option
                            value="<?= (int) $a['id_area'] ?>"
                            <?= $penuh ? 'disabled' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $a['nama_area'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            (<?= $terisi ?>/<?= $kapasitas ?>)
                            <?= $penuh ? '- PENUH' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button
            class="btn btn-primary"
            type="submit"
            <?= empty($kendaraan) || empty($area) ? 'disabled' : '' ?>
        >
            Simpan Kendaraan Masuk
        </button>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Kendaraan Sedang Parkir</h2>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Plat Nomor</th>
                    <th>Jenis</th>
                    <th>Area</th>
                    <th>Waktu Masuk</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($aktif)): ?>
                    <?php foreach ($aktif as $i => $t): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['plat_nomor'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['jenis_kendaraan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['nama_area'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $t['waktu_masuk'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            Tidak ada kendaraan yang sedang parkir.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
