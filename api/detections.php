<?php

declare(strict_types=1);

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function detections_api_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function detections_api_firebase_auth(): \Kreait\Firebase\Contract\Auth
{
    $credentialsPath = getenv('FIREBASE_CREDENTIALS') ?: __DIR__ . '/../config/firebase_key.json';
    if (!is_file($credentialsPath) || !is_readable($credentialsPath)) {
        throw new RuntimeException('Kredensial Firebase tidak ditemukan atau tidak dapat dibaca.');
    }

    $serviceAccount = json_decode(
        (string) file_get_contents($credentialsPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($serviceAccount) || empty($serviceAccount['project_id'])) {
        throw new RuntimeException('Kredensial Firebase tidak memiliki project_id yang valid.');
    }

    return (new Factory())
        ->withServiceAccount($serviceAccount)
        ->createAuth();
}

function detections_api_firebase_uid(): string
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($authorization) || preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $matches) !== 1) {
        header('WWW-Authenticate: Bearer');
        detections_api_respond(401, ['error' => 'Firebase ID token wajib dikirim pada header Authorization: Bearer.']);
    }

    try {
        $token = detections_api_firebase_auth()->verifyIdToken($matches[1], true);
    } catch (FailedToVerifyToken | RevokedIdToken $exception) {
        header('WWW-Authenticate: Bearer');
        detections_api_respond(401, ['error' => 'Firebase ID token tidak valid atau telah dicabut.']);
    } catch (Throwable $exception) {
        error_log('Detection API Firebase token verification failed: ' . $exception->getMessage());
        detections_api_respond(503, ['error' => 'Verifikasi Firebase sedang tidak tersedia.']);
    }

    $uid = $token->claims()->get('sub');
    if (!is_string($uid) || $uid === '') {
        detections_api_respond(401, ['error' => 'Firebase ID token tidak memiliki UID yang valid.']);
    }

    return $uid;
}

function detections_api_mysql_user_id(mysqli $connection, string $firebaseUid): int
{
    $statement = $connection->prepare('SELECT id_user FROM `user` WHERE firebase_uid = ? LIMIT 1');
    $statement->bind_param('s', $firebaseUid);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();

    if (!is_array($user)) {
        detections_api_respond(403, ['error' => 'Akun Firebase belum ditautkan ke akun pengguna web.']);
    }

    return (int) $user['id_user'];
}

function detections_api_integer_parameter(string $name, int $default, int $minimum, int $maximum): int
{
    if (!array_key_exists($name, $_GET)) {
        return $default;
    }

    $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum || $value > $maximum) {
        detections_api_respond(400, ['error' => "Parameter {$name} tidak valid."]);
    }

    return $value;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    detections_api_respond(405, ['error' => 'Metode HTTP tidak didukung.']);
}

$firebaseUid = detections_api_firebase_uid();

