<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$conn = getDBConnection();

// Initialize variables
$guest_data = null;
$profile_incomplete = isset($_SESSION['profile_incomplete']) && $_SESSION['profile_incomplete'];
$pending_bookings_count = 0;
$completed_bookings_count = 0;
$membership_tier = 'None';
$discount_rate = 0;
$pending_bookings = [];
$has_membership = false;

// Update membership tier before fetching data
require_once '../config/membership_helper.php';
if ($conn) {
  updateMembershipTier($conn, $guestID);
}

if ($conn) {
  // Get guest data for profile completion modal
  if ($profile_incomplete) {
    $sql = "SELECT guest_name, guest_email, guest_phoneNo, guest_gender, guest_address 
                FROM GUEST 
                WHERE guestID = :guestID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':guestID', $guestID);
    if (oci_execute($stmt)) {
      $row = oci_fetch_array($stmt, OCI_ASSOC);
      if ($row) {
        $guest_data = [
          'name' => $row['GUEST_NAME'] ?? '',
          'email' => $row['GUEST_EMAIL'] ?? '',
          'phoneNo' => $row['GUEST_PHONENO'] ?? '',
          'gender' => $row['GUEST_GENDER'] ?? '',
          'address' => $row['GUEST_ADDRESS'] ?? ''
        ];
      }
    }
    oci_free_statement($stmt);
  }

  // Get booking counts
  $pending_sql = "SELECT COUNT(*) as count FROM BOOKING b
                    LEFT JOIN BILL bl ON b.billNo = bl.billNo
                    WHERE b.guestID = :guestID
                      AND b.checkout_date >= SYSDATE
                      AND (b.billNo IS NULL OR UPPER(bl.bill_status) <> 'PAID')";
  $pending_stmt = oci_parse($conn, $pending_sql);
  oci_bind_by_name($pending_stmt, ':guestID', $guestID);
  if (oci_execute($pending_stmt)) {
    $row = oci_fetch_array($pending_stmt, OCI_ASSOC);
    $pending_bookings_count = $row['COUNT'] ?? 0;
  }
  oci_free_statement($pending_stmt);

  // Completed = past checkout date and fully paid bill
  // Completed = any booking with a paid bill (date-agnostic per request)
  $completed_sql = "SELECT COUNT(*) as count
                      FROM BOOKING b
                      JOIN BILL bl ON b.billNo = bl.billNo
                      WHERE b.guestID = :guestID
                        AND UPPER(bl.bill_status) = 'PAID'";
  $completed_stmt = oci_parse($conn, $completed_sql);
  oci_bind_by_name($completed_stmt, ':guestID', $guestID);
  if (oci_execute($completed_stmt)) {
    $row = oci_fetch_array($completed_stmt, OCI_ASSOC);
    $completed_bookings_count = $row['COUNT'] ?? 0;
  }
  oci_free_statement($completed_stmt);

  // Get pending bookings with homestay details
  $bookings_sql = "SELECT b.bookingID, b.checkin_date, b.checkout_date, h.homestay_name, h.homestayID, bl.bill_status
             FROM BOOKING b
             JOIN HOMESTAY h ON b.homestayID = h.homestayID
             LEFT JOIN BILL bl ON b.billNo = bl.billNo
             WHERE b.guestID = :guestID
               AND b.checkout_date >= SYSDATE
               AND (b.billNo IS NULL OR UPPER(bl.bill_status) <> 'PAID')
             ORDER BY b.checkin_date ASC";
  $bookings_stmt = oci_parse($conn, $bookings_sql);
  oci_bind_by_name($bookings_stmt, ':guestID', $guestID);
  if (oci_execute($bookings_stmt)) {
    while ($row = oci_fetch_array($bookings_stmt, OCI_ASSOC)) {
      $status = strtolower($row['BILL_STATUS'] ?? 'pending');
      $status_class = ($status === 'paid' || $status === 'confirmed') ? 'confirmed' : 'pending';
      $pending_bookings[] = [
        'bookingID' => $row['BOOKINGID'],
        'homestay_name' => $row['HOMESTAY_NAME'],
        'homestayID' => $row['HOMESTAYID'],
        'checkin_date' => $row['CHECKIN_DATE'],
        'checkout_date' => $row['CHECKOUT_DATE'],
        'status' => $status,
        'status_class' => $status_class
      ];
    }
  }
  oci_free_statement($bookings_stmt);


  // Get membership info
  $membership_sql = "SELECT m.disc_rate 
                       FROM MEMBERSHIP m 
                       WHERE m.guestID = :guestID";
  $membership_stmt = oci_parse($conn, $membership_sql);
  oci_bind_by_name($membership_stmt, ':guestID', $guestID);
  if (oci_execute($membership_stmt)) {
    $row = oci_fetch_array($membership_stmt, OCI_ASSOC);
    if ($row) {
      $has_membership = true;
      $discount_rate = $row['DISC_RATE'] ?? 0;
      // Determine tier based on discount rate
      if ($discount_rate >= 30) {
        $membership_tier = 'Gold';
      } elseif ($discount_rate >= 20) {
        $membership_tier = 'Silver';
      } else {
        $membership_tier = 'Bronze';
      }
    }
  }
  oci_free_statement($membership_stmt);

  closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Home - Serena Sanctuary</title>
  <link rel="stylesheet" href="../../css/phpStyle/guestStyle/homeStyle.css">
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
        <li><a href="home.php" class="nav-link active">Home</a></li>
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
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">Welcome Back!</h1>
          <p class="hero-subtitle">Discover your next perfect homestay experience with Serena Sanctuary.</p>
        </div>
        <div class="quick-actions">
          <a href="booking.php" class="action-card">
            <div class="action-icon">
              <i class='bxr  bx-calendar-check'></i>
            </div>
            <h3>Book Now</h3>
            <p>Reserve your stay</p>
          </a>
          <a href="homestay.php" class="action-card">
            <div class="action-icon">
              <i class='bxr  bx-home-heart'></i>
            </div>
            <h3>Browse Homestays</h3>
            <p>Explore our properties</p>
          </a>
          <a href="membership.php" class="action-card">
            <div class="action-icon">
              <i class='bxr  bx-crown'></i>
            </div>
            <h3>Membership</h3>
            <p>View benefits</p>
          </a>
        </div>
        <div class="stats-section">
          <div class="stat-item">
            <div class="stat-icon">
              <i class='bxr  bx-bookmark-heart'></i>
            </div>
            <div class="stat-content">
              <h4>Pending Booking</h4>
              <p class="stat-value"><?php echo $pending_bookings_count; ?></p>
            </div>
          </div>
          <div class="stat-item">
            <div class="stat-icon">
              <i class='bxr  bx-bookmark-star'></i>
            </div>
            <div class="stat-content">
              <h4>Completed Booking</h4>
              <p class="stat-value"><?php echo $completed_bookings_count; ?></p>
            </div>
          </div>
          <div class="stat-item">
            <div class="stat-icon">
              <i class='bx bx-crown'></i>
            </div>
            <div class="stat-content">
              <h4>Membership</h4>
              <p class="stat-value"><?php echo $membership_tier; ?></p>
            </div>
          </div>
          <div class="stat-item">
            <div class="stat-icon">
              <i class='bx bx-discount'></i>
            </div>
            <div class="stat-content">
              <h4>Discount Rate</h4>
              <p class="stat-value"><?php echo $has_membership ? number_format($discount_rate, 0) . '%' : '0%'; ?></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pending Booking Section -->
    <section class="pending-booking-section">
      <div class="container">
        <h2 class="section-title">Pending Bookings</h2>
        <p class="section-description">Track your upcoming reservations and manage your stay details. View booking
          information, check-in dates, and booking status all in one place.</p>
        <div class="bookings-list">
          <?php if (empty($pending_bookings)): ?>
            <p style="text-align: center; color: #666; padding: 40px;">No pending bookings found.</p>
          <?php else: ?>
            <?php foreach ($pending_bookings as $booking):
              $checkin = date('d M Y', strtotime($booking['checkin_date']));
              $checkout = date('d M Y', strtotime($booking['checkout_date']));
              $homestayID = trim($booking['homestayID']);
              $id_map = [
                'HM101' => 'homestay1',
                'HM102' => 'homestay2',
                'HM103' => 'homestay3',
                'HM104' => 'homestay4'
              ];
              $target_folder = $id_map[$homestayID] ?? 'homestay1';
              ?>
              <div class="booking-card">
                <div class="booking-image">
                  <img src="../../images/<?php echo $target_folder; ?>/<?php echo $target_folder; ?>.jpg"
                    onerror="this.onerror=null; this.src='../../images/homestay1/homestay1.jpg';"
                    alt="<?php echo htmlspecialchars($booking['homestay_name']); ?>">
                </div>
                <div class="booking-info">
                  <h3><?php echo htmlspecialchars($booking['homestay_name']); ?></h3>
                  <p class="booking-dates"><i class='bx bx-calendar'></i> Check-in: <?php echo $checkin; ?> | Check-out:
                    <?php echo $checkout; ?>
                  </p>
                  <p class="booking-status <?php echo htmlspecialchars($booking['status_class']); ?>">
                    <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                  </p>
                </div>
                <div class="booking-actions">
                  <a href="booking.php" class="btn btn-secondary">View Details</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Homestay Section -->
    <section class="homestay-section">
      <div class="container">
        <h2 class="section-title">Available Homestays</h2>
        <div class="homestays-grid">
          <div class="homestay-card">
            <div class="homestay-image">
              <img src="../../images/homestay1/homestay1.jpg" alt="Serene Villa">
            </div>
            <div class="homestay-details">
              <h3>The Grand Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 11 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 8 Bathrooms</span>
                <span><i class='bx bx-group'></i> 16-30 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 500</span>
                <span class="period">/ night</span>
              </div>
              <a href="homestay.php" class="btn btn-primary">View Details</a>
            </div>
          </div>
          <div class="homestay-card">
            <div class="homestay-image">
              <img src="../../images/homestay2/homestay2.jpg" alt="Twin Haven">
            </div>
            <div class="homestay-details">
              <h3>Twin Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 4 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 4 Bathrooms</span>
                <span><i class='bx bx-group'></i> 10-20 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 450</span>
                <span class="period">/ night</span>
              </div>
              <a href="homestay.php" class="btn btn-primary">View Details</a>
            </div>
          </div>
          <div class="homestay-card">
            <div class="homestay-image">
              <img src="../../images/homestay3/homestay3.jpg" alt="The Riverside Retreat">
            </div>
            <div class="homestay-details">
              <h3>The Riverside Retreat</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 8 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 6 Bathrooms</span>
                <span><i class='bx bx-group'></i> 20-36 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 400</span>
                <span class="period">/ night</span>
              </div>
              <a href="homestay.php" class="btn btn-primary">View Details</a>
            </div>
          </div>
          <div class="homestay-card">
            <div class="homestay-image">
              <img src="../../images/homestay4/homestay4.jpg" alt="Hilltop Haven">
            </div>
            <div class="homestay-details">
              <h3>Hilltop Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 5 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 5 Bathrooms</span>
                <span><i class='bx bx-group'></i> Up to 10 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 550</span>
                <span class="period">/ night</span>
              </div>
              <a href="homestay.php" class="btn btn-primary">View Details</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Membership & Discount Section -->
    <section class="membership-discount-section">
      <div class="container">
        <h2 class="section-title white">Membership Benefits</h2>
        <p class="section-description white">Unlock exclusive rewards with our membership program! The more you book and
          stay with us, the higher your discount rate becomes. Start earning bigger savings today with every reservation
          you make.</p>
        <div class="membership-stats">
          <div class="membership-stat-card">
            <div class="stat-icon">
              <i class='bx bx-crown'></i>
            </div>
            <div class="stat-content">
              <h3>Membership</h3>
              <p class="stat-value"><?php echo $membership_tier; ?></p>
            </div>
          </div>
          <div class="membership-stat-card">
            <div class="stat-icon">
              <i class='bx bx-discount'></i>
            </div>
            <div class="stat-content">
              <h3>Discount Rate</h3>
              <p class="stat-value"><?php echo $has_membership ? number_format($discount_rate, 0) . '%' : '0%'; ?></p>
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

  <!-- Profile Completion Modal -->
  <?php if ($profile_incomplete): ?>
    <div id="profileModal" class="profile-modal-overlay">
      <div class="profile-modal">
        <div class="profile-modal-header">
          <h2>Complete Your Profile</h2>
          <p>Please provide your details to continue using our services</p>
        </div>
        <form id="profileForm" class="profile-modal-form">
          <div class="form-group">
            <label for="modal_phoneNo">Phone Number <span class="required">*</span></label>
            <input type="tel" id="modal_phoneNo" name="guest_phoneNo" class="form-input"
              placeholder="e.g., +60 12-345 6789" value="<?php echo htmlspecialchars($guest_data['phoneNo'] ?? ''); ?>"
              required>
          </div>
          <div class="form-group">
            <label for="modal_gender">Gender <span class="required">*</span></label>
            <select id="modal_gender" name="guest_gender" class="form-input" required>
              <option value="">Select Gender</option>
              <option value="Male" <?php echo ($guest_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo ($guest_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female
              </option>
            </select>
          </div>
          <div class="form-group">
            <label for="modal_address">Address <span class="required">*</span></label>
            <textarea id="modal_address" name="guest_address" class="form-input" rows="3"
              placeholder="Enter your full address"
              required><?php echo htmlspecialchars($guest_data['address'] ?? ''); ?></textarea>
          </div>
          <div id="profileError" class="alert alert-error" style="display: none;"></div>
          <div class="profile-modal-actions">
            <button type="submit" class="btn btn-primary">Save & Continue</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <script>
    // Mobile Menu Toggle
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
      hamburger.classList.toggle('active');
    });

    // Close menu when clicking on a link
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
      });
    });

    // Navbar background on scroll
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // Scroll-triggered animations using Intersection Observer
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate');
          observer.unobserve(entry.target); // Stop observing once animated
        }
      });
    }, observerOptions);

    // Observe all elements that need scroll animations
    const animateElements = document.querySelectorAll('.section-title, .section-description, .booking-card, .homestay-card, .membership-stat-card');
    animateElements.forEach(element => {
      observer.observe(element);
    });

    // Profile Completion Modal
    <?php if ($profile_incomplete): ?>
      const profileModal = document.getElementById('profileModal');
      const profileForm = document.getElementById('profileForm');
      const profileError = document.getElementById('profileError');

      // Show modal on page load
      if (profileModal) {
        profileModal.style.display = 'flex';
      }

      // Handle form submission
      profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(profileForm);
        profileError.style.display = 'none';

        try {
          const response = await fetch('update_profile.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            // Close modal and reload page
            profileModal.style.display = 'none';
            window.location.reload();
          } else {
            profileError.textContent = result.message || 'An error occurred. Please try again.';
            profileError.style.display = 'block';
          }
        } catch (error) {
          profileError.textContent = 'Network error. Please try again.';
          profileError.style.display = 'block';
        }
      });
    <?php endif; ?>
  </script>

  <!-- WhatsApp Chat Widget -->
  <script src="https://sofowfweidqzxgaojsdq.supabase.co/storage/v1/object/public/widget-scripts/widget.js"
    data-widget-id="wa_d4nbxppub" async></script>
</body>

</html>