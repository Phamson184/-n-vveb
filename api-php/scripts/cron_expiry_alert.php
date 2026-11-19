<?php
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance($config['db']);

$sql = "
    SELECT ib.BatchID, ib.BatchCode, ib.ExpiryDate, ib.CurrentStock,
           DATEDIFF(DAY, CAST(GETDATE() AS DATE), ib.ExpiryDate) AS DaysRemaining,
           d.DrugCode, d.DrugName, d.Concentration, d.IsControlled
    FROM InventoryBatches ib
    INNER JOIN Drugs d ON ib.DrugID = d.DrugID
    WHERE ib.CurrentStock > 0
      AND ib.ExpiryDate <= DATEADD(DAY, 90, CAST(GETDATE() AS DATE))
    ORDER BY ib.ExpiryDate ASC
";
$stmt = $pdo->query($sql);
$batches = $stmt->fetchAll();

$report = [
    'generated_at' => date('Y-m-d H:i:s'),
    'threshold_days' => 90,
    'total_alert_batches' => count($batches),
    'batches' => $batches
];

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;