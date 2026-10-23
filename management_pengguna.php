<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/firebase.php';

$authorization = database()->prepare(
    'SELECT role.nama_role
     FROM `user` AS user
     LEFT JOIN role ON role.id_role = user.id_role
     WHERE user.id_user = ?
     LIMIT 1'
);
$currentUserId = (int) $_SESSION['user']['id'];
$authorization->bind_param('i', $currentUserId);
$authorization->execute();
if (strcasecmp((string) $authorization->get_result()->fetch_column(), 'Administrator') !== 0) {
    http_response_code(403);
    exit('Akses ditolak. Halaman manajemen pengguna hanya dapat diakses oleh Administrator.');
}

$userMessage = null;
$userMessageType = 'success';
$userError = null;
$userRecords = [];
$firebaseUsers = [];
$firebaseAuthError = null;
$roleOptions = [];
$userForm = [
    'id_user' => '',
    'nama' => '',
    'email' => '',
    'id_role' => '',
];

try {
    $roleOptions = database()->query('SELECT id_role, nama_role FROM role ORDER BY nama_role')->fetch_all(MYSQLI_ASSOC);
    if ($userForm['id_role'] === '') {
        foreach ($roleOptions as $roleOption) {
            if (strcasecmp((string) $roleOption['nama_role'], 'Petani') === 0) {
                $userForm['id_role'] = (string) $roleOption['id_role'];
                break;
            }
        }
    }
} catch (Throwable $exception) {
    error_log('User management role loading failed: ' . $exception->getMessage());
    $userError = 'Daftar peran tidak dapat dimuat dari MySQL.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'save') {
        $userForm = [
            'id_user' => trim((string) ($_POST['id_user'] ?? '')),
            'nama' => trim((string) ($_POST['nama'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'id_role' => trim((string) ($_POST['id_role'] ?? '')),
        ];
    }

    try {
        if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
        }

        $connection = database();
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $targetId = filter_var($_POST['id_user'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($targetId === false) {
                throw new InvalidArgumentException('ID pengguna tidak valid.');
            }
            if ($targetId === (int) $_SESSION['user']['id']) {
                throw new InvalidArgumentException('Akun yang sedang digunakan tidak dapat dihapus.');
            }

            $find = $connection->prepare(
                'SELECT user.id_user, role.nama_role
                 FROM `user` AS user
                 LEFT JOIN role ON role.id_role = user.id_role
                 WHERE user.id_user = ?'
            );
            $find->bind_param('i', $targetId);
            $find->execute();
            $target = $find->get_result()->fetch_assoc();
            if (!$target) {
                throw new InvalidArgumentException('Pengguna tidak ditemukan.');
            }

            if (strcasecmp((string) $target['nama_role'], 'Administrator') === 0) {
                $adminCount = $connection->query(
                    "SELECT COUNT(*) FROM `user` AS user INNER JOIN role ON role.id_role = user.id_role WHERE role.nama_role = 'Administrator'"
                )->fetch_row()[0];
                if ((int) $adminCount <= 1) {
                    throw new InvalidArgumentException('Administrator terakhir tidak dapat dihapus.');
                }
            }

            $delete = $connection->prepare('DELETE FROM `user` WHERE id_user = ?');
            $delete->bind_param('i', $targetId);
            $delete->execute();

            try {
                firebase_firestore_delete_user((string) $targetId);
                $_SESSION['user_management_flash'] = ['type' => 'success', 'message' => 'Pengguna berhasil dihapus dan disinkronkan ke Firebase.'];
            } catch (Throwable $exception) {
                error_log('Firebase user deletion sync failed: ' . $exception->getMessage());
                $_SESSION['user_management_flash'] = [
                    'type' => 'warning',
                    'message' => 'Pengguna berhasil dihapus dari MySQL, tetapi penghapusan sinkronisasi Firebase gagal.',
                ];
            }

            header('Location: management_pengguna.php');
            exit;
        }

        if ($action !== 'save') {
            throw new InvalidArgumentException('Aksi pengguna tidak dikenali.');
        }

        $idInput = $userForm['id_user'];
        $userId = $idInput === ''
            ? null
            : filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $roleId = filter_var($userForm['id_role'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $password = (string) ($_POST['password'] ?? '');

        if ($userId === false || $roleId === false) {
            throw new InvalidArgumentException('ID pengguna atau peran tidak valid.');
        }
        if ($userForm['nama'] === '' || mb_strlen($userForm['nama'], 'UTF-8') > 100) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
        }
        if (!filter_var($userForm['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($userForm['email'], 'UTF-8') > 100) {
            throw new InvalidArgumentException('Alamat email tidak valid atau melebihi 100 karakter.');
        }
        if ($userId === null && strlen($password) < 8) {
            throw new InvalidArgumentException('Kata sandi akun baru minimal 8 karakter.');
        }
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 1024)) {
            throw new InvalidArgumentException('Kata sandi baru harus terdiri dari 8 sampai 1024 karakter.');
        }

        $selectedRole = null;
        foreach ($roleOptions as $roleOption) {
            if ((int) $roleOption['id_role'] === $roleId) {
                $selectedRole = (string) $roleOption['nama_role'];
                break;
            }
        }
        if ($selectedRole === null) {
            throw new InvalidArgumentException('Peran pengguna tidak tersedia.');
        }

        if ($userId === null) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $connection->prepare(
                'INSERT INTO `user` (nama, email, password, id_role) VALUES (?, ?, ?, ?)'
            );
            $insert->bind_param('sssi', $userForm['nama'], $userForm['email'], $passwordHash, $roleId);
            $insert->execute();
            $userId = $insert->insert_id;
            $successMessage = 'Akun pengguna berhasil dibuat.';
        } else {
            $find = $connection->prepare('SELECT id_user FROM `user` WHERE id_user = ?');
            $find->bind_param('i', $userId);
            $find->execute();
            $existingUser = $find->get_result()->fetch_assoc();
            if (!$existingUser) {
                throw new InvalidArgumentException('Pengguna yang akan diubah tidak ditemukan.');
            }

            $existingRoleStatement = $connection->prepare(
                'SELECT role.nama_role
                 FROM `user` AS user
                 LEFT JOIN role ON role.id_role = user.id_role
                 WHERE user.id_user = ?'
            );
            $existingRoleStatement->bind_param('i', $userId);
            $existingRoleStatement->execute();
            $existingRole = (string) $existingRoleStatement->get_result()->fetch_column();
            if (
                strcasecmp($existingRole, 'Administrator') === 0
                && strcasecmp($selectedRole, 'Administrator') !== 0
            ) {
                $adminCount = $connection->query(
                    "SELECT COUNT(*) FROM `user` AS user INNER JOIN role ON role.id_role = user.id_role WHERE role.nama_role = 'Administrator'"
                )->fetch_row()[0];
                if ((int) $adminCount <= 1) {
                    throw new InvalidArgumentException('Peran Administrator terakhir tidak dapat dicabut.');
                }
            }

            if ($password === '') {
                $update = $connection->prepare(
                    'UPDATE `user` SET nama = ?, email = ?, id_role = ? WHERE id_user = ?'
                );
                $update->bind_param('ssii', $userForm['nama'], $userForm['email'], $roleId, $userId);
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $connection->prepare(
                    'UPDATE `user` SET nama = ?, email = ?, password = ?, id_role = ? WHERE id_user = ?'
                );
                $update->bind_param('sssii', $userForm['nama'], $userForm['email'], $passwordHash, $roleId, $userId);
            }
            $update->execute();
            $successMessage = 'Data pengguna berhasil diperbarui.';
        }

        try {
            firebase_firestore_upsert_user((string) $userId, [
                'name' => $userForm['nama'],
                'email' => $userForm['email'],
                'role_id' => $roleId,
                'role' => $selectedRole,
            ]);
            if ((int) $_SESSION['user']['id'] === $userId) {
                $_SESSION['user']['name'] = $userForm['nama'];
                $_SESSION['user']['email'] = $userForm['email'];
            }
            $_SESSION['user_management_flash'] = ['type' => 'success', 'message' => $successMessage . ' Profil tersinkronisasi ke Firebase.'];
        } catch (Throwable $exception) {
            error_log('Firebase user profile sync failed: ' . $exception->getMessage());
            if ((int) $_SESSION['user']['id'] === $userId) {
                $_SESSION['user']['name'] = $userForm['nama'];
                $_SESSION['user']['email'] = $userForm['email'];
            }
            $_SESSION['user_management_flash'] = [
                'type' => 'warning',
                'message' => $successMessage . ' Sinkronisasi Firebase gagal; data MySQL tetap tersimpan.',
            ];
        }

        header('Location: management_pengguna.php');
        exit;
    } catch (InvalidArgumentException $exception) {
        $userMessage = $exception->getMessage();
        $userMessageType = 'danger';
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() === 1062) {
            $userMessage = 'Alamat email tersebut sudah digunakan akun lain.';
            $userMessageType = 'danger';
        } else {
            error_log('User management database operation failed: ' . $exception->getMessage());
            $userMessage = 'Perubahan pengguna gagal disimpan di MySQL.';
            $userMessageType = 'danger';
        }
    } catch (Throwable $exception) {
        error_log('User management operation failed: ' . $exception->getMessage());
        $userMessage = 'Operasi pengguna gagal. Periksa konfigurasi basis data.';
        $userMessageType = 'danger';
    }
}

if (isset($_SESSION['user_management_flash'])) {
    $userMessage = $_SESSION['user_management_flash']['message'];
    $userMessageType = $_SESSION['user_management_flash']['type'];
    unset($_SESSION['user_management_flash']);
}

try {
    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId !== false) {
            $find = database()->prepare('SELECT id_user, nama, email, id_role FROM `user` WHERE id_user = ?');
            $find->bind_param('i', $editId);
            $find->execute();
            $existingUser = $find->get_result()->fetch_assoc();
            if ($existingUser) {
                $userForm = $existingUser;
            } else {
                $userMessage = 'Pengguna yang akan diubah tidak ditemukan.';
                $userMessageType = 'warning';
            }
        } else {
            $userMessage = 'ID pengguna tidak valid.';
            $userMessageType = 'warning';
        }
    }

    $userRecords = database()->query(
        'SELECT user.id_user, user.nama, user.email, user.id_role, role.nama_role, user.last_login_at
         FROM `user` AS user
         LEFT JOIN role ON role.id_role = user.id_role
         ORDER BY user.id_user DESC'
    )->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    error_log('User management list loading failed: ' . $exception->getMessage());
    $userError = 'Daftar akun tidak dapat dimuat dari MySQL.';
}

