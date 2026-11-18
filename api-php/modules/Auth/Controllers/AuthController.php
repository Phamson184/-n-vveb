<?php
class AuthController {
    private $pdo;
    private $middleware;

    public function __construct(PDO $pdo, Middleware $middleware) {
        $this->pdo = $pdo;
        $this->middleware = $middleware;
    }

    public function login(array $body) {
        $username = trim($body['Username'] ?? '');
        $password = $body['Password'] ?? '';

        if ($username === '' || $password === '') {
            http_response_code(400);
            return ['error' => 'Vui lòng nhập đầy đủ Username và Password.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT u.UserID, u.EmployeeCode, u.FullName, u.LicenseNumber, u.Username, u.PasswordHash, u.IsActive,
                   r.RoleID, r.RoleCode, r.RoleName
            FROM Users u
            INNER JOIN Roles r ON u.RoleID = r.RoleID
            WHERE u.Username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !hash_equals($user['PasswordHash'], hash('sha256', $password))) {
            http_response_code(401);
            return ['error' => 'Tài khoản hoặc mật khẩu không chính xác.'];
        }

        if (!$user['IsActive']) {
            http_response_code(403);
            return ['error' => 'Tài khoản đã bị khóa.'];
        }

        unset($user['PasswordHash']);
        $token = $this->middleware->generateToken([
            'UserID'   => $user['UserID'],
            'RoleID'   => $user['RoleID'],
            'RoleCode' => $user['RoleCode'],
            'FullName' => $user['FullName']
        ]);

        return [
            'success' => true,
            'message' => 'Đăng nhập PharmaPro v2 thành công!',
            'token'   => $token,
            'user'    => $user
        ];
    }
}
