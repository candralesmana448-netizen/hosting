<?php
// =====================================================
// Konfigurasi koneksi database
// Sesuaikan dengan setting hosting/XAMPP Anda
// =====================================================

$db_host = 'localhost';
$db_name = 'arrahmah_db';
$db_user = 'root';       // ganti sesuai user database Anda
$db_pass = '';           // ganti sesuai password database Anda

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}
