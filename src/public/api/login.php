<?php
require_once __DIR__ . "/../../lib/orm.php";
require_once __DIR__ . "/../../conf/config.php";

session_start();

function json_response(array $data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$pwd = $_POST['password'] ?? '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_until'] = 0;
}

$now = time();
$maxAttempts = 5;
$lockoutSeconds = 10;

if ($_SESSION['lockout_until'] > $now) {
    $remaining = $_SESSION['lockout_until'] - $now;
    json_response(['ok' => false, 'error' => 'locked', 'locked_for' => $remaining], 429);
}

// ORM usage: get password hash
$settingsManager = new PersistentEntityManager(KeyValue::class, $logger, DB, 'settings');
$passwordObject = $settingsManager->find(["key" => "passwordHash"]);
$passwordConfig = $passwordObject->value ?? '';

if (!password_verify($pwd, $passwordConfig)) {
    $_SESSION['login_attempts']++;
    if ($_SESSION['login_attempts'] >= $maxAttempts) {
        $_SESSION['lockout_until'] = $now + $lockoutSeconds;
        $_SESSION['login_attempts'] = 0;
        json_response(['ok' => false, 'error' => 'locked', 'locked_for' => $lockoutSeconds], 429);
    }
    json_response(['ok' => false, 'error' => 'invalid', 'attempts_left' => $maxAttempts - $_SESSION['login_attempts']], 401);
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'username' => 'admin',
    'logged_in_at' => $now
];

$_SESSION['login_attempts'] = 0;
$_SESSION['lockout_until'] = 0;

json_response(['ok' => true, 'redirect' => '/home.php']);
?>
