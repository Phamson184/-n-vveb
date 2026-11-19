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
require_once __DIR__ . '/modules/Inventory/Controllers/InventoryController.php';
require_once __DIR__ . '/modules/Clinical/Controllers/ClinicalController.php';
require_once __DIR__ . '/modules/Prescription/Controllers/PrescriptionController.php';

try {
    $pdo = Database::getInstance($config['db']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi kết nối CSDL PharmaPro_v2: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$middleware = new Middleware($config['jwt']['secret']);
$router = new Router($middleware);

$authController = new AuthController($pdo, $middleware);
$inventoryController = new InventoryController($pdo);
$clinicalController = new ClinicalController($pdo);
$prescriptionController = new PrescriptionController($pdo);

// --- ROUTES: AUTH ---
$router->add('POST', '/api/v2/auth/login', [$authController, 'login'], null);
$router->add('GET',  '/api/v2/auth/me', fn($body, $u) => ['success' => true, 'profile' => $u], []);

// --- ROUTES: INVENTORY ---
$router->add('GET',  '/api/v2/inventory/drugs', [$inventoryController, 'listDrugs'], []);
$router->add('POST', '/api/v2/inventory/drugs', [$inventoryController, 'createDrug'], ['ADMIN', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/batches', [$inventoryController, 'importBatch'], ['ADMIN', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/fefo-dispense-test', [$inventoryController, 'testFefoDispense'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);

// --- ROUTES: CLINICAL ---
$router->add('POST', '/api/v2/clinical/patients', [$clinicalController, 'createPatient'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('POST', '/api/v2/clinical/allergies', [$clinicalController, 'addAllergy'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('GET',  '/api/v2/clinical/patients/360', [$clinicalController, 'getPatient360'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);

// --- ROUTES: PRESCRIPTION ---
$router->add('POST', '/api/v2/prescriptions', [$prescriptionController, 'createPrescription'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('POST', '/api/v2/prescriptions/validate', [$prescriptionController, 'validatePrescription'], ['ADMIN', 'PHARMACIST']);
$router->add('POST', '/api/v2/prescriptions/dispense', [$prescriptionController, 'dispensePrescription'], ['ADMIN', 'PHARMACIST', 'CASHIER']);

$rawInput = preg_replace('/^[\x{FEFF}\x{FFFE}]/u', '', file_get_contents('php://input'));
$body = json_decode($rawInput, true) ?? [];

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $body);