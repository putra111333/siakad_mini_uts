<?php
// File: public/edit.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/DosenRepository.php';

// Proteksi: Semua user yang login (admin/operator) bisa akses halaman ini
Auth::guard();

$db = Database::getConnection();
$repo = new DosenRepository($db);

$id = (int)($_GET['id'] ?? 0);
$dosen = $repo->find($id);

// Jika ID dosen ngasal atau dosen sudah di-soft delete, hentikan program
if (!$dosen) {
    http_response_code(404);
    die("❌ Error 404: Data dosen tidak ditemukan atau telah dihapus.");
}

// Ambil semua daftar mata kuliah untuk pilihan di HTML <select multiple>
$mkStmt = $db->query("SELECT * FROM mata_kuliah ORDER BY kode ASC");
$allMataKuliah = $mkStmt->fetchAll();

// Ambil ID mata kuliah apa saja yang saat ini sedang diambil dosen ini
$selectedMkIds = $repo->getDosenMatakuliahIds($id);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validasi Token Anti-CSRF (Level 1)
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("❌ Validasi Keamanan Gagal: Token CSRF Tidak Valid!");
    }

    $nama          = trim($_POST['nama'] ?? '');
    $nidn          = trim($_POST['nidn'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $program_studi = trim($_POST['program_studi'] ?? '');
    $status        = trim($_POST['status'] ?? 'aktif');
    
    // Data untuk relasi many-to-many
    $matakuliahIds = $_POST['matakuliah'] ?? []; // Berupa array ID
    $semester      = trim($_POST['semester'] ?? 'Ganjil');

    // 2. Validasi Server-Side
    if (empty($nama)) $errors[] = "Nama dosen wajib diisi.";
    if (!preg_match('/^[0-9]{10}$/', $nidn)) $errors[] = "NIDN harus tepat 10 digit angka.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format alamat email tidak valid.";

    // 3. Validasi & Proses Upload Foto Dosen (MIME Jenis Biner - Level 1)
    $fotoNama = $dosen['foto']; // Secara default pakai foto lama jika tidak ganti
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['foto']['tmp_name'];
        $fileSize = $_FILES['foto']['size'];

        // Batasi ukuran file (Maksimal 2 Megabyte)
        if ($fileSize > 2 * 1024 * 1024) {
            $errors[] = "Ukuran file foto terlalu besar! Maksimal adalah 2MB.";
        }

        // PENTING: Cek tipe MIME asli berdasarkan isi file (bukan ekstensi nama file)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fileTmp);
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeType, $allowedMime, true)) {
            $errors[] = "Format file ditolak! Foto harus berupa berkas JPG, PNG, atau WebP asli.";
        }

        // Jika lolos validasi file foto
        if (empty($errors)) {
            // Berikan nama unik baru di-hash SHA256 agar tidak tabrakan & aman dari path traversal
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $fotoNama = hash('sha256', (string)microtime(true)) . '.' . $ext;
            
            // Pindahkan berkas dari folder temporary internal ke folder uploads project
            move_uploaded_file($fileTmp, __DIR__ . '/../uploads/' . $fotoNama);
        }
    }

    // 4. Jika semua data inputan lolos validasi, eksekusi penyimpanan ke database
    if (empty($errors)) {
        $updateData = [
            'nidn'           => $nidn,
            'nama'           => $nama,
            'email'          => $email,
            'program_studi'  => $program_studi,
            'status'         => $status,
            'foto'           => $fotoNama
        ];

        // Eksekusi update data dosen + many-to-many via Database Transaction di kelas Repository
        if ($repo->update($id, $updateData, $matakuliahIds, $semester)) {
            // Sukses! Kembalikan ke dashboard utama dengan flag sukses
            header("Location: index.php?msg=success");
            exit;
        } else {
            $errors[] = "Gagal memperbarui database. Kemungkinan data NIDN atau Email sudah terdaftar pada dosen lain.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Dosen & Alokasi MK - SIAKAD MINI</title>
    <style>
        body { font-family: sans-serif; margin: 30px; background: #f8fafc; color: #334155; }
        .container { max-width: 650px; background: white; padding: 25px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #1e293b; }
        input[type="text"], input[type="email"], select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .btn-back { display: inline-block; margin-bottom: 15px; text-decoration: none; color: #2563eb; font-weight: bold; }
        .btn-submit { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: bold; margin-top: 15px; }
        .btn-submit:hover { background: #1d4ed8; }
        .error-box { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9rem; }
        .hint { font-size: 0.8rem; color: #64748b; margin-top: 3px; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">← Kembali ke Dashboard</a>
    <h2>Formulir Data Dosen & Penugasan Mengajar</h2>
    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 20px;">

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Terjadi Kesalahan Pengisian:</strong><br>
            <?php foreach ($errors as $err) echo "• " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "<br>"; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="nidn">NIDN Dosen (10 Digit Angka)</label>
            <input type="text" name="nidn" id="nidn" value="<?= htmlspecialchars($dosen['nidn'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="nama">Nama Lengkap beserta Gelar Akademik</label>
            <input type="text" name="nama" id="nama" value="<?= htmlspecialchars($dosen['nama'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Alamat Email Institusi / Resmi</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($dosen['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="program_studi">Homebase Program Studi</label>
            <select name="program_studi" id="program_studi" required>
                <option value="Teknik Informatika" <?= $dosen['program_studi'] === 'Teknik Informatika' ? 'selected' : '' ?>>Teknik Informatika</option>
                <option value="Sistem Informasi" <?= $dosen['program_studi'] === 'Sistem Informasi' ? 'selected' : '' ?>>Sistem Informasi</option>
                <option value="Teknik Elektro" <?= $dosen['program_studi'] === 'Teknik Elektro' ? 'selected' : '' ?>>Teknik Elektro</option>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status Mengajar Saat Ini</label>
            <select name="status" id="status">
                <option value="aktif" <?= $dosen['status'] === 'aktif' ? 'selected' : '' ?>>Aktif Mengajar</option>
                <option value="nonaktif" <?= $dosen['status'] === 'nonaktif' ? 'selected' : '' ?>>Non-Aktif / Cuti Sabbatical</option>
            </select>
        </div>

        <div class="form-group">
            <label for="foto">Perbarui Foto Profil Dosen</label>
            <input type="file" name="foto" id="foto" accept="image/jpeg, image/png, image/webp">
            <div class="hint">Kosongkan berkas ini apabila Anda tidak berniat mengganti foto yang sudah ada. Maksimal ukuran berkas 2MB (Format: JPG, PNG, WebP).</div>
        </div>

        <div style="background: #f1f5f9; padding: 15px; border-radius: 6px; margin-top: 25px; border-left: 4px solid #2563eb;">
            <h3 style="margin-top: 0; color: #1e293b;">🔗 Alokasi Penugasan Mata Kuliah</h3>
            
            <div class="form-group">
                <label for="semester">Periode Semester</label>
                <select name="semester" id="semester">
                    <option value="Ganjil">Semester Ganjil</option>
                    <option value="Genap">Semester Genap</option>
                </select>
            </div>

            <div class="form-group">
                <label for="matakuliah">Daftar Mata Kuliah Diampu</label>
                <select name="matakuliah[]" id="matakuliah" multiple style="height: 130px;">
                    <?php foreach ($allMataKuliah as $mk): ?>
                        <?php $isAssigned = in_array($mk['id'], $selectedMkIds, false); ?>
                        <option value="<?= (int)$mk['id'] ?>" <?= $isAssigned ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($mk['kode'], ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($mk['nama'], ENT_QUOTES, 'UTF-8') ?> — (<?= (int)$mk['sks'] ?> SKS)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">💡 <strong>Petunjuk Mac/Windows:</strong> Tahan tombol <strong>Ctrl (Windows)</strong> atau <strong>Command (Mac)</strong> sambil mengeklik mouse untuk memilih atau membatalkan penugasan lebih dari satu mata kuliah.</div>
            </div>
        </div>

        <button type="submit" class="btn-submit">Simpan Seluruh Perubahan</button>
    </form>
</div>

</body>
</html>