<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_NAME', 'ERP Administrativo');
define('BASE_URL', '/erp_administrativo');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'erp_administrativo');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    return $pdo;
}
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function money($value): string {
    return '$ '.number_format((float)$value, 2, ',', '.');
}
function redirect(string $url): never {
    header('Location: '.$url);
    exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('La sesión del formulario expiró. Regresa e inténtalo de nuevo.');
    }
}
function require_login(): void {
    if (empty($_SESSION['user_id'])) redirect('login.php');
}