try {
    $firebaseUsers = firebase_auth_list_users();
} catch (Throwable $exception) {
    error_log('Firebase Authentication user list failed: ' . $exception->getMessage());
    $firebaseAuthError = 'Daftar Firebase Authentication gagal dimuat. Pastikan API Firebase Authentication aktif dan service account memiliki izin Firebase Authentication Admin.';
}

$mysqlUsersByEmail = [];
foreach ($userRecords as $userRecord) {
    $mysqlUsersByEmail[mb_strtolower((string) $userRecord['email'], 'UTF-8')] = $userRecord;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Pengguna - Tilik Padi</title>

  <meta name="description" content="Kelola akun petani dan administrator.">
  <meta name="author" content="Tim Tilik Padi">

  <link rel="icon" type="image/png" href="assets/images/logo.jpg">

  <link rel="stylesheet" href="assets/libs/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/libs/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/libs/apexcharts/apexcharts.css">
  <link rel="stylesheet" href="assets/libs/flatpickr/flatpickr.min.css">

  <link rel="stylesheet" href="assets/css/main.css">
</head>

<body>

  <div class="sidebar-wrapper" id="sidebar">
    <a href="index.php" class="sidebar-brand">
      <img src="assets/images/logo.jpg" alt="Logo Tilik Padi" class="brand-logo-image">
      <span>Tilik Padi</span>
    </a>

    <div class="flex-grow-1 overflow-y-auto">
      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Menu</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item"><a href="index.php" class="sidebar-menu-link" id="menu-overview" title="Ringkasan"><i class="bi bi-grid-fill"></i><span>Dasbor</span></a></li>
        </ul>
      </div>
      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Komponen</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item"><a href="management_penyakit.php" class="sidebar-menu-link" id="menu-basictables" title="Manajemen Penyakit &amp; Solusi"><i class="bi bi-bug"></i><span>Manajemen Penyakit &amp; Solusi</span></a></li>
          <li class="sidebar-menu-item"><a href="management_pengguna.php" class="sidebar-menu-link active" id="menu-uiforms" title="Manajemen Pengguna"><i class="bi bi-people"></i><span>Manajemen Pengguna</span></a></li>
          <li class="sidebar-menu-item"><a href="riwayat_pemantauan.php" class="sidebar-menu-link" id="menu-uibuttons" title="Riwayat Pemantauan"><i class="bi bi-journal-text"></i><span>Riwayat Pemantauan</span></a></li>
        </ul>
      </div>
      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Profil</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item"><a href="profil_administrator.php" class="sidebar-menu-link" id="menu-blankpage" title="Profil Administrator"><i class="bi bi-person-circle"></i><span>Profil Administrator</span></a></li>
        </ul>
      </div>
    </div>

    <div class="sidebar-profile">
      <img src="assets/images/logo.jpg" alt="Admin" class="sidebar-profile-img"
        onerror="this.onerror=null;this.src='assets/images/logo.jpg'">
      <div class="sidebar-profile-info">
        <div class="sidebar-profile-name"><?= escape_html((string) $_SESSION['user']['name']) ?></div>
        <div class="sidebar-profile-email"><?= escape_html((string) $_SESSION['user']['email']) ?></div>
      </div>
    </div>
  </div>


  <div class="main-wrapper">

    <header class="navbar-custom">
      <div class="navbar-left">
        <button class="btn-desktop-toggle d-none d-xl-flex align-items-center justify-content-center me-3"
          id="desktop-sidebar-toggle" aria-label="Ciutkan menu samping">
          <i class="bi bi-chevron-bar-left"></i>
        </button>
        <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Buka atau tutup navigasi">
          <i class="bi bi-list"></i>
        </button>

      </div>

      <div class="navbar-search-wrapper">
        <input type="text" class="navbar-search-input" placeholder="Cari data pemindaian..." id="main-search">
        <button class="navbar-search-btn" aria-label="Cari">
          <i class="bi bi-search"></i>
        </button>
      </div>

      <div class="navbar-actions">
        <button class="navbar-action-btn me-1" aria-label="Aktifkan atau nonaktifkan layar penuh" id="btn-fullscreen">
          <i class="bi bi-arrows-fullscreen"></i>
        </button>
        <div class="dropdown ms-2">
          <button class="navbar-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
            aria-expanded="false" id="profile-dropdown">
            <img src="assets/images/logo.jpg" alt="Foto Profil" class="navbar-profile-img">
            <span class="navbar-profile-name d-none d-md-inline"><?= escape_html((string) $_SESSION['user']['name']) ?></span>
            <i class="bi bi-chevron-down navbar-profile-caret"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-profile" aria-labelledby="profile-dropdown">
            <li class="dropdown-header">Selamat datang!</li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i>
                Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>

    <div class="page-header">
      <div>
        <h1 class="page-title">Manajemen Pengguna</h1>
        <p class="page-subtitle">Kelola akun petani dan administrator.</p>
      </div>
      <nav aria-label="navigasi jejak">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted-green">Beranda</a></li>
          <li class="breadcrumb-item text-muted-green">Manajemen</li>
          <li class="breadcrumb-item active text-main" aria-current="page">Pengguna</li>
        </ol>
      </nav>
    </div>

    <?php if ($userMessage !== null): ?>
      <div class="alert alert-<?= escape_html($userMessageType) ?>" role="alert"><?= escape_html($userMessage) ?></div>
    <?php endif; ?>
    <?php if ($userError !== null): ?>
      <div class="alert alert-danger" role="alert"><?= escape_html($userError) ?> Pastikan MySQL Laragon aktif dan tabel peran sudah tersedia.</div>
    <?php endif; ?>
    <?php if ($firebaseAuthError !== null): ?>
      <div class="alert alert-warning" role="alert"><?= escape_html($firebaseAuthError) ?> Periksa juga ekstensi PHP OpenSSL.</div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="user-form-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <h2 class="h5 mb-1" id="user-form-title"><?= $userForm['id_user'] !== '' ? 'Ubah Akun Pengguna' : 'Tambah Akun Pengguna' ?></h2>
            <p class="text-muted mb-0">Akun portal tersimpan di MySQL; akun aplikasi ditampilkan dari Firebase Authentication. Kata sandi tidak ditampilkan.</p>
          </div>
          <?php if ($userForm['id_user'] !== ''): ?>
            <a href="management_pengguna.php" class="btn btn-outline-secondary btn-sm">Batal mengubah</a>
          <?php endif; ?>
        </div>

        <form method="post" action="management_pengguna.php" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id_user" value="<?= escape_html((string) $userForm['id_user']) ?>">
          <div class="col-12 col-lg-6">
            <label class="form-label" for="user-name">Nama Lengkap <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="user-name" name="nama" maxlength="100" required value="<?= escape_html((string) $userForm['nama']) ?>" placeholder="Nama petani atau pengelola">
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="user-email">Email Akun <span class="text-danger">*</span></label>
            <input class="form-control" type="email" id="user-email" name="email" maxlength="100" required value="<?= escape_html((string) $userForm['email']) ?>" placeholder="nama@contoh.com" autocomplete="email">
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="user-role">Peran Pengguna <span class="text-danger">*</span></label>
            <select class="form-select" id="user-role" name="id_role" required>
              <option value="" disabled <?= $userForm['id_role'] === '' ? 'selected' : '' ?>>Pilih peran...</option>
              <?php foreach ($roleOptions as $roleOption): ?>
                <option value="<?= (int) $roleOption['id_role'] ?>" <?= (string) $userForm['id_role'] === (string) $roleOption['id_role'] ? 'selected' : '' ?>><?= escape_html((string) $roleOption['nama_role']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="user-password">Kata Sandi <?= $userForm['id_user'] === '' ? '<span class="text-danger">*</span>' : '(opsional)' ?></label>
            <input class="form-control" type="password" id="user-password" name="password" minlength="8" <?= $userForm['id_user'] === '' ? 'required' : '' ?> autocomplete="new-password" placeholder="<?= $userForm['id_user'] === '' ? 'Minimal 8 karakter' : 'Kosongkan jika tidak ingin mengubah' ?>">
            <div class="form-text">Kata sandi dan hash tidak pernah ditampilkan atau dikirim ke Firebase.</div>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-success" type="submit"><i class="bi bi-person-check me-1"></i><?= $userForm['id_user'] !== '' ? 'Simpan Perubahan' : 'Buat Akun' ?></button>
          </div>
        </form>
      </div>
    </section>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="firebase-user-list-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1" id="firebase-user-list-title">Pengguna Aplikasi (Firebase Authentication)</h2>
            <p class="text-muted mb-0">Menampilkan <?= count($firebaseUsers) ?> akun Firebase<?= count($firebaseUsers) === 1000 ? ' pertama (batas 1.000)' : '' ?>. Kredensial kata sandi tidak tersedia untuk ditampilkan.</p>
          </div>
          <div class="input-group" style="max-width: 320px">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input class="form-control" type="search" id="firebase-user-search" placeholder="Cari UID, nama, atau email..." aria-label="Cari akun Firebase">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th scope="col">Nama</th>
                <th scope="col">Email / Firebase UID</th>
                <th scope="col">Status Akun</th>
                <th scope="col">Terdaftar</th>
                <th scope="col">Login Terakhir</th>
                <th scope="col">Akun Portal</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($firebaseAuthError !== null): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Daftar Firebase Authentication tidak tersedia.</td></tr>
              <?php elseif ($firebaseUsers === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada akun di Firebase Authentication.</td></tr>
              <?php else: ?>
                <?php foreach ($firebaseUsers as $firebaseUser): ?>
                  <?php
                    $firebaseEmailKey = mb_strtolower((string) ($firebaseUser['email'] ?? ''), 'UTF-8');
                    $linkedMysqlUser = $firebaseEmailKey !== '' ? ($mysqlUsersByEmail[$firebaseEmailKey] ?? null) : null;
                    $firebaseCreatedAt = $firebaseUser['created_at']
                        ->setTimezone(new DateTimeZone('Asia/Jakarta'))
                        ->format('d/m/Y H:i');
                    $firebaseLastLoginAt = $firebaseUser['last_login_at'] instanceof DateTimeImmutable
                        ? $firebaseUser['last_login_at']->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i')
                        : null;
                  ?>
                  <tr data-firebase-user-row>
                    <td><strong><?= escape_html((string) ($firebaseUser['display_name'] ?: 'Nama belum diisi')) ?></strong></td>
                    <td>
                      <div><?= escape_html((string) ($firebaseUser['email'] ?? 'Email tidak tersedia')) ?></div>
                      <div class="small text-muted">UID: <?= escape_html((string) $firebaseUser['uid']) ?></div>
                    </td>
                    <td>
                      <span class="badge text-bg-<?= $firebaseUser['disabled'] ? 'secondary' : 'success' ?>"><?= $firebaseUser['disabled'] ? 'Dinonaktifkan' : 'Aktif' ?></span>
                      <div class="small text-muted mt-1"><?= $firebaseUser['email_verified'] ? 'Email terverifikasi' : 'Email belum terverifikasi' ?></div>
                    </td>
                    <td><?= escape_html($firebaseCreatedAt) ?></td>
                    <td><?= $firebaseLastLoginAt !== null ? escape_html($firebaseLastLoginAt) : '<span class="text-muted">Belum login</span>' ?></td>
                    <td>
                      <?php if ($linkedMysqlUser !== null): ?>
                        <span class="badge text-bg-success">Terhubung</span>
                        <div class="small text-muted mt-1"><?= escape_html((string) ($linkedMysqlUser['nama_role'] ?? 'Peran belum ditentukan')) ?></div>
                      <?php else: ?>
                        <span class="badge text-bg-warning">Belum terhubung</span>
                        <div class="small text-muted mt-1">Belum ada email yang sama di MySQL</div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr id="firebase-users-no-results" hidden><td colspan="6" class="text-center text-muted py-4">Tidak ada akun Firebase yang cocok dengan pencarian.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="card border-0 shadow-sm" aria-labelledby="user-list-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1" id="user-list-title">Akun Portal (MySQL)</h2>
            <p class="text-muted mb-0">Total <?= count($userRecords) ?> akun untuk login ke portal web. Kata sandi tidak ditampilkan.</p>
          </div>
          <div class="input-group" style="max-width: 320px">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input class="form-control" type="search" id="user-search" placeholder="Cari nama, email, atau peran..." aria-label="Cari pengguna">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th scope="col">Pengguna</th>
                <th scope="col">Email / ID Akun</th>
                <th scope="col">Peran</th>
                <th scope="col">Login Terakhir</th>
                <th scope="col" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($userRecords === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada akun pengguna.</td></tr>
              <?php else: ?>
                <?php foreach ($userRecords as $userRecord): ?>
                  <tr data-user-row>
                    <td><strong><?= escape_html((string) $userRecord['nama']) ?></strong></td>
                    <td><div><?= escape_html((string) $userRecord['email']) ?></div><div class="small text-muted">ID: <?= (int) $userRecord['id_user'] ?></div></td>
                    <td><span class="badge text-bg-success"><?= escape_html((string) ($userRecord['nama_role'] ?? 'Belum ditentukan')) ?></span></td>
                    <td><?= $userRecord['last_login_at'] ? escape_html((string) $userRecord['last_login_at']) : '<span class="text-muted">Belum pernah</span>' ?></td>
                    <td>
                      <div class="d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="management_pengguna.php?edit=<?= (int) $userRecord['id_user'] ?>" aria-label="Ubah <?= escape_html((string) $userRecord['nama']) ?>"><i class="bi bi-pencil"></i></a>
                        <?php if ((int) $userRecord['id_user'] !== (int) $_SESSION['user']['id']): ?>
                          <form method="post" action="management_pengguna.php" onsubmit="return confirm('Hapus akun <?= escape_html((string) $userRecord['nama']) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_user" value="<?= (int) $userRecord['id_user'] ?>">
                            <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Hapus <?= escape_html((string) $userRecord['nama']) ?>"><i class="bi bi-trash"></i></button>
                          </form>
                        <?php else: ?>
                          <button class="btn btn-outline-secondary btn-sm" type="button" disabled aria-label="Akun yang sedang digunakan"><i class="bi bi-shield-lock"></i></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr id="users-no-results" hidden><td colspan="5" class="text-center text-muted py-4">Tidak ada akun yang cocok dengan pencarian.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <script>
      (() => {
        const search = document.getElementById('user-search');
        const rows = Array.from(document.querySelectorAll('[data-user-row]'));
        const noResults = document.getElementById('users-no-results');
        search?.addEventListener('input', () => {
          const query = search.value.trim().toLocaleLowerCase('id');
          let visibleCount = 0;
          rows.forEach((row) => {
            const matches = row.textContent.toLocaleLowerCase('id').includes(query);
            row.hidden = !matches;
            visibleCount += matches ? 1 : 0;
          });
          if (noResults) noResults.hidden = visibleCount > 0;
        });

        const firebaseSearch = document.getElementById('firebase-user-search');
        const firebaseRows = Array.from(document.querySelectorAll('[data-firebase-user-row]'));
        const firebaseNoResults = document.getElementById('firebase-users-no-results');
        firebaseSearch?.addEventListener('input', () => {
          const query = firebaseSearch.value.trim().toLocaleLowerCase('id');
          let visibleCount = 0;
          firebaseRows.forEach((row) => {
            const matches = row.textContent.toLocaleLowerCase('id').includes(query);
            row.hidden = !matches;
            visibleCount += matches ? 1 : 0;
          });
          if (firebaseNoResults) firebaseNoResults.hidden = visibleCount > 0;
        });
      })();
    </script>

    <footer class="footer-custom">
      <div class="footer-left">
        <span class="footer-logo">
          <img src="assets/images/logo.jpg" alt="Logo Tilik Padi"> Tilik Padi
        </span>
        <span class="footer-separator">|</span>
        <span class="footer-copy">&copy; 2026 Dibuat oleh Admin Tilik Padi</span>
      </div>
      <div class="footer-right">
        <ul class="footer-links">
          <li><a href="index.php" class="footer-link">Beranda</a></li>
          <li><a href="index.php" class="footer-link">Analitik Pemindaian</a></li>
          <li><a href="riwayat_pemantauan.php" class="footer-link">Riwayat Scan</a></li>
        </ul>
      </div>
    </footer>

  </div>

  <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
  <script src="assets/libs/flatpickr/flatpickr.min.js"></script>

  <script src="assets/js/dashboard.js"></script>
</body>

</html>