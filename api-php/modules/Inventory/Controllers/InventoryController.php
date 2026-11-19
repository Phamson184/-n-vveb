<?php
require_once __DIR__ . '/../Services/FefoService.php';

class InventoryController {
    private $pdo;
    private $fefoService;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->fefoService = new FefoService($pdo);
    }

    // Lấy danh sách thuốc kèm tổng tồn kho khả dụng
    public function listDrugs(array $body, $user) {
        $stmt = $this->pdo->query("
            SELECT d.DrugID, d.DrugCode, d.DrugName, d.Concentration, d.Manufacturer,
                   d.IsPrescriptionOnly, d.IsControlled, d.MinSafeCrCl, d.CurrentPrice, d.CurrentStock,
                   ai.IngredientName, df.FormName, df.RouteOfAdmin, dep.DeptCode, dep.DeptName
            FROM Drugs d
            INNER JOIN ActiveIngredients ai ON d.IngredientID = ai.IngredientID
            INNER JOIN DosageForms df ON d.DosageFormID = df.DosageFormID
            LEFT JOIN Departments dep ON d.DepartmentID = dep.DepartmentID
            ORDER BY d.DrugID DESC
        ");
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    // API Danh mục Y khoa nền cho Frontend (Departments, DosageForms, ActiveIngredients, Interactions)
    public function getMedicalCatalogs(array $body, $user) {
        $depts = $this->pdo->query("SELECT * FROM Departments ORDER BY DeptName ASC")->fetchAll();
        $forms = $this->pdo->query("SELECT * FROM DosageForms ORDER BY FormName ASC")->fetchAll();
        $ings  = $this->pdo->query("SELECT * FROM ActiveIngredients ORDER BY IngredientName ASC")->fetchAll();
        $ddis  = $this->pdo->query("
            SELECT i.*, a1.IngredientName AS IngredientA_Name, a2.IngredientName AS IngredientB_Name
            FROM Interactions i
            INNER JOIN ActiveIngredients a1 ON i.IngredientA_ID = a1.IngredientID
            INNER JOIN ActiveIngredients a2 ON i.IngredientB_ID = a2.IngredientID
            ORDER BY i.InteractionID DESC
        ")->fetchAll();

        return [
            'success'           => true,
            'departments'       => $depts,
            'dosage_forms'      => $forms,
            'active_ingredients'=> $ings,
            'interactions'      => $ddis
        ];
    }

    // Substitution Engine 4 tầng: Cùng INN, cùng DosageForm, cùng Concentration, biên độ giá ±20%, xếp theo hạn dùng xa nhất
    public function getSubstitutes(array $body, $user) {
        $drugId = (int)($_GET['DrugID'] ?? $body['DrugID'] ?? 0);
        if ($drugId <= 0) {
            http_response_code(400);
            return ['error' => 'Thiếu tham số DrugID.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT DrugID, DrugCode, DrugName, IngredientID, DosageFormID, Concentration, Manufacturer, CurrentPrice, CurrentStock
            FROM Drugs
            WHERE DrugID = ?
        ");
        $stmt->execute([$drugId]);
        $target = $stmt->fetch();

        if (!$target) {
            http_response_code(404);
            return ['error' => "Không tìm thấy thuốc DrugID={$drugId}."];
        }

        $basePrice = (float)$target['CurrentPrice'];
        $minPrice  = round($basePrice * 0.80, 2);
        $maxPrice  = round($basePrice * 1.20, 2);

        // Khai thác Covering Index IX_Drugs_Substitution + sắp xếp theo hạn sử dụng xa nhất (FurthestExpiryDate DESC)
        $subStmt = $this->pdo->prepare("
            SELECT d.DrugID, d.DrugCode, d.DrugName, d.Manufacturer, d.CurrentPrice, d.CurrentStock,
                   MAX(ib.ExpiryDate) AS FurthestExpiryDate
            FROM Drugs d
            INNER JOIN InventoryBatches ib ON d.DrugID = ib.DrugID
            WHERE d.IngredientID = ?
              AND d.DosageFormID = ?
              AND d.Concentration = ?
              AND d.DrugID <> ?
              AND d.CurrentStock > 0
              AND d.CurrentPrice BETWEEN ? AND ?
              AND ib.CurrentStock > 0
              AND ib.IsLocked = 0
              AND ib.ExpiryDate > CAST(GETDATE() AS DATE)
            GROUP BY d.DrugID, d.DrugCode, d.DrugName, d.Manufacturer, d.CurrentPrice, d.CurrentStock
            ORDER BY FurthestExpiryDate DESC, d.CurrentStock DESC
        ");
        $subStmt->execute([
            (int)$target['IngredientID'],
            (int)$target['DosageFormID'],
            $target['Concentration'],
            $drugId,
            $minPrice,
            $maxPrice
        ]);

        return [
            'success'      => true,
            'target_drug'  => $target,
            'price_window' => ['min' => $minPrice, 'max' => $maxPrice, 'tolerance' => '±20%'],
            'substitutes'  => $subStmt->fetchAll()
        ];
    }

    // Thêm biệt dược mới vào danh mục Drugs
    public function createDrug(array $body, $user) {
        $sql = "
            INSERT INTO Drugs (DrugCode, NationalDrugCode, DrugName, IngredientID, DosageFormID,
                               Concentration, Manufacturer, DepartmentID, IsPrescriptionOnly,
                               IsControlled, MinSafeCrCl, CurrentPrice, CurrentStock)
            OUTPUT INSERTED.DrugID
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $body['DrugCode'],
            $body['NationalDrugCode'] ?? null,
            $body['DrugName'],
            $body['IngredientID'],
            $body['DosageFormID'],
            $body['Concentration'],
            $body['Manufacturer'],
            $body['DepartmentID'] ?? null,
            $body['IsPrescriptionOnly'] ?? 0,
            $body['IsControlled'] ?? 0,
            $body['MinSafeCrCl'] ?? 30.00,
            $body['CurrentPrice']
        ]);
        $newId = $stmt->fetchColumn();
        return ['success' => true, 'message' => 'Thêm biệt dược thành công!', 'DrugID' => (int)$newId];
    }

    // Nhập lô hàng mới (Tự động đồng bộ CurrentStock, ghi sổ Phụ lục VIII nếu IsControlled = 1)
    public function importBatch(array $body, $user) {
        $drugId = (int)($body['DrugID'] ?? 0);
        $qty = (int)($body['Quantity'] ?? 0);

        $chkDrug = $this->pdo->prepare("SELECT DrugID, DrugName, IsControlled, CurrentStock FROM Drugs WHERE DrugID = ?");
        $chkDrug->execute([$drugId]);
        $drug = $chkDrug->fetch();
        if (!$drug) {
            http_response_code(404);
            return ['error' => "Không tìm thấy thuốc DrugID={$drugId}."];
        }

        if ((int)$drug['IsControlled'] === 1 && empty(trim($body['HandoverReportCode'] ?? ''))) {
            http_response_code(422);
            return ['error' => 'Thuốc kiểm soát đặc biệt (TT 20/2017): Bắt buộc nhập mã Biên bản giao nhận Phụ lục IX (HandoverReportCode) khi nhập kho!'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SET NOCOUNT ON;
                DECLARE @Out TABLE (ID INT);
                INSERT INTO InventoryBatches (BatchCode, DrugID, ManufactureDate, ExpiryDate, CurrentStock, ImportPrice, IsLocked, ModifiedByUserID)
                OUTPUT INSERTED.BatchID INTO @Out
                VALUES (?, ?, ?, ?, ?, ?, 0, ?);
                SELECT ID FROM @Out;
            ");
            $stmt->execute([
                $body['BatchCode'],
                $drugId,
                $body['ManufactureDate'],
                $body['ExpiryDate'],
                $qty,
                $body['ImportPrice'],
                $user['UserID']
            ]);
            $batchId = (int)$stmt->fetchColumn();

            $updDrug = $this->pdo->prepare("UPDATE Drugs SET CurrentStock = CurrentStock + ? WHERE DrugID = ?");
            $updDrug->execute([$qty, $drugId]);

            $newSysStock = (int)$drug['CurrentStock'] + $qty;
            if ((int)$drug['IsControlled'] === 1) {
                $cabinetStock = isset($body['CabinetPhysicalStock']) ? (int)$body['CabinetPhysicalStock'] : $newSysStock;
                $insLog = $this->pdo->prepare("
                    INSERT INTO ControlledDrugLogs (
                        DrugID, BatchID, TransactionType, QuantityChange,
                        CabinetPhysicalStock, SystemStock, HandoverReportCode, ModifiedByUserID
                    ) VALUES (?, ?, 'IMPORT', ?, ?, ?, ?, ?)
                ");
                $insLog->execute([
                    $drugId,
                    $batchId,
                    $qty,
                    $cabinetStock,
                    $newSysStock,
                    trim($body['HandoverReportCode']),
                    $user['UserID']
                ]);
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Nhập lô hàng thành công và đã đồng bộ tồn kho!',
                'BatchID' => $batchId
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            http_response_code(400);
            return ['error' => 'Lỗi nhập kho: ' . $e->getMessage()];
        }
    }

    // Kiểm kê tủ khóa riêng thuốc kiểm soát đặc biệt (Phụ lục VIII - TT 20/2017) & cảnh báo lệch tồn
    public function auditControlledCabinet(array $body, $user) {
        $drugId = (int)($body['DrugID'] ?? 0);
        $batchId = (int)($body['BatchID'] ?? 0);
        $physicalStock = (int)($body['CabinetPhysicalStock'] ?? -1);

        if ($drugId <= 0 || $batchId <= 0 || $physicalStock < 0) {
            http_response_code(400);
            return ['error' => 'Thiếu DrugID, BatchID hoặc CabinetPhysicalStock hợp lệ.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT d.DrugName, d.IsControlled, d.CurrentStock AS SystemStock
            FROM Drugs d
            WHERE d.DrugID = ?
        ");
        $stmt->execute([$drugId]);
        $drug = $stmt->fetch();

        if (!$drug || (int)$drug['IsControlled'] !== 1) {
            http_response_code(400);
            return ['error' => 'Thuốc không thuộc danh mục kiểm soát đặc biệt (IsControlled = 1).'];
        }

        $sysStock = (int)$drug['SystemStock'];
        $discrepancy = $physicalStock - $sysStock;

        $ins = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO ControlledDrugLogs (
                DrugID, BatchID, TransactionType, QuantityChange,
                CabinetPhysicalStock, SystemStock, HandoverReportCode, ModifiedByUserID
            )
            OUTPUT INSERTED.LogID INTO @Out
            VALUES (?, ?, 'AUDIT', ?, ?, ?, ?, ?);
            SELECT ID FROM @Out;
        ");
        $ins->execute([
            $drugId,
            $batchId,
            $discrepancy,
            $physicalStock,
            $sysStock,
            $body['HandoverReportCode'] ?? null,
            $user['UserID']
        ]);

        return [
            'success'          => true,
            'LogID'            => (int)$ins->fetchColumn(),
            'DrugName'         => $drug['DrugName'],
            'CabinetPhysical'  => $physicalStock,
            'SystemStock'      => $sysStock,
            'Discrepancy'      => $discrepancy,
            'Alert'            => $discrepancy !== 0
                ? "CẢNH BÁO NGHIÊM TRỌNG: Lệch {$discrepancy} đơn vị giữa tủ khóa riêng và sổ hệ thống!"
                : 'Khớp số liệu 100% giữa tủ khóa riêng và sổ hệ thống.'
        ];
    }

    // Truy xuất Sổ theo dõi thuốc kiểm soát đặc biệt (Phụ lục VIII - TT 20/2017)
    public function getControlledLogs(array $body, $user) {
        $stmt = $this->pdo->query("
            SELECT c.LogID, c.TransactionType, c.QuantityChange, c.CabinetPhysicalStock, c.SystemStock,
                   c.HandoverReportCode, c.SysStartTime AS RecordedAt,
                   d.DrugCode, d.DrugName, ib.BatchCode, p.NationalCode, u.FullName AS PerformedBy
            FROM ControlledDrugLogs c
            INNER JOIN Drugs d ON c.DrugID = d.DrugID
            INNER JOIN InventoryBatches ib ON c.BatchID = ib.BatchID
            INNER JOIN Users u ON c.ModifiedByUserID = u.UserID
            LEFT JOIN Prescriptions p ON c.PrescriptionID = p.PrescriptionID
            ORDER BY c.SysStartTime DESC
        ");
        return ['success' => true, 'logs' => $stmt->fetchAll()];
    }

    // Báo cáo tiêu thụ thuốc theo Khoa/Phòng cho Hội đồng Thuốc và Điều trị (HĐTDT)
    public function getDepartmentConsumptionReport(array $body, $user) {
        $stmt = $this->pdo->query("
            SELECT COALESCE(dep.DeptCode, 'CHUNG') AS DeptCode,
                   COALESCE(dep.DeptName, N'Danh mục dùng chung') AS DeptName,
                   SUM(CASE WHEN d.IsControlled = 1 THEN dbd.DispensedQty ELSE 0 END) AS ControlledQtyDispensed,
                   SUM(CASE WHEN d.IsControlled = 0 THEN dbd.DispensedQty ELSE 0 END) AS RegularQtyDispensed,
                   SUM(dbd.SubTotal) AS TotalDispensedValue
            FROM DispenseBatchDetails dbd
            INNER JOIN PrescriptionItems pi ON dbd.ItemID = pi.ItemID
            INNER JOIN Drugs d ON pi.DrugID = d.DrugID
            LEFT JOIN Departments dep ON d.DepartmentID = dep.DepartmentID
            GROUP BY dep.DeptCode, dep.DeptName
            ORDER BY TotalDispensedValue DESC
        ");
        return ['success' => true, 'department_report' => $stmt->fetchAll()];
    }

    // API kiểm thử trực tiếp thuật toán xuất kho FEFO chia dòng
    public function testFefoDispense(array $body, $user) {
        $this->pdo->beginTransaction();
        try {
            $res = $this->fefoService->dispenseFefo(
                (int)$body['DrugID'],
                (int)$body['Quantity'],
                (int)$user['UserID']
            );
            $this->pdo->commit();
            return ['success' => true, 'fefo_result' => $res];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}