<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$bookings = [];
$homestays = [];
$homestayLookup = [];
$bookingErrors = [];
$paymentErrors = [];
$bookingSuccessMessage = '';
$paymentSuccessMessage = '';
$bookingSummary = null;
$paymentSummary = null;
$discountRate = 0;
$hasMembership = false;
$membershipFlash = $_SESSION['membership_flash'] ?? '';
$membershipFlashType = $_SESSION['membership_flash_type'] ?? '';
unset($_SESSION['membership_flash'], $_SESSION['membership_flash_type']);
$outstandingBookings = [];
$selectedHomestayID = isset($_GET['homestay']) ? (int) $_GET['homestay'] : null;
$selectedPaymentBookingID = null;
$shouldPromptPayment = false;
$paymentMethodInput = '';
$queryPaymentBooking = isset($_GET['booking']) ? (int) $_GET['booking'] : null;
if ($queryPaymentBooking && $queryPaymentBooking > 0) {
  $selectedPaymentBookingID = $queryPaymentBooking;
  $shouldPromptPayment = true;
}
$checkinInput = '';
$checkoutInput = '';
$numAdultsInput = 1;
$numChildrenInput = 0;

$conn = getDBConnection();

if (!$conn) {
  $errors[] = 'Unable to connect to the booking system. Please try again later.';
} else {
  $membership_sql = "SELECT disc_rate FROM MEMBERSHIP WHERE guestID = :guestID";
  $membership_stmt = oci_parse($conn, $membership_sql);
  oci_bind_by_name($membership_stmt, ':guestID', $guestID);
  if (oci_execute($membership_stmt)) {
    $membership_row = oci_fetch_array($membership_stmt, OCI_ASSOC);
    if ($membership_row) {
      $hasMembership = true;
      if (isset($membership_row['DISC_RATE'])) {
        $discountRate = (float) $membership_row['DISC_RATE'];
      }
    }
  }
  oci_free_statement($membership_stmt);

  // Load homestay catalog for selection and pricing
  $homestay_sql = "SELECT homestayID, homestay_name, homestay_address, rent_price
           FROM HOMESTAY
           ORDER BY homestay_name";
  $homestay_stmt = oci_parse($conn, $homestay_sql);
  if (oci_execute($homestay_stmt)) {
    while ($row = oci_fetch_array($homestay_stmt, OCI_ASSOC)) {
      $stay = [
        'homestayID' => (int) $row['HOMESTAYID'],
        'homestay_name' => $row['HOMESTAY_NAME'],
        'homestay_address' => $row['HOMESTAY_ADDRESS'],
        'rent_price' => (float) $row['RENT_PRICE']
      ];
      $homestays[] = $stay;
      $homestayLookup[$stay['homestayID']] = $stay;
    }
  }
  oci_free_statement($homestay_stmt);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? 'create_booking';

    if ($formAction === 'pay_deposit') {
      $selectedPaymentBookingID = isset($_POST['bookingID']) ? (int) $_POST['bookingID'] : null;
      $paymentMethod = trim($_POST['payment_method'] ?? '');
      $paymentMethodInput = $paymentMethod;
      $addMembership = !$hasMembership && isset($_POST['add_membership']) && $_POST['add_membership'] === '1';

      if (!$selectedPaymentBookingID) {
        $paymentErrors[] = 'Please select a booking to pay for.';
      }

      if ($paymentMethod === '') {
        $paymentErrors[] = 'Please choose a payment method.';
      }

      if (empty($paymentErrors)) {
        // Check if there's a pending booking in session for this booking ID
        $pendingBooking = $_SESSION['pending_booking'] ?? null;
        
        if ($pendingBooking && $pendingBooking['bookingID'] == $selectedPaymentBookingID) {
          // Use pending booking data from session
          $booking_row = [
            'BOOKINGID' => $pendingBooking['bookingID'],
            'CHECKIN_DATE' => $pendingBooking['checkin_date'],
            'CHECKOUT_DATE' => $pendingBooking['checkout_date'],
            'DEPOSIT_AMOUNT' => $pendingBooking['deposit_amount'],
            'BILLNO' => null
          ];
          
          // Get homestay details for the pending booking
          if (isset($homestayLookup[$pendingBooking['homestayID']])) {
            $booking_row['HOMESTAY_NAME'] = $homestayLookup[$pendingBooking['homestayID']]['homestay_name'];
            $booking_row['HOMESTAY_ADDRESS'] = $homestayLookup[$pendingBooking['homestayID']]['homestay_address'];
          }
          
          if ($booking_row) {
            $depositAmount = isset($booking_row['DEPOSIT_AMOUNT']) ? (float) $booking_row['DEPOSIT_AMOUNT'] : 0.0;
            $membershipFee = $addMembership ? 30.00 : 0.0;
            $discountAmount = round($depositAmount * ($discountRate / 100), 2);
            $subtotal = $depositAmount + $membershipFee;
            $taxAmount = 0.0;
            $lateCharges = 0.0;
            $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount + $lateCharges);

            $bill_id_stmt = oci_parse($conn, 'SELECT NVL(MAX(billNo), 0) + 1 AS NEXT_ID FROM BILL');
            $nextBillNo = null;
            if (oci_execute($bill_id_stmt)) {
              $bill_id_row = oci_fetch_array($bill_id_stmt, OCI_ASSOC);
              $nextBillNo = isset($bill_id_row['NEXT_ID']) ? (int) $bill_id_row['NEXT_ID'] : 1;
            }
            oci_free_statement($bill_id_stmt);

            if ($nextBillNo === null) {
              $paymentErrors[] = 'Unable to generate a new bill number. Please try again shortly.';
            } else {
              $bill_insert_sql = "INSERT INTO BILL (billNo, bill_date, bill_subtotal, disc_amount, tax_amount,
                                                      total_amount, late_charges, bill_status, payment_date,
                                                      payment_method, guestID, staffID)
                                  VALUES (:billNo, SYSDATE, :subtotal, :disc_amount, :tax_amount, :total_amount,
                                          :late_charges, 'Pending', SYSDATE, :payment_method, :guestID, NULL)";
              $bill_insert_stmt = oci_parse($conn, $bill_insert_sql);
              oci_bind_by_name($bill_insert_stmt, ':billNo', $nextBillNo);
              oci_bind_by_name($bill_insert_stmt, ':subtotal', $subtotal);
              oci_bind_by_name($bill_insert_stmt, ':disc_amount', $discountAmount);
              oci_bind_by_name($bill_insert_stmt, ':tax_amount', $taxAmount);
              oci_bind_by_name($bill_insert_stmt, ':total_amount', $totalAmount);
              oci_bind_by_name($bill_insert_stmt, ':late_charges', $lateCharges);
              oci_bind_by_name($bill_insert_stmt, ':payment_method', $paymentMethod);
              oci_bind_by_name($bill_insert_stmt, ':guestID', $guestID);

              // Execute bill insert FIRST (must exist before booking can reference it)
              $bill_insert_result = oci_execute($bill_insert_stmt, OCI_NO_AUTO_COMMIT);

              // Create the booking in database (if from pending session)
              $booking_insert_result = true;
              if ($bill_insert_result && $pendingBooking) {
                $insert_booking_sql = "INSERT INTO BOOKING (bookingID, checkin_date, checkout_date, num_adults, num_children,
                                        deposit_amount, homestayID, guestID, staffID, billNo)
                                 VALUES (:bookingID,
                                     TO_DATE(:checkin_date, 'YYYY-MM-DD'),
                                     TO_DATE(:checkout_date, 'YYYY-MM-DD'),
                                     :num_adults,
                                     :num_children,
                                     :deposit_amount,
                                     :homestayID,
                                     :guestID,
                                     NULL,
                                     :billNo)";
                $insert_booking_stmt = oci_parse($conn, $insert_booking_sql);
                oci_bind_by_name($insert_booking_stmt, ':bookingID', $pendingBooking['bookingID']);
                oci_bind_by_name($insert_booking_stmt, ':checkin_date', $pendingBooking['checkin_date']);
                oci_bind_by_name($insert_booking_stmt, ':checkout_date', $pendingBooking['checkout_date']);
                oci_bind_by_name($insert_booking_stmt, ':num_adults', $pendingBooking['num_adults']);
                oci_bind_by_name($insert_booking_stmt, ':num_children', $pendingBooking['num_children']);
                oci_bind_by_name($insert_booking_stmt, ':deposit_amount', $pendingBooking['deposit_amount']);
                oci_bind_by_name($insert_booking_stmt, ':homestayID', $pendingBooking['homestayID']);
                oci_bind_by_name($insert_booking_stmt, ':guestID', $pendingBooking['guestID']);
                oci_bind_by_name($insert_booking_stmt, ':billNo', $nextBillNo);
                $booking_insert_result = oci_execute($insert_booking_stmt, OCI_NO_AUTO_COMMIT);
              } elseif ($bill_insert_result && !$pendingBooking) {
                // For existing bookings (if any), update billNo
                $booking_update_sql = 'UPDATE BOOKING SET billNo = :billNo WHERE bookingID = :bookingID';
                $booking_update_stmt = oci_parse($conn, $booking_update_sql);
                oci_bind_by_name($booking_update_stmt, ':billNo', $nextBillNo);
                oci_bind_by_name($booking_update_stmt, ':bookingID', $selectedPaymentBookingID);
                $booking_insert_result = oci_execute($booking_update_stmt, OCI_NO_AUTO_COMMIT);
              } elseif (!$bill_insert_result) {
                $booking_insert_result = false;
              }

              if ($bill_insert_result && $booking_insert_result) {
                // Create membership if user opted in and doesn't have one
                $membershipCreated = false;
                if ($addMembership) {
                  $id_sql = "SELECT NVL(MAX(membershipID), 0) + 1 AS NEXT_ID FROM MEMBERSHIP";
                  $id_stmt = oci_parse($conn, $id_sql);
                  if (oci_execute($id_stmt)) {
                    $id_row = oci_fetch_array($id_stmt, OCI_ASSOC);
                    $membershipID = isset($id_row['NEXT_ID']) ? (int) $id_row['NEXT_ID'] : 1;
                    $disc_rate = 10.00;
                    $insert_membership_sql = "INSERT INTO MEMBERSHIP (membershipID, guestID, disc_rate) VALUES (:membershipID, :guestID, :disc_rate)";
                    $insert_membership_stmt = oci_parse($conn, $insert_membership_sql);
                    oci_bind_by_name($insert_membership_stmt, ':membershipID', $membershipID);
                    oci_bind_by_name($insert_membership_stmt, ':guestID', $guestID);
                    oci_bind_by_name($insert_membership_stmt, ':disc_rate', $disc_rate);
                    if (oci_execute($insert_membership_stmt, OCI_NO_AUTO_COMMIT)) {
                      $guest_type = 'MEMBERSHIP';
                      $update_guest_sql = "UPDATE GUEST SET guest_type = :guest_type WHERE guestID = :guestID";
                      $update_guest_stmt = oci_parse($conn, $update_guest_sql);
                      oci_bind_by_name($update_guest_stmt, ':guest_type', $guest_type);
                      oci_bind_by_name($update_guest_stmt, ':guestID', $guestID);
                      if (oci_execute($update_guest_stmt, OCI_NO_AUTO_COMMIT)) {
                        $membershipCreated = true;
                      }
                      oci_free_statement($update_guest_stmt);
                    }
                    oci_free_statement($insert_membership_stmt);
                  }
                  oci_free_statement($id_stmt);
                }
                oci_commit($conn);
                
                // Clear pending booking from session after successful payment
                if (isset($_SESSION['pending_booking'])) {
                  unset($_SESSION['pending_booking']);
                }
                
                $hasMembership = $hasMembership || $membershipCreated;
                $discountRate = $membershipCreated ? 10.00 : $discountRate;
                $paymentSuccessMessage = 'Payment received successfully. Your booking is now confirmed!';
                if ($membershipCreated) {
                  $paymentSuccessMessage .= ' Membership activated with 10% discount!';
                }
                $paymentSummary = [
                  'billNo' => $nextBillNo,
                  'bookingID' => $booking_row['BOOKINGID'],
                  'homestay' => $booking_row['HOMESTAY_NAME'],
                  'checkin' => $booking_row['CHECKIN_DATE'],
                  'checkout' => $booking_row['CHECKOUT_DATE'],
                  'subtotal' => $subtotal,
                  'membershipFee' => $membershipFee,
                  'discount' => $discountAmount,
                  'total' => $totalAmount,
                  'method' => $paymentMethod,
                  'membershipCreated' => $membershipCreated
                ];
                $selectedPaymentBookingID = null;
                $paymentMethodInput = '';
              } else {
                oci_rollback($conn);
                $errorSource = !$bill_insert_result ? $bill_insert_stmt : ($pendingBooking ? $insert_booking_stmt : $booking_update_stmt);
                $errorMessage = oci_error($errorSource);
                $paymentErrors[] = 'Unable to record your payment: ' . ($errorMessage['message'] ?? 'Unexpected error');
              }

              oci_free_statement($bill_insert_stmt);
              if ($pendingBooking && isset($insert_booking_stmt)) {
                oci_free_statement($insert_booking_stmt);
              } elseif (isset($booking_update_stmt)) {
                oci_free_statement($booking_update_stmt);
              }
            }
          } else {
            $paymentErrors[] = 'The selected booking is not available for payment or has already been settled.';
          }
        } else {
          $paymentErrors[] = 'Booking not found. Please create a new booking and try again.';
        }
      }
    } else {
      $selectedHomestayID = isset($_POST['homestayID']) ? (int) $_POST['homestayID'] : null;
      $checkinInput = trim($_POST['checkin_date'] ?? '');
      $checkoutInput = trim($_POST['checkout_date'] ?? '');
      $numAdultsInput = max(1, (int) ($_POST['num_adults'] ?? 1));
      $numChildrenInput = max(0, (int) ($_POST['num_children'] ?? 0));

      if (!$selectedHomestayID || !isset($homestayLookup[$selectedHomestayID])) {
        $bookingErrors[] = 'Please select a valid homestay.';
      }

      $checkinDate = $checkinInput ? DateTime::createFromFormat('Y-m-d', $checkinInput) : false;
      $checkoutDate = $checkoutInput ? DateTime::createFromFormat('Y-m-d', $checkoutInput) : false;
      $today = new DateTime('today');

      if (!$checkinDate || !$checkoutDate) {
        $bookingErrors[] = 'Please provide both check-in and check-out dates.';
      } elseif ($checkoutDate <= $checkinDate) {
        $bookingErrors[] = 'Check-out must be after check-in.';
      } elseif ($checkinDate < $today) {
        $bookingErrors[] = 'Check-in date cannot be in the past.';
      }

      if ($numAdultsInput < 1) {
        $bookingErrors[] = 'At least one adult is required for a booking.';
      }

      if (empty($bookingErrors) && isset($homestayLookup[$selectedHomestayID])) {
        $checkinStr = $checkinDate->format('Y-m-d');
        $checkoutStr = $checkoutDate->format('Y-m-d');

        // Ensure the selected homestay is available for the requested dates
        $availability_sql = "SELECT COUNT(*) AS TOTAL
                             FROM BOOKING
                             WHERE homestayID = :homestayID
                               AND (TO_DATE(:checkin, 'YYYY-MM-DD') < checkout_date
                                    AND TO_DATE(:checkout, 'YYYY-MM-DD') > checkin_date)";
        $availability_stmt = oci_parse($conn, $availability_sql);
        oci_bind_by_name($availability_stmt, ':homestayID', $selectedHomestayID);
        oci_bind_by_name($availability_stmt, ':checkin', $checkinStr);
        oci_bind_by_name($availability_stmt, ':checkout', $checkoutStr);
        if (oci_execute($availability_stmt)) {
          $availability = oci_fetch_array($availability_stmt, OCI_ASSOC);
          if (!empty($availability['TOTAL'])) {
            $bookingErrors[] = 'The selected homestay is unavailable for the chosen dates. Please choose a different date range.';
          }
        } else {
          $bookingErrors[] = 'Unable to validate availability. Please try again.';
        }
        oci_free_statement($availability_stmt);
      }

      if (empty($bookingErrors) && isset($homestayLookup[$selectedHomestayID])) {
        $nights = (int) $checkinDate->diff($checkoutDate)->format('%a');
        $ratePerNight = $homestayLookup[$selectedHomestayID]['rent_price'];
        $totalAmount = $ratePerNight * $nights;
        $depositAmount = round($totalAmount * 0.30, 2);

        // Generate next booking ID
        $id_stmt = oci_parse($conn, 'SELECT NVL(MAX(bookingID), 0) + 1 AS NEXT_ID FROM BOOKING');
        $nextBookingID = null;
        if (oci_execute($id_stmt)) {
          $id_row = oci_fetch_array($id_stmt, OCI_ASSOC);
          $nextBookingID = (int) ($id_row['NEXT_ID'] ?? 1);
        }
        oci_free_statement($id_stmt);

        if (!$nextBookingID) {
          $bookingErrors[] = 'Unable to generate a booking reference. Please try again shortly.';
        }

        if (empty($bookingErrors)) {
          // Store booking details in session instead of database
          // Booking will only be created after deposit payment is completed
          $_SESSION['pending_booking'] = [
            'bookingID' => $nextBookingID,
            'checkin_date' => $checkinStr,
            'checkout_date' => $checkoutStr,
            'num_adults' => $numAdultsInput,
            'num_children' => $numChildrenInput,
            'deposit_amount' => $depositAmount,
            'homestayID' => $selectedHomestayID,
            'guestID' => $guestID,
            'nights' => $nights,
            'total_amount' => $totalAmount
          ];
          
          $bookingSuccessMessage = 'Your booking request has been prepared! Complete the deposit payment below to confirm your booking.';
          $bookingSummary = [
            'bookingID' => $nextBookingID,
            'homestay' => $homestayLookup[$selectedHomestayID]['homestay_name'],
            'nights' => $nights,
            'checkin' => $checkinDate->format('d M Y'),
            'checkout' => $checkoutDate->format('d M Y'),
            'adults' => $numAdultsInput,
            'children' => $numChildrenInput,
            'total' => $totalAmount,
            'deposit' => $depositAmount
          ];
          $selectedPaymentBookingID = $nextBookingID;
          $shouldPromptPayment = true;
          // Reset form values so a fresh booking can be created
          $checkinInput = '';
          $checkoutInput = '';
          $numAdultsInput = 1;
          $numChildrenInput = 0;
          $selectedHomestayID = null;
        }
      }
    }
  }

  // Get all bookings for this guest
  $bookings_sql = "SELECT b.bookingID, b.checkin_date, b.checkout_date, b.num_adults, b.num_children,
              b.deposit_amount, h.homestay_name, h.homestayID, h.homestay_address,
              b.billNo, bl.bill_status
           FROM BOOKING b
           JOIN HOMESTAY h ON b.homestayID = h.homestayID
           LEFT JOIN BILL bl ON b.billNo = bl.billNo
           WHERE b.guestID = :guestID
           ORDER BY b.checkin_date DESC";
  $bookings_stmt = oci_parse($conn, $bookings_sql);
  oci_bind_by_name($bookings_stmt, ':guestID', $guestID);
  if (oci_execute($bookings_stmt)) {
    while ($row = oci_fetch_array($bookings_stmt, OCI_ASSOC)) {
      $bookings[] = [
        'bookingID' => $row['BOOKINGID'],
        'homestay_name' => $row['HOMESTAY_NAME'],
        'homestayID' => $row['HOMESTAYID'],
        'homestay_address' => $row['HOMESTAY_ADDRESS'],
        'checkin_date' => $row['CHECKIN_DATE'],
        'checkout_date' => $row['CHECKOUT_DATE'],
        'num_adults' => $row['NUM_ADULTS'],
        'num_children' => $row['NUM_CHILDREN'],
        'deposit_amount' => $row['DEPOSIT_AMOUNT'],
        'bill_status' => $row['BILL_STATUS'] ?? null
      ];
    }
  }
  oci_free_statement($bookings_stmt);

  // Only show pending booking from session in outstanding bookings
  if (isset($_SESSION['pending_booking'])) {
    $pendingBooking = $_SESSION['pending_booking'];
    $homestayName = isset($homestayLookup[$pendingBooking['homestayID']]) 
      ? $homestayLookup[$pendingBooking['homestayID']]['homestay_name'] 
      : 'Unknown Homestay';
    $homestayAddress = isset($homestayLookup[$pendingBooking['homestayID']]) 
      ? $homestayLookup[$pendingBooking['homestayID']]['homestay_address'] 
      : '';
    
    // Only add the pending booking from session
    $outstandingBookings[] = [
      'bookingID' => $pendingBooking['bookingID'],
      'checkin_date' => $pendingBooking['checkin_date'],
      'checkout_date' => $pendingBooking['checkout_date'],
      'deposit_amount' => $pendingBooking['deposit_amount'],
      'homestay_name' => $homestayName,
      'homestay_address' => $homestayAddress
    ];
  }
  
  $showDepositSection = $shouldPromptPayment
    || $bookingSummary !== null
    || !empty($paymentErrors);
  
  $showConfirmationSection = $paymentSummary !== null || !empty($paymentSuccessMessage);
  
  closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Booking - Serena Sanctuary</title>
    <link rel="stylesheet" href="../../css/phpStyle/guestStyle/bookingStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../images/logoNbg.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- shadcn Calendar Styles -->
    <style>
      /* shadcn Calendar Component Styling */
      .calendar-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
      }

      .calendar-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
      }

      .calendar-input:hover {
        border-color: #C5814B;
        background: #fafafa;
      }

      .calendar-input:focus {
        outline: none;
        border-color: #C5814B;
        box-shadow: 0 0 0 3px rgba(197, 129, 75, 0.1);
      }

      .calendar-popup {
        position: absolute;
        top: 100%;
        left: 0;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        padding: 16px;
        margin-top: 8px;
        z-index: 1000;
        min-width: 320px;
        display: none;
      }

      .calendar-popup.active {
        display: block;
      }

      .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
      }

      .calendar-nav-button {
        background: none;
        border: none;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border-radius: 6px;
        color: #333;
        font-size: 16px;
        transition: all 0.2s ease;
      }

      .calendar-nav-button:hover {
        background: #f0f0f0;
        color: #C5814B;
      }

      .calendar-month-year {
        font-weight: 600;
        font-size: 14px;
        color: #333;
        text-align: center;
        flex: 1;
      }

      .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        margin-bottom: 8px;
      }

      .calendar-weekday {
        text-align: center;
        font-weight: 500;
        font-size: 12px;
        color: #888;
        padding: 8px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
      }

      .calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        background: transparent;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        font-weight: 500;
      }

      .calendar-day:not(.other-month):hover {
        background: #f0f0f0;
        border-color: #e0e0e0;
      }

      .calendar-day.other-month {
        color: #ccc;
        cursor: default;
      }

      .calendar-day.disabled {
        background: #e8e8e8;
        color: #999;
        cursor: not-allowed;
        border-color: #d0d0d0;
        pointer-events: none;
      }

      .calendar-day.booked {
        background: #ff4444 !important;
        color: #fff !important;
        cursor: not-allowed !important;
        border-color: #ff2222 !important;
        pointer-events: none;
      }

      .calendar-day.in-range {
        background: #f5e6d3;
        border-color: #C5814B;
        color: #333;
      }

      .calendar-day.selected {
        background: #C5814B;
        color: #fff;
        border-color: #C5814B;
        font-weight: 600;
      }

      .calendar-day.selected:hover {
        background: #b3703a;
        border-color: #b3703a;
      }

      .calendar-day.range-start,
      .calendar-day.range-end {
        background: #C5814B;
        color: #fff;
        border-color: #C5814B;
        font-weight: 600;
      }

      .calendar-day.today {
        font-weight: 600;
        border: 2px solid #C5814B;
      }

      .calendar-footer {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #e0e0e0;
        text-align: center;
        font-size: 12px;
        color: #888;
      }

      /* Disabled form elements styling */
      input:disabled,
      select:disabled,
      textarea:disabled {
        background-color: #f5f5f5 !important;
        color: #ccc !important;
        cursor: not-allowed !important;
        opacity: 0.6;
      }

      input:disabled::placeholder,
      select:disabled::placeholder {
        color: #ccc !important;
      }

      .form-group:has(input:disabled),
      .form-group:has(select:disabled) {
        opacity: 0.6;
        pointer-events: none;
      }

      button:disabled,
      button[disabled] {
        background-color: #ccc !important;
        color: #666 !important;
        cursor: not-allowed !important;
        opacity: 0.6;
      }

      button:disabled:hover,
      button[disabled]:hover {
        background-color: #ccc !important;
      }
    </style>
  </head>
