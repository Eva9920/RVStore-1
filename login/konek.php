<?php
// Menampilkan semua error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parameter koneksi database
$host = "localhost";
$user = "root";
$password = "";
$database = "manageproduct";

// Membuat koneksi
$conn = new mysqli($host, $user, $password, $database);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Jika berhasil
// echo "Koneksi berhasil!";
?>
