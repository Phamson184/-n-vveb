<?php
$serverName = "db,1433";
$connectionOptions = array(
    "UID" => "sa",
    "PWD" => "YourStrong@Passw0rd123",
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true
);

// Kết nối master để tạo DB nếu chưa có
$conn_master = sqlsrv_connect($serverName, $connectionOptions);
if ($conn_master !== false) {
    sqlsrv_query($conn_master, "IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'QuanLyNhaThuocDB') CREATE DATABASE QuanLyNhaThuocDB;");
    sqlsrv_close($conn_master);
}

// Kết nối chính thức vào CSDL QuanLyNhaThuocDB
$connectionInfo = array(
    "Database" => "QuanLyNhaThuocDB",
    "UID" => "sa",
    "PWD" => "YourStrong@Passw0rd123",
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) {
    http_response_code(500);
    echo json_encode(["error" => "Lỗi kết nối CSDL: " . print_r(sqlsrv_errors(), true)], JSON_UNESCAPED_UNICODE);
    exit;
}