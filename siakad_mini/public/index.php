<?php
// File: public/index.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/DosenRepository.php';

// Proteksi halaman: Yang belum login bakal ditendang ke login.php
Auth::guard();

$db = Database::getConnection();
$repo = new DosenRepository($db);

// 1. Ambil State/Kondisi dari URL Query String (Guna mempertahankan filter saat paginasi)
$search  = trim($_GET['q'] ?? '');
$studi   = trim($_GET['studi'] ?? '');
$sortCol = trim($_GET['sort'] ?? 'nama');
$sortDir = trim($_GET['dir'] ?? 'ASC');

// 2. Setup Paginasi Data (Level 2)
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 5; // Menampilkan 5 data dosen per halaman
$offset    = ($page - 1) * $limit;

// 3. Tarik data dari kelas Repository OOP
$dosenList = $repo->getAllActive($search, $studi, $sortCol, $sortDir, $limit, $offset);
$totalData = $repo->countActive($search, $studi);
$totalPages = ceil($totalData / $limit);

// Helper membalik arah urutan sorting saat link kolom diklik
$nextDir = ($sortDir === 'ASC') ? 'DESC' : 'ASC';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIAKAD MINI</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f8fafc; color: #334155; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #1e293b; color: white; padding: 10px 20px; border-radius: 6px; margin-bottom: 20px; }
        .navbar a { color: #f87171; text-decoration: none; font-weight: bold; }
        .trash-link { color: #38bdf8 !important; margin-right: 15px; }
        .filter-box { background: white; padding: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-box input, .filter-box select, .filter-box button { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; margin-right: 10px; }
        .filter-box button { background: #2563eb; color: white; border: none; cursor: pointer; }
        .filter-box a { color: #64748b; text-decoration: none; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; }
        th a { text-decoration: none; color: #1e293b; font-weight: bold; }
        .avatar { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; background: #e2e8f0; display: inline-block; text-align: center; line-height: 45px; }
        .btn { padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-del { background: #ef4444; color: white; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; }
        .pagination a { padding: 8px 12px; border: 1px solid #cbd5e1; background: white; text-decoration: none; color: #334155; border-radius: 4px; }
        .pagination a.active { background: #2563eb; color: white; border-color: #2563eb; }
        .msg-success { background: #dcfce7; color: #15803d; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="navbar">
    <div>
        <strong>SIAKAD MINI</strong> | Login: <u><?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></u> 
        (<span style="text-transform: uppercase; font-size: 0.85rem;"><?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?></span>)
    </div>
    <div>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="trash.php" class="trash-link">🗑️ Keranjang Sampah</a>
        <?php endif; ?>
        <a href="logout.php">Keluar (Logout)</a>
    </div>
</div>

<h2>Manajemen Data Dosen</h2>

<div class="mb-3">
    <a href="export_excel.php" class="btn btn-success btn-sm">🟢 Export Excel (.csv)</a>
    <button onclick="window.print()" class="btn btn-secondary btn-sm">🔵 Cetak PDF / Print</button>
</div>

<form method="GET" action="" class="row g-2 mb-3">
    </form>

<table class="table table-striped">
    </table>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="msg-success">✅ Perubahan data berhasil disimpan ke database!</div>
<?php endif; ?>

<div class="filter-box">
    <form method="GET" action="">
        <input type="text" name="q" placeholder="Cari Nama / NIDN..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        
        <select name="studi">
            <option value="">-- Semua Program Studi --</option>
            <option value="Teknik Informatika" <?= $studi === 'Teknik Informatika' ? 'selected' : '' ?>>Teknik Informatika</option>
            <option value="Sistem Informasi" <?= $studi === 'Sistem Informasi' ? 'selected' : '' ?>>Sistem Informasi</option>
            <option value="Teknik Elektro" <?= $studi === 'Teknik Elektro' ? 'selected' : '' ?>>Teknik Elektro</option>
        </select>
        
        <button type="submit">Cari & Filter</button>
        <?php if ($search !== '' || $studi !== ''): ?>
            <a href="index.php">Reset Filter</a>
        <?php endif; ?>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Foto</th>
            <th><a href="?q=<?= urlencode($search) ?>&studi=<?= urlencode($studi) ?>&sort=nidn&dir=<?= $nextDir ?>">NIDN ↕️</a></th>
            <th><a href="?q=<?= urlencode($search) ?>&studi=<?= urlencode($studi) ?>&sort=nama&dir=<?= $nextDir ?>">Nama Lengkap ↕️</a></th>
            <th><a href="?q=<?= urlencode($search) ?>&studi=<?= urlencode($studi) ?>&sort=email&dir=<?= $nextDir ?>">Email ↕️</a></th>
            <th><a href="?q=<?= urlencode($search) ?>&studi=<?= urlencode($studi) ?>&sort=program_studi&dir=<?= $nextDir ?>">Program Studi ↕️</a></th>
            <th>Status</th>
            <th>Beban Mengajar</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($dosenList)): ?>
            <tr><td colspan="8" style="text-align: center; color: #94a3b8;">Data dosen tidak ditemukan.</td></tr>
        <?php else: ?>
            <?php foreach ($dosenList as $dosen): ?>
                <tr>
                    <td>
                        <?php if (!empty($dosen['foto'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($dosen['foto'], ENT_QUOTES, 'UTF-8') ?>" class="avatar" alt="Foto">
                        <?php else: ?>
                            <div class="avatar">👤</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($dosen['nidn'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($dosen['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($dosen['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($dosen['program_studi'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span style="padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; background: <?= $dosen['status'] === 'aktif' ? '#dcfce7; color: #16a34a;' : '#fee2e2; color: #dc2626;' ?>">
                            <?= htmlspecialchars($dosen['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><strong><?= (int)$dosen['total_mk'] ?></strong> Mata Kuliah</td>
                    <td>
                        <a href="edit.php?id=<?= (int)$dosen['id'] ?>" class="btn btn-edit">Edit / Assign MK</a>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="delete.php?id=<?= (int)$dosen['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" 
                               class="btn btn-del" 
                               onclick="return confirm('Apakah Anda yakin ingin memindahkan dosen ini ke Keranjang Sampah?')">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?q=<?= urlencode($search) ?>&studi=<?= urlencode($studi) ?>&sort=<?= urlencode($sortCol) ?>&dir=<?= urlencode($sortDir) ?>&page=<?= $i ?>" 
           class="<?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</body>
</html>