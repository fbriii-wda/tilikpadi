<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan nama dan alamat email yang valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Kata sandi harus terdiri dari minimal 8 karakter.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        try {
            require_once __DIR__ . '/config/database.php';
            $lastLoginAt = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Jakarta')
            ))->format('Y-m-d H:i:s');
            $statement = database()->prepare(
                'INSERT INTO `user` (nama, email, password, last_login_at) VALUES (?, ?, ?, ?)'
            );
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $statement->bind_param('ssss', $name, $email, $passwordHash, $lastLoginAt);
            $statement->execute();

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $statement->insert_id,
                'name' => $name,
                'email' => $email,
            ];
            header('Location: index.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $error = $exception->getCode() === 1062
                ? 'Alamat email tersebut sudah terdaftar.'
                : 'Registrasi gagal. Pastikan database tilikpadi dan tabel user sudah tersedia.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Tilik Padi</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
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
                <img src="assets/images/logo.jpg" alt="Logo Tilik Padi" class="brand-logo-image">
                <span>Tilik Padi</span>
            </a>
            <p class="login-subtitle">Buat akun untuk mengakses dasbor.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= escape_html($error) ?></div>
            <?php endif; ?>

            <form method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <div class="login-form-group">
                    <label for="name" class="login-form-label">Nama</label>
                    <div class="login-input-group">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" id="name" name="name" class="login-input" value="<?= escape_html($name) ?>" required>
                    </div>
                </div>
                <div class="login-form-group">
                    <label for="email" class="login-form-label">Email</label>
                    <div class="login-input-group">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="login-input" value="<?= escape_html($email) ?>" required>
                    </div>
                </div>
                <div class="login-form-group">
                    <label for="password" class="login-form-label">Kata Sandi (minimal 8 karakter)</label>
                    <div class="login-input-group">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="login-input" required minlength="8">
                    </div>
                </div>
                <div class="login-form-group">
                    <label for="password_confirmation" class="login-form-label">Konfirmasi Kata Sandi</label>
                    <div class="login-input-group">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="login-input" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <span>Daftar</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
            <p class="login-footer-text">
                Sudah punya akun? <a href="page-login.php">Masuk</a>
            </p>
        </div>
    </div>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
