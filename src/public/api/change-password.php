<?php

require_once __DIR__."/../../lib/orm.php";
require_once __DIR__."/../../lib/settings.php";
require_once __DIR__."/../../conf/config.php";

session_start();
header('Content-Type: application/json; charset=utf-8');

// ensure user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

// read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$newPwd = trim($input['password'] ?? '');

// basic validation
if (strlen($newPwd) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters long']);
    exit;
}

// hash the new password
$hashedPassword = password_hash($newPwd, PASSWORD_ARGON2ID);

$settingsManager = new PersistentEntityManager(KeyValue::class, $logger, DB, 'settings');
$passwordObject = $settingsManager->find(['key' => 'passwordHash']);
$passwordObject->value = $hashedPassword;
$settingsManager->save($passwordObject);

// respond with success
echo json_encode(['ok' => true]);
