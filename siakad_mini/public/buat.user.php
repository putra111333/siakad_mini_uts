<?php
// File: public/buat_user.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = Database::getConnection();
    
    // Kosongkan tabel user lama biar bersih
    $db->exec("TRUNCATE TABLE users");
    
    // Biarkan PHP laptop lu sendiri yang generate hash-nya secara natural
    $hashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
    $hashOperator = password_hash('admin123', PASSWORD_BCRYPT); // operator kita samakan dulu biar gampang
    
    // Masukkan ke database
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $stmt->execute(['admin', $hashAdmin, 'admin']);
    $stmt->execute(['operator', $hashOperator, 'operator']);
    
    echo "<h2>✅ Sukses Gembok Database!</h2>";
    echo "<p>User admin & operator berhasil dibuat pakai hash asli laptop lu.</p>";
    echo "<p><a href='login.php'>Silakan Klik Disini untuk Login kembali</a></p>";

} catch (Exception $e) {
    die("Gagal: " . $e->getMessage());
}