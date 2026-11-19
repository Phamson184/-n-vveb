<?php
require_once __DIR__ . '/../../Clinical/Services/ClinicalSafetyService.php';
require_once __DIR__ . '/../../Inventory/Services/FefoService.php';

class PrescriptionController {
    private $pdo;
    private $safetyService;
    private $fefoService;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->safetyService = new ClinicalSafetyService($pdo);
        $this->fefoService = new FefoService($pdo);
    }

    private function generateNationalCode(string $suffix): string {
        $facilityCode = '79001';
        do {
            $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $code = "{$facilityCode}{$rand}-{$suffix}";
            $stmt = $this->pdo->prepare("SELECT 1 FROM Prescriptions WHERE NationalCode = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn());
        return $code;
    }

    public function createPrescription(array $body, $user) {
        $patientId = (int)($body['PatientID'] ?? 0);
        $items = $body['items'] ?? [];
        if ($patientId <= 0 || empty($items)) {
            http_response_code(400);
            return ['error' => 'Thiếu PatientID hoặc danh sách thuốc (items).'];
        }

        $safety = $this->safetyService->evaluatePrescriptionSafety($patientId, $items);
        if (!empty($safety['hard_stops'])) {
            http_response_code(422);
            return [
                'success' => false,
                'blocked_by' => 'HARD_STOP',
                'message' => 'Đơn thuốc vi phạm chống chỉ định tuyệt đối, hệ thống từ chối lưu đơn!',
                'safety_report' => $safety
            ];
        }
        if (!empty($safety['soft_stops'])) {
            http_response_code(422);
            return [
                'success' => false,
                'blocked_by' => 'SOFT_STOP',
                'message' => 'Có cảnh báo lâm sàng cần bổ sung OverrideReason cho từng thuốc liên quan.',
                'safety_report' => $safety
            ];
        }

        $hasControlled = false;
        $totalAmount = 0.0;
        $drugStmt = $this->pdo->prepare("SELECT DrugID, IsControlled, CurrentPrice FROM Drugs WHERE DrugID = ?");

        foreach ($items as $item) {
            $drugStmt->execute([(int)$item['DrugID']]);
            $d = $drugStmt->fetch();
            if (!$d) {
                http_response_code(404);
                return ['error' => "Không tìm thấy thuốc DrugID={$item['DrugID']}"];
            }
            if ((int)$d['IsControlled'] === 1) {
                $hasControlled = true;
            }
            $qty = (int)($item['PrescribedQty'] ?? 1);
            $totalAmount += $qty * (float)$d['CurrentPrice'];
        }

        $suffix = $hasControlled ? (in_array($body['ControlledType'] ?? '', ['N', 'H']) ? $body['ControlledType'] : 'N') : 'C';
        $nationalCode = $this->generateNationalCode($suffix);

        try {
            $this->pdo->beginTransaction();

            $stmtRx = $this->pdo->prepare("
                SET NOCOUNT ON;
                DECLARE @Out TABLE (ID INT);
                INSERT INTO Prescriptions (
                    NationalCode, PatientID, DoctorID, Diagnosis, ClinicalInterventionNote,
                    RequiresCommitteeConsult, IsChronic, ChronicDurationDays, Status,
                    TotalAmount, ModifiedByUserID
                )
                OUTPUT INSERTED.PrescriptionID INTO @Out
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?);
                SELECT ID FROM @Out;
            ");
            $stmtRx->execute([
                $nationalCode,
                $patientId,
                $user['RoleCode'] === 'DOCTOR' ? $user['UserID'] : ($body['DoctorID'] ?? null),
                $body['Diagnosis'] ?? null,
                $body['ClinicalInterventionNote'] ?? null,
                !empty($body['RequiresCommitteeConsult']) ? 1 : 0,
                !empty($body['IsChronic']) ? 1 : 0,
                $body['ChronicDurationDays'] ?? null,
                $totalAmount,
                $user['UserID']
            ]);
            $prescriptionId = (int)$stmtRx->fetchColumn();

            $stmtItem = $this->pdo->prepare("
                INSERT INTO PrescriptionItems (PrescriptionID, DrugID, PrescribedQty, DosageInstruction, OcrConfidenceScore, OverrideReason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $stmtItem->execute([
                    $prescriptionId,
                    (int)$item['DrugID'],
                    (int)$item['PrescribedQty'],
                    $item['DosageInstruction'] ?? 'Dùng theo chỉ định',
                    $item['OcrConfidenceScore'] ?? null,
                    $item['OverrideReason'] ?? null
                ]);
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Khởi tạo đơn thuốc điện tử thành công!',
                'PrescriptionID' => $prescriptionId,
                'NationalCode' => $nationalCode,
                'Status' => 'Pending',
                'TotalAmount' => $totalAmount,
                'safety_report' => $safety
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            http_response_code(500);
            return ['error' => 'Lỗi lưu đơn thuốc: ' . $e->getMessage()];
        }
    }

    public function validatePrescription(array $body, $user) {
        $rxId = (int)($body['PrescriptionID'] ?? 0);
        $decision = $body['Decision'] ?? 'Validated';
        if ($rxId <= 0 || !in_array($decision, ['Validated', 'Rejected'])) {
            http_response_code(400);
            return ['error' => 'Thiếu PrescriptionID hoặc Decision không hợp lệ (Validated/Rejected).'];
        }

        if ($decision === 'Validated' && empty($body['DigitalSignature'])) {
            http_response_code(400);
            return ['error' => 'Bắt buộc cung cấp chữ ký số (DigitalSignature) khi duyệt đơn Validated.'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE Prescriptions
            SET Status = ?, PharmacistID = ?, ClinicalInterventionNote = COALESCE(?, ClinicalInterventionNote),
                DigitalSignature = ?, ModifiedByUserID = ?
            WHERE PrescriptionID = ? AND Status = 'Pending'
        ");
        $stmt->execute([
            $decision,
            $user['UserID'],
            $body['ClinicalInterventionNote'] ?? null,
            $body['DigitalSignature'] ?? null,
            $user['UserID'],
            $rxId
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            return ['error' => 'Không thể cập nhật: Đơn không tồn tại hoặc không ở trạng thái Pending.'];
        }

        return [
            'success' => true,
            'message' => "Đã cập nhật trạng thái đơn #{$rxId} sang {$decision}.",
            'Status' => $decision
        ];
    }

    public function dispensePrescription(array $body, $user) {
        $rxId = (int)($body['PrescriptionID'] ?? 0);
        if ($rxId <= 0) {
            http_response_code(400);
            return ['error' => 'Thiếu PrescriptionID.'];
        }

        $stmtRx = $this->pdo->prepare("SELECT * FROM Prescriptions WHERE PrescriptionID = ?");
        $stmtRx->execute([$rxId]);
        $rx = $stmtRx->fetch();

        if (!$rx) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy đơn thuốc.'];
        }
        if ($rx['Status'] !== 'Validated') {
            http_response_code(409);
            return ['error' => "Chỉ được xuất kho đơn ở trạng thái Validated (Hiện tại: {$rx['Status']})."];
        }

        $stmtItems = $this->pdo->prepare("SELECT * FROM PrescriptionItems WHERE PrescriptionID = ?");
        $stmtItems->execute([$rxId]);
        $items = $stmtItems->fetchAll();

        try {
            $this->pdo->beginTransaction();

            $dispenseSummary = [];
            foreach ($items as $item) {
                $fefoResult = $this->fefoService->dispenseFefo(
                    (int)$item['DrugID'],
                    (int)$item['PrescribedQty'],
                    (int)$user['UserID'],
                    (int)$item['ItemID']
                );
                $dispenseSummary[] = $fefoResult;
            }

            $updRx = $this->pdo->prepare("
                UPDATE Prescriptions SET Status = 'Dispensed', ModifiedByUserID = ? WHERE PrescriptionID = ?
            ");
            $updRx->execute([$user['UserID'], $rxId]);

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => "Đã xuất kho FEFO và cấp phát thành công đơn {$rx['NationalCode']}!",
                'PrescriptionID' => $rxId,
                'Status' => 'Dispensed',
                'dispense_details' => $dispenseSummary
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            http_response_code(422);
            return ['error' => 'Xuất kho FEFO thất bại (đã rollback): ' . $e->getMessage()];
        }
    }
}