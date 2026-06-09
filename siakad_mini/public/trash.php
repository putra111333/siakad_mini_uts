<?php
// File: public/trash.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/DosenRepository.php';

// LEVEL 2 SECURITY: Hanya boleh diakses oleh ADMIN di sisi server (RBAC)
Auth::requireRole('admin');

$db = Database::getConnection();
$repo = new DosenRepository($db);

// Menangani Aksi Pemulihan (Restore) jika tombol Pulihkan diklik
if (isset($_GET['restore_id'])) {
    $restoreId = (int)$_GET['restore_id'];
    
    if ($restoreId > 0) {
        $repo->restore($restoreId);
        // Redirect kembali ke trash.php dengan pesan sukses
        header("Location: trash.php?msg=restored");
        exit;
    }
}

// Mengambil semua data dosen yang ada di keranjang sampah
$trashList = $repo->getTrash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Sampah Dosen - SIAKAD MINI</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #fafafa; color: #334155; }
        .container { max-width: 900px; margin: 0 auto; }
        .header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-back { text-decoration: none; color: #2563eb; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #fee2e2; color: #991b1b; } /* Tema merah melambangkan tempat sampah/delete */
        .btn-restore { background: #16a34a; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold; }
        .btn-restore:hover { background: #15803d; }
        .msg-info { background: #dcfce7; color: #15803d; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .empty-state { text-align: center; color: #64748b; padding: 30px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-area">
        <h2>🗑️ Keranjang Sampah Dosen (Soft Delete)</h2>
        <a href="index.php" class="btn-back">← Kembali ke Dashboard</a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'restored'): ?>
        <div class="msg-info">✅ Data dosen berhasil dipulihkan dan kembali aktif di dashboard utama!</div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>NIDN</th>
                <th>Nama Dosen</th>
                <th>Email</th>
                <th>Program Studi</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($trashList)): ?>
                <tr>
                    <td colspan="5" class="empty-state">
                        Keranjang sampah kosong. Tidak ada data dosen yang terhapus sementara.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($trashList as $dosen): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($dosen['nidn'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($dosen['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($dosen['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($dosen['program_studi'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="?restore_id=<?= (int)$dosen['id'] ?>" 
                               class="btn-restore" 
                               onclick="return confirm('Apakah Anda yakin ingin memulihkan data dosen ini?')">Pulihkan Data</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>