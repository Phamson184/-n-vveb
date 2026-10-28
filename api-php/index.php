<?php
// Tương đương server.js: khởi tạo app, cors, đọc JSON, khai báo route, xử lý lỗi.
// Chạy (giữ nguyên cổng 3000 như Node nên frontend KHÔNG phải sửa URL):
//     php -S localhost:3000 index.php
require __DIR__ . '/config.php';
require __DIR__ . '/handlers.php';

// app.use(cors())
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// app.use(express.json())  ->  $body (dùng cho các route POST/PUT sau này)
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Bảng route: mỗi dòng = một app.get / app.post trong Node
$routes = [
    'GET /api/thuoc/danhsach' => 'getDanhSachThuoc',
    'GET /api/kho/danhsach'   => 'getDanhSachKho',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (PHP_SAPI !== 'cli-server') { // chạy qua Apache/XAMPP trong thư mục con: cắt phần tiền tố thư mục
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
}
$key = $_SERVER['REQUEST_METHOD'] . ' ' . rtrim($path, '/');

if (!isset($routes[$key])) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy API: ' . $key], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    echo json_encode($routes[$key]($body), JSON_UNESCAPED_UNICODE); // res.json(...)
} catch (Throwable $e) {
    http_response_code(500);                                         // res.status(500).json({error})
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

