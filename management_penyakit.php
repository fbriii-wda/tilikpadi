<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

function disease_catalog_severity(float $ratio): string
{
    if ($ratio <= 25) {
        return 'Ringan';
    }

    return $ratio <= 50 ? 'Sedang' : 'Parah';
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/firebase_storage.php';

$catalogMessage = null;
$catalogMessageType = 'success';
$catalogError = null;
$catalogRecords = [];
$storageReady = is_string(getenv('FIREBASE_STORAGE_BUCKET'))
    && trim((string) getenv('FIREBASE_STORAGE_BUCKET')) !== '';
$catalogStorageWarning = $storageReady
    ? null
    : 'Firebase Storage belum dikonfigurasi. Atur FIREBASE_STORAGE_BUCKET lalu mulai ulang Apache/Laragon sebelum mengunggah gambar.';
$catalogForm = [
    'id' => '',
    'disease_name' => '',
    'cause' => '',
    'infected_ratio' => '',
    'recommendation' => '',
    'image_path' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newImagePath = null;
    if (($_POST['action'] ?? '') === 'save') {
        $catalogForm = [
            'id' => (string) ($_POST['id'] ?? ''),
            'disease_name' => (string) ($_POST['disease_name'] ?? ''),
            'cause' => (string) ($_POST['cause'] ?? ''),
            'infected_ratio' => (string) ($_POST['infected_ratio'] ?? ''),
            'recommendation' => (string) ($_POST['recommendation'] ?? ''),
            'image_path' => '',
        ];
    }

    try {
        if (!valid_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.');
        }

        $action = $_POST['action'] ?? '';
        $connection = database();

        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new InvalidArgumentException('ID katalog tidak valid.');
            }

            $find = $connection->prepare('SELECT image_path FROM disease_catalog WHERE id = ?');
            $find->bind_param('i', $id);
            $find->execute();
            $record = $find->get_result()->fetch_assoc();
            if (!$record) {
                throw new InvalidArgumentException('Data penyakit tidak ditemukan.');
            }

            $delete = $connection->prepare('DELETE FROM disease_catalog WHERE id = ?');
            $delete->bind_param('i', $id);
            $delete->execute();

            try {
                firebase_storage_delete_image((string) $record['image_path']);
                $_SESSION['disease_catalog_flash'] = ['type' => 'success', 'message' => 'Data penyakit dan gambar referensi berhasil dihapus.'];
            } catch (Throwable $exception) {
                error_log('Disease catalog image cleanup failed: ' . $exception->getMessage());
                $_SESSION['disease_catalog_flash'] = [
                    'type' => 'warning',
                    'message' => 'Data katalog berhasil dihapus, tetapi gambar di Firebase Storage belum dapat dihapus.',
                ];
            }

            header('Location: management_penyakit.php');
            exit;
        }

        if ($action !== 'save') {
            throw new InvalidArgumentException('Aksi katalog tidak dikenali.');
        }

        $id = trim((string) ($_POST['id'] ?? ''));
        $diseaseName = trim((string) ($_POST['disease_name'] ?? ''));
        $cause = trim((string) ($_POST['cause'] ?? ''));
        $ratioInput = trim((string) ($_POST['infected_ratio'] ?? ''));
        $recommendation = trim((string) ($_POST['recommendation'] ?? ''));
        $editId = $id === '' ? null : filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($editId === false) {
            throw new InvalidArgumentException('ID katalog tidak valid.');
        }
        if ($diseaseName === '' || mb_strlen($diseaseName, 'UTF-8') > 150) {
            throw new InvalidArgumentException('Nama penyakit wajib diisi dan maksimal 150 karakter.');
        }
        if ($cause === '' || mb_strlen($cause, 'UTF-8') > 10000) {
            throw new InvalidArgumentException('Penyebab wajib diisi dan maksimal 10.000 karakter.');
        }
        if (!is_numeric($ratioInput) || (float) $ratioInput < 0 || (float) $ratioInput > 100) {
            throw new InvalidArgumentException('Rasio area terinfeksi harus berupa angka antara 0 dan 100%.');
        }
        if ($recommendation === '' || mb_strlen($recommendation, 'UTF-8') > 10000) {
            throw new InvalidArgumentException('Rekomendasi penanganan wajib diisi dan maksimal 10.000 karakter.');
        }

        $existingImagePath = '';
        if ($editId !== null) {
            $find = $connection->prepare('SELECT image_path FROM disease_catalog WHERE id = ?');
            $find->bind_param('i', $editId);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc();
            if (!$existing) {
                throw new InvalidArgumentException('Data penyakit yang akan diubah tidak ditemukan.');
            }
            $existingImagePath = (string) $existing['image_path'];
        }

        $hasImage = isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$hasImage && $editId === null) {
            throw new InvalidArgumentException('Gambar referensi wajib diunggah untuk data baru.');
        }

        if ($hasImage) {
            $upload = $_FILES['reference_image'];
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                throw new InvalidArgumentException('Unggahan gambar gagal. Silakan pilih ulang berkas.');
            }
            if ((int) $upload['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
            }

            $imageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $contentType = $fileInfo->file($upload['tmp_name']);
            if (!is_string($contentType) || !isset($imageTypes[$contentType])) {
                throw new InvalidArgumentException('Format gambar harus JPG, PNG, atau WEBP.');
            }

            $newImagePath = firebase_storage_upload_image(
                $upload['tmp_name'],
                $contentType,
                $imageTypes[$contentType]
            );
        }

        $imagePath = $newImagePath ?? $existingImagePath;
        $ratio = round((float) $ratioInput, 2);
        $severity = disease_catalog_severity($ratio);

        if ($editId === null) {
            $statement = $connection->prepare(
                'INSERT INTO disease_catalog (disease_name, cause, infected_ratio, severity, recommendation, image_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param('ssdsss', $diseaseName, $cause, $ratio, $severity, $recommendation, $imagePath);
            $statement->execute();
            $successMessage = 'Informasi penyakit berhasil ditambahkan.';
        } else {
            $statement = $connection->prepare(
                'UPDATE disease_catalog
                 SET disease_name = ?, cause = ?, infected_ratio = ?, severity = ?, recommendation = ?, image_path = ?
                 WHERE id = ?'
            );
            $statement->bind_param('ssdsssi', $diseaseName, $cause, $ratio, $severity, $recommendation, $imagePath, $editId);
            $statement->execute();
            $successMessage = 'Informasi penyakit berhasil diperbarui.';
        }

        $successType = 'success';
        if ($newImagePath !== null && $existingImagePath !== '') {
            try {
                firebase_storage_delete_image($existingImagePath);
            } catch (Throwable $exception) {
                error_log('Disease catalog replaced image cleanup failed: ' . $exception->getMessage());
                $successType = 'warning';
                $successMessage .= ' Gambar lama masih tersimpan di Firebase Storage.';
            }
        }

        $_SESSION['disease_catalog_flash'] = ['type' => $successType, 'message' => $successMessage];
        header('Location: management_penyakit.php');
        exit;
    } catch (InvalidArgumentException $exception) {
        $catalogMessage = $exception->getMessage();
        $catalogMessageType = 'danger';
    } catch (Throwable $exception) {
        if ($newImagePath !== null) {
            try {
                firebase_storage_delete_image($newImagePath);
            } catch (Throwable $cleanupException) {
                error_log('Disease catalog failed upload cleanup failed: ' . $cleanupException->getMessage());
            }
        }
        error_log('Disease catalog save failed: ' . $exception->getMessage());
        $catalogMessage = 'Penyimpanan katalog gagal. Pastikan MySQL Laragon dan konfigurasi Firebase Storage tersedia.';
        $catalogMessageType = 'danger';
    }
}

