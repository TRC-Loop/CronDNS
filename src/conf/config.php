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
  $settingsManager->save($password);
}
?>
