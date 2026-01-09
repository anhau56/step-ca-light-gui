<?php
/**
 * Audit Logger Library
 * Logs all certificate actions to a JSON file
 */

class AuditLogger {
    private static $logFile = '/var/www/html/logs/audit.json';

    /**
     * Log an action
     */
    public static function log($action, $certificate = null, $details = []) {
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0755, true)) {
                // If we can't create the directory, try a fallback location
                self::$logFile = '/tmp/step-ca-audit.json';
            }
        }

        // Check if directory is writable
        if (!is_writable(dirname(self::$logFile))) {
            self::$logFile = '/tmp/step-ca-audit.json';
        }

        // Get current user from auth
        $user = $_SERVER['PHP_AUTH_USER'] ?? 'anonymous';

        // Build log entry
        $entry = [
            'timestamp' => date('c'),
            'user' => $user,
            'action' => $action,
            'certificate' => $certificate,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];

        // Append to log file (one JSON object per line)
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);

        return true;
    }

    /**
     * Get client IP address
     */
    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Read log entries with optional filtering
     */
    public static function getLogs($filters = [], $limit = 100, $offset = 0) {
        if (!file_exists(self::$logFile)) {
            return ['entries' => [], 'total' => 0];
        }

        $entries = [];
        $handle = fopen(self::$logFile, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode(trim($line), true);
                if ($entry) {
                    // Apply filters
                    if (!empty($filters['action']) && $entry['action'] !== $filters['action']) {
                        continue;
                    }
                    if (!empty($filters['user']) && $entry['user'] !== $filters['user']) {
                        continue;
                    }
                    if (!empty($filters['certificate']) && stripos($entry['certificate'], $filters['certificate']) === false) {
                        continue;
                    }
                    if (!empty($filters['date_from'])) {
                        $entryDate = strtotime($entry['timestamp']);
                        $fromDate = strtotime($filters['date_from']);
                        if ($entryDate < $fromDate) {
                            continue;
                        }
                    }
                    if (!empty($filters['date_to'])) {
                        $entryDate = strtotime($entry['timestamp']);
                        $toDate = strtotime($filters['date_to'] . ' 23:59:59');
                        if ($entryDate > $toDate) {
                            continue;
                        }
                    }
                    $entries[] = $entry;
                }
            }
            fclose($handle);
        }

        // Sort by timestamp descending (newest first)
        usort($entries, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $total = count($entries);

        // Apply pagination
        $entries = array_slice($entries, $offset, $limit);

        return [
            'entries' => $entries,
            'total' => $total
        ];
    }

    /**
     * Get available actions for filtering
     */
    public static function getActions() {
        return [
            'generate' => 'Certificate Generated',
            'download' => 'Certificate Downloaded',
            'delete' => 'Certificate Deleted',
            'revoke' => 'Certificate Revoked',
            'renew' => 'Certificate Renewed',
            'export' => 'Certificate Exported (PFX)',
            'login' => 'User Login',
            'api_access' => 'API Access'
        ];
    }

    /**
     * Get unique users from logs
     */
    public static function getUsers() {
        $users = [];

        if (!file_exists(self::$logFile)) {
            return $users;
        }

        $handle = fopen(self::$logFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode(trim($line), true);
                if ($entry && !empty($entry['user'])) {
                    $users[$entry['user']] = true;
                }
            }
            fclose($handle);
        }

        return array_keys($users);
    }
}

// Action constants
define('LOG_GENERATE', 'generate');
define('LOG_DOWNLOAD', 'download');
define('LOG_DELETE', 'delete');
define('LOG_REVOKE', 'revoke');
define('LOG_RENEW', 'renew');
define('LOG_EXPORT', 'export');
define('LOG_LOGIN', 'login');
define('LOG_API', 'api_access');