if (isset($_SESSION['disease_catalog_flash'])) {
    $catalogMessage = $_SESSION['disease_catalog_flash']['message'];
    $catalogMessageType = $_SESSION['disease_catalog_flash']['type'];
    unset($_SESSION['disease_catalog_flash']);
}

try {
    $connection = database();
    if (isset($_GET['edit'])) {
        $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId !== false) {
            $statement = $connection->prepare('SELECT * FROM disease_catalog WHERE id = ?');
            $statement->bind_param('i', $editId);
            $statement->execute();
            $record = $statement->get_result()->fetch_assoc();
            if ($record) {
                $catalogForm = $record;
            } else {
                $catalogMessage = 'Data penyakit yang akan diubah tidak ditemukan.';
                $catalogMessageType = 'warning';
            }
        }
    }

    $catalogRecords = $connection
        ->query('SELECT * FROM disease_catalog ORDER BY disease_name ASC')
        ->fetch_all(MYSQLI_ASSOC);
    foreach ($catalogRecords as &$record) {
        if ($storageReady) {
            try {
                $record['image_url'] = firebase_storage_image_url((string) $record['image_path']);
            } catch (Throwable $exception) {
                error_log('Disease catalog image URL generation failed: ' . $exception->getMessage());
                $storageReady = false;
                $record['image_url'] = null;
                $catalogStorageWarning = 'Gambar tidak dapat dimuat. Pastikan FIREBASE_STORAGE_BUCKET dan izin Storage pada akun layanan sudah benar.';
            }
        } else {
            $record['image_url'] = null;
        }
        if ((string) $record['id'] === (string) ($catalogForm['id'] ?? '')) {
            $catalogForm['image_url'] = $record['image_url'];
        }
    }
    unset($record);
} catch (Throwable $exception) {
    error_log('Disease catalog loading failed: ' . $exception->getMessage());
    $catalogError = 'Katalog belum dapat dimuat. Pastikan MySQL Laragon aktif dan migrasi disease_catalog sudah dijalankan.';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Penyakit &amp; Solusi - Tilik Padi</title>

  <meta name="description" content="Kelola katalog edukasi penyakit padi dan rekomendasi penanganan.">
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
          <li class="sidebar-menu-item"><a href="management_penyakit.php" class="sidebar-menu-link active" id="menu-basictables" title="Manajemen Penyakit &amp; Solusi"><i class="bi bi-bug"></i><span>Manajemen Penyakit &amp; Solusi</span></a></li>
          <li class="sidebar-menu-item"><a href="management_pengguna.php" class="sidebar-menu-link" id="menu-uiforms" title="Manajemen Pengguna"><i class="bi bi-people"></i><span>Manajemen Pengguna</span></a></li>
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
        <h1 class="page-title">Manajemen Penyakit &amp; Solusi</h1>
        <p class="page-subtitle">Kelola basis pengetahuan penyakit padi, artikel edukasi, dan rekomendasi penanganan.</p>
      </div>
      <nav aria-label="navigasi jejak">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted-green">Beranda</a></li>
          <li class="breadcrumb-item text-muted-green">Manajemen</li>
          <li class="breadcrumb-item active text-main" aria-current="page">Katalog Penyakit</li>
        </ol>
      </nav>
    </div>

    <?php if ($catalogMessage !== null): ?>
      <div class="alert alert-<?= escape_html($catalogMessageType) ?>" role="alert"><?= escape_html($catalogMessage) ?></div>
    <?php endif; ?>
    <?php if ($catalogError !== null): ?>
      <div class="alert alert-danger" role="alert"><?= escape_html($catalogError) ?> Jalankan migrasi <code>database/migrations/20261022_create_disease_catalog.sql</code> satu kali melalui phpMyAdmin setelah MySQL aktif.</div>
    <?php endif; ?>
    <?php if ($catalogStorageWarning !== null): ?>
      <div class="alert alert-warning" role="alert"><?= escape_html($catalogStorageWarning) ?></div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="disease-form-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <h2 class="h5 mb-1" id="disease-form-title"><?= $catalogForm['id'] !== '' ? 'Ubah Informasi Penyakit' : 'Tambah Informasi Penyakit' ?></h2>
            <p class="text-muted mb-0">Data teks disimpan di MySQL Laragon; gambar referensi disimpan di Firebase Storage.</p>
          </div>
          <?php if ($catalogForm['id'] !== ''): ?>
            <a href="management_penyakit.php" class="btn btn-outline-secondary btn-sm">Batal mengubah</a>
          <?php endif; ?>
        </div>

        <form method="post" action="management_penyakit.php" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= escape_html((string) $catalogForm['id']) ?>">

          <div class="col-12 col-lg-6">
            <label class="form-label" for="disease-name">Nama Penyakit <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="disease-name" name="disease_name" maxlength="150" required
              placeholder="Contoh: Hawar Daun Bakteri" value="<?= escape_html((string) $catalogForm['disease_name']) ?>">
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="infected-ratio">Rasio Area Daun Terinfeksi (%) <span class="text-danger">*</span></label>
            <input class="form-control" type="number" id="infected-ratio" name="infected_ratio" min="0" max="100" step="0.01" required
              placeholder="0-100" value="<?= escape_html((string) $catalogForm['infected_ratio']) ?>" aria-describedby="severity-guide">
            <div class="form-text" id="severity-guide">Ringan: 0-25%; Sedang: &gt;25-50%; Parah: &gt;50-100%.</div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="disease-cause">Penyebab / Patogen <span class="text-danger">*</span></label>
            <textarea class="form-control" id="disease-cause" name="cause" rows="4" maxlength="10000" required
              placeholder="Jelaskan patogen atau faktor penyebab penyakit."><?= escape_html((string) $catalogForm['cause']) ?></textarea>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="severity-preview">Kategori Keparahan (otomatis)</label>
            <input class="form-control" type="text" id="severity-preview" value="<?= $catalogForm['infected_ratio'] !== '' ? escape_html(disease_catalog_severity((float) $catalogForm['infected_ratio'])) : 'Masukkan rasio area terinfeksi' ?>" readonly>
            <div class="form-text">Kategori dihitung dari rasio area daun terinfeksi; nilainya divalidasi ulang oleh server.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="recommendation">Rekomendasi Penanganan <span class="text-danger">*</span></label>
            <textarea class="form-control" id="recommendation" name="recommendation" rows="4" maxlength="10000" required
              placeholder="Contoh: pengaturan irigasi, pemupukan, atau bahan pengendalian yang sesuai."><?= escape_html((string) $catalogForm['recommendation']) ?></textarea>
          </div>
          <div class="col-12 col-lg-8">
            <label class="form-label" for="reference-image">Gambar Referensi <?= $catalogForm['id'] === '' ? '<span class="text-danger">*</span>' : '' ?></label>
            <input class="form-control" type="file" id="reference-image" name="reference_image" accept="image/jpeg,image/png,image/webp" <?= $catalogForm['id'] === '' ? 'required' : '' ?> aria-describedby="image-guide">
            <div class="form-text" id="image-guide">JPG, PNG, atau WEBP; maksimal 5 MB. Gunakan gambar pembanding daun sehat dan daun sakit.</div>
          </div>
          <?php if (!empty($catalogForm['image_url'])): ?>
            <div class="col-12 col-lg-4">
              <div class="form-label">Gambar tersimpan saat ini</div>
              <img src="<?= escape_html((string) $catalogForm['image_url']) ?>" alt="Gambar referensi penyakit saat ini" class="img-thumbnail" style="max-height: 120px">
            </div>
          <?php endif; ?>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-success" type="submit"><i class="bi bi-cloud-arrow-up me-1"></i><?= $catalogForm['id'] !== '' ? 'Simpan Perubahan' : 'Simpan Penyakit' ?></button>
          </div>
        </form>
      </div>
    </section>

    <section class="card border-0 shadow-sm" aria-labelledby="catalog-list-title">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1" id="catalog-list-title">Katalog Edukasi Penyakit</h2>
            <p class="text-muted mb-0">Jumlah data: <?= count($catalogRecords) ?></p>
          </div>
          <div class="input-group" style="max-width: 320px">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input class="form-control" type="search" id="catalog-search" placeholder="Cari nama atau penyebab..." aria-label="Cari katalog penyakit">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th scope="col">Penyakit</th>
                <th scope="col">Penyebab</th>
                <th scope="col">Keparahan</th>
                <th scope="col">Rekomendasi</th>
                <th scope="col">Gambar</th>
                <th scope="col" class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody id="catalog-table-body">
              <?php if ($catalogRecords === []): ?>
                <tr id="catalog-empty-row"><td colspan="6" class="text-center text-muted py-4">Belum ada data katalog penyakit.</td></tr>
              <?php else: ?>
                <?php foreach ($catalogRecords as $record): ?>
                  <tr data-catalog-row>
                    <td><strong><?= escape_html((string) $record['disease_name']) ?></strong></td>
                    <td class="text-wrap" style="min-width: 180px; max-width: 260px"><?= nl2br(escape_html((string) $record['cause'])) ?></td>
                    <td>
                      <span class="badge text-bg-<?= $record['severity'] === 'Parah' ? 'danger' : ($record['severity'] === 'Sedang' ? 'warning' : 'success') ?>"><?= escape_html((string) $record['severity']) ?></span>
                      <div class="small text-muted mt-1"><?= escape_html(number_format((float) $record['infected_ratio'], 2, ',', '.')) ?>% area terinfeksi</div>
                    </td>
                    <td class="text-wrap" style="min-width: 200px; max-width: 320px"><?= nl2br(escape_html((string) $record['recommendation'])) ?></td>
                    <td>
                      <?php if (is_string($record['image_url'])): ?>
                        <a href="<?= escape_html($record['image_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Buka gambar referensi <?= escape_html((string) $record['disease_name']) ?>">
                          <img src="<?= escape_html($record['image_url']) ?>" alt="Referensi <?= escape_html((string) $record['disease_name']) ?>" class="img-thumbnail" style="width: 72px; height: 56px; object-fit: cover">
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">Tidak tersedia</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="management_penyakit.php?edit=<?= (int) $record['id'] ?>" aria-label="Ubah <?= escape_html((string) $record['disease_name']) ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="management_penyakit.php" onsubmit="return confirm('Hapus data penyakit ini?')">
                          <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                          <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Hapus <?= escape_html((string) $record['disease_name']) ?>"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr id="catalog-no-results" hidden><td colspan="6" class="text-center text-muted py-4">Tidak ada data yang cocok dengan pencarian.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <script>
      (() => {
        const ratioInput = document.getElementById('infected-ratio');
        const severityPreview = document.getElementById('severity-preview');
        const searchInput = document.getElementById('catalog-search');
        const rows = Array.from(document.querySelectorAll('[data-catalog-row]'));
        const noResults = document.getElementById('catalog-no-results');

        ratioInput?.addEventListener('input', () => {
          const ratio = Number(ratioInput.value);
          severityPreview.value = ratioInput.value === '' || Number.isNaN(ratio)
            ? 'Masukkan rasio area terinfeksi'
            : ratio <= 25 ? 'Ringan' : ratio <= 50 ? 'Sedang' : 'Parah';
        });

        searchInput?.addEventListener('input', () => {
          const query = searchInput.value.trim().toLocaleLowerCase('id');
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
          <li><a href="index.php#scan-analytics" class="footer-link">Analitik Pemindaian</a></li>
          <li><a href="index.php#scan-history" class="footer-link">Riwayat Scan</a></li>
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