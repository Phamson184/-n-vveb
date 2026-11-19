<?php
class SmartCareController {
    private $pdo;
    private $appId;
    private $oaSecretKey;

    public function __construct(PDO $pdo, string $oaSecretKey, string $appId = 'PHARMAPRO_ZALO_APP_2026') {
        $this->pdo = $pdo;
        $this->oaSecretKey = $oaSecretKey;
        $this->appId = $appId;
    }

    /**
     * Kích hoạt gửi tin nhắn Zalo ZNS chăm sóc sau bán / nhắc lịch tái khám bệnh mạn tính (30/90 ngày)
     */
    public function sendZnsCare(array $body, $user) {
        $rxId = (int)($body['PrescriptionID'] ?? 0);
        $templateId = trim($body['TemplateID'] ?? 'ZNS_RX_CARE_2026');

        if ($rxId <= 0) {
            http_response_code(400);
            return ['error' => 'Thiếu PrescriptionID.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT p.PrescriptionID, p.NationalCode, p.Status, p.IsChronic, p.ChronicDurationDays,
                   pt.PatientID, pt.FullName, pt.Phone, pt.ZaloUserID
            FROM Prescriptions p
            INNER JOIN Patients pt ON p.PatientID = pt.PatientID
            WHERE p.PrescriptionID = ?
        ");
        $stmt->execute([$rxId]);
        $rx = $stmt->fetch();

        if (!$rx) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy đơn thuốc.'];
        }

        if (!in_array($rx['Status'], ['Dispensed', 'Completed'])) {
            http_response_code(409);
            return ['error' => "Chỉ gửi ZNS sau khi đơn đã cấp phát Dispensed/Completed (Hiện tại: {$rx['Status']})."];
        }

        $journeyToken = 'ZNS_' . strtoupper(bin2hex(random_bytes(8)));

        $ins = $this->pdo->prepare("
            SET NOCOUNT ON;
            DECLARE @Out TABLE (ID INT);
            INSERT INTO ZnsLogs (PatientID, PrescriptionID, JourneyToken, TemplateID, Status, UpdatedAt)
            OUTPUT INSERTED.ZnsLogID INTO @Out
            VALUES (?, ?, ?, ?, 'SENT', SYSUTCDATETIME());
            SELECT ID FROM @Out;
        ");
        $ins->execute([
            (int)$rx['PatientID'],
            $rxId,
            $journeyToken,
            $templateId
        ]);
        $logId = (int)$ins->fetchColumn();

        return [
            'success'       => true,
            'message'       => "Đã khởi tạo hành trình Zalo ZNS cho bệnh nhân {$rx['FullName']} ({$rx['Phone']}).",
            'ZnsLogID'      => $logId,
            'JourneyToken'  => $journeyToken,
            'TemplateID'    => $templateId,
            'IsChronic'     => (int)$rx['IsChronic'],
            'FollowUpDays'  => $rx['ChronicDurationDays'] ? (int)$rx['ChronicDurationDays'] : 7,
            'Status'        => 'SENT'
        ];
    }

    /**
     * Webhook nhận phản hồi từ hệ sinh thái Zalo ZNS
     * Công thức chuẩn Mục 1.1: mac = hash_hmac('sha256', $appId . $data . $timeStamp . $OAsecretKey)
     * So khớp tuyệt đối với HTTP Header X-ZEvent-Signature
     */
    public function handleWebhook(array $body, $user) {
        $rawPayload = file_get_contents('php://input');
        $signature  = $_SERVER['HTTP_X_ZEVENT_SIGNATURE'] ?? '';

        $appId     = (string)($body['app_id'] ?? $this->appId);
        $timeStamp = (string)($body['timestamp'] ?? '');
        $dataStr   = isset($body['data'])
            ? (is_string($body['data']) ? $body['data'] : json_encode($body['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            : $rawPayload;

        $expectedMac = hash_hmac('sha256', $appId . $dataStr . $timeStamp . $this->oaSecretKey, $this->oaSecretKey);
        if ($signature === '' || !hash_equals($expectedMac, $signature)) {
            http_response_code(401);
            return ['error' => 'Chữ ký Webhook X-ZEvent-Signature (HMAC SHA-256) không hợp lệ!'];
        }

        $eventData    = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $journeyToken = trim($eventData['JourneyToken'] ?? '');
        $newStatus    = strtoupper(trim($eventData['Status'] ?? ''));

        if ($journeyToken === '' || !in_array($newStatus, ['DELIVERED', 'READ', 'FAILED'])) {
            http_response_code(400);
            return ['error' => 'Payload thiếu JourneyToken hoặc Status không hợp lệ (DELIVERED/READ/FAILED).'];
        }

        $upd = $this->pdo->prepare("
            UPDATE ZnsLogs
            SET Status = ?, WebhookPayload = ?, UpdatedAt = SYSUTCDATETIME()
            WHERE JourneyToken = ?
        ");
        $upd->execute([$newStatus, $rawPayload, $journeyToken]);

        if ($upd->rowCount() === 0) {
            http_response_code(404);
            return ['error' => 'Không tìm thấy JourneyToken tương ứng trong ZnsLogs.'];
        }

        return [
            'success'      => true,
            'message'      => "Đã cập nhật trạng thái ZNS [{$journeyToken}] -> {$newStatus}.",
            'JourneyToken' => $journeyToken,
            'Status'       => $newStatus
        ];
    }

    public function listLogs(array $body, $user) {
        $stmt = $this->pdo->query("
            SELECT z.ZnsLogID, z.JourneyToken, z.TemplateID, z.Status, z.UpdatedAt,
                   pt.PatientCode, pt.FullName AS PatientName, pt.Phone,
                   p.NationalCode
            FROM ZnsLogs z
            INNER JOIN Patients pt ON z.PatientID = pt.PatientID
            LEFT JOIN Prescriptions p ON z.PrescriptionID = p.PrescriptionID
            ORDER BY z.UpdatedAt DESC
        ");
        return ['success' => true, 'logs' => $stmt->fetchAll()];
    }
}