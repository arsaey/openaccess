<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://' . $_SERVER['HTTP_HOST']);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

function validateCsrfToken($receivedToken) {
    if (empty($receivedToken)) {
        return false;
    }
    $decodedToken = json_decode(base64_decode($receivedToken), true);
    if (!$decodedToken || empty($decodedToken['token']) || empty($decodedToken['expiry']) || empty($decodedToken['owner'])) {
        return false;
    }
    if (time() > $decodedToken['expiry']) {
        return false;
    }
    if ($decodedToken['owner'] !== $_SESSION["owner"]) {
        return false;
    }
    return hash_equals(json_decode(base64_decode($_SESSION['csrf_token']), true)['token'], $decodedToken['token']);
}

switch ($_SERVER["REQUEST_METHOD"]) {
    case "POST":
        $data = json_decode(file_get_contents('php://input'), true);
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $email = htmlspecialchars($data['email'] ?? '');
        $name = htmlspecialchars($data['name'] ?? '');
        $password = htmlspecialchars($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }

        if (!empty($email) && !empty($password)) {
	        $save_path = '../secure_directory';
            $file = $save_path . '/users.csv';

            if (!is_dir($save_path)) {
                mkdir($save_path, 0700, true);
            }
            $isNewFile = !is_file($file);

            $csvFile = fopen($file, 'a');

            if ($isNewFile) {
                fputcsv($csvFile, ['Email', 'Name', 'Password']);
            }

            fputcsv($csvFile, [$email, $name, $password]);
            fclose($csvFile);

            echo json_encode(['message' => 'User signed up successfully']);
        } else {
            echo json_encode(['error' => 'Email and password are required']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid request method']);
        break;
}
