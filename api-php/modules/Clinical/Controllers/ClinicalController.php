<?php
class ClinicalController {
    private $pdo;
    private $safetyService;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->safetyService = new ClinicalSafetyService($pdo);
    }

    public function createPatient(array $body, $user) {
        $code   = trim($body['PatientCode'] ?? '');
        $name   = trim($body['FullName'] ?? '');
        $phone  = trim($body['Phone'] ?? '');
        $dob    = trim($body['DateOfBirth'] ?? '');
        $gender = strtoupper(trim($body['Gender'] ?? 'M'));
        $weight = (float)($body['Weight_Kg'] ?? 0);
        $scr    = (float)($body['SerumCreatinine'] ?? 1.0);
        $pType  = trim($body['PatientType'] ?? 'Thường');
        $cond   = trim($body['ChronicCondition'] ?? '');

        if ($code === '' || $name === '' || $dob === '' || $weight <= 0 || $scr <= 0) {
            http_response_code(400);
            return ['error' => 'Thiếu thông tin bắt buộc hoặc cân nặng/Creatinine không hợp lệ.'];
        }

        $stmt = $this->pdo->prepare("
            DECLARE @Out TABLE (PatientID INT);
            INSERT INTO Patients (PatientCode, FullName, Phone, DateOfBirth, Gender, Weight_Kg, SerumCreatinine, PatientType, ChronicCondition, ModifiedByUserID)
            OUTPUT INSERTED.PatientID INTO @Out
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
            SELECT PatientID FROM @Out;
        ");
        $stmt->execute([$code, $name, $phone, $dob, $gender, $weight, $scr, $pType, $cond ?: null, $user['UserID'] ?? null]);
        $row = $stmt->fetch();

        return [
            'success'   => true,
            'message'   => 'Tạo hồ sơ bệnh nhân thành công!',
            'PatientID' => (int)$row['PatientID']
        ];
    }

    public function addAllergy(array $body, $user) {
        $patientId    = (int)($body['PatientID'] ?? 0);
        $ingredientId = (int)($body['ActiveIngredientID'] ?? 0);
        $severity     = trim($body['Severity'] ?? 'Hard Stop');
        $note         = trim($body['ReactionNote'] ?? '');

        if ($patientId <= 0 || $ingredientId <= 0) {
            http_response_code(400);
            return ['error' => 'PatientID và ActiveIngredientID là bắt buộc.'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO PatientAllergies (PatientID, ActiveIngredientID, Severity, ReactionNote, ModifiedByUserID)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patientId, $ingredientId, $severity, $note ?: null, $user['UserID'] ?? null]);

        return ['success' => true, 'message' => 'Đã ghi nhận tiền sử dị ứng chuẩn hoạt chất INN.'];
    }

    public function getPatient360(array $body, $user) {
        $patientId = (int)($_GET['PatientID'] ?? $body['PatientID'] ?? 0);
        if ($patientId <= 0) {
            http_response_code(400);
            return ['error' => 'Vui lòng cung cấp PatientID.'];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM Patients WHERE PatientID = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        if (!$patient) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy bệnh nhân.'];
        }

        $crcl = $this->safetyService->calculateCrCl($patient);

        $stAllergy = $this->pdo->prepare("
            SELECT pa.AllergyID, pa.Severity, pa.ReactionNote, ai.IngredientName
            FROM PatientAllergies pa
            INNER JOIN ActiveIngredients ai ON pa.ActiveIngredientID = ai.IngredientID
            WHERE pa.PatientID = ?
        ");
        $stAllergy->execute([$patientId]);
        $allergies = $stAllergy->fetchAll();

        $stRx = $this->pdo->prepare("
            SELECT PrescriptionID, NationalCode, Diagnosis, Status, IsChronic, ChronicDurationDays,
                   ClinicalInterventionNote, TotalAmount, CreatedAt
            FROM Prescriptions
            WHERE PatientID = ?
            ORDER BY CreatedAt DESC
        ");
        $stRx->execute([$patientId]);
        $prescriptions = $stRx->fetchAll();

        return [
            'success'              => true,
            'patient'              => $patient,
            'calculated_CrCl'      => $crcl,
            'allergies_INN'        => $allergies,
            'prescription_history' => $prescriptions
        ];
    }

    public function searchPatients(array $body, $user) {
        $q = trim($_GET['q'] ?? $body['q'] ?? '');
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