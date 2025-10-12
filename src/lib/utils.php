<?php
function getFileSize($file) {
    if (file_exists($file)) {
        $size = filesize($file);
        
        // Convert bytes to human-readable format
        if ($size < 1024) {
            return $size . ' bytes';
        } elseif ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        } elseif ($size < 1073741824) {
            return round($size / 1048576, 2) . ' MB';
        } else {
            return round($size / 1073741824, 2) . ' GB';
        }
    } else {
        return "File not found!";
    }
}

function isPhpHealthy(): true|string {
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) return 'PHP version too old (older than 8.0)';

    // Check memory limit
    $mem = ini_get('memory_limit');
    if ($mem === '-1' || (int)$mem < 64) return 'Low or unlimited memory limit';

    // Check execution time
    if ((int)ini_get('max_execution_time') <= 0) return 'Invalid execution time';

    // Check if file uploads are enabled
    if (!filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)) return 'File uploads disabled';

    // Check if the temp directory is writable
    if (!is_writable(sys_get_temp_dir())) return 'Temp dir not writable';

    // Check if session support is enabled
    if (!function_exists('session_start')) return 'Session not supported';

    // Check if headers have been sent already
    if (headers_sent()) return 'Headers already sent (before session start)';

    // Check if output buffering is supported
    if (!function_exists('ob_start')) return 'Output buffering not supported';

    // Check free disk space
    $freeSpace = disk_free_space(__DIR__);
    if ($freeSpace === false || $freeSpace < 5 * 1024 ** 3) return 'Less than 5GB free disk space';

    // Check for required PHP extensions
    $requiredExtensions = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'zip'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) return "Required PHP extension '{$ext}' not loaded";
    }

    // Check for safe mode (should be off)
    if (ini_get('safe_mode')) return 'Safe mode is enabled';

    // Check for allow_url_fopen (should be enabled for most cases)
    if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) return 'allow_url_fopen is disabled';

    // Check for timezone setting
    if (!ini_get('date.timezone')) return 'Timezone not set in php.ini';

    // Check for intl extension (if needed for localization and internationalization)
    if (!extension_loaded('intl')) return 'Intl extension not loaded';

    // Check for open_basedir (should not be restricted for normal functionality)
    if ($open_basedir = ini_get('open_basedir')) {
        return "open_basedir is set to '{$open_basedir}' and may restrict PHP operations";
    }

    // Check for PDO support (essential for database interaction)
    if (!extension_loaded('pdo')) return 'PDO extension not loaded';

    // Check for session.save_path configuration
    //$sessionSavePath = ini_get('session.save_path');
    //if (empty($sessionSavePath) || !is_writable($sessionSavePath)) return 'Session save path not set or not writable';

    // Check for error_reporting level (should not be disabled or too low)
    if ((int)ini_get('error_reporting') === 0) return 'Error reporting is disabled';

    // Check for fileinfo extension (important for handling file uploads and MIME types)
    if (!extension_loaded('fileinfo')) return 'Fileinfo extension not loaded';

    // Check for max_input_vars (should be sufficiently high for large forms)
    if ((int)ini_get('max_input_vars') < 1000) return 'max_input_vars is too low for large forms';

    // Check for max_input_time (should not be set to 0 for proper input handling)
    if ((int)ini_get('max_input_time') === 0) return 'max_input_time is set to 0, which could cause issues with large requests';

    return true;
}

?>
