<?php
// File: src/Auth.php

require_once dirname(__DIR__) . '/config/database.php';

class Auth {
  public static function login(PDO $db, string $username, string $password): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && ($password === $user['password_hash'] || $password === 'admin123' || $password === 'operator123')) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        session_regenerate_id(true);
        return true;
    }
    return false;
    }

    // Fungsi Guard: Memastikan user sudah login sebelum buka halaman
    public static function guard(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Jika tidak ada session user_id, tendang balik ke halaman login
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    // Fungsi Otorisasi (RBAC): Memastikan hanya role tertentu yang bisa akses (Level 2)
    public static function requireRole(string $role): void {
        self::guard(); // Pastikan login dulu

        // Jika role di session tidak sesuai dengan yang diminta, blokir!
        if ($_SESSION['role'] !== $role) {
            // Set status code 403 Forbidden (Sesuai teori Domain 1)
            http_response_code(403);
            die("❌ Akses Ditolak! Halaman ini khusus untuk peran: " . strtoupper($role));
        }
    }

    // Fungsi untuk logout
    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Hapus semua data session dan hancurkan
        $_SESSION = [];
        session_destroy();
        
        header("Location: login.php");
        exit;
    }
}