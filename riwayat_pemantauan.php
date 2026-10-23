<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/firebase_storage.php';
require_once __DIR__ . '/includes/history_excel_export.php';

$historyRecords = [];
$historyError = null;
$historyStorageWarning = null;
$historyStorageReady = is_string(getenv('FIREBASE_STORAGE_BUCKET'))
    && trim((string) getenv('FIREBASE_STORAGE_BUCKET')) !== '';

if (($_GET['export'] ?? '') === 'excel') {
    history_excel_export(database());
}

try {
    $historyRecords = database()->query(
        'SELECT detection.id_detection, detection.id_user, detection.image_path,
            detection.nama_penyakit, detection.confidence, detection.id_keparahan,
            detection.tanggal_deteksi, detection.infected_ratio_percent,
            user.nama AS user_name, user.email AS user_email,
            severity.label_keparahan, severity.deskripsi AS severity_description
         FROM detection
         LEFT JOIN `user` AS user ON user.id_user = detection.id_user
         LEFT JOIN tingkat_keparahan AS severity ON severity.id_keparahan = detection.id_keparahan
         ORDER BY detection.tanggal_deteksi DESC, detection.id_detection DESC
         LIMIT 100'
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($historyRecords as &$historyRecord) {
        $historyRecord['image_url'] = null;
        $imagePath = trim((string) ($historyRecord['image_path'] ?? ''));
        if ($historyStorageReady && $imagePath !== '') {
            try {
                $historyRecord['image_url'] = firebase_storage_detection_image_url($imagePath);
            } catch (Throwable $exception) {
                error_log('Scan history image URL generation failed: ' . $exception->getMessage());
                $historyStorageReady = false;
                $historyStorageWarning = 'Sebagian gambar tidak dapat dimuat dari Firebase Storage. Periksa FIREBASE_STORAGE_BUCKET, izin Storage, dan nilai detection.image_path.';
            }
        } elseif ($historyStorageReady === false && $imagePath !== '') {
            $historyStorageWarning = 'Firebase Storage belum dikonfigurasi; gambar scan tidak dapat ditampilkan. Atur FIREBASE_STORAGE_BUCKET dan mulai ulang Apache/Laragon.';
        }
    }
    unset($historyRecord);
} catch (Throwable $exception) {
    error_log('Scan history loading failed: ' . $exception->getMessage());
    $historyError = 'Riwayat pemindaian belum dapat dimuat. Pastikan MySQL aktif dan migrasi persentase area terinfeksi telah dijalankan.';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Pemantauan - Tilik Padi</title>

  <meta name="description" content="Tinjau rekam jejak pemindaian dan hasil deteksi penyakit tanaman padi.">
  <meta name="author" content="Tim Tilik Padi">

  <link rel="icon" type="image/png" href="assets/images/logo.jpg">

  <link rel="stylesheet" href="assets/libs/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/libs/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/libs/apexcharts/apexcharts.css">
  <link rel="stylesheet" href="assets/libs/flatpickr/flatpickr.min.css">

  <link rel="stylesheet" href="assets/css/main.css?v=202610220612">
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
          <li class="sidebar-menu-item"><a href="management_pengguna.php" class="sidebar-menu-link" id="menu-uiforms" title="Manajemen Pengguna"><i class="bi bi-people"></i><span>Manajemen Pengguna</span></a></li>
          <li class="sidebar-menu-item"><a href="riwayat_pemantauan.php" class="sidebar-menu-link active" id="menu-uibuttons" title="Riwayat Pemantauan"><i class="bi bi-journal-text"></i><span>Riwayat Pemantauan</span></a></li>
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
        <a class="history-export-btn me-1" href="riwayat_pemantauan.php?export=excel" aria-label="Ekspor riwayat pemantauan ke Excel" title="Ekspor riwayat pemantauan ke Excel">
          <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
          <span>Excel</span>
        </a>
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
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i>
                Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>

    <div class="page-header">
      <div>
        <h1 class="page-title">Riwayat Pemantauan</h1>
        <p class="page-subtitle">Tinjau waktu pemindaian, citra daun, hasil klasifikasi, keyakinan model, dan tingkat keparahan.</p>
      </div>
      <nav aria-label="navigasi jejak">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted-green" >Beranda</a></li>
          <li class="breadcrumb-item text-muted-green">Pemantauan</li>
          <li class="breadcrumb-item active text-main" aria-current="page">Riwayat Scan</li>
        </ol>
      </nav>
    </div>

    <?php if ($historyError !== null): ?>
      <div class="alert alert-danger" role="alert"><?= escape_html($historyError) ?> Jalankan migrasi <code>database/migrations/20261022_add_infected_ratio_to_detection.sql</code> satu kali melalui phpMyAdmin.</div>
    <?php endif; ?>
    <?php if ($historyStorageWarning !== null): ?>
      <div class="alert alert-warning" role="alert"><?= escape_html($historyStorageWarning) ?></div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm" aria-labelledby="scan-history-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1" id="scan-history-title">Rekam Jejak Pemindaian</h2>
            <p class="text-muted mb-0">Menampilkan <?= count($historyRecords) ?> pemindaian terbaru dari MySQL.</p>
          </div>
          <div class="input-group" style="max-width: 340px">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input class="form-control" type="search" id="history-search" placeholder="Cari penyakit atau pengguna..." aria-label="Cari riwayat pemindaian">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th scope="col">Waktu Pemindaian</th>
                <th scope="col">Citra Daun</th>
                <th scope="col">Pengguna</th>
                <th scope="col">Hasil Penyakit</th>
                <th scope="col">Tingkat Kepercayaan</th>
                <th scope="col">Keparahan Infeksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($historyRecords === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">Belum ada rekam jejak pemindaian di database.</td></tr>
              <?php else: ?>
                <?php foreach ($historyRecords as $historyRecord): ?>
                  <tr data-history-row>
                    <td>
                      <?php if (is_string($historyRecord['tanggal_deteksi'])): ?>
                        <time datetime="<?= escape_html((string) $historyRecord['tanggal_deteksi']) ?>"><?= escape_html((new DateTimeImmutable((string) $historyRecord['tanggal_deteksi'], new DateTimeZone('Asia/Jakarta')))->format('d/m/Y H:i:s')) ?></time>
                      <?php else: ?>
                        <span class="text-muted">Waktu tidak tercatat</span>
                      <?php endif; ?>
                      <div class="small text-muted">ID scan #<?= (int) $historyRecord['id_detection'] ?></div>
                    </td>
                    <td>
                      <?php if (is_string($historyRecord['image_url'])): ?>
                        <a href="<?= escape_html($historyRecord['image_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Buka citra scan #<?= (int) $historyRecord['id_detection'] ?>">
                          <img src="<?= escape_html($historyRecord['image_url']) ?>" alt="Citra daun pada scan #<?= (int) $historyRecord['id_detection'] ?>" loading="lazy" class="img-thumbnail" style="width: 96px; height: 72px; object-fit: cover">
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">Citra tidak tersedia</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (is_string($historyRecord['user_name'])): ?>
                        <div><?= escape_html((string) $historyRecord['user_name']) ?></div>
                        <?php if (is_string($historyRecord['user_email'])): ?><div class="small text-muted"><?= escape_html((string) $historyRecord['user_email']) ?></div><?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">Pengguna tidak tersedia</span>
                      <?php endif; ?>
                    </td>
                    <td><strong><?= escape_html((string) $historyRecord['nama_penyakit']) ?></strong></td>
                    <td><?= escape_html(number_format((float) $historyRecord['confidence'], 4, ',', '.')) ?><div class="small text-muted">skor model</div></td>
                    <td>
                      <?php if (is_string($historyRecord['label_keparahan'])): ?>
                        <span class="badge text-bg-<?= $historyRecord['label_keparahan'] === 'Parah' ? 'danger' : ($historyRecord['label_keparahan'] === 'Sedang' ? 'warning' : 'success') ?>"><?= escape_html((string) $historyRecord['label_keparahan']) ?></span>
                      <?php else: ?>
                        <span class="text-muted">Belum diklasifikasikan</span>
                      <?php endif; ?>
                      <?php if ($historyRecord['infected_ratio_percent'] !== null): ?>
                        <div class="small text-muted mt-1"><?= escape_html(number_format((float) $historyRecord['infected_ratio_percent'], 2, ',', '.')) ?>% area terinfeksi</div>
                      <?php else: ?>
                        <div class="small text-muted mt-1">Persentase belum tercatat</div>
                      <?php endif; ?>
                      <?php if (is_string($historyRecord['severity_description']) && $historyRecord['severity_description'] !== ''): ?>
                        <div class="small text-muted mt-1"><?= escape_html((string) $historyRecord['severity_description']) ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr id="history-no-results" hidden><td colspan="6" class="text-center text-muted py-4">Tidak ada riwayat yang cocok dengan pencarian.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <p class="small text-muted mb-0">Data dimuat dari MySQL (maksimal 100 scan terbaru). Laporan Excel memuat logo, tabel data pemindaian, serta path citra. Persentase infeksi ditampilkan bila tersedia dari proses deteksi.</p>
      </div>
    </section>

    <script>
      (() => {
        const search = document.getElementById('history-search');
        const rows = Array.from(document.querySelectorAll('[data-history-row]'));
        const noResults = document.getElementById('history-no-results');
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