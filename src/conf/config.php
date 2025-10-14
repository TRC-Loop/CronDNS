<?php

// Database related settings
define("DB", __DIR__."/../data/crondns.db");

// Logger
require_once __DIR__.'/../lib/logger.php';
$log_filepath = "/../data/latest.log";
$logger = new Logger($log_filepath, LogLevel::INFO);

require_once __DIR__.'/../lib/settings.php';

// Setup Settings (KeyValue)
$settingsManager = new PersistentEntityManager(KeyValue::class, $logger, DB, 'settings');
$passwordObject = $settingsManager->find(["key"=>"passwordHash"]);
if (empty($passwordObject)) {
  $password = new KeyValue();
  $password->key = "passwordHash";
  $password->value = '$argon2id$v=19$m=65536,t=4,p=1$bFRhazF1S0NtTWxicmhzMQ$aEXuLwDEc8qLJHGOb9agk/QC9K3FfujLHxb7mfQFzqw';
  // This is the default password: cr0ndns!42+ Please change it immediatly to a more secure one.
  // TODO: Implement a better "First-Time" OOBE Password/Wizard, like the Server's IP being the Password or something similar.
  $settingsManager->save($password);
}

$showipObject = $settingsManager->find(["key"=>"showIP"]);
if (empty($showipObject)) {
  $showIP = new KeyValue();
  $showIP->key = "showIP";
  $showIP->value = false;
  $settingsManager->save($showIP);
}

$apiKeyObject = $settingsManager->find(["key"=>"apiKey"]);
if (empty($apiKeyObject)) {
  $apiKey = new KeyValue();
  $apiKey->key = "apiKey";
  $apiKey->value = bin2hex(random_bytes(32));
  $settingsManager->save($apiKey);
}

?>
