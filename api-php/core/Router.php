<?php
class Router {
    private $routes = [];
    private $middleware;

    public function __construct(Middleware $middleware) {
        $this->middleware = $middleware;
    }

    public function add(string $method, string $path, callable $handler, array $allowedRoles = null) {
        $this->routes["$method $path"] = [
            'handler' => $handler,
            'roles' => $allowedRoles // null = Public API (không cần login), [] = Cần login mọi role, ['ADMIN'] = Giới hạn role
        ];
    }

    public function dispatch(string $method, string $uri, array $body) {
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/');
        $key = "$method $path";

        if (!isset($this->routes[$key])) {
            http_response_code(404);
            echo json_encode(['error' => "Endpoint không tồn tại: $key"], JSON_UNESCAPED_UNICODE);
            return;
        }

        $route = $this->routes[$key];
        $currentUser = null;

        if ($route['roles'] !== null) {
            $currentUser = $this->middleware->handle($route['roles']);
        }

        try {
            $result = call_user_func($route['handler'], $body, $currentUser);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi hệ thống: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
