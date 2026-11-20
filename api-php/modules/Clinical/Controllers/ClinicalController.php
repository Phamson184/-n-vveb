<?php
require_once __DIR__ . '/../Services/ClinicalSafetyService.php';

class ClinicalController {
    private $pdo;
    private $safetyService;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->safetyService = new ClinicalSafetyService($pdo);
    }

    public function createPatient(array $body, $user) {
        if (empty($body['PatientCode']) || empty($body['FullName']) || empty($body['Phone'])) {
            http_response_code(400);
            return ['error' => 'Thiếu thông tin bắt buộc (PatientCode, FullName, Phone).'];
        }

        $chk = $this->pdo->prepare("SELECT PatientID FROM Patients WHERE PatientCode = ?");
        $chk->execute([$body['PatientCode']]);
        if ($existingId = $chk->fetchColumn()) {
            return ['success' => true, 'message' => 'Hồ sơ bệnh nhân đã tồn tại.', 'PatientID' => (int)$existingId];
        }

        $stmt = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO Patients (PatientCode, FullName, Phone, DateOfBirth, Gender, Weight_Kg,
                                  SerumCreatinine, PatientType, ChronicCondition, Address, ModifiedByUserID)
            OUTPUT INSERTED.PatientID INTO @Out
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
            SELECT ID FROM @Out;
        ");
        $stmt->execute([
            $body['PatientCode'],
            $body['FullName'],
            $body['Phone'],
            $body['DateOfBirth'],
            $body['Gender'],
            $body['Weight_Kg'],
            $body['SerumCreatinine'] ?? null,
            $body['PatientType'] ?? 'Thường',
            $body['ChronicCondition'] ?? null,
            $body['Address'] ?? null,
            $user['UserID']
        ]);
        return ['success' => true, 'message' => 'Tạo hồ sơ dược bạ thành công!', 'PatientID' => (int)$stmt->fetchColumn()];
    }

    public function addAllergy(array $body, $user) {
        if (empty($body['PatientID']) || empty($body['ActiveIngredientID']) || empty($body['Severity'])) {
            http_response_code(400);
            return ['error' => 'Thiếu PatientID, ActiveIngredientID hoặc Severity.'];
        }

        $chk = $this->pdo->prepare("SELECT AllergyID FROM PatientAllergies WHERE PatientID = ? AND ActiveIngredientID = ?");
        $chk->execute([(int)$body['PatientID'], (int)$body['ActiveIngredientID']]);
        if ($existingId = $chk->fetchColumn()) {
            return ['success' => true, 'message' => 'Cảnh báo dị ứng này đã có sẵn trong hồ sơ.', 'AllergyID' => (int)$existingId];
        }

        $stmt = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO PatientAllergies (PatientID, ActiveIngredientID, Severity, ReactionNote, ModifiedByUserID)
            OUTPUT INSERTED.AllergyID INTO @Out
            VALUES (?, ?, ?, ?, ?);
            SELECT ID FROM @Out;
        ");
        $stmt->execute([
            (int)$body['PatientID'],
            (int)$body['ActiveIngredientID'],
            $body['Severity'],
            $body['ReactionNote'] ?? null,
            $user['UserID']
        ]);
        return [
            'success'   => true,
            'message'   => "Đã ghi nhận cảnh báo dị ứng [{$body['Severity']}] vào hồ sơ bệnh nhân!",
            'AllergyID' => (int)$stmt->fetchColumn()
        ];
    }

    public function getPatient360(array $body, $user) {
        $patientId = (int)($body['PatientID'] ?? $_GET['PatientID'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM Patients WHERE PatientID = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy bệnh nhân.'];
        }

        $crcl = $this->safetyService->calculateCrCl($patient);

        $stmtA = $this->pdo->prepare("
            SELECT pa.AllergyID, pa.Severity, pa.ReactionNote, ai.IngredientID, ai.IngredientName
            FROM PatientAllergies pa
            INNER JOIN ActiveIngredients ai ON pa.ActiveIngredientID = ai.IngredientID
            WHERE pa.PatientID = ?
        ");
        $stmtA->execute([$patientId]);

        $stmtRx = $this->pdo->prepare("
            SELECT PrescriptionID, NationalCode, Diagnosis, ClinicalInterventionNote, Status, TotalAmount, CreatedAt
            FROM Prescriptions
            WHERE PatientID = ?
            ORDER BY CreatedAt DESC
        ");
        $stmtRx->execute([$patientId]);

        return [
            'success'              => true,
            'patient'              => $patient,
            'calculated_CrCl'      => $crcl,
            'allergies_INN'        => $stmtA->fetchAll(),
            'prescription_history' => $stmtRx->fetchAll()
        ];
    }

    // Tìm kiếm danh sách bệnh nhân theo tên, mã BN hoặc số điện thoại (phục vụ POS & Hồ sơ 360)
    public function searchPatients(array $body, $user) {
        $q = trim(<?php
require_once __DIR__ . '/../Services/ClinicalSafetyService.php';

class ClinicalController {
    private $pdo;
    private $safetyService;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->safetyService = new ClinicalSafetyService($pdo);
    }

    public function createPatient(array $body, $user) {
        if (empty($body['PatientCode']) || empty($body['FullName']) || empty($body['Phone'])) {
            http_response_code(400);
            return ['error' => 'Thiếu thông tin bắt buộc (PatientCode, FullName, Phone).'];
        }

        $chk = $this->pdo->prepare("SELECT PatientID FROM Patients WHERE PatientCode = ?");
        $chk->execute([$body['PatientCode']]);
        if ($existingId = $chk->fetchColumn()) {
            return ['success' => true, 'message' => 'Hồ sơ bệnh nhân đã tồn tại.', 'PatientID' => (int)$existingId];
        }

        $stmt = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO Patients (PatientCode, FullName, Phone, DateOfBirth, Gender, Weight_Kg,
                                  SerumCreatinine, PatientType, ChronicCondition, Address, ModifiedByUserID)
            OUTPUT INSERTED.PatientID INTO @Out
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
            SELECT ID FROM @Out;
        ");
        $stmt->execute([
            $body['PatientCode'],
            $body['FullName'],
            $body['Phone'],
            $body['DateOfBirth'],
            $body['Gender'],
            $body['Weight_Kg'],
            $body['SerumCreatinine'] ?? null,
            $body['PatientType'] ?? 'Thường',
            $body['ChronicCondition'] ?? null,
            $body['Address'] ?? null,
            $user['UserID']
        ]);
        return ['success' => true, 'message' => 'Tạo hồ sơ dược bạ thành công!', 'PatientID' => (int)$stmt->fetchColumn()];
    }

    public function addAllergy(array $body, $user) {
        if (empty($body['PatientID']) || empty($body['ActiveIngredientID']) || empty($body['Severity'])) {
            http_response_code(400);
            return ['error' => 'Thiếu PatientID, ActiveIngredientID hoặc Severity.'];
        }

        $chk = $this->pdo->prepare("SELECT AllergyID FROM PatientAllergies WHERE PatientID = ? AND ActiveIngredientID = ?");
        $chk->execute([(int)$body['PatientID'], (int)$body['ActiveIngredientID']]);
        if ($existingId = $chk->fetchColumn()) {
            return ['success' => true, 'message' => 'Cảnh báo dị ứng này đã có sẵn trong hồ sơ.', 'AllergyID' => (int)$existingId];
        }

        $stmt = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO PatientAllergies (PatientID, ActiveIngredientID, Severity, ReactionNote, ModifiedByUserID)
            OUTPUT INSERTED.AllergyID INTO @Out
            VALUES (?, ?, ?, ?, ?);
            SELECT ID FROM @Out;
        ");
        $stmt->execute([
            (int)$body['PatientID'],
            (int)$body['ActiveIngredientID'],
            $body['Severity'],
            $body['ReactionNote'] ?? null,
            $user['UserID']
        ]);
        return [
            'success'   => true,
            'message'   => "Đã ghi nhận cảnh báo dị ứng [{$body['Severity']}] vào hồ sơ bệnh nhân!",
            'AllergyID' => (int)$stmt->fetchColumn()
        ];
    }

    public function getPatient360(array $body, $user) {
        $patientId = (int)($body['PatientID'] ?? $_GET['PatientID'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM Patients WHERE PatientID = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy bệnh nhân.'];
        }

        $crcl = $this->safetyService->calculateCrCl($patient);

        $stmtA = $this->pdo->prepare("
            SELECT pa.AllergyID, pa.Severity, pa.ReactionNote, ai.IngredientID, ai.IngredientName
            FROM PatientAllergies pa
            INNER JOIN ActiveIngredients ai ON pa.ActiveIngredientID = ai.IngredientID
            WHERE pa.PatientID = ?
        ");
        $stmtA->execute([$patientId]);

        $stmtRx = $this->pdo->prepare("
            SELECT PrescriptionID, NationalCode, Diagnosis, ClinicalInterventionNote, Status, TotalAmount, CreatedAt
            FROM Prescriptions
            WHERE PatientID = ?
            ORDER BY CreatedAt DESC
        ");
        $stmtRx->execute([$patientId]);

        return [
            'success'              => true,
            'patient'              => $patient,
            'calculated_CrCl'      => $crcl,
            'allergies_INN'        => $stmtA->fetchAll(),
            'prescription_history' => $stmtRx->fetchAll()
        ];
    }
}GET['q'] ?? $body['q'] ?? '');
        if ($q === '') {
            $stmt = $this->pdo->query("
                SELECT TOP 50 PatientID, PatientCode, FullName, Phone, Gender, DateOfBirth,
                       Weight_Kg, SerumCreatinine, PatientType, ChronicCondition, LoyaltyPoints
                FROM Patients
                ORDER BY PatientID DESC
            ");
            return ['success' => true, 'patients' => $stmt->fetchAll()];
        }
        $kw = "%{$q}%";
        $stmt = $this->pdo->prepare("
            SELECT TOP 50 PatientID, PatientCode, FullName, Phone, Gender, DateOfBirth,
                   Weight_Kg, SerumCreatinine, PatientType, ChronicCondition, LoyaltyPoints
            FROM Patients
            WHERE FullName LIKE ? OR PatientCode LIKE ? OR Phone LIKE ?
            ORDER BY PatientID DESC
        ");
        $stmt->execute([$kw, $kw, $kw]);
        return ['success' => true, 'patients' => $stmt->fetchAll()];
    }
}