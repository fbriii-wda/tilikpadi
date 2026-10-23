<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';

$profileStatement = database()->prepare(
    'SELECT role.nama_role
     FROM `user` AS user
     LEFT JOIN role ON role.id_role = user.id_role
     WHERE user.id_user = ?
     LIMIT 1'
);
$profileUserId = (int) $_SESSION['user']['id'];
$profileStatement->bind_param('i', $profileUserId);
$profileStatement->execute();
$profileRole = $profileStatement->get_result()->fetch_column();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Administrator - Tilik Padi</title>

  <meta name="description" content="Profil Administrator - Tilik Padi">
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
          <li class="sidebar-menu-item">
            <a href="index.php" class="sidebar-menu-link" id="menu-overview" title="Ringkasan">
              <i class="bi bi-grid-fill"></i>
              <span>Dasbor</span>
            </a>
          </li>
        </ul>
      </div>

      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Komponen</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item"><a href="management_penyakit.php" class="sidebar-menu-link" id="menu-basictables" title="Manajemen Penyakit &amp; Solusi"><i class="bi bi-bug"></i><span>Manajemen Penyakit &amp; Solusi</span></a></li>
          <li class="sidebar-menu-item"><a href="management_pengguna.php" class="sidebar-menu-link" id="menu-uiforms" title="Manajemen Pengguna"><i class="bi bi-people"></i><span>Manajemen Pengguna</span></a></li>
          <li class="sidebar-menu-item"><a href="riwayat_pemantauan.php" class="sidebar-menu-link" id="menu-uibuttons" title="Riwayat Pemantauan"><i class="bi bi-journal-text"></i><span>Riwayat Pemantauan</span></a></li>
        </ul>
      </div>

      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Profil</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item"><a href="profil_administrator.php" class="sidebar-menu-link active" id="menu-blankpage" title="Profil Administrator"><i class="bi bi-person-circle"></i><span>Profil Administrator</span></a></li>
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
        <h1 class="page-title">Profil Administrator</h1>
        <p class="page-subtitle">Informasi akun yang sedang digunakan untuk mengelola portal Tilik Padi.</p>
      </div>
      <nav aria-label="navigasi jejak">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted-green" >Beranda</a></li>
          <li class="breadcrumb-item active text-main" aria-current="page">Profil Administrator</li>
        </ol>
      </nav>
    </div>

    <section class="card p-4 border-light shadow-sm" aria-labelledby="administrator-profile-heading">
      <div class="d-flex align-items-center gap-3 mb-4">
        <img src="assets/images/avatar.png" alt="" class="navbar-profile-img">
        <div>
          <h2 class="card-title mb-1" id="administrator-profile-heading"><?= escape_html((string) $_SESSION['user']['name']) ?></h2>
          <span class="badge text-bg-success"><?= escape_html(is_string($profileRole) ? $profileRole : 'Peran belum ditetapkan') ?></span>
        </div>
      </div>
      <dl class="row mb-0">
        <dt class="col-sm-3">Nama</dt>
        <dd class="col-sm-9"><?= escape_html((string) $_SESSION['user']['name']) ?></dd>
        <dt class="col-sm-3">Alamat email</dt>
        <dd class="col-sm-9"><?= escape_html((string) $_SESSION['user']['email']) ?></dd>
      </dl>
    </section>


    <footer class="footer-custom">
      <div class="footer-left">
        <span class="footer-logo">
          <img src="assets/images/favicon.png" alt="" aria-hidden="true"> Tilik Padi
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

  <script src="assets/js/dashboard.js?v=202610220123"></script>
</body>

</html>