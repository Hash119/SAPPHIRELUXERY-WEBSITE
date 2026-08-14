<?php
/**
 * Sapphire Luxury Aesthetics - Contact Inquiry Handler
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

$inquiriesFile = $dataDir . '/inquiries.json';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

$name = isset($data['name']) ? trim($data['name']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$treatment = isset($data['treatment']) ? trim($data['treatment']) : 'General Inquiry';
$message = isset($data['message']) ? trim($data['message']) : '';

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Please provide your name and phone number.'
    ]);
    exit;
}

$inquiryRecord = [
    'id' => 'INQ-' . date('Ymd-His') . '-' . mt_rand(100, 999),
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'treatment' => $treatment,
    'message' => $message,
    'created_at' => date('Y-m-d H:i:s'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
];

$inquiries = [];
if (file_exists($inquiriesFile)) {
    $existing = file_get_contents($inquiriesFile);
    $decoded = json_decode($existing, true);
    if (is_array($decoded)) {
        $inquiries = $decoded;
    }
}

$inquiries[] = $inquiryRecord;
@file_put_contents($inquiriesFile, json_encode($inquiries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'status' => 'success',
    'message' => 'Thank you for contacting Sapphire Luxury Aesthetics. Our medical concierge will connect with you.',
    'inquiry' => $inquiryRecord
]);
exit;
