<?php
/**
 * Sapphire Luxury Aesthetics - Appointment Booking API Handler
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

// Ensure data directory exists
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$appointmentsFile = $dataDir . '/appointments.json';

// Retrieve input data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

// Validate essential fields
$name = isset($data['name']) ? trim($data['name']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$treatment = isset($data['treatment']) ? trim($data['treatment']) : 'General Aesthetic Consultation';
$doctor = isset($data['doctor']) ? trim($data['doctor']) : 'Dr. Indi (Lead Aesthetic Physician)';
$date = isset($data['date']) ? trim($data['date']) : date('Y-m-d');
$time = isset($data['time']) ? trim($data['time']) : '10:00 AM';
$notes = isset($data['notes']) ? trim($data['notes']) : '';

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Client full name and contact phone number are required.'
    ]);
    exit;
}

// Generate unique booking reference (e.g. SLA-2026-894215)
$bookingRef = 'SLA-' . date('Y') . '-' . mt_rand(100000, 999999);

$bookingRecord = [
    'reference' => $bookingRef,
    'client_name' => $name,
    'phone' => $phone,
    'email' => $email,
    'treatment' => $treatment,
    'doctor' => $doctor,
    'appointment_date' => $date,
    'appointment_time' => $time,
    'medical_notes' => $notes,
    'status' => 'Pending Confirmation',
    'created_at' => date('Y-m-d H:i:s'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
];

// Load existing appointments
$appointments = [];
if (file_exists($appointmentsFile)) {
    $existing = file_get_contents($appointmentsFile);
    $decoded = json_decode($existing, true);
    if (is_array($decoded)) {
        $appointments = $decoded;
    }
}

// Append new appointment
$appointments[] = $bookingRecord;

// Save back to JSON storage
@file_put_contents($appointmentsFile, json_encode($appointments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Construct WhatsApp Direct Confirmation URL
$clinicWhatsApp = "94777143626";
$waText = "*Sapphire Luxury Aesthetics Appointment Request*\n"
        . "Ref: {$bookingRef}\n"
        . "Name: {$name}\n"
        . "Phone: {$phone}\n"
        . "Treatment: {$treatment}\n"
        . "Doctor: {$doctor}\n"
        . "Date: {$date}\n"
        . "Time: {$time}\n";

if (!empty($notes)) {
    $waText .= "Notes: {$notes}\n";
}
$waText .= "Please confirm my booking slot. Thank you!";

$waUrl = "https://wa.me/{$clinicWhatsApp}?text=" . rawurlencode($waText);

// Return success JSON
echo json_encode([
    'status' => 'success',
    'message' => 'Appointment request successfully registered.',
    'reference' => $bookingRef,
    'whatsapp_url' => $waUrl,
    'booking' => $bookingRecord
]);
exit;
