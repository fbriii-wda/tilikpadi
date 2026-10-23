<?php

declare(strict_types=1);

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Kreait\Firebase\Factory;

require_once __DIR__ . '/../vendor/autoload.php';

function firebase_firestore_access(): array
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

    if (
        !is_array($serviceAccount)
        || !isset($serviceAccount['project_id'])
        || !is_string($serviceAccount['project_id'])
        || $serviceAccount['project_id'] === ''
    ) {
        throw new RuntimeException('Kredensial Firebase tidak memiliki project_id yang valid.');
    }

    $credentials = new ServiceAccountCredentials(
        ['https://www.googleapis.com/auth/datastore'],
        $serviceAccount
    );
    $token = $credentials->fetchAuthToken();
    $accessToken = $token['access_token'] ?? null;

    if (!is_string($accessToken) || $accessToken === '') {
        throw new RuntimeException('Token akses Firebase tidak tersedia.');
    }

    return [
        'project_id' => $serviceAccount['project_id'],
        'access_token' => $accessToken,
        'client' => new Client(['timeout' => 10]),
    ];
}

function firebase_firestore_collection_documents(string $collectionId): array
{
    if ($collectionId === '' || str_contains($collectionId, '/')) {
        throw new InvalidArgumentException('ID koleksi Firestore tidak valid.');
    }

    $access = firebase_firestore_access();
    $projectId = rawurlencode($access['project_id']);
    $collectionId = rawurlencode($collectionId);
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collectionId}";
    $documents = [];
    $pageToken = null;

    do {
        $query = ['pageSize' => 1000];
        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken;
        }

        $response = $access['client']->get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access['access_token']],
            'query' => $query,
        ]);
        $page = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $documents = array_merge($documents, $page['documents'] ?? []);
        $pageToken = $page['nextPageToken'] ?? null;
    } while (is_string($pageToken) && $pageToken !== '');

    return $documents;
}

function firebase_firestore_upsert_user(string $documentId, array $user): void
{
    if ($documentId === '' || !ctype_digit($documentId) || str_contains($documentId, '/')) {
        throw new InvalidArgumentException('ID dokumen pengguna Firebase tidak valid.');
    }

    $access = firebase_firestore_access();
    $projectId = rawurlencode($access['project_id']);
    $safeDocumentId = rawurlencode($documentId);
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users/{$safeDocumentId}";
    $fields = [
        'mysqlUserId' => ['integerValue' => $documentId],
        'name' => ['stringValue' => (string) $user['name']],
        'email' => ['stringValue' => (string) $user['email']],
        'roleId' => ['integerValue' => (string) $user['role_id']],
        'role' => ['stringValue' => (string) $user['role']],
        'syncedAt' => ['timestampValue' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z')],
    ];

    $access['client']->patch($url, [
        'headers' => ['Authorization' => 'Bearer ' . $access['access_token']],
        'json' => ['fields' => $fields],
    ]);
}

function firebase_firestore_delete_user(string $documentId): void
{
    if ($documentId === '' || !ctype_digit($documentId) || str_contains($documentId, '/')) {
        throw new InvalidArgumentException('ID dokumen pengguna Firebase tidak valid.');
    }

    $access = firebase_firestore_access();
    $projectId = rawurlencode($access['project_id']);
    $safeDocumentId = rawurlencode($documentId);
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users/{$safeDocumentId}";
    try {
        $access['client']->delete($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access['access_token']],
        ]);
    } catch (\GuzzleHttp\Exception\ClientException $exception) {
        if ($exception->getResponse()?->getStatusCode() !== 404) {
            throw $exception;
        }
    }
}

function firebase_auth_list_users(int $maxResults = 1000): array
{
    if ($maxResults < 1 || $maxResults > 1000) {
        throw new InvalidArgumentException('Batas daftar pengguna Firebase harus antara 1 dan 1000.');
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
    if (
        !is_array($serviceAccount)
        || !isset($serviceAccount['project_id'])
        || !is_string($serviceAccount['project_id'])
        || $serviceAccount['project_id'] === ''
    ) {
        throw new RuntimeException('Kredensial Firebase tidak memiliki project_id yang valid.');
    }

    $auth = (new Factory())
        ->withServiceAccount($serviceAccount)
        ->createAuth();
    $users = [];

    foreach ($auth->listUsers($maxResults, $maxResults) as $user) {
        $users[] = [
            'uid' => $user->uid,
            'email' => $user->email,
            'display_name' => $user->displayName,
            'email_verified' => $user->emailVerified,
            'disabled' => $user->disabled,
            'created_at' => $user->metadata->createdAt,
            'last_login_at' => $user->metadata->lastLoginAt,
        ];
    }

    return $users;
}
