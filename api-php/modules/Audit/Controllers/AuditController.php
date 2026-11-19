<?php
class AuditController {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function queryAsOf(array $body, $user) {
        $table = trim($_GET['table'] ?? $body['table'] ?? 'InventoryBatches');
        $asOf  = trim($_GET['as_of'] ?? $body['as_of'] ?? '');
        $allowedTables = ['InventoryBatches', 'Prescriptions', 'Patients', 'PatientAllergies', 'ControlledDrugLogs'];

        if (!in_array($table, $allowedTables, true)) {
            http_response_code(400);
            return ['error' => 'Bảng không thuộc danh sách Temporal Tables được phép truy vấn.'];
        }

        if ($asOf === '') {
            $sql = "
                SELECT TOP 50 t.*, u.FullName AS ModifiedByName, u.EmployeeCode
                FROM {$table} FOR SYSTEM_TIME ALL t
                LEFT JOIN Users u ON t.ModifiedByUserID = u.UserID
                ORDER BY t.SysStartTime DESC
            ";
            $stmt = $this->pdo->query($sql);
            return ['success' => true, 'table' => $table, 'mode' => 'ALL_HISTORY', 'records' => $stmt->fetchAll()];
        }

        $stmt = $this->pdo->prepare("
            SELECT t.*, u.FullName AS ModifiedByName, u.EmployeeCode
            FROM {$table} FOR SYSTEM_TIME AS OF ? t
            LEFT JOIN Users u ON t.ModifiedByUserID = u.UserID
        ");
        $stmt->execute([$asOf]);
        return [
            'success' => true,
            'table'   => $table,
            'as_of'   => $asOf,
            'records' => $stmt->fetchAll()
        ];
    }

    public function getExpiringBatches(array $body, $user) {
        $days = (int)($_GET['days'] ?? 90);
        if ($days <= 0) $days = 90;

        $stmt = $this->pdo->prepare("
            SELECT ib.BatchID, ib.BatchCode, ib.ExpiryDate, ib.CurrentStock, ib.IsLocked,
                   DATEDIFF(DAY, CAST(GETDATE() AS DATE), ib.ExpiryDate) AS DaysRemaining,
                   d.DrugCode, d.DrugName, d.Concentration, d.IsControlled
            FROM InventoryBatches ib
            INNER JOIN Drugs d ON ib.DrugID = d.DrugID
            WHERE ib.CurrentStock > 0
              AND ib.ExpiryDate <= DATEADD(DAY, CAST(? AS INT), CAST(GETDATE() AS DATE))
            ORDER BY ib.ExpiryDate ASC
        ");
        $stmt->execute([$days]);
        return [
            'success'        => true,
            'threshold_days' => $days,
            'batches'        => $stmt->fetchAll()
        ];
    }
}