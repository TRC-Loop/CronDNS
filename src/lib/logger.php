<?php

// Enum for log levels
enum LogLevel: string {
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
}

class Logger {
    private string $logFile;
    private LogLevel $logLevel;

    public function __construct(string $logFile, LogLevel $logLevel = LogLevel::DEBUG) {
        // Use an absolute path to ensure it works reliably
        $this->logFile = realpath($logFile) ? $logFile : __DIR__ . '/' . $logFile;
        $this->logLevel = $logLevel;

        // Ensure the log file exists, create it if not
        if (!file_exists($this->logFile)) {
            $directory = dirname($this->logFile);
            // Try to create the directory if it doesn't exist
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                    // If we can't create the directory, show an error
                    throw new RuntimeException("Unable to create directory: $directory");
                }
            }

            // Attempt to create the log file
            if (!touch($this->logFile)) {
                throw new RuntimeException("Unable to create log file: {$this->logFile}");
            }
        }
    }

    private function writeLog(LogLevel $level, string $message): void {
        if ($this->shouldLog($level)) {
            $timestamp = (new DateTime())->format('Y-m-d H:i:s');
    
            // Inspect the caller info
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    
            // Use the second frame if available (caller of logXYZ())
            $caller = $trace[2] ?? $trace[1] ?? null;
    
            $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
            $line = $caller['line'] ?? '?';
            $function = $caller['function'] ?? 'global';
    
            $context = "{$file}:{$line} {$function}()";
            $logMessage = "[{$timestamp}] [{$level->value}] [{$context}] {$message}\n";
    
            // Write to the file
            if (file_put_contents($this->logFile, $logMessage, FILE_APPEND) === false) {
                throw new RuntimeException("Failed to write to log file: {$this->logFile}");
            }
    
            // Optionally log to browser/CLI console
            $this->logToConsole($logMessage);
        }
    }
    
    private function shouldLog(LogLevel $level): bool {
        // List of log levels in the correct order of priority
        $levels = [
            LogLevel::DEBUG->value => 0,
            LogLevel::INFO->value => 1,
            LogLevel::WARNING->value => 2,
            LogLevel::ERROR->value => 3,
            LogLevel::CRITICAL->value => 4
        ];

        return $levels[$level->value] >= $levels[$this->logLevel->value];
    }

    private function logToConsole(string $message): void {
        // If running in a browser environment, log to console
        if (php_sapi_name() === 'cli') {
            echo $message . PHP_EOL; // Output in terminal if in CLI
        } else {
            // For browser environments (using JavaScript)
            echo "<script>console.log('{$message}');</script>";
        }
    }

    public function debug(string $message): void {
        $this->writeLog(LogLevel::DEBUG, $message);
    }

    public function info(string $message): void {
        $this->writeLog(LogLevel::INFO, $message);
    }

    public function warning(string $message): void {
        $this->writeLog(LogLevel::WARNING, $message);
    }

    public function error(string $message): void {
        $this->writeLog(LogLevel::ERROR, $message);
    }

    public function critical(string $message): void {
        $this->writeLog(LogLevel::CRITICAL, $message);
    }

    public function getLatestLogFileContent(): string {
        // $logFilePath = __DIR__ . '/../data/latest.log'; // adjust if your log path differs
    
        if (!file_exists($this->logFile) || !is_readable($this->logFile)) {
            return "Log file not found or not readable: $this->logFile";
        }
    
        return file_get_contents($this->logFile);
    }
}



// Example Usage
/*
$logger = new Logger(__DIR__ . '/data/latest.log', LogLevel::INFO);
$logger->info('This is an info message');
$logger->error('This is an error message');
$logger->debug('This debug message won’t be logged since level is INFO');
*/
?>
