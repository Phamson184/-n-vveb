<?php
class ClinicalSafetyService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Tính độ thanh thải Creatinin (CrCl - ml/phút) theo công thức Cockcroft-Gault
     */
    public function calculateCrCl(array $patient): ?float {
        if (empty($patient['SerumCreatinine']) || (float)$patient['SerumCreatinine'] <= 0) {
            return null;
        }
        $dob = new DateTime($patient['DateOfBirth']);
        $age = $dob->diff(new DateTime())->y;
        $weight = (float)$patient['Weight_Kg'];
        $scr = (float)$patient['SerumCreatinine'];
        $genderFactor = ($patient['Gender'] === 'F') ? 0.85 : 1.0;

        $crcl = ((140 - $age) * $weight * $genderFactor) / (72 * $scr);
        return round($crcl, 2);
    }

    /**
     * Quét an toàn lâm sàng cho danh sách DrugID của một bệnh nhân
     * Trả về danh sách HardStops (chặn tuyệt đối) và SoftStops (cảnh báo cần OverrideReason)
     */
    public function evaluatePrescriptionSafety(int $patientId, array $items): array {
        $stmtP = $this->pdo->prepare("SELECT * FROM Patients WHERE PatientID = ?");
        $stmtP->execute([$patientId]);
        $patient = $stmtP->fetch();
        if (!$patient) {
            throw new Exception("Không tìm thấy hồ sơ bệnh nhân ID={$patientId}.");
        }

        $crcl = $this->calculateCrCl($patient);

        // Lấy danh sách dị ứng INN của bệnh nhân
        $stmtA = $this->pdo->prepare("
            SELECT pa.ActiveIngredientID, pa.Severity, pa.ReactionNote, ai.IngredientName
            FROM PatientAllergies pa
            INNER JOIN ActiveIngredients ai ON pa.ActiveIngredientID = ai.IngredientID
            WHERE pa.PatientID = ?
        ");
        $stmtA->execute([$patientId]);
        $allergies = [];
        foreach ($stmtA->fetchAll() as $row) {
            $allergies[(int)$row['ActiveIngredientID']] = $row;
        }

        $hardStops = [];
        $softStops = [];
        $ingredientMap = []; // [IngredientID => DrugName]

        // Lấy thông tin thuốc & kiểm tra Dị ứng INN + Ngưỡng thận CrCl
        $stmtD = $this->pdo->prepare("
            SELECT d.DrugID, d.DrugName, d.IngredientID, d.MinSafeCrCl, ai.IngredientName
            FROM Drugs d
            INNER JOIN ActiveIngredients ai ON d.IngredientID = ai.IngredientID
            WHERE d.DrugID = ?
        ");

        foreach ($items as $item) {
            $drugId = (int)$item['DrugID'];
            $overrideReason = trim($item['OverrideReason'] ?? '');

            $stmtD->execute([$drugId]);
            $drug = $stmtD->fetch();
            if (!$drug) {
                throw new Exception("Thuốc DrugID={$drugId} không tồn tại trong danh mục.");
            }

            $ingId = (int)$drug['IngredientID'];
            $ingredientMap[$ingId] = $drug['DrugName'];

            // 1. Kiểm tra Dị ứng hoạt chất INN
            if (isset($allergies[$ingId])) {
                $al = $allergies[$ingId];
                $msg = "Dị ứng hoạt chất [{$al['IngredientName']}] trong thuốc [{$drug['DrugName']}] ({$al['Severity']}): {$al['ReactionNote']}";
                if ($al['Severity'] === 'Hard Stop') {
                    $hardStops[] = $msg;
                } else {
                    if ($overrideReason === '') {
                        $softStops[] = $msg . " -> Bắt buộc nhập OverrideReason để tiếp tục.";
                    }
                }
            }

            // 2. Kiểm tra Ngưỡng suy thận Cockcroft-Gault (CrCl)
            if ($crcl !== null && $drug['MinSafeCrCl'] !== null && $crcl < (float)$drug['MinSafeCrCl']) {
                $msg = "Cảnh báo suy thận: CrCl bệnh nhân = {$crcl} ml/phút thấp hơn ngưỡng an toàn của [{$drug['DrugName']}] ({$drug['MinSafeCrCl']} ml/phút).";
                if ($overrideReason === '') {
                    $softStops[] = $msg . " -> Cần hiệu chỉnh liều và nhập OverrideReason.";
                }
            }
        }

        // 3. Kiểm tra Tương tác thuốc (DDI) giữa các hoạt chất trong cùng đơn
        $ingIds = array_keys($ingredientMap);
        if (count($ingIds) >= 2) {
            $placeholders = implode(',', array_fill(0, count($ingIds), '?'));
            $stmtDdi = $this->pdo->prepare("
                SELECT i.*, a1.IngredientName AS NameA, a2.IngredientName AS NameB
                FROM Interactions i
                INNER JOIN ActiveIngredients a1 ON i.IngredientA_ID = a1.IngredientID
                INNER JOIN ActiveIngredients a2 ON i.IngredientB_ID = a2.IngredientID
                WHERE i.IngredientA_ID IN ($placeholders) AND i.IngredientB_ID IN ($placeholders)
            ");
            $stmtDdi->execute(array_merge($ingIds, $ingIds));
            foreach ($stmtDdi->fetchAll() as $ddi) {
                $msg = "Tương tác DDI [{$ddi['Severity']}] giữa {$ddi['NameA']} và {$ddi['NameB']}: {$ddi['ClinicalEffect']} ({$ddi['ManagementAdvice']})";
                if ($ddi['Severity'] === 'Contraindicated') {
                    $hardStops[] = $msg;
                } else {
                    $softStops[] = $msg;
                }
            }
        }

        return [
            'CrCl_ml_min' => $crcl,
            'is_safe'     => (empty($hardStops) && empty($softStops)),
            'hard_stops'  => $hardStops,
            'soft_stops'  => $softStops
        ];
    }
}