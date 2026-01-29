<?php
// Start output buffering to prevent any output before PDF
ob_start();

// Suppress error display during PDF generation
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

// Load Composer autoloader so classes (e.g., TCPDF) are discoverable by linters
@include_once __DIR__ . '/../../vendor/autoload.php';

// Provide minimal OCI8 stubs for linters/IDEs when extension isn't loaded
if (!function_exists('oci_parse')) {
    function oci_parse($conn, $sql)
    {
        return null;
    }
    function oci_bind_by_name(&$statement, $name, &$variable)
    {
    }
    function oci_execute($statement, $mode = null)
    {
        return false;
    }
    function oci_fetch_array($statement, $mode = 0)
    {
        return [];
    }
    function oci_free_statement($statement)
    {
    }
}

// ... (keeps the includes and function definitions for OCI stubs)

function generateReceiptPDF($bookingID, $guestID, $outputMode = 'I')
{
    // Start output buffering to prevent any output before PDF if downloading
    if ($outputMode !== 'S') {
        ob_start();
    }

    $conn = getDBConnection();
    if (!$conn) {
        if ($outputMode === 'S') {
            echo 'Database connection failed.';
            return null;
        }
        die('Database connection failed.');
    }

    // Safe flags for oci_fetch_array
    $ociFetchFlags = (defined('OCI_ASSOC') ? OCI_ASSOC : 0) | (defined('OCI_RETURN_NULLS') ? OCI_RETURN_NULLS : 0);

    // Fetch membership discount
    $membershipDiscount = 0.0;
    $membership_sql = 'SELECT disc_rate FROM MEMBERSHIP WHERE guestID = :guestID';
    $membership_stmt = oci_parse($conn, $membership_sql);
    oci_bind_by_name($membership_stmt, ':guestID', $guestID);
    if (oci_execute($membership_stmt)) {
        $membership_row = oci_fetch_array($membership_stmt, $ociFetchFlags);
        if ($membership_row && isset($membership_row['DISC_RATE'])) {
            $membershipDiscount = (float) $membership_row['DISC_RATE'];
        }
    }
    oci_free_statement($membership_stmt);

    // Fetch booking details
    $booking_sql = "SELECT b.bookingID, b.checkin_date, b.checkout_date, b.num_adults, b.num_children,
                           b.deposit_amount, b.homestayID, h.homestay_name, h.homestay_address, h.rent_price,
                           b.billNo, bl.bill_status, bl.bill_date, bl.bill_subtotal, bl.disc_amount, bl.tax_amount,
                           bl.total_amount, bl.payment_method, bl.payment_date,
                           g.guest_name, g.guest_email, g.guest_phoneNo
                    FROM BOOKING b
                    JOIN HOMESTAY h ON b.homestayID = h.homestayID
                    LEFT JOIN BILL bl ON b.billNo = bl.billNo
                    LEFT JOIN GUEST g ON b.guestID = g.guestID
                    WHERE b.bookingID = :bookingID AND b.guestID = :guestID";
    $booking_stmt = oci_parse($conn, $booking_sql);
    oci_bind_by_name($booking_stmt, ':bookingID', $bookingID);
    oci_bind_by_name($booking_stmt, ':guestID', $guestID);

    if (!oci_execute($booking_stmt)) {
        if ($outputMode === 'S') {
            echo 'Failed to fetch booking details. OCI execute error.';
            return null;
        }
        die('Failed to fetch booking details.');
    }

    $row = oci_fetch_array($booking_stmt, $ociFetchFlags);
    if (!$row) {
        if ($outputMode === 'S') {
            echo 'Booking not found or access denied (ID: ' . $bookingID . ').';
            return null;
        }
        die('Booking not found or access denied.');
    }

    // Calculate booking details
    $checkinDate = !empty($row['CHECKIN_DATE']) ? date_create($row['CHECKIN_DATE']) : null;
    $checkoutDate = !empty($row['CHECKOUT_DATE']) ? date_create($row['CHECKOUT_DATE']) : null;
    $nights = ($checkinDate && $checkoutDate) ? (int) $checkinDate->diff($checkoutDate)->format('%a') : 0;
    $nights = max($nights, 1);

    $rentPrice = isset($row['RENT_PRICE']) ? (float) $row['RENT_PRICE'] : 0.0;
    $baseTotal = round($rentPrice * $nights, 2);
    $depositAmount = isset($row['DEPOSIT_AMOUNT']) ? (float) $row['DEPOSIT_AMOUNT'] : 0.0;
    $discountAmount = isset($row['DISC_AMOUNT']) ? (float) $row['DISC_AMOUNT'] : 0.0;
    $taxAmount = isset($row['TAX_AMOUNT']) ? (float) $row['TAX_AMOUNT'] : 0.0;
    $paidAmount = isset($row['TOTAL_AMOUNT']) ? (float) $row['TOTAL_AMOUNT'] : 0.0;
    $paymentMethod = isset($row['PAYMENT_METHOD']) ? $row['PAYMENT_METHOD'] : 'Not yet selected';
    $billStatusRaw = isset($row['BILL_STATUS']) ? $row['BILL_STATUS'] : null;
    $isPaid = $billStatusRaw && strcasecmp($billStatusRaw, 'PAID') === 0;
    $remainingBalance = max(0, $baseTotal - $depositAmount);

    $booking = [
        'id' => $row['BOOKINGID'],
        'checkin' => $checkinDate ? $checkinDate->format('d M Y') : '--',
        'checkout' => $checkoutDate ? $checkoutDate->format('d M Y') : '--',
        'nights' => $nights,
        'adults' => isset($row['NUM_ADULTS']) ? (int) $row['NUM_ADULTS'] : 0,
        'children' => isset($row['NUM_CHILDREN']) ? (int) $row['NUM_CHILDREN'] : 0,
        'homestay_name' => $row['HOMESTAY_NAME'] ?? 'Homestay',
        'homestay_address' => $row['HOMESTAY_ADDRESS'] ?? 'Address not available',
        'rent_price' => $rentPrice,
        'base_total' => $baseTotal,
        'deposit_amount' => $depositAmount,
        'remaining_balance' => $remainingBalance,
        'bill_no' => isset($row['BILLNO']) ? $row['BILLNO'] : null,
        'bill_date' => !empty($row['BILL_DATE']) ? date('d M Y', strtotime($row['BILL_DATE'])) : date('d M Y'),
        'payment_date' => !empty($row['PAYMENT_DATE']) ? date('d M Y', strtotime($row['PAYMENT_DATE'])) : '—',
        'discount_amount' => $discountAmount,
        'paid_amount' => $paidAmount ?: max(0, $depositAmount - $discountAmount + $taxAmount),
        'payment_method' => $paymentMethod,
        'tax_amount' => $taxAmount,
        'guest_name' => $row['GUEST_NAME'] ?? '',
        'guest_email' => $row['GUEST_EMAIL'] ?? '',
        'guest_phone' => $row['GUEST_PHONENO'] ?? ''
    ];

    oci_free_statement($booking_stmt);
    closeDBConnection($conn);

    // Clear and end any output buffering before sending headers/content
    if ($outputMode !== 'S' && ob_get_level()) {
        ob_end_clean();
    }

    // Check if TCPDF is available, otherwise use FPDF
    $tcpdfPath = __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

    if (file_exists($tcpdfPath)) {
        require_once($tcpdfPath);

        // Create PDF using TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->SetCreator('Serena Sanctuary');
        $pdf->SetAuthor('Serena Sanctuary');
        $pdf->SetTitle('Booking Receipt #' . $booking['id']);
        $pdf->SetSubject('Booking Receipt');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Header
        $logoPath = __DIR__ . '/../../images/logoTitle.png';
        if (file_exists($logoPath)) {
            // Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $fitonpage)
            $pdf->Image($logoPath, 15, 10, 0, 22, 'PNG');
        }

        // Ensure we move past the logo height (10 + 22 = 32)
        $pdf->SetY(35);

        // Horizontal line
        $pdf->SetDrawColor(197, 129, 75);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(8);

        // Booking and Bill info
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(138, 83, 40);
        $currentY = $pdf->GetY();
        $pdf->Cell(100, 5, 'Booking Reference: #' . $booking['id'], 0, 0, 'L');
        if ($booking['bill_no']) {
            $pdf->Cell(80, 5, 'Bill: #' . $booking['bill_no'], 0, 1, 'R');
        } else {
            $pdf->Ln();
        }
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(100, 5, 'Issued: ' . $booking['bill_date'], 0, 1, 'L');
        $pdf->Ln(5);

        // Guest details
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(42, 28, 18);
        $pdf->Cell(0, 6, 'Guest Information', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, $booking['guest_name'], 0, 1, 'L');
        if ($booking['guest_email']) {
            $pdf->Cell(0, 5, $booking['guest_email'], 0, 1, 'L');
        }
        if ($booking['guest_phone']) {
            $pdf->Cell(0, 5, $booking['guest_phone'], 0, 1, 'L');
        }
        $pdf->Ln(5);

        // Homestay details
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(42, 28, 18);
        $pdf->Cell(0, 6, 'Homestay Details', 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, $booking['homestay_name'], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, $booking['homestay_address'], 0, 1, 'L');
        $pdf->Ln(5);

        // Stay details in a table
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(42, 28, 18);
        $pdf->Cell(0, 6, 'Stay Information', 0, 1, 'L');

        $pdf->SetFillColor(245, 230, 211);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(138, 83, 40);

        $colWidth = 45;
        $pdf->Cell($colWidth, 6, 'Check-in', 1, 0, 'L', true);
        $pdf->Cell($colWidth, 6, 'Check-out', 1, 0, 'L', true);
        $pdf->Cell($colWidth, 6, 'Nights', 1, 0, 'L', true);
        $pdf->Cell($colWidth, 6, 'Guests', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(42, 28, 18);
        $guestText = $booking['adults'] . ' adult' . ($booking['adults'] > 1 ? 's' : '');
        if ($booking['children'] > 0) {
            $guestText .= ', ' . $booking['children'] . ' child' . ($booking['children'] > 1 ? 'ren' : '');
        }
        $pdf->Cell($colWidth, 6, $booking['checkin'], 1, 0, 'L');
        $pdf->Cell($colWidth, 6, $booking['checkout'], 1, 0, 'L');
        $pdf->Cell($colWidth, 6, (string) $booking['nights'], 1, 0, 'L');
        $pdf->Cell($colWidth, 6, $guestText, 1, 1, 'L');
        $pdf->Ln(8);

        // Payment breakdown
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(42, 28, 18);
        $pdf->Cell(0, 6, 'Payment Summary', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);

        $labelWidth = 130;
        $amountWidth = 50;

        $pdf->Cell($labelWidth, 5, 'Nightly rate x ' . $booking['nights'] . ' night' . ($booking['nights'] > 1 ? 's' : ''), 0, 0, 'L');
        $pdf->Cell($amountWidth, 5, 'RM ' . number_format($booking['base_total'], 2), 0, 1, 'R');

        $pdf->Cell($labelWidth, 5, 'Deposit (30%)', 0, 0, 'L');
        $pdf->Cell($amountWidth, 5, 'RM ' . number_format($booking['deposit_amount'], 2), 0, 1, 'R');

        $pdf->Cell($labelWidth, 5, 'Membership savings', 0, 0, 'L');
        $pdf->Cell($amountWidth, 5, '- RM ' . number_format($booking['discount_amount'], 2), 0, 1, 'R');

        $pdf->Cell($labelWidth, 5, 'Tax', 0, 0, 'L');
        $pdf->Cell($amountWidth, 5, 'RM ' . number_format($booking['tax_amount'], 2), 0, 1, 'R');

        $pdf->Ln(2);
        $pdf->SetDrawColor(197, 129, 75);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(42, 28, 18);
        $pdf->Cell($labelWidth, 6, 'Total Paid', 0, 0, 'L');
        $pdf->Cell($amountWidth, 6, 'RM ' . number_format($booking['paid_amount'], 2), 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(107, 75, 50);
        $pdf->Cell($labelWidth, 5, 'Balance on arrival', 0, 0, 'L');
        $pdf->Cell($amountWidth, 5, 'RM ' . number_format($booking['remaining_balance'], 2), 0, 1, 'R');

        $pdf->Ln(5);

        // Payment details
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(138, 83, 40);
        $pdf->Cell(60, 5, 'Payment Method:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, $booking['payment_method'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(138, 83, 40);
        $pdf->Cell(60, 5, 'Payment Date:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 5, $booking['payment_date'], 0, 1, 'L');

        $pdf->Ln(8);

        // Footer note
        $pdf->SetFillColor(255, 253, 249);
        $pdf->SetDrawColor(231, 205, 178);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(92, 70, 52);
        $noteText = "Please present this receipt upon arrival. For any changes or inquiries, contact us at info@serenasanctuary.com or +60 17-204 2390.";
        $pdf->MultiCell(0, 5, $noteText, 1, 'L', true);

        // Output PDF
        if ($outputMode === 'S') {
            return $pdf->Output('Booking_Receipt_' . $booking['id'] . '.pdf', 'S');
        } else {
            $pdf->Output('Booking_Receipt_' . $booking['id'] . '.pdf', 'I');
            exit;
        }

    } else {
        // TCPDF not found
        if ($outputMode === 'S') {
            echo "TCPDF library not found at: " . $tcpdfPath;
            return null;
        }

        error_log("TCPDF not found at " . $tcpdfPath);
        return null;
    }
}

// Check if this script is being accessed directly (e.g. via browser)
// If so, execute the function.
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    $guestID = getCurrentGuestID();
    $bookingID = isset($_GET['bookingID']) ? $_GET['bookingID'] : null;

    if (!$bookingID) {
        die('No booking ID provided.');
    }

    generateReceiptPDF($bookingID, $guestID, 'I');
}