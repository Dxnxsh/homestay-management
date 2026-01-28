<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();

// Redirect to FPX payment page
header('Location: payment_fpx.php');
exit();
?>

$conn = getDBConnection();

if ($conn) {
// Check if guest already has membership
$check_sql = "SELECT membershipID FROM MEMBERSHIP WHERE guestID = :guestID";
$check_stmt = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stmt, ':guestID', $guestID);

if (oci_execute($check_stmt)) {
$existing = oci_fetch_array($check_stmt, OCI_ASSOC);

if ($existing) {
// Guest already has membership
$error_message = 'You already have an active membership!';
} else {
// Insert new membership with Bronze tier (10% discount)
// membershipID will be auto-generated
$disc_rate = 10.00; // Bronze tier starts at 10%
$insert_sql = "INSERT INTO MEMBERSHIP (guestID, disc_rate)
VALUES (:guestID, :disc_rate)";
$insert_stmt = oci_parse($conn, $insert_sql);
oci_bind_by_name($insert_stmt, ':guestID', $guestID);
oci_bind_by_name($insert_stmt, ':disc_rate', $disc_rate);

if (oci_execute($insert_stmt)) {
// Update guest_type to "MEMBERSHIP" in GUEST table (uppercase)
$guest_type = strtoupper('Member');
$update_sql = "UPDATE GUEST SET guest_type = :guest_type WHERE guestID = :guestID";
$update_stmt = oci_parse($conn, $update_sql);
oci_bind_by_name($update_stmt, ':guest_type', $guest_type);
oci_bind_by_name($update_stmt, ':guestID', $guestID);

if (oci_execute($update_stmt)) {
oci_commit($conn);
$success = true;
$_SESSION['membership_purchased'] = true;
oci_free_statement($update_stmt);
oci_free_statement($insert_stmt);
closeDBConnection($conn);
header('Location: membership.php?purchased=1');
exit();
} else {
// Rollback membership insert if guest update fails
oci_rollback($conn);
$error = oci_error($update_stmt);
$error_message = 'Failed to update guest type: ' . $error['message'];
oci_free_statement($update_stmt);
oci_free_statement($insert_stmt);
}
} else {
$error = oci_error($insert_stmt);
$error_message = 'Failed to purchase membership: ' . $error['message'];
oci_free_statement($insert_stmt);
}
}
} else {
$error = oci_error($check_stmt);
$error_message = 'Database error: ' . $error['message'];
}

oci_free_statement($check_stmt);
closeDBConnection($conn);
} else {
$error_message = 'Unable to connect to database. Please try again later.';
}
} else {
// If not POST, redirect to membership page
header('Location: membership.php');
exit();
}

// If we reach here, there was an error
$_SESSION['membership_error'] = $error_message;
header('Location: membership.php?error=1');
exit();
?>