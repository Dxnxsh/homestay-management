<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

ob_start();
header('Content-Type: application/json');

session_start();

// Check if user is logged in first before requiring session_check
if (!isset($_SESSION['guestID']) || empty($_SESSION['guestID'])) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Now require the connection  
require_once '../config/db_connection.php';

if (!isset($_GET['homestayID'])) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode(['error' => 'homestayID is required']);
    exit;
}

$homestayID = $_GET['homestayID'];

try {
    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Unable to connect to the database');
    }

    // Get all booked dates for this homestay
    // We'll get dates that are either currently booked or pending
    $sql = "SELECT checkin_date, checkout_date
            FROM BOOKING
            WHERE homestayID = :homestayID
            ORDER BY checkin_date ASC";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);

    $bookedDates = [];

    if (oci_execute($stmt)) {
        while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
            $checkinDate = $row['CHECKIN_DATE'];
            $checkoutDate = $row['CHECKOUT_DATE'];

            // Handle both string and date formats
            if (is_string($checkinDate)) {
                $checkinDate = new DateTime($checkinDate);
            } else {
                $checkinDate = DateTime::createFromFormat('d-M-y', $checkinDate);
            }

            if (is_string($checkoutDate)) {
                $checkoutDate = new DateTime($checkoutDate);
            } else {
                $checkoutDate = DateTime::createFromFormat('d-M-y', $checkoutDate);
            }

            // Add all dates between checkin and checkout (inclusive of both)
            $currentDate = clone $checkinDate;
            while ($currentDate <= $checkoutDate) {
                $bookedDates[] = $currentDate->format('Y-m-d');
                $currentDate->modify('+1 day');
            }
        }
    }

    oci_free_statement($stmt);

    // Return unique sorted dates
    $bookedDates = array_unique($bookedDates);
    sort($bookedDates);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'bookedDates' => $bookedDates
    ]);

} catch (Exception $e) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'error' => 'Failed to fetch booked dates: ' . $e->getMessage()
    ]);
}
