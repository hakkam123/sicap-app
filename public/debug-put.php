<?php
/**
 * Diagnostic Endpoint for HTTP Methods (GET, POST, PUT, DELETE, PATCH)
 * Used to verify PHP runtime environment, version, binary path, and FastCGI process ID per HTTP verb in IIS.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

if ($method === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'CORS preflight allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');

$debugData = [
    'timestamp'            => date('Y-m-d H:i:s T'),
    'http_method'          => $method,
    'php_version'          => PHP_VERSION,
    'php_version_id'       => PHP_VERSION_ID,
    'php_int_size'         => PHP_INT_SIZE,
    'is_64_bit'            => (PHP_INT_SIZE === 8),
    'php_sapi'             => PHP_SAPI,
    'php_binary'           => defined('PHP_BINARY') ? PHP_BINARY : 'N/A',
    'loaded_php_ini'       => php_ini_loaded_file() ?: 'None',
    'process_id'           => getmypid(),
    'script_filename'      => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
    'server_software'      => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'server_protocol'      => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
    'request_uri'          => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'content_type'         => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
    'content_length'       => $_SERVER['CONTENT_LENGTH'] ?? 0,
    'handler_info'         => [
        'is_php_8_4_1_or_higher' => (PHP_VERSION_ID >= 80401),
        'status' => (PHP_VERSION_ID >= 80401) ? 'PASS' : 'FAIL (PHP Version too low for Composer deps!)',
    ],
    'raw_body_sample'      => substr($rawInput, 0, 500),
];

// Write log to storage/logs/debug-put.log
$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/debug-put.log';

$logLine = sprintf(
    "[%s] [%s] PID:%s | PHP:%s (ID:%s) | SAPI:%s | Binary:%s | INI:%s | URI:%s\n",
    $debugData['timestamp'],
    $debugData['http_method'],
    $debugData['process_id'],
    $debugData['php_version'],
    $debugData['php_version_id'],
    $debugData['php_sapi'],
    $debugData['php_binary'],
    $debugData['loaded_php_ini'],
    $debugData['request_uri']
);

@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

http_response_code(200);
echo json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

