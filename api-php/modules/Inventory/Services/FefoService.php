<?php
class FefoService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Xuất kho tự động theo hạn dùng gần nhất (FEFO) có khóa dòng UPDLOCK chống tranh chấp
     * Tự động chia tách dòng (line splitting) nếu lô đầu không đủ số lượng
     */
    public function dispenseFefo(int $drugId, int $requiredQty, int $userId, int $itemId = null): array {
        // Khóa mức dòng các lô khả dụng của thuốc này theo thứ tự ExpiryDate tăng dần
        $stmt = $this->pdo->prepare("
            SELECT BatchID, BatchCode, ExpiryDate, CurrentStock, ImportPrice
            FROM InventoryBatches WITH (UPDLOCK, ROWLOCK)
            WHERE DrugID = ? AND CurrentStock > 0 AND IsLocked = 0 AND ExpiryDate > CAST(GETDATE() AS DATE)
            ORDER BY ExpiryDate ASC, BatchID ASC
        ");
        $stmt->execute([$drugId]);
        $batches = $stmt->fetchAll();

        $totalAvailable = array_sum(array_column($batches, 'CurrentStock'));
        if ($totalAvailable < $requiredQty) {
            throw new Exception("Tồn kho khả dụng không đủ cho DrugID={$drugId} (Yêu cầu: {$requiredQty}, Khả dụng: {$totalAvailable}).");
        }

        // Lấy đơn giá bán hiện tại của thuốc
        $drugStmt = $this->pdo->prepare("SELECT CurrentPrice, DrugName FROM Drugs WHERE DrugID = ?");
        $drugStmt->execute([$drugId]);
        $drug = $drugStmt->fetch();

        $remaining = $requiredQty;
        $allocatedLines = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $takeQty = min($remaining, (int)$batch['CurrentStock']);
            $newBatchStock = (int)$batch['CurrentStock'] - $takeQty;

            // Trừ tồn kho lô hàng kèm ModifiedByUserID để Temporal Table ghi nhận người thao tác
            $updBatch = $this->pdo->prepare("
                UPDATE InventoryBatches
                SET CurrentStock = ?, ModifiedByUserID = ?
                WHERE BatchID = ?
            ");
            $updBatch->execute([$newBatchStock, $userId, $batch['BatchID']]);

            // Nếu gắn với dòng đơn thuốc (ItemID), lưu chi tiết dòng xuất tách lô
            if ($itemId !== null) {
                $insDetail = $this->pdo->prepare("
                    INSERT INTO DispenseBatchDetails (ItemID, BatchID, DispensedQty, UnitPrice)
                    VALUES (?, ?, ?, ?)
                ");
                $insDetail->execute([$itemId, $batch['BatchID'], $takeQty, $drug['CurrentPrice']]);
            }

            $allocatedLines[] = [
                'BatchID'      => $batch['BatchID'],
                'BatchCode'    => $batch['BatchCode'],
                'ExpiryDate'   => $batch['ExpiryDate'],
                'DispensedQty' => $takeQty,
                'UnitPrice'    => $drug['CurrentPrice'],
                'SubTotal'     => $takeQty * (float)$drug['CurrentPrice']
            ];

            $remaining -= $takeQty;
        }

        // Đồng bộ giảm tồn kho tổng hợp trên bảng Drugs (phục vụ Covering Index)
        $updDrug = $this->pdo->prepare("
            UPDATE Drugs SET CurrentStock = CurrentStock - ? WHERE DrugID = ?
        ");
        $updDrug->execute([$requiredQty, $drugId]);

        return [
            'DrugID'         => $drugId,
            'DrugName'       => $drug['DrugName'],
            'TotalDispensed' => $requiredQty,
            'LineSplitCount' => count($allocatedLines),
            'Batches'        => $allocatedLines
        ];
    }
}