<?php
// File: public/export_excel.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Auth.php';

// Pastikan hanya admin/operator yang bisa download
$db = Database::getConnection();

// Set header biar browser mendownloadnya sebagai file Excel (CSV)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data_dosen_siakad.csv');

// Buka output stream PHP
$output = fopen('php://output', 'w');

// Tulis baris judul kolom (Header) di Excel
fputcsv($output, ['NIDN', 'Nama Lengkap', 'Email', 'Program Studi', 'Status']);

// Ambil data dari database (hanya yang belum di-soft delete)
$query = "SELECT nidn, nama_lengkap, email, program_studi, status FROM dosen WHERE deleted_at IS NULL";
$stmt = $db->query($query);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit();