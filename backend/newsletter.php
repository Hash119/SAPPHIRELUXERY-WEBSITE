<?php
/**
 * Sapphire Luxury Aesthetics - VIP Newsletter Subscription Handler
 * Colombo 05, Sri Lanka
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$subscribersFile = $dataDir . '/subscribers.json';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$email = isset($data['email']) ? trim(filter_var($data['email'], FILTER_SANITIZE_EMAIL)) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

$subscribers = [];
if (file_exists($subscribersFile)) {
    $existing = file_get_contents($subscribersFile);
    $decoded = json_decode($existing, true);
    if (is_array($decoded)) {
        $subscribers = $decoded;
    }
}

// Prevent duplicate
foreach ($subscribers as $sub) {
    if (isset($sub['email']) && strtolower($sub['email']) === strtolower($email)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'You are already registered in the Sapphire VIP Club.'
        ]);
        exit;
    }
}

$subscribers[] = [
    'email' => $email,
    'subscribed_at' => date('Y-m-d H:i:s'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
];

@file_put_contents($subscribersFile, json_encode($subscribers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'status' => 'success',
    'message' => 'Welcome to the Sapphire Luxury VIP Club!'
]);
exit;
