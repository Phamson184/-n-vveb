<?php
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Middleware.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/modules/Auth/Controllers/AuthController.php';

try {
    $pdo = Database::getInstance($config['db']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi kết nối CSDL PharmaPro_v2: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$middleware = new Middleware($config['jwt']['secret']);
$router = new Router($middleware);

// Khởi tạo Controllers
$authController = new AuthController($pdo, $middleware);

// --- ĐỊNH NGHĨA ROUTES ---
// 1. Public Route (Không cần Token)
$router->add('POST', '/api/v2/auth/login', [$authController, 'login'], null);

// 2. Protected Routes (Kiểm thử RBAC Middleware)
$router->add('GET', '/api/v2/auth/me', function($body, $currentUser) {
    return ['success' => true, 'profile' => $currentUser];
}, []); // Mọi role đã đăng nhập đều xem được

$router->add('POST', '/api/v2/clinical/approve-test', function($body, $currentUser) {
    return ['success' => true, 'message' => 'Đã duyệt đơn lâm sàng bởi: ' . $currentUser['FullName']];
}, ['ADMIN', 'PHARMACIST']); // Chỉ ADMIN hoặc PHARMACIST được phép, CASHIER sẽ bị chặn 403

// Đọc JSON body (xử lý BOM UTF-8)
$rawInput = preg_replace('/^[\x{FEFF}\x{FFFE}]/u', '', file_get_contents('php://input'));
$body = json_decode($rawInput, true) ?? [];

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $body);
