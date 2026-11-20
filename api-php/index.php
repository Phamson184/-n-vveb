<?php
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-ZEvent-Signature, X-Zalo-Signature");
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
require_once __DIR__ . '/modules/SmartCare/Controllers/SmartCareController.php';
require_once __DIR__ . '/modules/Audit/Controllers/AuditController.php';

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
$smartCareController = new SmartCareController($pdo, $config['jwt']['secret']);
$auditController = new AuditController($pdo);

// --- ROUTES: AUTH ---
$router->add('POST', '/api/v2/auth/login', [$authController, 'login'], null);
$router->add('GET',  '/api/v2/auth/me', fn($body, $u) => ['success' => true, 'profile' => $u], []);

// --- ROUTES: INVENTORY & CATALOGS & CONTROLLED DRUGS ---
$router->add('GET',  '/api/v2/catalogs/init', [$inventoryController, 'getMedicalCatalogs'], []);
$router->add('GET',  '/api/v2/inventory/drugs', [$inventoryController, 'listDrugs'], []);
$router->add('GET',  '/api/v2/inventory/drugs/substitutes', [$inventoryController, 'getSubstitutes'], []);
$router->add('GET',  '/api/v2/inventory/expiring-batches', [$auditController, 'getExpiringBatches'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/drugs', [$inventoryController, 'createDrug'], ['ADMIN', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/batches', [$inventoryController, 'importBatch'], ['ADMIN', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/fefo-dispense-test', [$inventoryController, 'testFefoDispense'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);
$router->add('GET',  '/api/v2/inventory/controlled-logs', [$inventoryController, 'getControlledLogs'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);
$router->add('POST', '/api/v2/inventory/controlled-audit', [$inventoryController, 'auditControlledCabinet'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);
$router->add('GET',  '/api/v2/reports/department-consumption', [$inventoryController, 'getDepartmentConsumptionReport'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);

// --- ROUTES: CLINICAL ---
$router->add('GET',  '/api/v2/clinical/patients', [$clinicalController, 'searchPatients'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('POST', '/api/v2/clinical/patients', [$clinicalController, 'createPatient'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('POST', '/api/v2/clinical/allergies', [$clinicalController, 'addAllergy'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('GET',  '/api/v2/clinical/patients/360', [$clinicalController, 'getPatient360'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);

// --- ROUTES: PRESCRIPTION & OCR MAPPING ---
$router->add('GET',  '/api/v2/prescriptions', [$prescriptionController, 'listPrescriptions'], ['ADMIN', 'DOCTOR', 'PHARMACIST', 'CASHIER']);
$router->add('POST', '/api/v2/prescriptions', [$prescriptionController, 'createPrescription'], ['ADMIN', 'DOCTOR', 'PHARMACIST']);
$router->add('POST', '/api/v2/prescriptions/validate', [$prescriptionController, 'validatePrescription'], ['ADMIN', 'PHARMACIST']);
$router->add('POST', '/api/v2/prescriptions/dispense', [$prescriptionController, 'dispensePrescription'], ['ADMIN', 'PHARMACIST', 'CASHIER']);
$router->add('POST', '/api/v2/prescriptions/complete', [$prescriptionController, 'completePrescription'], ['ADMIN', 'PHARMACIST', 'CASHIER']);
$router->add('POST', '/api/v2/prescriptions/ocr-map', function(array $body, $user) use ($pdo) {
    $ocrItems = $body['items'] ?? [];
    $mapped = [];
    $stmt = $pdo->prepare("
        SELECT TOP 1 d.DrugID, d.DrugCode, d.DrugName, d.Concentration, d.CurrentPrice, d.CurrentStock
        FROM Drugs d
        INNER JOIN ActiveIngredients ai ON d.IngredientID = ai.IngredientID
        WHERE (d.DrugName LIKE ? OR ai.IngredientName LIKE ?) AND d.Concentration = ?
    ");
    foreach ($ocrItems as $item) {
        $kw = '%' . trim($item['drug_name'] ?? '') . '%';
        $conc = trim($item['concentration'] ?? '');
        $stmt->execute([$kw, $kw, $conc]);
        $drug = $stmt->fetch();
        $score = (float)($item['confidence_score'] ?? 0);
        $mapped[] = [
            'ocr_raw'                => $item,
            'matched_drug'           => $drug ?: null,
            'confidence_score'       => $score,
            'needs_yellow_highlight' => ($score < 0.85 || !$drug)
        ];
    }
    return ['success' => true, 'mapped_pos_items' => $mapped];
}, ['ADMIN', 'DOCTOR', 'PHARMACIST']);

// --- ROUTES: SMARTCARE (ZALO ZNS & WEBHOOK) ---
$router->add('GET',  '/api/v2/smartcare/logs', [$smartCareController, 'listLogs'], ['ADMIN', 'PHARMACIST', 'CASHIER']);
$router->add('POST', '/api/v2/smartcare/send-zns', [$smartCareController, 'sendZnsCare'], ['ADMIN', 'PHARMACIST', 'CASHIER']);
$router->add('POST', '/api/v2/smartcare/webhook', [$smartCareController, 'handleWebhook'], null);

// --- ROUTES: AUDIT TEMPORAL TABLES (THANH TRA GPP) ---
$router->add('GET',  '/api/v2/audit/as-of', [$auditController, 'queryAsOf'], ['ADMIN', 'PHARMACIST', 'WAREHOUSE']);

$rawInput = preg_replace('/^[\x{FEFF}\x{FFFE}]/u', '', file_get_contents('php://input'));
$body = json_decode($rawInput, true) ?? [];

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $body);