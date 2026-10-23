<?php

declare(strict_types=1);

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Storage\StorageClient;

require_once __DIR__ . '/../vendor/autoload.php';

function firebase_storage_bucket(): \Google\Cloud\Storage\Bucket
{
    static $bucket = null;
    if ($bucket instanceof \Google\Cloud\Storage\Bucket) {
        return $bucket;
    }

    $bucketName = getenv('FIREBASE_STORAGE_BUCKET');
    if (!is_string($bucketName) || trim($bucketName) === '') {
        throw new RuntimeException('FIREBASE_STORAGE_BUCKET belum dikonfigurasi.');
    }

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

    $credentials = new ServiceAccountCredentials(
        [StorageClient::READ_WRITE_SCOPE],
        $serviceAccount
    );
    $storage = new StorageClient([
        'projectId' => $serviceAccount['project_id'],
        'credentialsFetcher' => $credentials,
    ]);

    $bucket = $storage->bucket(trim($bucketName));

    return $bucket;
}

function firebase_storage_upload_image(string $temporaryPath, string $contentType, string $extension): string
{
    $objectName = 'disease-catalog/' . bin2hex(random_bytes(16)) . '.' . $extension;
    $stream = fopen($temporaryPath, 'rb');
    if ($stream === false) {
        throw new RuntimeException('Berkas gambar tidak dapat dibaca.');
    }

    try {
        firebase_storage_bucket()->upload($stream, [
            'name' => $objectName,
            'metadata' => ['contentType' => $contentType],
        ]);
    } finally {
        fclose($stream);
    }

    return $objectName;
}

function firebase_storage_delete_image(string $objectName): void
{
    if (!str_starts_with($objectName, 'disease-catalog/') || str_contains($objectName, '..')) {
        throw new InvalidArgumentException('Lokasi gambar Firebase tidak valid.');
    }

    firebase_storage_bucket()->object($objectName)->delete();
}

function firebase_storage_image_url(string $objectName): string
{
    if (!str_starts_with($objectName, 'disease-catalog/') || str_contains($objectName, '..')) {
        throw new InvalidArgumentException('Lokasi gambar Firebase tidak valid.');
    }

    return firebase_storage_bucket()
        ->object($objectName)
        ->signedUrl(new DateTimeImmutable('+1 hour'));
}

function firebase_storage_detection_image_url(string $imagePath): string
{
    $imagePath = trim($imagePath);
    $bucketName = trim((string) (getenv('FIREBASE_STORAGE_BUCKET') ?: ''));

    if (str_starts_with($imagePath, 'gs://')) {
        $path = parse_url($imagePath);
        if (
            !is_array($path)
            || ($path['scheme'] ?? '') !== 'gs'
            || ($path['host'] ?? '') !== $bucketName
            || !isset($path['path'])
        ) {
            throw new InvalidArgumentException('Path gambar tidak mengarah ke bucket Firebase yang dikonfigurasi.');
        }
        $imagePath = ltrim($path['path'], '/');
    } elseif (filter_var($imagePath, FILTER_VALIDATE_URL) !== false) {
        $path = parse_url($imagePath);
        if (!is_array($path) || ($path['scheme'] ?? '') !== 'https') {
            throw new InvalidArgumentException('URL gambar Firebase tidak valid.');
        }

        if (($path['host'] ?? '') === 'firebasestorage.googleapis.com') {
            if (preg_match('~^/v0/b/([^/]+)/o/(.+)$~', (string) ($path['path'] ?? ''), $matches) !== 1 || $matches[1] !== $bucketName) {
                throw new InvalidArgumentException('URL gambar tidak mengarah ke bucket Firebase yang dikonfigurasi.');
            }
            $imagePath = rawurldecode($matches[2]);
        } elseif (($path['host'] ?? '') === 'storage.googleapis.com') {
            $segments = explode('/', ltrim((string) ($path['path'] ?? ''), '/'), 2);
            if (count($segments) !== 2 || $segments[0] !== $bucketName) {
                throw new InvalidArgumentException('URL gambar tidak mengarah ke bucket Firebase yang dikonfigurasi.');
            }
            $imagePath = rawurldecode($segments[1]);
        } else {
            throw new InvalidArgumentException('URL citra harus berasal dari Firebase Storage.');
        }
    }

    if (
        $imagePath === ''
        || str_starts_with($imagePath, '/')
        || str_contains($imagePath, '..')
        || preg_match('/[\x00-\x1F\x7F]/', $imagePath) === 1
    ) {
        throw new InvalidArgumentException('Lokasi gambar Firebase tidak valid.');
    }

    return firebase_storage_bucket()
        ->object($imagePath)
        ->signedUrl(new DateTimeImmutable('+1 hour'));
}
