<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesi login telah berakhir.']);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';

    $timezone = new DateTimeZone('Asia/Jakarta');
    $currentYear = (int) (new DateTimeImmutable('now', $timezone))->format('Y');
    $requestedYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
    $year = $requestedYear === null ? $currentYear : $requestedYear;

    if ($year < 2000 || $year > 2100) {
        http_response_code(400);
        echo json_encode(['error' => 'Tahun statistik tidak valid.'], JSON_THROW_ON_ERROR);
        exit;
    }

    $today = new DateTimeImmutable('today', $timezone);
    $tomorrow = $today->modify('+1 day');
    $startOfDay = $today->format('Y-m-d H:i:s');
    $startOfTomorrow = $tomorrow->format('Y-m-d H:i:s');
    $startOfYear = sprintf('%04d-01-01 00:00:00', $year);
    $startOfNextYear = sprintf('%04d-01-01 00:00:00', $year + 1);
    $monthlyScans = array_fill(1, 12, 0);

    $scanStatement = database()->prepare(
        'SELECT MONTH(tanggal_deteksi) AS scan_month, COUNT(*) AS scan_count
         FROM detection
         WHERE tanggal_deteksi >= ? AND tanggal_deteksi < ?
         GROUP BY MONTH(tanggal_deteksi)'
    );
    $scanStatement->bind_param('ss', $startOfYear, $startOfNextYear);
    $scanStatement->execute();
    foreach ($scanStatement->get_result()->fetch_all(MYSQLI_ASSOC) as $month) {
        $monthlyScans[(int) $month['scan_month']] = (int) $month['scan_count'];
    }

    $severityStatement = database()->prepare(
        'SELECT severity.id_keparahan, severity.label_keparahan, COUNT(detection.id_detection) AS scan_count
         FROM tingkat_keparahan AS severity
         LEFT JOIN detection
            ON detection.id_keparahan = severity.id_keparahan
            AND detection.tanggal_deteksi >= ?
            AND detection.tanggal_deteksi < ?
         GROUP BY severity.id_keparahan, severity.label_keparahan
         ORDER BY severity.id_keparahan'
    );
    $severityStatement->bind_param('ss', $startOfYear, $startOfNextYear);
    $severityStatement->execute();
    $severityDistribution = [];
    foreach ($severityStatement->get_result()->fetch_all(MYSQLI_ASSOC) as $severity) {
        $severityDistribution[] = [
            'label' => $severity['label_keparahan'],
            'count' => (int) $severity['scan_count'],
        ];
    }

    $recentStatement = database()->prepare(
        'SELECT detection.nama_penyakit, detection.confidence, detection.tanggal_deteksi,
            user.nama AS user_name, severity.label_keparahan
         FROM detection
         LEFT JOIN `user` AS user ON user.id_user = detection.id_user
         LEFT JOIN tingkat_keparahan AS severity ON severity.id_keparahan = detection.id_keparahan
         WHERE detection.tanggal_deteksi >= ? AND detection.tanggal_deteksi < ?
         ORDER BY detection.tanggal_deteksi DESC
         LIMIT 5'
    );
    $recentStatement->bind_param('ss', $startOfYear, $startOfNextYear);
    $recentStatement->execute();
    $recentDetections = [];
    foreach ($recentStatement->get_result()->fetch_all(MYSQLI_ASSOC) as $detection) {
        $recentDetections[] = [
            'disease' => $detection['nama_penyakit'],
            'confidence' => $detection['confidence'] === null
                ? null
                : (float) $detection['confidence'],
            'date' => $detection['tanggal_deteksi'],
            'user' => $detection['user_name'],
            'severity' => $detection['label_keparahan'],
        ];
    }

    $registeredUsers = null;
    $activeUsers = null;
    $userStatsError = null;
    try {
        require_once __DIR__ . '/../includes/firebase.php';

        $firebaseUsers = firebase_auth_list_users();
        $registeredUsers = count($firebaseUsers);
        $activeUsers = 0;
        foreach ($firebaseUsers as $firebaseUser) {
            $lastLoginAt = $firebaseUser['last_login_at'];
            if (
                $lastLoginAt instanceof DateTimeImmutable
                && $lastLoginAt->setTimezone($timezone) >= $today
                && $lastLoginAt->setTimezone($timezone) < $tomorrow
            ) {
                $activeUsers++;
            }
        }
    } catch (Throwable $exception) {
        error_log('Dashboard Firebase Auth user stats failed: ' . $exception->getMessage());
        $userStatsError = 'Statistik pengguna Firebase Authentication gagal dimuat.';
    }

    $response = [
        'registeredUsers' => $registeredUsers,
        'activeUsers' => $activeUsers,
        'year' => $year,
        'annualScanCount' => array_sum($monthlyScans),
        'monthlyScans' => array_values($monthlyScans),
        'severityDistribution' => $severityDistribution,
        'recentDetections' => $recentDetections,
        'diseaseTypes' => null,
        'updatedAt' => (new DateTimeImmutable('now', $timezone))->format(DATE_ATOM),
        'errors' => $userStatsError === null ? [] : ['users' => $userStatsError],
    ];

    try {
        require_once __DIR__ . '/../includes/firebase.php';

        $diseaseNames = [];
        foreach (firebase_firestore_collection_documents('detections') as $document) {
            $name = $document['fields']['nama_penyakit']['stringValue'] ?? null;

            if (is_string($name) && trim($name) !== '') {
                $normalizedName = mb_strtolower(trim($name), 'UTF-8');
                $diseaseNames[$normalizedName] = true;
            }
        }

        $response['diseaseTypes'] = count($diseaseNames);
    } catch (Throwable $exception) {
        error_log('Dashboard Firebase disease stats failed: ' . $exception->getMessage());
        $response['errors']['diseaseTypes'] = 'Data penyakit Firebase gagal dimuat.';
    }

    echo json_encode($response, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log('Dashboard user stats failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Statistik pengguna gagal dimuat.'], JSON_THROW_ON_ERROR);
}
