<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$conn = getDBConnection();
$bookings = [];

if ($conn) {
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
                'bill_status' => $row['BILL_STATUS']
            ];
        }
    }
    oci_free_statement($bookings_stmt);
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
        <li><a href="membership.php" class="nav-link">Membership</a></li>
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

    <section class="content-section">
      <div class="container">
        <div class="booking-header">
          <h2>Your Bookings</h2>
          <a href="homestay.php" class="btn btn-primary">New Booking</a>
        </div>
        <div class="bookings-list">
          <?php if (empty($bookings)): ?>
            <p style="text-align: center; color: #666; padding: 40px;">No bookings found. <a href="homestay.php" style="color: #C5814B;">Book now</a> to get started!</p>
          <?php else: ?>
            <?php foreach ($bookings as $booking): 
              $checkin = date('d M Y', strtotime($booking['checkin_date']));
              $checkout = date('d M Y', strtotime($booking['checkout_date']));
              $image_map = [1 => 'homestay1', 2 => 'homestay2', 3 => 'homestay3', 4 => 'homestay4'];
              $img_folder = $image_map[$booking['homestayID']] ?? 'homestay1';
              $status = strtolower($booking['bill_status'] ?? 'pending');
              $status_class = ($status === 'paid' || $status === 'confirmed') ? 'confirmed' : 'pending';
            ?>
            <div class="booking-card">
              <div class="booking-image">
                <img src="../../images/<?php echo $img_folder; ?>/<?php echo $img_folder; ?>.jpg" alt="<?php echo htmlspecialchars($booking['homestay_name']); ?>">
              </div>
              <div class="booking-info">
                <h3><?php echo htmlspecialchars($booking['homestay_name']); ?></h3>
                <p class="booking-dates"><i class='bx bx-calendar'></i> Check-in: <?php echo $checkin; ?> | Check-out: <?php echo $checkout; ?></p>
                <p class="booking-details">Adults: <?php echo $booking['num_adults']; ?> | Children: <?php echo $booking['num_children']; ?></p>
                <?php if ($booking['deposit_amount']): ?>
                <p class="booking-details">Deposit: RM <?php echo number_format($booking['deposit_amount'], 2); ?></p>
                <?php endif; ?>
                <p class="booking-status <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></p>
              </div>
              <div class="booking-actions">
                <button class="btn btn-secondary">View Details</button>
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
