<?php
// 1. XƯỞNG CORS HEADERS BẮT BUỘC (Phải đặt ở dòng đầu tiên tuyệt đối)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // Cache trong 1 ngày
}

// Xử lý các request dạng OPTIONS (Preflight) của trình duyệt
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

// Thiết lập định dạng trả về chung là JSON UTF-8
header('Content-Type: application/json; charset=utf-8');

// 2. NẠP KẾT NỐI CSDL
require_once __DIR__ . '/config.php';

// 3. XỬ LÝ ROUTING VÀ CHUẨN HÓA ĐƯỜNG DẪN
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim(rtrim($path, '.'), '/');

if (PHP_SAPI !== 'cli-server') {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
}

$key = $_SERVER['REQUEST_METHOD'] . ' ' . $path;

// 4. KHAI BÁO CÁC ROUTE API
$routes = [
    'GET /api/thuoc/danhsach' => function() use ($conn) {
        $sql = "SELECT * FROM THUOC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            return ["error" => print_r(sqlsrv_errors(), true)];
        }
        $data = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }
];

// 5. KIỂM TRA VÀ PHẢN HỒI API
if (!isset($routes[$key])) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy API: ' . $key], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    echo json_encode($routes[$key]($body), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>