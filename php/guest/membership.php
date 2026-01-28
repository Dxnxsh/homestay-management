<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$conn = getDBConnection();
$has_membership = false;
$discount_rate = 0;
$booking_count = 0;
$guest_type = 'Regular';
$show_buy_button = false;
$success_message = '';
$error_message = '';
$show_success_modal = false;

// Check for success/error messages
if (isset($_GET['purchased']) && $_GET['purchased'] == '1') {
  if (isset($_SESSION['membership_purchased'])) {
    $success_message = 'Payment successful! Membership purchased successfully! Welcome to Bronze tier.';
    unset($_SESSION['membership_purchased']);
    $show_success_modal = true;
  }
}

if (isset($_GET['error']) && $_GET['error'] == '1') {
  if (isset($_SESSION['membership_error'])) {
    $error_message = $_SESSION['membership_error'];
    unset($_SESSION['membership_error']);
  }
}

if ($conn) {
  // Get guest_type from GUEST table
  $guest_type_sql = "SELECT guest_type FROM GUEST WHERE guestID = :guestID";
  $guest_type_stmt = oci_parse($conn, $guest_type_sql);
  oci_bind_by_name($guest_type_stmt, ':guestID', $guestID);
  if (oci_execute($guest_type_stmt)) {
    $row = oci_fetch_array($guest_type_stmt, OCI_ASSOC);
    if ($row) {
      $guest_type = strtoupper(trim($row['GUEST_TYPE'] ?? 'Regular'));
    }
  }
  oci_free_statement($guest_type_stmt);

  // Get membership info
  $membership_sql = "SELECT disc_rate 
                       FROM MEMBERSHIP 
                       WHERE guestID = :guestID";
  $membership_stmt = oci_parse($conn, $membership_sql);
  oci_bind_by_name($membership_stmt, ':guestID', $guestID);
  if (oci_execute($membership_stmt)) {
    $row = oci_fetch_array($membership_stmt, OCI_ASSOC);
    if ($row) {
      $has_membership = true;
      $discount_rate = $row['DISC_RATE'] ?? 0;
    }
  }
  oci_free_statement($membership_stmt);

  // Count all paid bookings (date-agnostic per request)
  $booking_sql = "SELECT COUNT(*) as count
            FROM BOOKING b
            JOIN BILL bl ON b.billNo = bl.billNo
            WHERE b.guestID = :guestID
              AND UPPER(bl.bill_status) = 'PAID'";
  $booking_stmt = oci_parse($conn, $booking_sql);
  oci_bind_by_name($booking_stmt, ':guestID', $guestID);
  if (oci_execute($booking_stmt)) {
    $row = oci_fetch_array($booking_stmt, OCI_ASSOC);
    $booking_count = $row['COUNT'] ?? 0;
  }
  oci_free_statement($booking_stmt);

  // Show buy button only if guest_type is "REGULAR" (case-insensitive check)
  $show_buy_button = (strtoupper($guest_type) === 'REGULAR' && !$has_membership);

  closeDBConnection($conn);
}

