<?php

declare(strict_types=1);

require __DIR__ . '/includes/firebase.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    firebase_firestore_collection_documents('firebaseConnectionChecks');

    http_response_code(200);
    echo 'Koneksi ke Firebase Firestore berhasil.';
} catch (Throwable $exception) {
    error_log('Firebase connection failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Koneksi Firebase gagal. Periksa log PHP dan konfigurasi Firebase.';
}