<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <div class="nav-logo">
        <img src="../../images/logoNbg.png" alt="Serena Sanctuary logo" class="logo-icon">
        <span class="logo-name">Serena Sanctuary</span>
      </div>
      <ul class="nav-menu" id="navMenu">
        <li><a href="home.php" class="nav-link">Home</a></li>
        <li><a href="booking.php" class="nav-link active">Booking</a></li>
        <li><a href="homestay.php" class="nav-link">Homestay</a></li>
        <li><a href="profile.php" class="nav-link">Profile</a></li>
        <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
      </ul>
      <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <section class="page-header">
      <div class="container">
        <h1 class="page-title">My Bookings</h1>
        <p class="page-subtitle">Manage your reservations and upcoming stays</p>
      </div>
    </section>

    <?php if (!$showDepositSection && !$showConfirmationSection): ?>
    <section class="booking-flow-section">
      <div class="container">
        <?php if ($membershipFlash): ?>
          <div class="alert <?php echo ($membershipFlashType === 'success') ? 'alert-success' : 'alert-error'; ?>">
            <p><?php echo htmlspecialchars($membershipFlash); ?></p>
          </div>
        <?php endif; ?>
        <div class="booking-flow-grid">
          <?php if (!$showDepositSection && !$showConfirmationSection): ?>
          <div class="booking-form-card">
            <div class="form-header">
              <div>
                <p class="eyebrow">Step 1</p>
                <h2>Create a Booking</h2>
                <p class="form-subtitle">Choose your preferred homestay, pick your travel dates, and tell us how many guests are coming along.</p>
              </div>
            </div>

            <?php if ($bookingSuccessMessage): ?>
              <div class="alert alert-success">
                <p><?php echo htmlspecialchars($bookingSuccessMessage); ?></p>
              </div>
            <?php endif; ?>

            <?php if (!empty($bookingErrors)): ?>
              <div class="alert alert-error">
                <ul>
                  <?php foreach ($bookingErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php if ($bookingSummary): ?>
              <div class="booking-success-summary">
                <div class="summary-row">
                  <span>Booking Ref</span>
                  <span>#<?php echo htmlspecialchars($bookingSummary['bookingID']); ?></span>
                </div>
                <div class="summary-row">
                  <span>Homestay</span>
                  <span><?php echo htmlspecialchars($bookingSummary['homestay']); ?></span>
                </div>
                <div class="summary-row">
                  <span>Dates</span>
                  <span><?php echo htmlspecialchars($bookingSummary['checkin'] . ' to ' . $bookingSummary['checkout']); ?></span>
                </div>
                <div class="summary-row">
                  <span>Guests</span>
                  <span><?php echo $bookingSummary['adults']; ?> adults<?php if ($bookingSummary['children'] > 0) { echo ' · ' . $bookingSummary['children'] . ' children'; } ?></span>
                </div>
                <div class="summary-row highlight">
                  <span>Deposit Due</span>
                  <span>RM <?php echo number_format($bookingSummary['deposit'], 2); ?></span>
                </div>
                <div class="summary-actions">
                  <p>Complete the deposit below to secure this stay.</p>
                  <button type="button" class="btn btn-pay pay-trigger" data-booking-id="<?php echo htmlspecialchars($bookingSummary['bookingID']); ?>">Pay Deposit Now</button>
                </div>
              </div>
            <?php endif; ?>

            <?php if (empty($homestays)): ?>
              <p class="empty-state">Homestays are currently unavailable. Please check back later.</p>
            <?php else: ?>
              <form method="POST" class="booking-form" id="guestBookingForm">
                <input type="hidden" name="form_action" value="create_booking">
                <div class="form-grid">
                  <div class="form-group full-width">
                    <label for="homestayID">Choose Homestay</label>
                    <div class="select-wrapper">
                      <select name="homestayID" id="homestayID" required>
                        <option value="">Select a homestay</option>
                        <?php foreach ($homestays as $stay): ?>
                          <option value="<?php echo $stay['homestayID']; ?>"
                                  data-name="<?php echo htmlspecialchars($stay['homestay_name']); ?>"
                                  data-price="<?php echo number_format($stay['rent_price'], 2, '.', ''); ?>"
                                  data-address="<?php echo htmlspecialchars($stay['homestay_address']); ?>"
                                  <?php echo ($selectedHomestayID === $stay['homestayID']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($stay['homestay_name']); ?> - RM <?php echo number_format($stay['rent_price'], 2); ?> / night
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="form-group full-width">
                    <label for="dateRangeInput">Select Dates (Check-in - Check-out)</label>
                    <div class="calendar-wrapper">
                      <input type="text" id="dateRangeInput" class="calendar-input" placeholder="Click to select dates" readonly disabled>
                      <div id="calendarPopup" class="calendar-popup">
                        <div class="calendar-header">
                          <button type="button" class="calendar-nav-button" id="prevMonth">←</button>
                          <div class="calendar-month-year" id="monthYear"></div>
                          <button type="button" class="calendar-nav-button" id="nextMonth">→</button>
                        </div>
                        <div class="calendar-weekdays">
                          <div class="calendar-weekday">Sun</div>
                          <div class="calendar-weekday">Mon</div>
                          <div class="calendar-weekday">Tue</div>
                          <div class="calendar-weekday">Wed</div>
                          <div class="calendar-weekday">Thu</div>
                          <div class="calendar-weekday">Fri</div>
                          <div class="calendar-weekday">Sat</div>
                        </div>
                        <div class="calendar-days" id="calendarDays"></div>
                      </div>
                    </div>
                    <!-- Hidden inputs for form submission -->
                    <input type="hidden" name="checkin_date" id="checkin_date" value="<?php echo htmlspecialchars($checkinInput); ?>">
                    <input type="hidden" name="checkout_date" id="checkout_date" value="<?php echo htmlspecialchars($checkoutInput); ?>">
                  </div>

                  <div class="form-group">
                    <label for="num_adults">Adults</label>
                    <input type="number" name="num_adults" id="num_adults" min="1" value="<?php echo htmlspecialchars($numAdultsInput); ?>" required disabled>
                  </div>

                  <div class="form-group">
                    <label for="num_children">Children</label>
                    <input type="number" name="num_children" id="num_children" min="0" value="<?php echo htmlspecialchars($numChildrenInput); ?>" disabled>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary btn-submit" disabled>Submit Booking Request</button>
                <p class="form-footnote">A 30% deposit is required to secure your booking. Remaining balance can be settled upon confirmation.</p>
              </form>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if (!$showDepositSection && !$showConfirmationSection): ?>
          <div class="booking-summary-card" id="bookingSummaryCard">
            <h3>Live Estimate</h3>
            <p class="summary-description">We calculate the number of nights, estimated total, and deposit as you complete the form.</p>
            <div class="summary-block">
              <div class="summary-item">
                <span class="label">Homestay</span>
                <span class="value" id="summaryHomestay">--</span>
              </div>
              <div class="summary-item">
                <span class="label">Dates</span>
                <span class="value" id="summaryDates">--</span>
              </div>
              <div class="summary-item">
                <span class="label">Guests</span>
                <span class="value" id="summaryGuests">--</span>
              </div>
              <div class="summary-item">
                <span class="label">Nights</span>
                <span class="value" id="summaryNights">0</span>
              </div>
              <div class="summary-item highlight">
                <span class="label">Estimated Total</span>
                <span class="value" id="summaryTotal">RM 0.00</span>
              </div>
              <div class="summary-item">
                <span class="label">Deposit (30%)</span>
                <span class="value" id="summaryDeposit">RM 0.00</span>
              </div>
            </div>
            <p class="summary-note">Final totals may adjust slightly once taxes or optional services are added.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($showDepositSection && !$showConfirmationSection): ?>
    <section class="deposit-payment-section" id="depositPayment">
      <div class="container">
        <div class="deposit-header">
          <p class="eyebrow">Step 2</p>
          <h2>Secure Your Booking</h2>
          <p>Pay the 30% deposit for any outstanding booking right away. Membership discounts are applied automatically.</p>
        </div>
        <div class="deposit-grid">
          <div class="deposit-form-card">
            <div class="form-header">
              <div>
                <h3>Deposit Payment</h3>
                <p>Select the booking you want to settle and choose a payment method.</p>
              </div>
              <span class="badge">Required</span>
            </div>

            <?php if (!empty($paymentErrors)): ?>
              <div class="alert alert-error">
                <ul>
                  <?php foreach ($paymentErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <?php if (empty($outstandingBookings)): ?>
              <p class="empty-state">No outstanding deposits right now. Submit a booking above to get started.</p>
            <?php else: ?>
              <form method="POST" id="depositPaymentForm" class="payment-form">
                <input type="hidden" name="form_action" value="pay_deposit">
                <div class="form-group">
                  <label for="paymentBookingID">Outstanding Booking</label>
                  <div class="select-wrapper">
                    <select name="bookingID" id="paymentBookingID" data-scroll-onload="<?php echo $shouldPromptPayment ? '1' : '0'; ?>" required>
                      <option value="">Select a booking</option>
                      <?php foreach ($outstandingBookings as $booking):
                        $checkinInline = date('d M Y', strtotime($booking['checkin_date']));
                        $checkoutInline = date('d M Y', strtotime($booking['checkout_date']));
                        $depositLabel = 'RM ' . number_format($booking['deposit_amount'], 2);
                      ?>
                        <option value="<?php echo $booking['bookingID']; ?>"
                                data-deposit="<?php echo number_format($booking['deposit_amount'], 2, '.', ''); ?>"
                                data-homestay="<?php echo htmlspecialchars($booking['homestay_name'], ENT_QUOTES); ?>"
                                data-address="<?php echo htmlspecialchars($booking['homestay_address'], ENT_QUOTES); ?>"
                                data-checkin="<?php echo htmlspecialchars($booking['checkin_date']); ?>"
                                data-checkout="<?php echo htmlspecialchars($booking['checkout_date']); ?>"
                                <?php echo ($selectedPaymentBookingID === $booking['bookingID']) ? 'selected' : ''; ?>>
                          #<?php echo $booking['bookingID']; ?> · <?php echo htmlspecialchars($booking['homestay_name']); ?> (<?php echo $checkinInline; ?> - <?php echo $checkoutInline; ?>) · <?php echo $depositLabel; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label for="payment_method">Payment Method</label>
                  <div class="select-wrapper">
                    <select name="payment_method" id="payment_method" required>
                      <option value="">Choose a method</option>
                      <option value="Online Banking" <?php echo ($paymentMethodInput === 'Online Banking') ? 'selected' : ''; ?>>Online Banking</option>
                      <option value="Credit Card" <?php echo ($paymentMethodInput === 'Credit Card') ? 'selected' : ''; ?>>Credit / Debit Card</option>
                      <option value="E-Wallet" <?php echo ($paymentMethodInput === 'E-Wallet') ? 'selected' : ''; ?>>E-Wallet</option>
                    </select>
                  </div>
                </div>

                <?php if (!$hasMembership): ?>
                <div class="form-group" style="margin-top: 16px;">
                  <label class="membership-checkbox-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; border: 1px solid #e0e6ed; border-radius: 8px; background: #f8fafc;">
                    <div style="position: relative; width: 20px; height: 20px; flex-shrink: 0;">
                      <input type="checkbox" name="add_membership" value="1" id="add_membership" style="position: absolute; opacity: 0; width: 20px; height: 20px; cursor: pointer;">
                      <div class="custom-checkbox" style="position: absolute; top: 0; left: 0; width: 20px; height: 20px; border: 2px solid #2563eb; border-radius: 4px; background: white; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                        <i class='bx bx-check' style="font-size: 16px; color: white; display: none;"></i>
                      </div>
                    </div>
                    <div style="flex: 1;">
                      <strong style="display: block; margin-bottom: 2px;">Add Membership for RM30</strong>
                      <small style="color: #6b7280;">Get 10% off this and all future bookings instantly</small>
                    </div>
                  </label>
                </div>
                <style>
                  #add_membership:checked + .custom-checkbox {
                    background: #2563eb !important;
                    border-color: #2563eb !important;
                  }
                  #add_membership:checked + .custom-checkbox i {
                    display: block !important;
                  }
                  .membership-checkbox-label:hover .custom-checkbox {
                    border-color: #1d4ed8;
                  }
                </style>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Pay Deposit</button>
                <p class="form-footnote">We create an official bill instantly and notify the team to confirm your stay.</p>
              </form>
            <?php endif; ?>
          </div>

          <div class="deposit-summary-card" id="inlinePaymentSummary" data-discount-rate="<?php echo $discountRate; ?>">
            <h3>Payment Summary</h3>
            <p class="summary-description">
              <?php echo $hasMembership
                ? 'See how your membership discount reduces the deposit today.'
                : 'Membership discounts apply instantly once you add membership.'; ?>
            </p>
            <div class="summary-item">
              <span class="label">Homestay</span>
              <span class="value" id="paymentSummaryHomestay">--</span>
            </div>
            <div class="summary-item">
              <span class="label">Dates</span>
              <span class="value" id="paymentSummaryDates">--</span>
            </div>
            <div class="summary-item">
              <span class="label">Deposit</span>
              <span class="value" id="paymentSummaryDeposit">RM 0.00</span>
            </div>
            <?php if (!$hasMembership): ?>
            <div class="summary-item" style="display: none;">
              <span class="label">Membership</span>
              <span class="value" id="paymentSummaryMembership">RM 0.00</span>
            </div>
            <?php endif; ?>
            <div class="summary-item">
              <span class="label">Discount (<?php echo number_format($discountRate, 0); ?>%)</span>
              <span class="value" id="paymentSummaryDiscount">RM 0.00</span>
            </div>
            <div class="summary-item highlight">
              <span class="label">Amount Due Today</span>
              <span class="value" id="paymentSummaryTotal">RM 0.00</span>
            </div>
            <p class="summary-note">Deposits are non-refundable if bookings are cancelled within 7 days of arrival.</p>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($showConfirmationSection): ?>
    <section class="confirmation-section" id="paymentConfirmation">
      <div class="container">
        <div class="confirmation-header">
          <p class="eyebrow">Step 3</p>
          <h2>Payment Confirmed!</h2>
          <p>Your deposit has been received and your booking is now confirmed. The team has been notified.</p>
        </div>
        
        <div class="confirmation-content">
          <div class="confirmation-card">
            <div class="success-icon">
              <i class='bx bx-check-circle'></i>
            </div>
            
            <?php if ($paymentSuccessMessage): ?>
              <div class="confirmation-message">
                <h3><?php echo htmlspecialchars($paymentSuccessMessage); ?></h3>
              </div>
            <?php endif; ?>

            <?php if ($paymentSummary): ?>
              <?php
                $paymentSummaryCheckin = !empty($paymentSummary['checkin']) ? date('d M Y', strtotime($paymentSummary['checkin'])) : '--';
                $paymentSummaryCheckout = !empty($paymentSummary['checkout']) ? date('d M Y', strtotime($paymentSummary['checkout'])) : '--';
                $paymentSummaryRange = ($paymentSummaryCheckin !== '--' && $paymentSummaryCheckout !== '--')
                  ? $paymentSummaryCheckin . ' - ' . $paymentSummaryCheckout
                  : '--';
              ?>
              <div class="confirmation-details">
                <h4>Booking Details</h4>
                <div class="details-grid">
                  <div class="detail-item">
                    <span class="detail-label">Bill Number</span>
                    <span class="detail-value">#<?php echo htmlspecialchars($paymentSummary['billNo']); ?></span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Booking Reference</span>
                    <span class="detail-value">#<?php echo htmlspecialchars($paymentSummary['bookingID']); ?></span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Homestay</span>
                    <span class="detail-value"><?php echo htmlspecialchars($paymentSummary['homestay']); ?></span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Check-in to Check-out</span>
                    <span class="detail-value"><?php echo $paymentSummaryRange; ?></span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value"><?php echo htmlspecialchars($paymentSummary['method']); ?></span>
                  </div>
                  <div class="detail-item">
                    <span class="detail-label">Deposit Paid</span>
                    <span class="detail-value">RM <?php echo number_format($paymentSummary['subtotal'] - ($paymentSummary['membershipFee'] ?? 0), 2); ?></span>
                  </div>
                  <?php if (!empty($paymentSummary['membershipFee']) && $paymentSummary['membershipFee'] > 0): ?>
                  <div class="detail-item">
                    <span class="detail-label">Membership Fee</span>
                    <span class="detail-value">RM <?php echo number_format($paymentSummary['membershipFee'], 2); ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if (!empty($paymentSummary['discount']) && $paymentSummary['discount'] > 0): ?>
                  <div class="detail-item">
                    <span class="detail-label">Discount Applied</span>
                    <span class="detail-value">- RM <?php echo number_format($paymentSummary['discount'], 2); ?></span>
                  </div>
                  <?php endif; ?>
                  <div class="detail-item highlight">
                    <span class="detail-label">Total Paid</span>
                    <span class="detail-value">RM <?php echo number_format($paymentSummary['total'], 2); ?></span>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="confirmation-actions">
              <a href="booking_details.php?bookingID=<?php echo htmlspecialchars($paymentSummary['bookingID']); ?>" class="btn btn-primary">
                <i class='bx bx-receipt'></i> View Booking Details
              </a>
              <a href="booking.php" class="btn btn-secondary">Make Another Booking</a>
            </div>

            <div class="next-steps">
              <h4>What's Next?</h4>
              <div class="steps-list">
                <div class="step">
                  <div class="step-icon">
                    <i class='bx bx-check'></i>
                  </div>
                  <div class="step-content">
                    <h5>Deposit Received</h5>
                    <p>Your deposit payment has been confirmed and recorded.</p>
                  </div>
                </div>
                <div class="step">
                  <div class="step-icon">
                    <i class='bx bx-time'></i>
                  </div>
                  <div class="step-content">
                    <h5>Awaiting Full Payment</h5>
                    <p>Pay the remaining balance at our counter before your check-in date.</p>
                  </div>
                </div>
                <div class="step">
                  <div class="step-icon">
                    <i class='bx bx-home-heart'></i>
                  </div>
                  <div class="step-content">
                    <h5>Enjoy Your Stay</h5>
                    <p>Check in on your scheduled date and enjoy your homestay experience!</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <style>
      .confirmation-section {
        padding: 60px 0;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      }

      .confirmation-header {
        text-align: center;
        margin-bottom: 40px;
      }

      .confirmation-header .eyebrow {
        color: #28a745;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-size: 12px;
        margin-bottom: 8px;
      }

      .confirmation-header h2 {
        font-size: 36px;
        color: #2d3748;
        margin-bottom: 12px;
      }

      .confirmation-header p {
        color: #718096;
        font-size: 16px;
      }

      .confirmation-content {
        max-width: 800px;
        margin: 0 auto;
      }

      .confirmation-card {
        background: white;
        border-radius: 16px;
        padding: 48px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      }

      .success-icon {
        text-align: center;
        margin-bottom: 24px;
      }

      .success-icon i {
        font-size: 80px;
        color: #28a745;
        animation: scaleIn 0.5s ease-out;
      }

      @keyframes scaleIn {
        from {
          transform: scale(0);
          opacity: 0;
        }
        to {
          transform: scale(1);
          opacity: 1;
        }
      }

      .confirmation-message {
        text-align: center;
        margin-bottom: 32px;
        padding-bottom: 32px;
        border-bottom: 2px solid #e9ecef;
      }

      .confirmation-message h3 {
        font-size: 20px;
        color: #2d3748;
        line-height: 1.6;
      }

      .confirmation-details h4 {
        font-size: 18px;
        color: #2d3748;
        margin-bottom: 20px;
        font-weight: 600;
      }

      .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 32px;
      }

      .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 16px;
        background: #f8f9fa;
        border-radius: 8px;
      }

      .detail-item.highlight {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, #C5814B 0%, #b3703a 100%);
      }

      .detail-item.highlight .detail-label,
      .detail-item.highlight .detail-value {
        color: white;
      }

      .detail-label {
        font-size: 12px;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
      }

      .detail-value {
        font-size: 16px;
        color: #2d3748;
        font-weight: 600;
      }

      .confirmation-actions {
        display: flex;
        gap: 12px;
        margin-bottom: 32px;
        padding-bottom: 32px;
        border-bottom: 2px solid #e9ecef;
      }

      .confirmation-actions .btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 24px;
      }

      .next-steps h4 {
        font-size: 18px;
        color: #2d3748;
        margin-bottom: 20px;
        font-weight: 600;
      }

      .steps-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
      }

      .step {
        display: flex;
        gap: 16px;
        align-items: flex-start;
      }

      .step-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e6f4ea;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .step-icon i {
        font-size: 20px;
        color: #28a745;
      }

      .step-content h5 {
        font-size: 16px;
        color: #2d3748;
        margin-bottom: 4px;
        font-weight: 600;
      }

      .step-content p {
        font-size: 14px;
        color: #718096;
        line-height: 1.5;
        margin: 0;
      }

      @media (max-width: 768px) {
        .confirmation-card {
          padding: 32px 24px;
        }

        .details-grid {
          grid-template-columns: 1fr;
        }

        .detail-item.highlight {
          grid-column: 1;
        }

        .confirmation-actions {
          flex-direction: column;
        }

        .confirmation-header h2 {
          font-size: 28px;
        }
      }
    </style>
    <?php endif; ?>

    <section class="content-section">
      <div class="container">
        <div class="booking-header">
          <h2>Your Bookings</h2>
          <a style="display:none" href="homestay.php" class="btn btn-primary">New Booking</a>
        </div>
        <div class="bookings-list">
          <?php if (empty($bookings)): ?>
            <p style="text-align: center; color: #666; padding: 40px;">No bookings found. Book now to get started!</p>
          <?php else: ?>
            <?php foreach ($bookings as $booking): 
              $checkin = date('d M Y', strtotime($booking['checkin_date']));
              $checkout = date('d M Y', strtotime($booking['checkout_date']));
              $image_map = [1 => 'homestay1', 2 => 'homestay2', 3 => 'homestay3', 4 => 'homestay4'];
              $img_folder = $image_map[$booking['homestayID']] ?? 'homestay1';
              $status = strtolower($booking['bill_status'] ?? 'pending');
              $status_class = ($status === 'paid' || $status === 'confirmed') ? 'confirmed' : 'pending';
              $isDepositSettled = in_array($status, ['paid', 'confirmed'], true);
              $depositAmount = isset($booking['deposit_amount']) ? (float) $booking['deposit_amount'] : 0.0;
            ?>
            <div class="booking-card">
              <div class="booking-image">
                <img src="../../images/<?php echo $img_folder; ?>/<?php echo $img_folder; ?>.jpg" alt="<?php echo htmlspecialchars($booking['homestay_name']); ?>">
              </div>
              <div class="booking-info">
                <h3><?php echo htmlspecialchars($booking['homestay_name']); ?></h3>
                <p class="booking-dates"><i class='bx bx-calendar'></i> Check-in: <?php echo $checkin; ?> | Check-out: <?php echo $checkout; ?></p>
                <p class="booking-details">Adults: <?php echo $booking['num_adults']; ?> | Children: <?php echo $booking['num_children']; ?></p>
                <?php if ($depositAmount > 0): ?>
                <div class="booking-payment-meta">
                  <span class="deposit-chip">Deposit RM <?php echo number_format($depositAmount, 2); ?></span>
                  <span class="deposit-note <?php echo $isDepositSettled ? 'paid' : 'due'; ?>">
                    <?php echo $isDepositSettled ? 'Deposit received' : '30% due to confirm'; ?>
                  </span>
                </div>
                <?php endif; ?>
                <p class="booking-status <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></p>
              </div>
              <div class="booking-actions">
                <?php if ($status === 'pending'): ?>
                  <a class="btn btn-secondary" href="booking_details.php?bookingID=<?php echo htmlspecialchars($booking['bookingID']); ?>">View Details</a>
                  <p class="action-footnote">Awaiting full payment at counter</p>
                <?php elseif ($isDepositSettled): ?>
                  <a class="btn btn-secondary" href="booking_details.php?bookingID=<?php echo htmlspecialchars($booking['bookingID']); ?>">View Details</a>
                  <p class="action-footnote">Thank you for your stay.</p>
                <?php else: ?>
                  <button type="button" class="btn btn-pay pay-trigger" data-booking-id="<?php echo htmlspecialchars($booking['bookingID']); ?>">Pay Deposit</button>
                  <p class="action-footnote">Secure this stay today</p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <div class="footer-logo">
            <img src="../../images/logoNbg.png" alt="Serena Sanctuary logo" class="logo-icon">
            <span class="logo-name">Serena Sanctuary</span>
          </div>
          <p class="footer-description">Your trusted partner for exceptional homestay experiences.</p>
        </div>
        <div class="footer-section">
          <h4 class="footer-title">Quick Links</h4>
          <ul class="footer-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="homestay.php">Homestay</a></li>
            <li><a href="membership.php">Membership</a></li>
            <li><a href="profile.php">Profile</a></li>
          </ul>
        </div>
        <div class="footer-section">
          <h4 class="footer-title">Contact</h4>
          <ul class="footer-contact">
            <li><i class='bx bx-envelope'></i> info@serenasanctuary.com</li>
            <li><i class='bx bx-phone'></i> +60 17-204 2390</li>
            <li><i class='bx bx-map'></i> Malaysia</li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2025 Serena Sanctuary. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script>
    const bookingForm = document.getElementById('guestBookingForm');
    const homestaySelect = document.getElementById('homestayID');
    const checkinInputEl = document.getElementById('checkin_date');
    const checkoutInputEl = document.getElementById('checkout_date');
    const adultsInputEl = document.getElementById('num_adults');
    const childrenInputEl = document.getElementById('num_children');
    const submitBtn = bookingForm?.querySelector('button[type="submit"]');
    const summaryElements = {
      homestay: document.getElementById('summaryHomestay'),
      dates: document.getElementById('summaryDates'),
      guests: document.getElementById('summaryGuests'),
      nights: document.getElementById('summaryNights'),
      total: document.getElementById('summaryTotal'),
      deposit: document.getElementById('summaryDeposit')
    };

    // shadcn-style Calendar Implementation
    let bookedDates = [];
    let currentMonth = new Date();
    let selectedStart = null;
    let selectedEnd = null;

    const dateRangeInput = document.getElementById('dateRangeInput');
    const calendarPopup = document.getElementById('calendarPopup');
    const monthYearEl = document.getElementById('monthYear');
    const calendarDaysEl = document.getElementById('calendarDays');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');

    const formatDate = (date) => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    const formatDateDisplay = (date) => {
      return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const updateDateRangeDisplay = () => {
      if (selectedStart && selectedEnd) {
        dateRangeInput.value = `${formatDateDisplay(selectedStart)} to ${formatDateDisplay(selectedEnd)}`;
        checkinInputEl.value = formatDate(selectedStart);
        checkoutInputEl.value = formatDate(selectedEnd);
      } else if (selectedStart) {
        dateRangeInput.value = formatDateDisplay(selectedStart);
      }
      updateSummary();
    };

    const isDateBooked = (date) => {
      const dateStr = formatDate(date);
      const isBooked = bookedDates.includes(dateStr);
      return isBooked;
    };

    const isDateInRange = (date) => {
      if (!selectedStart || !selectedEnd) return false;
      return date > selectedStart && date < selectedEnd;
    };

    const isDateSelected = (date) => {
      if (selectedStart && formatDate(date) === formatDate(selectedStart)) return 'range-start';
      if (selectedEnd && formatDate(date) === formatDate(selectedEnd)) return 'range-end';
      return null;
    };

    const renderCalendar = () => {
      monthYearEl.textContent = currentMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
      
      const year = currentMonth.getFullYear();
      const month = currentMonth.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const prevLastDay = new Date(year, month, 0);
      
      const firstDayOfWeek = firstDay.getDay();
      const daysInMonth = lastDay.getDate();
      const daysInPrevMonth = prevLastDay.getDate();
      
      calendarDaysEl.innerHTML = '';
      
      // Previous month days
      for (let i = firstDayOfWeek - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const dayEl = createDayElement(day, true);
        calendarDaysEl.appendChild(dayEl);
      }
      
      // Current month days
      for (let i = 1; i <= daysInMonth; i++) {
        const date = new Date(year, month, i);
        const dayEl = createDayElement(i, false, date);
        calendarDaysEl.appendChild(dayEl);
      }
      
      // Next month days
      const remainingDays = 42 - (firstDayOfWeek + daysInMonth);
      for (let i = 1; i <= remainingDays; i++) {
        const dayEl = createDayElement(i, true);
        calendarDaysEl.appendChild(dayEl);
      }
    };

    const createDayElement = (day, isOtherMonth, date = null) => {
      const dayEl = document.createElement('div');
      dayEl.className = 'calendar-day';
      dayEl.textContent = day;
      
      if (isOtherMonth) {
        dayEl.classList.add('other-month');
        return dayEl;
      }
      
      const fullDate = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      // Check if date is booked by other guests - show in red
      if (isDateBooked(fullDate)) {
        dayEl.classList.add('booked');
        return dayEl;
      }
      
      // Check if date is in past - show in gray
      if (fullDate < today) {
        dayEl.classList.add('disabled');
        return dayEl;
      }
      
      // Check if date is in range
      if (isDateInRange(fullDate)) {
        dayEl.classList.add('in-range');
      }
      
      // Check if date is selected
      const selectedClass = isDateSelected(fullDate);
      if (selectedClass) {
        dayEl.classList.add('selected', selectedClass);
      }
      
      // Mark today
      if (fullDate.getTime() === today.getTime()) {
        dayEl.classList.add('today');
      }
      
      dayEl.addEventListener('click', () => {
        if (!selectedStart || fullDate <= selectedStart) {
          // Start or reset the range and keep the calendar open for checkout selection
          selectedStart = fullDate;
          selectedEnd = null;
          calendarPopup.classList.add('active');
        } else {
          selectedEnd = fullDate;
          calendarPopup.classList.remove('active');
        }
        renderCalendar();
        updateDateRangeDisplay();
      });
      
      return dayEl;
    };

    // Fetch booked dates when homestay is selected
    const fetchBookedDates = async (homestayID) => {
      if (!homestayID) {
        bookedDates = [];
        renderCalendar();
        return;
      }

      try {
        const response = await fetch(`api_get_booked_dates.php?homestayID=${encodeURIComponent(homestayID)}`);
        const data = await response.json();
        
        if (data.success) {
          bookedDates = data.bookedDates;
          renderCalendar();
        } else {
          console.error('Error fetching booked dates:', data.error);
          bookedDates = [];
          renderCalendar();
        }
      } catch (error) {
        console.error('Failed to fetch booked dates:', error);
        bookedDates = [];
        renderCalendar();
      }
    };

    // Calendar UI event listeners
    dateRangeInput.addEventListener('click', () => {
      // Always open the calendar when clicking the input
      calendarPopup.classList.add('active');
    });

    prevMonthBtn.addEventListener('click', (e) => {
      e.preventDefault();
      currentMonth.setMonth(currentMonth.getMonth() - 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener('click', (e) => {
      e.preventDefault();
      currentMonth.setMonth(currentMonth.getMonth() + 1);
      renderCalendar();
    });

    // Close calendar when clicking outside
    document.addEventListener('click', (e) => {
      const clickedInside = e.target.closest('.calendar-wrapper');
      const clickedInput = e.target === dateRangeInput;

      if (clickedInside || clickedInput) {
        return;
      }

      // Keep the calendar open if check-in is chosen but checkout is not yet set
      if (selectedStart && !selectedEnd) {
        calendarPopup.classList.add('active');
        return;
      }

      calendarPopup.classList.remove('active');
    });

    // Initialize calendar on load
    renderCalendar();
    if (checkinInputEl.value && checkoutInputEl.value) {
      selectedStart = new Date(checkinInputEl.value);
      selectedEnd = new Date(checkoutInputEl.value);
      renderCalendar();
      updateDateRangeDisplay();
    }

    // Fetch booked dates if homestay is already selected on page load
    const selectedHomestayValue = homestaySelect?.value;
    if (selectedHomestayValue) {
      fetchBookedDates(selectedHomestayValue);
      // Enable form elements if homestay is already selected
      dateRangeInput.disabled = false;
      adultsInputEl.disabled = false;
      childrenInputEl.disabled = false;
      submitBtn.disabled = false;
    }

    // Listen for homestay selection change
    homestaySelect?.addEventListener('change', (e) => {
      selectedStart = null;
      selectedEnd = null;
      dateRangeInput.value = '';
      checkinInputEl.value = '';
      checkoutInputEl.value = '';
      
      // Enable or disable form elements based on homestay selection
      const hasSelection = e.target.value !== '';
      dateRangeInput.disabled = !hasSelection;
      adultsInputEl.disabled = !hasSelection;
      childrenInputEl.disabled = !hasSelection;
      submitBtn.disabled = !hasSelection;
      
      if (hasSelection) {
        fetchBookedDates(e.target.value);
      }
      updateSummary();
    });

    const formatCurrency = (value) => {
      const amount = Number.isFinite(value) ? value : 0;
      return `RM ${amount.toFixed(2)}`;
    };

    const updateSummary = () => {
      if (!bookingForm) {
        return;
      }

      const selectedOption = homestaySelect?.options[homestaySelect.selectedIndex];
      const nightlyRate = selectedOption && selectedOption.dataset.price ? parseFloat(selectedOption.dataset.price) : 0;
      const homestayName = selectedOption && selectedOption.value ? (selectedOption.dataset.name || selectedOption.text).trim() : '--';
      const checkin = checkinInputEl?.value || '';
      const checkout = checkoutInputEl?.value || '';
      const adults = adultsInputEl ? Math.max(1, parseInt(adultsInputEl.value || '1', 10)) : 1;
      const children = childrenInputEl ? Math.max(0, parseInt(childrenInputEl.value || '0', 10)) : 0;

      let nights = 0;
      if (checkin && checkout) {
        const start = new Date(checkin);
        const end = new Date(checkout);
        const diff = (end - start) / (1000 * 60 * 60 * 24);
        nights = diff > 0 ? diff : 0;
      }

      const total = nightlyRate * nights;
      const deposit = total * 0.3;
      const dateFormatter = (value) => value ? new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '';

      if (summaryElements.homestay) summaryElements.homestay.textContent = homestayName;
      if (summaryElements.dates) summaryElements.dates.textContent = (checkin && checkout && nights > 0)
        ? `${dateFormatter(checkin)} to ${dateFormatter(checkout)}`
        : '--';
      if (summaryElements.guests) summaryElements.guests.textContent = `${adults} adults${children > 0 ? ` · ${children} children` : ''}`;
      if (summaryElements.nights) summaryElements.nights.textContent = nights;
      if (summaryElements.total) summaryElements.total.textContent = formatCurrency(total);
      if (summaryElements.deposit) summaryElements.deposit.textContent = formatCurrency(deposit);
    };

    // Event listeners for summary updates
    adultsInputEl?.addEventListener('input', updateSummary);
    childrenInputEl?.addEventListener('input', updateSummary);
    updateSummary();

    const paymentBookingSelect = document.getElementById('paymentBookingID');
    const paymentMethodSelect = document.getElementById('payment_method');
    const addMembershipCheckbox = document.getElementById('add_membership');
    const paymentSummaryCard = document.getElementById('inlinePaymentSummary');
    const inlineDiscountRate = paymentSummaryCard ? parseFloat(paymentSummaryCard.dataset.discountRate || '0') : 0;
    const paymentSummaryFields = {
      homestay: document.getElementById('paymentSummaryHomestay'),
      dates: document.getElementById('paymentSummaryDates'),
      deposit: document.getElementById('paymentSummaryDeposit'),
      discount: document.getElementById('paymentSummaryDiscount'),
      total: document.getElementById('paymentSummaryTotal'),
      membership: document.getElementById('paymentSummaryMembership')
    };

    const formatRange = (startRaw, endRaw) => {
      if (!startRaw || !endRaw) {
        return '--';
      }
      const start = new Date(startRaw);
      const end = new Date(endRaw);
      if (Number.isNaN(start.valueOf()) || Number.isNaN(end.valueOf())) {
        return '--';
      }
      const formatter = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
      return `${formatter.format(start)} - ${formatter.format(end)}`;
    };

    const updatePaymentSummary = () => {
      if (!paymentBookingSelect) {
        return;
      }
      const selectedOption = paymentBookingSelect.options[paymentBookingSelect.selectedIndex];
      if (!selectedOption || !selectedOption.value) {
        paymentSummaryFields.homestay && (paymentSummaryFields.homestay.textContent = '--');
        paymentSummaryFields.dates && (paymentSummaryFields.dates.textContent = '--');
        paymentSummaryFields.deposit && (paymentSummaryFields.deposit.textContent = 'RM 0.00');
        paymentSummaryFields.discount && (paymentSummaryFields.discount.textContent = 'RM 0.00');
        paymentSummaryFields.total && (paymentSummaryFields.total.textContent = 'RM 0.00');
        if (paymentSummaryFields.membership) {
          paymentSummaryFields.membership.textContent = 'RM 0.00';
        }
        return;
      }
      const deposit = parseFloat(selectedOption.dataset.deposit || '0');
      const membershipFee = addMembershipCheckbox && addMembershipCheckbox.checked ? 30.00 : 0.00;
      const discountValue = deposit * (inlineDiscountRate / 100);
      const total = Math.max(0, deposit + membershipFee - discountValue);
      paymentSummaryFields.homestay && (paymentSummaryFields.homestay.textContent = selectedOption.dataset.homestay || '--');
      paymentSummaryFields.dates && (paymentSummaryFields.dates.textContent = formatRange(selectedOption.dataset.checkin, selectedOption.dataset.checkout));
      paymentSummaryFields.deposit && (paymentSummaryFields.deposit.textContent = formatCurrency(deposit));
      paymentSummaryFields.discount && (paymentSummaryFields.discount.textContent = formatCurrency(discountValue));
      paymentSummaryFields.total && (paymentSummaryFields.total.textContent = formatCurrency(total));
      if (paymentSummaryFields.membership) {
        paymentSummaryFields.membership.textContent = formatCurrency(membershipFee);
        const membershipRow = paymentSummaryFields.membership.closest('.summary-item');
        if (membershipRow) {
          membershipRow.style.display = membershipFee > 0 ? '' : 'none';
        }
      }
    };

    paymentBookingSelect?.addEventListener('change', updatePaymentSummary);
    addMembershipCheckbox?.addEventListener('change', updatePaymentSummary);
    updatePaymentSummary();

    const scrollToPaymentSection = () => {
      const paymentSection = document.getElementById('depositPayment');
      if (!paymentSection) {
        return;
      }
      const targetOffset = paymentSection.getBoundingClientRect().top + window.scrollY - 120;
      window.scrollTo({ top: Math.max(0, targetOffset), behavior: 'smooth' });
    };

    const selectPaymentBooking = (bookingId) => {
      if (!paymentBookingSelect) {
        return;
      }
      const exists = Array.from(paymentBookingSelect.options).some(option => option.value === String(bookingId));
      if (!exists) {
        return;
      }
      paymentBookingSelect.value = String(bookingId);
      paymentBookingSelect.dispatchEvent(new Event('change'));
      paymentMethodSelect?.focus();
      scrollToPaymentSection();
    };

    document.querySelectorAll('.pay-trigger').forEach(button => {
      button.addEventListener('click', () => {
        const bookingId = button.getAttribute('data-booking-id');
        if (bookingId) {
          selectPaymentBooking(bookingId);
        }
      });
    });

    if (paymentBookingSelect && paymentBookingSelect.dataset.scrollOnload === '1') {
      const initialBooking = paymentBookingSelect.value;
      if (initialBooking) {
        setTimeout(() => {
          paymentBookingSelect.dispatchEvent(new Event('change'));
          scrollToPaymentSection();
        }, 400);
      }
    }

    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
      hamburger.classList.toggle('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
      });
    });

    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
  </script>

  <!-- WhatsApp Chat Widget -->
  <script src="https://sofowfweidqzxgaojsdq.supabase.co/storage/v1/object/public/widget-scripts/widget.js" data-widget-id="wa_d4nbxppub" async></script>
</body>
</html>
