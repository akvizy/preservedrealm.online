<?php
declare(strict_types=1);

/*
 * Preserved Realm — local status proxy.
 *
 * The browser asks this file on the same host, so you don't depend on
 * cross-origin requests from the frontend. The upstream service is fixed
 * to your Minecraft server address.
 */

const SERVER = 'mc.preservedrealm.online';
const UPSTREAM = 'https://api.mcstatus.io/v2/status/java/' . SERVER;
const CACHE_FILE = __DIR__ . '/status-cache.json';
const CACHE_TTL = 15;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function send_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Return a very short local cache if available. */
if (is_file(CACHE_FILE)) {
    $raw = @file_get_contents(CACHE_FILE);
    if ($raw !== false) {
        $cached = json_decode($raw, true);
        if (is_array($cached) && isset($cached['_cached_at']) &&
            (time() - (int)$cached['_cached_at']) < CACHE_TTL &&
            isset($cached['data']) && is_array($cached['data'])) {
            $cached['data']['_proxy'] = ['cache' => true];
            send_json($cached['data']);
        }
    }
}

$responseBody = false;
$httpCode = 0;

/* Prefer cURL when the hosting provider has it. */
if (function_exists('curl_init')) {
    $ch = curl_init(UPSTREAM);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'PreservedRealm-Site/1.0',
    ]);
    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    /* Fallback for hosts where cURL is disabled. */
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: PreservedRealm-Site/1.0\r\n"
        ]
    ]);
    $responseBody = @file_get_contents(UPSTREAM, false, $context);
    if (isset($http_response_header[0]) &&
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
}

if ($responseBody === false || $httpCode < 200 || $httpCode >= 300) {
    send_json([
        'online' => false,
        'error' => 'status_upstream_unavailable',
        '_proxy' => ['ok' => false]
    ], 502);
}

$data = json_decode($responseBody, true);
if (!is_array($data)) {
    send_json([
        'online' => false,
        'error' => 'invalid_upstream_json',
        '_proxy' => ['ok' => false]
    ], 502);
}

/* Local 15-second cache reduces duplicate requests from visitors. */
@file_put_contents(
    CACHE_FILE,
    json_encode(['_cached_at' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

$data['_proxy'] = ['ok' => true, 'cache' => false];
send_json($data);
?>
