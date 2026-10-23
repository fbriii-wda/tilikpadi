<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$userName = escape_html((string) $_SESSION['user']['name']);
$userEmail = escape_html((string) $_SESSION['user']['email']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dasbor Admin Tilik Padi</title>

  <meta name="description" content="Admin Tilik Padi - Dasbor dan Sistem Pengelolaan Tilik Padi">
  <meta name="author" content="Tim Tilik Padi">

  <link rel="icon" type="image/png" href="assets/images/logo.jpg">

  <link rel="stylesheet" href="assets/libs/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/libs/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/libs/apexcharts/apexcharts.css">
  <link rel="stylesheet" href="assets/libs/flatpickr/flatpickr.min.css">

  <link rel="stylesheet" href="assets/css/main.css?v=202610220112">
</head>

<body>

  <div class="sidebar-wrapper" id="sidebar">
    <a href="index.php" class="sidebar-brand">
      <img src="assets/images/logo.jpg" alt="Logo Tilik Padi" class="brand-logo-image">
      <span>Admin Tilik Padi</span>
    </a>

    <div class="flex-grow-1 overflow-y-auto">
      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Menu</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item">
            <a href="index.php" class="sidebar-menu-link active" id="menu-overview" title="Ringkasan">
              <i class="bi bi-grid-fill"></i>
              <span>Beranda</span>
            </a>
          </li>
        </ul>
      </div>

      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Manajemen</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item">
            <a href="management_penyakit.php" class="sidebar-menu-link" id="menu-basictables" title="Manajemen Penyakit &amp; Solusi">
              <i class="bi bi-bug"></i>
              <span>Manajemen Penyakit &amp; Solusi</span>
            </a>
          </li>
          <li class="sidebar-menu-item">
            <a href="management_pengguna.php" class="sidebar-menu-link" id="menu-uiforms" title="Manajemen Pengguna">
              <i class="bi bi-people"></i>
              <span>Manajemen Pengguna</span>
            </a>
          </li>
          <li class="sidebar-menu-item">
            <a href="riwayat_pemantauan.php" class="sidebar-menu-link" id="menu-uibuttons" title="Riwayat Pemantauan">
              <i class="bi bi-journal-text"></i>
              <span>Riwayat Pemantauan</span>
            </a>
          </li>
        </ul>
      </div>

      <div class="sidebar-menu-section">
        <div class="sidebar-menu-title">Profil</div>
        <ul class="sidebar-menu-list">
          <li class="sidebar-menu-item">
            <a href="profil_administrator.php" class="sidebar-menu-link" id="menu-blankpage" title="Profil Administrator">
              <i class="bi bi-person-circle"></i>
              <span>Profil Administrator</span>
            </a>
          </li>
        </ul>
      </div>
    </div>

    <div class="sidebar-profile">
      <img src="assets/images/logo.jpg" alt="Admin" class="sidebar-profile-img"
        onerror="this.onerror=null;this.src='assets/images/logo.jpg'">
      <div class="sidebar-profile-info">
        <div class="sidebar-profile-name"><?= $userName ?></div>
        <div class="sidebar-profile-email"><?= $userEmail ?></div>
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
            <span class="navbar-profile-name d-none d-md-inline"><?= $userName ?></span>
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
        <h1 class="page-title">Dashboard Administrator</h1>
        <p class="page-subtitle">Pantau aktivitas pengguna dan hasil deteksi penyakit tanaman padi.</p>
      </div>
      <button class="btn-date-picker" type="button" id="date-picker-trigger">
        <i class="bi bi-calendar4-event"></i>
        <span id="selected-date"></span>
        <i class="bi bi-chevron-down ms-1"></i>
      </button>
    </div>

    <div class="row g-4">

      <div class="col-12" id="scan-analytics">
        <div class="row g-4">
          <div class="col-md-4">
            <div class="card card-stat">
              <div class="card-header">
                <span class="stat-label">Total Pendaftar</span>
                <i class="bi bi-person-plus text-success" aria-hidden="true"></i>
              </div>
              <div class="stat-value" data-stat-value="registeredUsers" aria-live="polite">Memuat...</div>
              <div class="trend-badge trend-up" data-stat-note="registeredUsers">
                <i class="bi bi-people"></i>
                <span>Firebase Authentication · diperbarui tiap 60 detik</span>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card card-stat">
              <div class="card-header">
                <span class="stat-label">Total Pengguna Aktif</span>
                <i class="bi bi-person-check text-success" aria-hidden="true"></i>
              </div>
              <div class="stat-value" data-stat-value="activeUsers" aria-live="polite">Memuat...</div>
              <div class="trend-badge trend-up" data-stat-note="activeUsers">
                <i class="bi bi-clock-history"></i>
                <span>Login Firebase hari ini · diperbarui tiap 60 detik</span>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="card card-stat">
              <div class="card-header">
                <span class="stat-label">Jumlah Jenis Penyakit</span>
                <i class="bi bi-bug text-success" aria-hidden="true"></i>
              </div>
              <div class="stat-value" data-stat-value="diseaseTypes" aria-live="polite">Memuat...</div>
              <div class="trend-badge trend-up" data-stat-note="diseaseTypes">
                <i class="bi bi-cloud-check"></i>
                <span>Firebase · diperbarui tiap 60 detik</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-9 col-lg-8">

        <div class="row g-4">
          <div class="col-12">
            <div class="card mb-0">
              <div class="card-header mb-2">
                <h2 class="card-title">Aktivitas Pemindaian</h2>
                <div class="d-flex gap-3 align-items-center">
                  <div class="chart-legend-item">
                    <span class="legend-dot bg-forest-medium"></span>
                    <span class="chart-legend-label">Hasil scan per bulan</span>
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-baseline gap-2 mb-3">
                <span class="stat-value-amount" data-scan-total>0</span>
                <span class="trend-badge trend-up fs-xs" data-scan-year-label>hasil scan tahun ini</span>
              </div>
              <div id="scan-chart"></div>
            </div>
          </div>

          <div class="col-md-7 d-flex flex-column" id="scan-history">
            <div class="card h-100 flex-grow-1">
              <div class="card-header">
                <h2 class="card-title">Riwayat Pemindaian Terbaru</h2>
              </div>
              <div class="transaction-list" id="recent-detections" aria-live="polite">
                <p class="text-muted-green mb-0">Memuat riwayat pemindaian...</p>
              </div>
            </div>
          </div>

          <div class="col-md-5 d-flex flex-column">
            <div class="card h-100 flex-grow-1">
              <div class="card-header">
                <h2 class="card-title">Tingkat Keparahan Hasil Scan</h2>
              </div>
              <div class="progress-container" id="severity-summary" aria-live="polite">
                <p class="text-muted-green mb-0">Memuat ringkasan tingkat keparahan...</p>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="col-xl-3 col-lg-4">
        <div class="right-panel-wrapper d-flex flex-column gap-4 h-100">

          <div class="card flex-grow-1 d-flex flex-column justify-content-between mb-0">
            <div class="card-header mb-1">
              <h2 class="card-title">Distribusi Tingkat Keparahan</h2>
            </div>

            <div id="severity-chart"></div>

            <div class="chart-legends-container" id="severity-chart-legend" aria-live="polite">
              <span class="text-muted-green">Memuat distribusi...</span>
            </div>
          </div>

          <div class="promo-banner-card">
            <img class="promo-banner-bg-shape" src="assets/images/favicon.png" alt="">

            <h3 class="promo-title">Portal Admin Tilik Padi</h3>
            <p class="promo-desc">Portal ini merangkum pengguna, riwayat pemindaian, dan tingkat keparahan. Hasil scan muncul setelah proses deteksi terhubung.</p>
          </div>
        </div>
      </div>

    </div>

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
          <li><a href="index.php#scan-analytics" class="footer-link">Analitik Pemindaian</a></li>
          <li><a href="index.php#scan-history" class="footer-link">Riwayat Scan</a></li>
        </ul>
      </div>
    </footer>

  </div>

  <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
  <script src="assets/libs/flatpickr/flatpickr.min.js"></script>

  <script src="assets/js/dashboard.js?v=202610220111"></script>
</body>

</html>