<?php
// File: public/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Menggunakan dirname(__DIR__) untuk memastikan path benar-benar mengarah ke root project
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Auth.php';

// ... sisa kode di bawahnya jangan diubah ...

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bikin CSRF token sekali pakai untuk keamanan form (Level 1)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Jika form dikirim lewat method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validasi CSRF Token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Token CSRF tidak valid!");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $db = Database::getConnection();
        
        // Coba login lewat class Auth OOP tadi
        if (Auth::login($db, $username, $password)) {
            // Ganti token setelah sukses biar aman
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Redirect ke dashboard utama
            header("Location: index.php");
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIAKAD MINI</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 350px; }
        h2 { text-align: center; color: #1f2937; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #4b5563; }
        input { width: 93%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; }
        button { width: 100%; padding: 0.7rem; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #1d4ed8; }
        .error { color: red; text-align: center; margin-bottom: 1rem; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>SIAKAD MINI</h2>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required autocomplete="off">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>
        </div>
        
        <button type="submit">Masuk</button>
    </form>
</div>

</body>
</html>