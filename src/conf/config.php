<?php

// Database related settings
define("DB", __DIR__."/../data/formification.db");

// Logger
require_once __DIR__.'/../lib/logger.php';
$log_filepath = "/../data/latest.log";
$logger = new Logger($log_filepath, LogLevel::INFO);
?>