try {
    $connection = database();
    $userId = detections_api_mysql_user_id($connection, $firebaseUid);

    if ($method === 'GET') {
        $limit = detections_api_integer_parameter('limit', 20, 1, 100);
        $offset = detections_api_integer_parameter('offset', 0, 0, 10000);
        $statement = $connection->prepare(
            'SELECT id_detection, nama_penyakit, confidence, id_keparahan,
                tanggal_deteksi, infected_ratio_percent, NULLIF(image_path, \'\') AS image_path
             FROM detection
             WHERE id_user = ?
             ORDER BY tanggal_deteksi DESC, id_detection DESC
             LIMIT ? OFFSET ?'
        );
        $statement->bind_param('iii', $userId, $limit, $offset);
        $statement->execute();
        $records = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($records as &$record) {
            $record['id_detection'] = (int) $record['id_detection'];
            $record['confidence'] = (float) $record['confidence'];
            $record['id_keparahan'] = $record['id_keparahan'] === null
                ? null
                : (int) $record['id_keparahan'];
            $record['infected_ratio_percent'] = $record['infected_ratio_percent'] === null
                ? null
                : (float) $record['infected_ratio_percent'];
        }
        unset($record);

        detections_api_respond(200, [
            'data' => $records,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        detections_api_respond(415, ['error' => 'Kirim data dengan Content-Type: application/json.']);
    }

    $rawBody = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($rawBody) || strlen($rawBody) > 16384) {
        detections_api_respond(413, ['error' => 'Ukuran permintaan melebihi batas 16 KB.']);
    }

    try {
        $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        detections_api_respond(400, ['error' => 'Body permintaan harus berupa JSON yang valid.']);
    }
    if (!is_array($payload)) {
        detections_api_respond(400, ['error' => 'Body permintaan harus berupa objek JSON.']);
    }

    $allowedFields = ['nama_penyakit', 'confidence', 'severity', 'infected_ratio_percent', 'image_path'];
    if (array_diff(array_keys($payload), $allowedFields) !== []) {
        detections_api_respond(400, ['error' => 'Body memuat field yang tidak didukung.']);
    }

    $diseaseName = $payload['nama_penyakit'] ?? null;
    if (!is_string($diseaseName) || trim($diseaseName) === '' || mb_strlen(trim($diseaseName), 'UTF-8') > 100) {
        detections_api_respond(422, ['error' => 'nama_penyakit wajib diisi dan maksimal 100 karakter.']);
    }
    $diseaseName = trim($diseaseName);

    $confidence = $payload['confidence'] ?? null;
    if ((!is_int($confidence) && !is_float($confidence)) || !is_finite((float) $confidence) || $confidence < 0 || $confidence > 1) {
        detections_api_respond(422, ['error' => 'confidence wajib berupa angka antara 0 dan 1.']);
    }
    $confidence = (float) $confidence;

    $infectedRatio = $payload['infected_ratio_percent'] ?? null;
    if ($infectedRatio !== null) {
        if (
            (!is_int($infectedRatio) && !is_float($infectedRatio))
            || !is_finite((float) $infectedRatio)
            || $infectedRatio < 0
            || $infectedRatio > 100
        ) {
            detections_api_respond(422, ['error' => 'infected_ratio_percent harus berupa angka antara 0 dan 100.']);
        }
        $infectedRatio = (float) $infectedRatio;
    }

    $imagePath = $payload['image_path'] ?? '';
    if (!is_string($imagePath) || strlen($imagePath) > 255 || preg_match('/[\x00-\x1F\x7F]/', $imagePath) === 1) {
        detections_api_respond(422, ['error' => 'image_path harus berupa path citra maksimal 255 byte tanpa karakter kontrol.']);
    }

    $severityId = null;
    if (array_key_exists('severity', $payload) && $payload['severity'] !== null) {
        $severity = $payload['severity'];
        if (!is_string($severity) || trim($severity) === '' || mb_strlen(trim($severity), 'UTF-8') > 50) {
            detections_api_respond(422, ['error' => 'severity harus berupa label tingkat keparahan yang valid.']);
        }

        $severity = trim($severity);
        $severityStatement = $connection->prepare(
            'SELECT id_keparahan FROM tingkat_keparahan WHERE label_keparahan = ? LIMIT 1'
        );
        $severityStatement->bind_param('s', $severity);
        $severityStatement->execute();
        $severityRecord = $severityStatement->get_result()->fetch_assoc();
        if (!is_array($severityRecord)) {
            detections_api_respond(422, ['error' => 'Label severity tidak ditemukan.']);
        }
        $severityId = (int) $severityRecord['id_keparahan'];
    }

    $insert = $connection->prepare(
        'INSERT INTO detection
            (id_user, image_path, nama_penyakit, confidence, id_keparahan, infected_ratio_percent)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param('issdid', $userId, $imagePath, $diseaseName, $confidence, $severityId, $infectedRatio);
    $insert->execute();

    detections_api_respond(201, [
        'message' => 'Hasil deteksi berhasil disimpan.',
        'id_detection' => $insert->insert_id,
    ]);
} catch (Throwable $exception) {
    error_log('Detection API request failed: ' . $exception->getMessage());
    detections_api_respond(500, ['error' => 'Permintaan deteksi gagal diproses. Periksa log PHP.']);
}
