<?php
session_start();

require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/generate_receipt_pdf.php'; // generateReceiptPDF()

requireGuestLogin();

if (!defined('OCI_ASSOC'))
    define('OCI_ASSOC', 1);
if (!defined('OCI_RETURN_NULLS'))
    define('OCI_RETURN_NULLS', 8);

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json');

$guestID = getCurrentGuestID();

// Read JSON input if sent as JSON, or POST data
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$bookingID = $input['bookingID'] ?? $_POST['bookingID'] ?? null;

if (!$bookingID) {
    echo json_encode(['success' => false, 'message' => 'No booking ID provided.']);
    exit;
}

// 1) Generate PDF content (String mode)
ob_start();
$pdfContent = generateReceiptPDF($bookingID, $guestID, 'S');
$pdfOutput = ob_get_clean();

if (!$pdfContent) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate receipt PDF. ' . $pdfOutput]);
    exit;
}

// 2) Fetch Guest Details for Email
$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$sql = "SELECT guest_email, guest_name FROM GUEST WHERE guestID = :guestID";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':guestID', $guestID);

$ok = oci_execute($stmt);
if (!$ok) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    closeDBConnection($conn);
    echo json_encode(['success' => false, 'message' => 'Failed to query guest details.', 'error' => $e['message'] ?? null]);
    exit;
}

$row = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);
closeDBConnection($conn);

$guestEmail = $row['GUEST_EMAIL'] ?? null;
$guestName = $row['GUEST_NAME'] ?? 'Guest';

if (!$guestEmail) {
    echo json_encode(['success' => false, 'message' => 'Guest email not found.']);
    exit;
}

// 3) Send Email using PHPMailer
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;

    // Encryption mapping
    $mail->Port = MAIL_PORT;

    if (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
    } elseif (defined('MAIL_ENCRYPTION') && MAIL_ENCRYPTION === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // 465
    } else {
        $mail->SMTPSecure = false;
    }

    // Optional: enable debug to docker logs (TEMPORARY)
    // $mail->SMTPDebug  = 2;
    // $mail->Debugoutput = function($str, $level) { error_log("PHPMailer[$level]: $str"); };

    // Recipients
    // For Gmail, From should match authenticated account (your config already does)
    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $mail->addAddress($guestEmail, $guestName);

    // Reply-To (same as From here; change if you want replies elsewhere)
    $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

    // Attachment
    $safeBooking = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $bookingID);
    $mail->addStringAttachment($pdfContent, "Receipt_{$safeBooking}.pdf", 'base64', 'application/pdf');

    // Content
    $mail->isHTML(false);
    $mail->Subject = "Booking Receipt - Booking #{$bookingID}";
    $mail->Body =
        "Dear {$guestName},\n\n" .
        "Thank you for choosing Serena Sanctuary. Please find your booking receipt attached to this email.\n\n" .
        "Ref: #{$bookingID}\n\n" .
        "Best regards,\nSerena Sanctuary Team";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => "Receipt has been emailed to {$guestEmail}"
    ]);
} catch (Exception $e) {
    // Log the actual error for debugging (docker logs)
    error_log("MAIL ERRORINFO: " . $mail->ErrorInfo);
    error_log("MAIL EXCEPTION: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email.',
        'server_error' => $mail->ErrorInfo,  // remove in production if you want
    ]);
}
