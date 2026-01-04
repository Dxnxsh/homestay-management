<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$guestID = getCurrentGuestID();
$phoneNo = trim($_POST['guest_phoneNo'] ?? '');
$gender = trim($_POST['guest_gender'] ?? '');
$address = trim($_POST['guest_address'] ?? '');

// Validation
$errors = [];
if (empty($phoneNo)) {
    $errors[] = 'Phone number is required';
}
if (empty($gender)) {
    $errors[] = 'Gender is required';
}
if (empty($address)) {
    $errors[] = 'Address is required';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Convert to uppercase (except email - not applicable here)
$gender = strtoupper($gender);
$address = strtoupper($address);

// Update database
$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$sql = "UPDATE GUEST 
        SET guest_phoneNo = :phoneNo, 
            guest_gender = :gender, 
            guest_address = :address 
        WHERE guestID = :guestID";
$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':phoneNo', $phoneNo);
oci_bind_by_name($stmt, ':gender', $gender);
oci_bind_by_name($stmt, ':address', $address);
oci_bind_by_name($stmt, ':guestID', $guestID);

if (oci_execute($stmt)) {
    oci_commit($conn);
    // Clear the profile incomplete flag
    unset($_SESSION['profile_incomplete']);
    
    oci_free_statement($stmt);
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    $error = oci_error($stmt);
    oci_free_statement($stmt);
    closeDBConnection($conn);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $error['message']]);
}
?>

