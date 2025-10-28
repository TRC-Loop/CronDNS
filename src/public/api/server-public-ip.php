<?php
require_once __DIR__."/../../conf/config.php";

// Get stored API key
$apiKeyObject = $settingsManager->find(["key" => "apiKey"]);
$apiKey = $apiKeyObject->value ?? '';

// Get the API key from request headers
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

// Validate API key
if (!$providedKey || $providedKey !== $apiKey) {
    header('Content-Type: application/json; charset=utf-8', true, 401);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid API key'
    ]);
    exit;
}

// Fetch server public IP via ipify
function getServerPublicIPv4(): ?string {
    $ip = @file_get_contents('https://api.ipify.org');
    if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return trim($ip);
    }
    return null;
}

header('Content-Type: application/json; charset=utf-8');
$ip = getServerPublicIPv4();

echo json_encode([
    'ok' => $ip !== null,
    'ipv4' => $ip
]);
?>
