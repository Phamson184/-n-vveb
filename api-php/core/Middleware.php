<?php
class Middleware {
    private $secret;

    public function __construct($secret) {
        $this->secret = $secret;
    }

    // Tạo JWT chuẩn HS256 (Không cần cài thêm thư viện ngoài)
    public function generateToken(array $payload): string {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['exp'] = time() + 86400;
        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$body", $this->secret, true));
        return "$header.$body.$signature";
    }

    // Kiểm tra xác thực & phân quyền RoleID
    public function handle(array $allowedRoles = []) {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->abort(401, 'Unauthorized: Thiếu JWT Token xác thực.');
        }

        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->abort(401, 'Unauthorized: Định dạng Token không hợp lệ.');
        }

        [$header, $body, $signature] = $parts;
        $validSig = $this->base64UrlEncode(hash_hmac('sha256', "$header.$body", $this->secret, true));

        if (!hash_equals($validSig, $signature)) {
            $this->abort(401, 'Unauthorized: Chữ ký Token bị giả mạo.');
        }

        $user = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        if (!$user || ($user['exp'] ?? 0) < time()) {
            $this->abort(401, 'Unauthorized: Phiên đăng nhập đã hết hạn.');
        }

        // Kiểm tra RBAC (Nếu route yêu cầu Role cụ thể)
        if (!empty($allowedRoles) && !in_array($user['RoleCode'], $allowedRoles)) {
            $this->abort(403, 'Forbidden: Tài khoản [' . $user['RoleCode'] . '] không có quyền thực hiện thao tác này.');
        }

        return $user; // Trả về thông tin User (UserID, RoleCode) để lưu ModifiedByUserID
    }

    private function base64UrlEncode($data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function abort(int $code, string $message) {
        http_response_code($code);
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
