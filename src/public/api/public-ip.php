<?php
require_once __DIR__."/../../conf/config.php";

$apiKeyObject = $settingsManager->find(["key" => "apiKey"]);
$apiKey = $apiKeyObject->value;

// Get the API key from the request header
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

// Check API key
if (!$providedKey || $providedKey !== $apiKey) {
    header('Content-Type: application/json; charset=utf-8', true, 401);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid API key'
    ]);
    exit;
}

function getPublicIPv4(): ?string {
    $headers = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null
    ];

    foreach ($headers as $header) {
        if ($header) {
            $parts = explode(',', $header);
            foreach ($parts as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($remote && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $remote;
    }

    $ifaces = @net_get_interfaces() ?: []; // PHP 8+ / fallback: parse `ip addr` if needed
    foreach ($ifaces as $iface) {
        foreach ($iface['unicast'] ?? [] as $addr) {
            $ip = $addr['address'] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && !str_starts_with($ip, '127.') 
                && !str_starts_with($ip, '192.168.') 
                && !str_starts_with($ip, '10.') 
                && !str_starts_with($ip, '172.')) {
                return $ip;
            }
        }
    }

    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
        'https://ident.me'
    ];

    foreach ($services as $url) {
        $ip = @file_get_contents($url);
        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return trim($ip);
        }
    }

    return null; // could not determine
}

header('Content-Type: application/json; charset=utf-8');
$ip = getPublicIPv4();

echo json_encode([
    'ok' => $ip !== null,
    'ipv4' => $ip
]);
