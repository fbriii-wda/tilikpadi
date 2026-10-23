<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Email atau kata sandi tidak valid.';
    } else {
        try {
            require_once __DIR__ . '/config/database.php';
            $statement = database()->prepare(
                'SELECT id_user, nama, email, password FROM `user` WHERE email = ? LIMIT 1'
            );
            $statement->bind_param('s', $email);
            $statement->execute();
            $user = $statement->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                $lastLoginAt = (new DateTimeImmutable(
                    'now',
                    new DateTimeZone('Asia/Jakarta')
                ))->format('Y-m-d H:i:s');
                $updateLastLogin = database()->prepare(
                    'UPDATE `user` SET last_login_at = ? WHERE id_user = ?'
                );
                $userId = (int) $user['id_user'];
                $updateLastLogin->bind_param('si', $lastLoginAt, $userId);
                $updateLastLogin->execute();

                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id_user'],
                    'name' => $user['nama'],
                    'email' => $user['email'],
                ];
                header('Location: index.php');
                exit;
            }

            $error = 'Email atau kata sandi salah.';
        } catch (mysqli_sql_exception $exception) {
            $error = 'Login gagal. Pastikan database tilikpadi dan tabel user sudah tersedia.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Masuk - Tilik Padi</title>
    
    <meta name="description" content="Halaman Masuk - Tilik Padi">
    <meta name="author" content="Tim Tilik Padi">
    
    <link rel="icon" type="image/png" href="assets/images/logo.jpg">
    
    <link rel="stylesheet" href="assets/libs/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/libs/bootstrap-icons/bootstrap-icons.css">
    
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="login-bg-shape login-bg-shape-1"></div>
        <div class="login-bg-shape login-bg-shape-2"></div>
        
        <div class="login-card">
            
            <a href="page-login.php" class="login-brand text-decoration-none">
                <img src="assets/images/favicon.png" alt="Logo Tilik Padi" class="brand-logo-image login-page-logo">
            </a>
            
            <p class="login-subtitle">Silakan masuk untuk mengakses dasbor.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= escape_html($error) ?></div>
            <?php endif; ?>
            
            <form action="page-login.php" method="POST" id="loginForm" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                
                <div class="login-form-group">
                    <label for="email" class="login-form-label">Alamat Email</label>
                    <div class="login-input-group">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="login-input" value="<?= escape_html($email) ?>" placeholder="email@gmail.com" required>
                    </div>
                </div>
                
                <div class="login-form-group">
                    <label for="password" class="login-form-label">Kata Sandi</label>
                    <div class="login-input-group">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="login-input login-input-password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle-btn" id="toggle-password" aria-label="Tampilkan kata sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="login-options">
                    <label class="custom-control-label">
                        <input type="checkbox" class="custom-checkbox-input" id="rememberMe">
                        <span>Ingat Saya</span>
                    </label>
                    <a href="#" class="forgot-password-link">Lupa Kata Sandi?</a>
                </div>
                
                <button type="submit" class="btn-login" id="btn-submit">
                    <span>Masuk ke Dasbor</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
                
            </form>
            
            <div class="login-divider">Atau masuk dengan</div>
            
            <div class="social-login-grid">
                <button class="btn-social" type="button" id="btn-google">
                    <i class="bi bi-google text-danger"></i>
                    <span>Google</span>
                </button>
                <button class="btn-social" type="button" id="btn-github">
                    <i class="bi bi-github"></i>
                    <span>GitHub</span>
                </button>
            </div>
            
            <p class="login-footer-text">
                Belum memiliki akun? <a href="register.php" id="link-register">Daftar Sekarang</a>
            </p>
            
        </div>
    </div>

    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>
