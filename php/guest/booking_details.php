<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$bookingID = isset($_GET['bookingID']) ? (int) $_GET['bookingID'] : null;
$errors = [];
$booking = null;
$membershipDiscount = 0.0;

if (!$bookingID) {
    $errors[] = 'No booking was selected. Choose a booking from your list to view its details.';
}

$conn = getDBConnection();
if (!$conn) {
    $errors[] = 'Unable to reach the booking system right now. Please try again in a moment.';
}

if ($conn && empty($errors)) {
    $membership_sql = 'SELECT disc_rate FROM MEMBERSHIP WHERE guestID = :guestID';
    $membership_stmt = oci_parse($conn, $membership_sql);
    oci_bind_by_name($membership_stmt, ':guestID', $guestID);
    if (oci_execute($membership_stmt)) {
        $membership_row = oci_fetch_array($membership_stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        if ($membership_row && isset($membership_row['DISC_RATE'])) {
            $membershipDiscount = (float) $membership_row['DISC_RATE'];
        }
    }
    oci_free_statement($membership_stmt);

    $booking_sql = "SELECT b.bookingID, b.checkin_date, b.checkout_date, b.num_adults, b.num_children,
                           b.deposit_amount, b.homestayID, h.homestay_name, h.homestay_address, h.rent_price,
                           b.billNo, bl.bill_status, bl.bill_date, bl.bill_subtotal, bl.disc_amount, bl.tax_amount,
                           bl.total_amount, bl.payment_method, bl.payment_date
                    FROM BOOKING b
                    JOIN HOMESTAY h ON b.homestayID = h.homestayID
                    LEFT JOIN BILL bl ON b.billNo = bl.billNo
                    WHERE b.bookingID = :bookingID AND b.guestID = :guestID";
    $booking_stmt = oci_parse($conn, $booking_sql);
    oci_bind_by_name($booking_stmt, ':bookingID', $bookingID);
    oci_bind_by_name($booking_stmt, ':guestID', $guestID);

    if (oci_execute($booking_stmt)) {
        $row = oci_fetch_array($booking_stmt, OCI_ASSOC + OCI_RETURN_NULLS);
        if ($row) {
            $checkinDate = !empty($row['CHECKIN_DATE']) ? date_create($row['CHECKIN_DATE']) : null;
            $checkoutDate = !empty($row['CHECKOUT_DATE']) ? date_create($row['CHECKOUT_DATE']) : null;
            $nights = ($checkinDate && $checkoutDate) ? (int) $checkinDate->diff($checkoutDate)->format('%a') : 0;
            $nights = max($nights, 1);

            $rentPrice = isset($row['RENT_PRICE']) ? (float) $row['RENT_PRICE'] : 0.0;
            $baseTotal = round($rentPrice * $nights, 2);
            $depositAmount = isset($row['DEPOSIT_AMOUNT']) ? (float) $row['DEPOSIT_AMOUNT'] : 0.0;
            $discountAmount = isset($row['DISC_AMOUNT']) ? (float) $row['DISC_AMOUNT'] : 0.0;
            $paidAmount = isset($row['TOTAL_AMOUNT']) ? (float) $row['TOTAL_AMOUNT'] : 0.0;
            $paymentMethod = isset($row['PAYMENT_METHOD']) ? $row['PAYMENT_METHOD'] : null;
            $billStatusRaw = isset($row['BILL_STATUS']) ? $row['BILL_STATUS'] : null;
            $isPaid = $billStatusRaw && strcasecmp($billStatusRaw, 'PAID') === 0;
            $statusLabel = $isPaid ? 'Deposit Paid' : ($billStatusRaw ? ucwords(strtolower($billStatusRaw)) : 'Deposit Pending');
            $statusClass = $isPaid ? 'status-paid' : 'status-pending';
            $remainingBalance = max(0, $baseTotal - $depositAmount);
            $imageMap = [1 => 'homestay1', 2 => 'homestay2', 3 => 'homestay3', 4 => 'homestay4'];
            $imageFolder = $imageMap[$row['HOMESTAYID']] ?? 'homestay1';

            $booking = [
                'id' => (int) $row['BOOKINGID'],
                'checkin' => $checkinDate ? $checkinDate->format('d M Y') : '--',
                'checkout' => $checkoutDate ? $checkoutDate->format('d M Y') : '--',
                'raw_checkin' => $row['CHECKIN_DATE'] ?? null,
                'raw_checkout' => $row['CHECKOUT_DATE'] ?? null,
                'nights' => $nights,
                'adults' => isset($row['NUM_ADULTS']) ? (int) $row['NUM_ADULTS'] : 0,
                'children' => isset($row['NUM_CHILDREN']) ? (int) $row['NUM_CHILDREN'] : 0,
                'homestay_name' => $row['HOMESTAY_NAME'] ?? 'Homestay',
                'homestay_address' => $row['HOMESTAY_ADDRESS'] ?? 'Address not available',
                'rent_price' => $rentPrice,
                'base_total' => $baseTotal,
                'deposit_amount' => $depositAmount,
                'remaining_balance' => $remainingBalance,
                'bill_no' => isset($row['BILLNO']) ? (int) $row['BILLNO'] : null,
                'bill_status' => $billStatusRaw,
                'bill_status_label' => $statusLabel,
                'bill_status_class' => $statusClass,
                'bill_date' => !empty($row['BILL_DATE']) ? date('d M Y', strtotime($row['BILL_DATE'])) : null,
                'payment_date' => !empty($row['PAYMENT_DATE']) ? date('d M Y', strtotime($row['PAYMENT_DATE'])) : null,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod,
                'tax_amount' => isset($row['TAX_AMOUNT']) ? (float) $row['TAX_AMOUNT'] : 0.0,
                'image_folder' => $imageFolder,
                'is_paid' => $isPaid,
                'deposit_discount_rate' => $membershipDiscount
            ];
        } else {
            $errors[] = 'We could not find that booking or it does not belong to your account.';
        }
    } else {
        $errors[] = 'Unable to load this booking right now. Please try again later.';
    }

    oci_free_statement($booking_stmt);
  }

  if ($conn) {
    closeDBConnection($conn);
  }
  ?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <title>Booking Details - Serena Sanctuary</title>
  <link rel="stylesheet" href="../../css/phpStyle/guestStyle/bookingStyle.css">
  <link rel="stylesheet" href="../../css/phpStyle/guestStyle/bookingDetailsStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../../images/logoNbg.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
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

  <main class="details-main">
    <?php if (!empty($errors)): ?>
      <section class="details-error">
        <div class="container">
          <div class="error-card">
            <h1>We hit a snag</h1>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
            <div class="error-actions">
              <a class="btn btn-secondary" href="booking.php">Back to Bookings</a>
            </div>
          </div>
        </div>
      </section>
    <?php else: ?>
      <?php $heroImage = '../../images/' . htmlspecialchars($booking['image_folder']) . '/' . htmlspecialchars($booking['image_folder']) . '.jpg'; ?>
      <section class="details-hero" style="background-image: linear-gradient(120deg, rgba(0,0,0,0.45), rgba(0,0,0,0.35)), url('<?php echo $heroImage; ?>');">
        <div class="container">
          <div class="hero-grid">
            <div class="hero-text">
              <div class="hero-actions">
                <a class="btn btn-secondary" href="booking.php">Back to Bookings</a>
                <a href="generate_receipt_pdf.php?bookingID=<?php echo htmlspecialchars($booking['id']); ?>" class="btn btn-secondary" aria-label="Download PDF" title="Download PDF">
                  <i class='bx bx-printer'></i>
                </a>
              </div><br>
              <p class="eyebrow">Booking #<?php echo htmlspecialchars($booking['id']); ?></p>
              <h1><?php echo htmlspecialchars($booking['homestay_name']); ?></h1>
              <p class="hero-address"><i class='bx bx-map'></i> <?php echo htmlspecialchars($booking['homestay_address']); ?></p>
              <div class="hero-chips">
                <span class="status-chip <?php echo htmlspecialchars($booking['bill_status_class']); ?>"><?php echo htmlspecialchars($booking['bill_status_label']); ?></span>
                <span class="meta-chip"><i class='bx bx-calendar-star'></i> <?php echo htmlspecialchars($booking['checkin']); ?> - <?php echo htmlspecialchars($booking['checkout']); ?></span>
                <span class="meta-chip"><i class='bx bx-moon'></i> <?php echo htmlspecialchars($booking['nights']); ?> nights</span>
              </div>
            </div>
            <div class="hero-card">
              <div class="hero-card-row">
                <span>Stay</span>
                <strong><?php echo htmlspecialchars($booking['checkin']); ?> - <?php echo htmlspecialchars($booking['checkout']); ?></strong>
              </div>
              <div class="hero-card-row">
                <span>Guests</span>
                <strong><?php echo htmlspecialchars($booking['adults']); ?> adults<?php echo $booking['children'] > 0 ? ' · ' . htmlspecialchars($booking['children']) . ' children' : ''; ?></strong>
              </div>
              <div class="hero-card-row">
                <span>Nightly Rate</span>
                <strong>RM <?php echo number_format($booking['rent_price'], 2); ?></strong>
              </div>
              <div class="hero-card-row highlight">
                <span>Deposit</span>
                <strong>RM <?php echo number_format($booking['deposit_amount'], 2); ?></strong>
              </div>
              <div class="hero-card-row">
                <span>Estimated Total</span>
                <strong>RM <?php echo number_format($booking['base_total'], 2); ?></strong>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="details-section">
        <div class="container">
          <div class="details-grid">
            <div class="data-card">
              <div class="card-header">
                <div>
                  <p class="eyebrow">Stay Overview</p>
                  <h2>Your itinerary</h2>
                </div>
              </div>
              <div class="data-grid">
                <div class="data-item">
                  <p class="label">Check-in</p>
                  <p class="value"><?php echo htmlspecialchars($booking['checkin']); ?></p>
                </div>
                <div class="data-item">
                  <p class="label">Check-out</p>
                  <p class="value"><?php echo htmlspecialchars($booking['checkout']); ?></p>
                </div>
                <div class="data-item">
                  <p class="label">Nights</p>
                  <p class="value"><?php echo htmlspecialchars($booking['nights']); ?></p>
                </div>
                <div class="data-item">
                  <p class="label">Guests</p>
                  <p class="value"><?php echo htmlspecialchars($booking['adults']); ?> adults<?php echo $booking['children'] > 0 ? ' · ' . htmlspecialchars($booking['children']) . ' children' : ''; ?></p>
                </div>
                <div class="data-item">
                  <p class="label">Address</p>
                  <p class="value"><?php echo htmlspecialchars($booking['homestay_address']); ?></p>
                </div>
                <div class="data-item">
                  <p class="label">Booking Reference</p>
                  <p class="value">#<?php echo htmlspecialchars($booking['id']); ?></p>
                </div>
              </div>
            </div>

            <div class="data-card">
              <div class="card-header">
                <div>
                  <p class="eyebrow">Payment</p>
                  <h2>Deposit summary</h2>
                </div>
                <?php if ($booking['bill_no']): ?>
                  <span class="pill">Bill #<?php echo htmlspecialchars($booking['bill_no']); ?></span>
                <?php endif; ?>
              </div>
              <div class="payment-breakdown">
                <div class="breakdown-row">
                  <span>Deposit (30%)</span>
                  <strong>RM <?php echo number_format($booking['deposit_amount'], 2); ?></strong>
                </div>
                <div class="breakdown-row">
                  <span>Membership savings (<?php echo number_format($booking['deposit_discount_rate'], 0); ?>%)</span>
                  <strong>- RM <?php echo number_format($booking['discount_amount'], 2); ?></strong>
                </div>
                <div class="breakdown-row">
                  <span>Tax</span>
                  <strong>RM <?php echo number_format($booking['tax_amount'], 2); ?></strong>
                </div>
                <div class="breakdown-row total">
                  <span>Paid</span>
                  <strong>RM <?php echo number_format($booking['paid_amount'] ?: max(0, $booking['deposit_amount'] - $booking['discount_amount'] + $booking['tax_amount']), 2); ?></strong>
                </div>
                <div class="breakdown-row muted">
                  <span>Estimated balance on arrival</span>
                  <strong>RM <?php echo number_format($booking['remaining_balance'], 2); ?></strong>
                </div>
              </div>
              <div class="payment-meta">
                <div class="meta">
                  <p class="label">Status</p>
                  <p class="value status-inline <?php echo htmlspecialchars($booking['bill_status_class']); ?>"><?php echo htmlspecialchars($booking['bill_status_label']); ?></p>
                </div>
                <div class="meta">
                  <p class="label">Payment method</p>
                  <p class="value"><?php echo $booking['payment_method'] ? htmlspecialchars($booking['payment_method']) : 'Not yet selected'; ?></p>
                </div>
                <div class="meta">
                  <p class="label">Payment date</p>
                  <p class="value"><?php echo $booking['payment_date'] ? htmlspecialchars($booking['payment_date']) : '—'; ?></p>
                </div>
              </div>
            </div>
          </div>

          <div class="details-grid">
            <div class="data-card">
              <div class="card-header">
                <div>
                  <p class="eyebrow">Timeline</p>
                  <h2>What happens next</h2>
                </div>
              </div>
              <ul class="timeline">
                <li class="timeline-step <?php echo $booking['is_paid'] ? 'done' : 'active'; ?>">
                  <div class="dot"></div>
                  <div class="step-content">
                    <p class="step-title">Deposit</p>
                    <p class="step-desc"><?php echo $booking['is_paid'] ? 'We have received your deposit and notified the team.' : 'Pay the 30% deposit to lock this stay.'; ?></p>
                  </div>
                </li>
                <li class="timeline-step <?php echo $booking['is_paid'] ? 'active' : ''; ?>">
                  <div class="dot"></div>
                  <div class="step-content">
                    <p class="step-title">Confirmation</p>
                    <p class="step-desc">We will confirm arrival details and any special requests before your check-in date.</p>
                  </div>
                </li>
                <li class="timeline-step">
                  <div class="dot"></div>
                  <div class="step-content">
                    <p class="step-title">Check-in</p>
                    <p class="step-desc">Settle the remaining balance on arrival and enjoy your stay.</p>
                  </div>
                </li>
              </ul>
            </div>

            <div class="data-card">
              <div class="card-header">
                <div>
                  <p class="eyebrow">Notes</p>
                  <h2>Good to know</h2>
                </div>
              </div>
              <ul class="notes-list">
                <li><i class='bx bx-check'></i> Check-in starts at 3:00 PM; check-out by 12:00 PM.</li>
                <li><i class='bx bx-check'></i> Present your booking reference (#<?php echo htmlspecialchars($booking['id']); ?>) at arrival.</li>
                <li><i class='bx bx-check'></i> If you need changes, reach our support team at info@serenasanctuary.com.</li>
              </ul>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

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
</body>
</html>
