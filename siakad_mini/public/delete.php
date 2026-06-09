<?php
// File: public/delete.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/DosenRepository.php';

// LEVEL 2 SECURITY: Hanya boleh dieksekusi oleh ADMIN di sisi server
Auth::requireRole('admin');

$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

// LEVEL 1 SECURITY: Validasi token anti hacking CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    die("Error: Serangan CSRF Terdeteksi! Operasi penghapusan ditolak.");
}

if ($id > 0) {
    $db = Database::getConnection();
    $repo = new DosenRepository($db);
    
    // Eksekusi Soft Delete (Data cuma ditandai terhapus)
    $repo->softDelete($id);
}

// Balikkan halaman ke dashboard utama
header("Location: index.php");
exit;