// Determine tier based on discount rate or booking count
$membership_tier = 'None';
if ($has_membership) {
  if ($discount_rate >= 30) {
    $membership_tier = 'Gold';
  } elseif ($discount_rate >= 20) {
    $membership_tier = 'Silver';
  } else {
    $membership_tier = 'Bronze';
  }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Membership - Serena Sanctuary</title>
  <link rel="stylesheet" href="../../css/phpStyle/guestStyle/membershipStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
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
        <li><a href="booking.php" class="nav-link">Booking</a></li>
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
        <h1 class="page-title">Membership</h1>
        <p class="page-subtitle">Unlock exclusive benefits and rewards</p>
      </div>
    </section>

    <section class="content-section">
      <div class="container">
        <?php if ($success_message): ?>
          <div class="alert alert-success-membership">
            <i class='bx bx-check-circle'></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
          </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
          <div class="alert alert-error-membership">
            <i class='bx bx-error-circle'></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
          </div>
        <?php endif; ?>

        <div class="membership-status">
          <div class="status-card" id="membershipCard">
            <div class="status-icon">
              <i class='bxr  bx-crown'></i>
            </div>
            <h2 id="membershipType">Bronze Member</h2>
            <p class="membership-discount" id="membershipDiscount">10% Discount</p>
            <div class="membership-progress" id="membershipProgress">
              <p><strong>0</strong> bookings completed</p>
              <p><strong>5</strong> more bookings to reach Silver tier</p>
            </div>
            <p class="membership-status-text">Your current membership tier</p>
          </div>
          <!-- No Membership Card (hidden by default) -->
          <div class="status-card no-membership" id="noMembershipCard" style="display: none;">
            <div class="status-icon">
              <i class='bxr  bx-user'></i>
            </div>
            <h2>Not a Member</h2>
            <p class="membership-status-text">Join our membership program to unlock exclusive benefits</p>
            <div id="buyMembershipSection" style="display: none;">
              <p style="font-size: 0.85rem; margin-top: 10px; opacity: 0.8;">
                Start at Bronze tier (10% discount) and upgrade automatically with each booking!
              </p>
            </div>
          </div>
        </div>
        <div class="membership-tiers">
          <h2 class="section-title">Membership Tiers & Progression</h2>
          <p class="section-description"
            style="text-align: center; color: #666; margin-bottom: 30px; max-width: 800px; margin-left: auto; margin-right: auto;">
            Purchase membership once and automatically upgrade as you complete more bookings. Start at Bronze tier and
            progress to higher tiers with each booking!
          </p>
          <div class="tiers-grid">
            <!-- Bronze Tier -->
            <div class="tier-card bronze">
              <div class="tier-header">
                <div class="tier-icon">
                  <i class='bxr  bx-medal'></i>
                </div>
                <h3>Bronze</h3>
                <div class="tier-discount">10% Discount</div>
                <div class="tier-requirement">Default Tier</div>
              </div>
              <div class="tier-benefits">
                <ul>
                  <li><i class='bx bx-check'></i> 10% discount on all bookings</li>
                  <li><i class='bx bx-check'></i> Priority customer support</li>
                  <li><i class='bx bx-check'></i> Access to exclusive deals</li>
                  <li><i class='bx bx-check'></i> Email notifications</li>
                </ul>
              </div>
              <div class="tier-progression">
                <p class="progression-text">Upgrade to Silver after <strong>5 bookings</strong></p>
              </div>
            </div>

            <!-- Silver Tier -->
            <div class="tier-card silver">
              <div class="tier-header">
                <div class="tier-icon">
                  <i class='bxr  bx-medal'></i>
                </div>
                <h3>Silver</h3>
                <div class="tier-discount">20% Discount</div>
                <div class="tier-requirement">5+ Bookings</div>
              </div>
              <div class="tier-benefits">
                <ul>
                  <li><i class='bx bx-check'></i> 20% discount on all bookings</li>
                  <li><i class='bx bx-check'></i> Priority customer support</li>
                  <li><i class='bx bx-check'></i> Early access to new homestays</li>
                  <li><i class='bx bx-check'></i> Exclusive member-only deals</li>
                  <li><i class='bx bx-check'></i> Free cancellation (up to 48h)</li>
                </ul>
              </div>
              <div class="tier-progression">
                <p class="progression-text">Upgrade to Gold after <strong>10 bookings</strong></p>
              </div>
            </div>

            <!-- Gold Tier -->
            <div class="tier-card gold">
              <div class="tier-header">
                <div class="tier-icon">
                  <i class='bxr  bx-crown'></i>
                </div>
                <h3>Gold</h3>
                <div class="tier-discount">30% Discount</div>
                <div class="tier-requirement">10+ Bookings</div>
              </div>
              <div class="tier-benefits">
                <ul>
                  <li><i class='bx bx-check'></i> 30% discount on all bookings</li>
                  <li><i class='bx bx-check'></i> 24/7 dedicated support</li>
                  <li><i class='bx bx-check'></i> First access to premium homestays</li>
                  <li><i class='bx bx-check'></i> Exclusive VIP events</li>
                  <li><i class='bx bx-check'></i> Free cancellation (up to 72h)</li>
                  <li><i class='bx bx-check'></i> Complimentary welcome amenities</li>
                </ul>
              </div>
              <div class="tier-progression">
                <p class="progression-text">Highest tier achieved!</p>
              </div>
            </div>
          </div>
        </div>

        <div class="membership-benefits">
          <h2 class="section-title">General Membership Benefits</h2>
          <div class="benefits-grid">
            <div class="benefit-item">
              <div class="benefit-icon">
                <i class='bxr  bx-star'></i>
              </div>
              <h3>Priority Booking</h3>
              <p>Get early access to new homestays</p>
            </div>
            <div class="benefit-item">
              <div class="benefit-icon">
                <i class='bxr  bx-discount'></i>
              </div>
              <h3>Special Discounts</h3>
              <p>Enjoy exclusive member rates</p>
            </div>
            <div class="benefit-item">
              <div class="benefit-icon">
                <i class='bxr  bx-trophy'></i>
              </div>
              <h3>Tier Progression</h3>
              <p>Automatically upgrade your tier with each booking. Start at Bronze and progress to Gold!</p>
            </div>
            <div class="benefit-item">
              <div class="benefit-icon">
                <i class='bxr  bx-timer'></i>
              </div>
              <h3>24/7 Support</h3>
              <p>Dedicated member support</p>
            </div>
          </div>
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

    // Membership Type Detection based on booking count
    function updateMembershipDisplay(hasMembership, bookingCount, showBuyButton) {
      const membershipCard = document.getElementById('membershipCard');
      const noMembershipCard = document.getElementById('noMembershipCard');
      const membershipType = document.getElementById('membershipType');
      const membershipDiscount = document.getElementById('membershipDiscount');
      const membershipProgress = document.getElementById('membershipProgress');
      const buyMembershipSection = document.getElementById('buyMembershipSection');

      // Remove existing tier classes
      membershipCard.classList.remove('bronze', 'silver', 'gold');

      if (!hasMembership) {
        // No membership
        membershipCard.style.display = 'none';
        noMembershipCard.style.display = 'block';
        // Show buy button only if guest_type is Regular
        if (buyMembershipSection) {
          buyMembershipSection.style.display = showBuyButton ? 'block' : 'none';
        }
      } else {
        // Has membership - determine tier based on booking count
        membershipCard.style.display = 'block';
        noMembershipCard.style.display = 'none';
        if (buyMembershipSection) {
          buyMembershipSection.style.display = 'none';
        }

        bookingCount = bookingCount || 0;

        if (bookingCount >= 10) {
          // Gold: 10+ bookings
          membershipType.textContent = 'Gold Member';
          membershipDiscount.textContent = '30% Discount';
          membershipCard.classList.add('gold');
          if (membershipProgress) {
            membershipProgress.innerHTML = `<p><strong>${bookingCount}</strong> bookings completed</p><p>You've reached the highest tier!</p>`;
          }
        } else if (bookingCount >= 5) {
          // Silver: 5-9 bookings
          membershipType.textContent = 'Silver Member';
          membershipDiscount.textContent = '20% Discount';
          membershipCard.classList.add('silver');
          if (membershipProgress) {
            const remaining = 10 - bookingCount;
            membershipProgress.innerHTML = `<p><strong>${bookingCount}</strong> bookings completed</p><p><strong>${remaining}</strong> more bookings to reach Gold tier</p>`;
          }
        } else {
          // Bronze: 0-4 bookings (default)
          membershipType.textContent = 'Bronze Member';
          membershipDiscount.textContent = '10% Discount';
          membershipCard.classList.add('bronze');
          if (membershipProgress) {
            const remaining = 5 - bookingCount;
            membershipProgress.innerHTML = `<p><strong>${bookingCount}</strong> bookings completed</p><p><strong>${remaining}</strong> more bookings to reach Silver tier</p>`;
          }
        }
      }
    }

    // Set membership status from database
    updateMembershipDisplay(<?php echo $has_membership ? 'true' : 'false'; ?>, <?php echo $booking_count; ?>, <?php echo $show_buy_button ? 'true' : 'false'; ?>);
  </script>

  <!-- Success Modal Popup -->
  <div id="successModal" class="success-modal-overlay <?php echo $show_success_modal ? 'show' : ''; ?>">
    <div class="success-modal">
      <div class="success-modal-content">
        <div class="success-modal-icon">
          <i class='bx bx-check'></i>
        </div>
        <h2 class="success-modal-title">Payment Successful!</h2>
        <p class="success-modal-message">
          Your membership has been purchased successfully! Welcome to Bronze tier. You can now enjoy exclusive benefits
          and discounts.
        </p>
        <button onclick="closeSuccessModal()" class="success-modal-button">
          <i class='bx bx-check-circle'></i>
          Continue
        </button>
      </div>
    </div>
  </div>

  <script>
    // Success modal handling
    <?php if ($show_success_modal): ?>
      function closeSuccessModal() {
        document.getElementById('successModal').classList.remove('show');
        // Refresh page to show updated membership status
        setTimeout(function () {
          window.location.reload();
        }, 300);
      }

      // Auto-close after 4 seconds
      setTimeout(function () {
        closeSuccessModal();
      }, 4000);

      // Close modal on overlay click
      document.getElementById('successModal').addEventListener('click', function (e) {
        if (e.target === this) {
          closeSuccessModal();
        }
      });
    <?php endif; ?>
  </script>

  <!-- WhatsApp Chat Widget -->
  <script src="https://sofowfweidqzxgaojsdq.supabase.co/storage/v1/object/public/widget-scripts/widget.js"
    data-widget-id="wa_d4nbxppub" async></script>
</body>

</html>