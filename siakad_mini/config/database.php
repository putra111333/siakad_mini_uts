<?php
// File: config/database.php

class Database {
    private static ?PDO $instance = null;

    // Pakai private constructor biar ga bisa di-new dari luar (Singleton Pattern)
    private function __construct() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=localhost;dbname=siakad_mini;charset=utf8mb4";
            $username = "root";
            $password = ""; // Kosongkan kalau pake XAMPP default bawaan

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Error otomatis jadi Exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Hasil fetch otomatis array asosiatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Pakai prepared statement asli bawaan MySQL
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die("❌ Koneksi database gagal: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}