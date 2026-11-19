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
                   ai.IngredientName, df.FormName, df.RouteOfAdmin
            FROM Drugs d
            INNER JOIN ActiveIngredients ai ON d.IngredientID = ai.IngredientID
            INNER JOIN DosageForms df ON d.DosageFormID = df.DosageFormID
            ORDER BY d.DrugID DESC
        ");
        return ['success' => true, 'data' => $stmt->fetchAll()];
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

    // Nhập lô hàng mới (Tự động đồng bộ cộng CurrentStock trên Drugs & lưu Temporal Table)
    public function importBatch(array $body, $user) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO InventoryBatches (BatchCode, DrugID, ManufactureDate, ExpiryDate, CurrentStock, ImportPrice, IsLocked, ModifiedByUserID)
                OUTPUT INSERTED.BatchID
                VALUES (?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $stmt->execute([
                $body['BatchCode'],
                $body['DrugID'],
                $body['ManufactureDate'],
                $body['ExpiryDate'],
                $body['Quantity'],
                $body['ImportPrice'],
                $user['UserID']
            ]);
            $batchId = $stmt->fetchColumn();

            $updDrug = $this->pdo->prepare("UPDATE Drugs SET CurrentStock = CurrentStock + ? WHERE DrugID = ?");
            $updDrug->execute([$body['Quantity'], $body['DrugID']]);

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Nhập lô hàng thành công và đã đồng bộ tồn kho!',
                'BatchID' => (int)$batchId
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(400);
            return ['error' => 'Lỗi nhập kho: ' . $e->getMessage()];
        }
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