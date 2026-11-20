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
        $chronic = trim($body['ChronicCondition'] ?? 'Không');
        $address = trim($body['Address'] ?? '');

        if ($code === '' || $name === '' || $phone === '' || $dob === '' || $weight <= 0 || $scr <= 0) {
            http_response_code(422);
            return ['error' => 'Vui lòng nhập đầy đủ thông tin bắt buộc và chỉ số hợp lệ.'];
        }

        $chkCode = $this->pdo->prepare("SELECT PatientID FROM Patients WHERE PatientCode = ?");
        $chkCode->execute([$code]);
        if ($chkCode->fetch()) {
            http_response_code(422);
            return ['error' => "Mã bệnh nhân '$code' đã tồn tại trong hệ thống.", 'field' => 'PatientCode'];
        }

        $chkPhone = $this->pdo->prepare("SELECT PatientID, FullName FROM Patients WHERE Phone = ?");
        $chkPhone->execute([$phone]);
        if ($exPhone = $chkPhone->fetch()) {
            http_response_code(422);
            $owner = $exPhone['FullName'] ?? '';
            return ['error' => "Số điện thoại '$phone' đã được đăng ký cho bệnh nhân ($owner).", 'field' => 'Phone'];
        }

        try {
            $this->pdo->exec("
                DECLARE @ck NVARCHAR(128);
                SELECT @ck = name FROM sys.check_constraints WHERE parent_object_id = OBJECT_ID('Patients') AND definition LIKE '%PatientType%';
                IF @ck IS NOT NULL EXEC('ALTER TABLE Patients DROP CONSTRAINT [' + @ck + ']');
                DECLARE @df NVARCHAR(128);
                SELECT @df = name FROM sys.default_constraints WHERE parent_object_id = OBJECT_ID('Patients') AND col_name(parent_object_id, parent_column_id) = 'PatientType';
                IF @df IS NOT NULL EXEC('ALTER TABLE Patients DROP CONSTRAINT [' + @df + ']');
                IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('Patients') AND name = 'PatientType' AND system_type_id = 167)
                BEGIN
                    ALTER TABLE Patients ALTER COLUMN PatientType NVARCHAR(30) NOT NULL;
                    ALTER TABLE Patients ADD CONSTRAINT DF_Patients_PatientType DEFAULT N'Thường' FOR PatientType;
                    ALTER TABLE Patients ADD CONSTRAINT CK_Patients_PatientType CHECK (PatientType IN (N'Thường', N'VIP', N'Bệnh mạn tính'));
                END
            ");
        } catch (Throwable $ignore) {}

        $uid = (int)($user['UserID'] ?? 1);
        $stmt = $this->pdo->prepare("
            INSERT INTO Patients (PatientCode, FullName, Phone, DateOfBirth, Gender, Weight_Kg, SerumCreatinine, PatientType, ChronicCondition, Address, ModifiedByUserID)
            OUTPUT INSERTED.PatientID
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([$code, $name, $phone, $dob, $gender, $weight, $scr, $pType, $chronic, $address !== '' ? $address : null, $uid]);
            $row = $stmt->fetch();
            $newId = (int)($row['PatientID'] ?? 0);
            return ['success' => true, 'PatientID' => $newId];
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            http_response_code(422);
            if (stripos($msg, 'Phone') !== false || stripos($msg, 'UQ__Patients__5C7E359E') !== false) {
                return ['error' => "Số điện thoại '$phone' đã tồn tại trong hệ thống.", 'field' => 'Phone'];
            }
            if (stripos($msg, 'PatientCode') !== false || stripos($msg, 'UNIQUE') !== false) {
                return ['error' => "Mã bệnh nhân '$code' đã tồn tại trong hệ thống.", 'field' => 'PatientCode'];
            }
            http_response_code(500);
            return ['error' => 'Không thể lưu bệnh nhân: ' . $msg];
        }
    }

    public function addAllergy(array $body, $user) {
        $patientId = (int)($body['PatientID'] ?? 0);
        $ingId     = (int)($body['ActiveIngredientID'] ?? 0);
        $severity  = trim($body['Severity'] ?? 'Soft Stop');
        $note      = trim($body['ReactionNote'] ?? '');

        if ($patientId <= 0 || $ingId <= 0) {
            http_response_code(422);
            return ['error' => 'Thiếu PatientID hoặc ActiveIngredientID.'];
        }

        $uid = (int)($user['UserID'] ?? 1);
        $stmt = $this->pdo->prepare("
            INSERT INTO PatientAllergies (PatientID, ActiveIngredientID, Severity, ReactionNote, ModifiedByUserID)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patientId, $ingId, $severity, $note, $uid]);
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