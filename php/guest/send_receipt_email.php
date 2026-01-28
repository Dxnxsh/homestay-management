<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
require_once 'generate_receipt_pdf.php'; // For generateReceiptPDF function

requireGuestLogin();

if (!defined('OCI_ASSOC'))
    define('OCI_ASSOC', 1);
if (!defined('OCI_RETURN_NULLS'))
    define('OCI_RETURN_NULLS', 8);

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$guestID = getCurrentGuestID();
// Read JSON input if sent as JSON, or POST data
$input = json_decode(file_get_contents('php://input'), true);
$bookingID = $input['bookingID'] ?? $_POST['bookingID'] ?? null;

if (!$bookingID) {
    echo json_encode(['success' => false, 'message' => 'No booking ID provided.']);
    exit;
}

// 1. Generate PDF content (String mode)
// Suppress output just in case
ob_start();
$pdfContent = generateReceiptPDF($bookingID, $guestID, 'S');
$output = ob_get_clean();

if (!$pdfContent) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate receipt PDF. ' . $output]);
    exit;
}

// 2. Fetch Guest Details for Email
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$sql = "SELECT guest_email, guest_name FROM GUEST WHERE guestID = :guestID";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':guestID', $guestID);
oci_execute($stmt);
$row = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);
closeDBConnection($conn);

if (!$row || empty($row['GUEST_EMAIL'])) {
    echo json_encode(['success' => false, 'message' => 'Guest email not found.']);
    exit;
}

// 3. Send Email using PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
require '../config/mail_config.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = MAIL_PORT;

    // Recipients
    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $mail->addAddress($row['GUEST_EMAIL'], $row['GUEST_NAME']);
    $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

    // Attachments
    $mail->addStringAttachment($pdfContent, "Receipt_" . $bookingID . ".pdf");

    // Content
    $mail->isHTML(false);
    $mail->Subject = "Booking Receipt - Booking #" . $bookingID;
    $mail->Body = "Dear " . $row['GUEST_NAME'] . ",\n\n" .
        "Thank you for choosing Serena Sanctuary. Please find your booking receipt attached to this email.\n\n" .
        "Ref: #" . $bookingID . "\n\n" .
        "Best regards,\nSerena Sanctuary Team";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Receipt has been emailed to ' . $row['GUEST_EMAIL']]);

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Server Error: ' . $mail->ErrorInfo]);
}
