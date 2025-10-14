<?php
require_once __DIR__."/../../conf/config.php";

$apiKeyObject = $settingsManager->find(["key" => "apiKey"]);
$currentApiKey = $apiKeyObject->value;

// Get old API key from URL arguments
$oldKey = $_GET['oldKey'] ?? null;

// Check old API key
if (!$oldKey || $oldKey !== $currentApiKey) {
    header('Content-Type: application/json; charset=utf-8', true, 401);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid old API key'
    ]);
    exit;
}

$newKey = bin2hex(random_bytes(32));
$settingsManager = new PersistentEntityManager(KeyValue::class, $logger, DB, 'settings');
$apiKeyObject->value = $newKey;
$settingsManager->save($apiKeyObject);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'oldKey' => $currentApiKey,
    'newKey' => $newKey
]);
