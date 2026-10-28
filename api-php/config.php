<?php
// Tự động lấy cấu hình từ môi trường Docker hoặc mặc định nếu chạy ngoài
$serverName = getenv('DB_HOST') ?: "localhost";
$database = getenv('DB_NAME') ?: "QuanLyNhaThuocDB";
$uid = getenv('DB_USER') ?: "sa";
$pwd = getenv('DB_PASS') ?: "P@ssw0rd123";

try {
    $conn = new PDO("sqlsrv:server=$serverName;Database=$database;TrustServerCertificate=1", $uid, $pwd);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
?>
