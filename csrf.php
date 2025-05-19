<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://' . $_SERVER['HTTP_HOST']);
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (!isset($_SESSION["owner"])) {
    $addr = filter_input(INPUT_SERVER, "REMOTE_ADDR", FILTER_VALIDATE_IP);
    $agent = filter_input(INPUT_SERVER, "HTTP_USER_AGENT", FILTER_SANITIZE_STRING);
    $_SESSION["owner"] = md5($addr . $agent);
}

function generateCsrfToken()
{
    $token = bin2hex(random_bytes(32));
    $expiry = time() + 1;
    $csrfToken = base64_encode(json_encode(['token' => $token, 'expiry' => $expiry, 'owner' => $_SESSION["owner"]]));
    $_SESSION['csrf_token'] = $csrfToken;
    return $csrfToken;
}

$csrfToken = generateCsrfToken();

$csrfTokenCookie = base64_encode(json_encode([
    'token' => $csrfToken,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'ip' => $_SERVER['REMOTE_ADDR']
]));

setcookie('csrf_token', $csrfToken, [
    'expires' => time() + 60,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict', 
]);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

echo json_encode(['csrf_token' => $csrfToken]);
