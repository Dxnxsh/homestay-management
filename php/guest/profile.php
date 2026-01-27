<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$guestID = getCurrentGuestID();
$conn = getDBConnection();
$guest = null;
$update_success = false;
$update_error = '';

if ($conn) {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_email = trim($_POST['guest_email'] ?? '');
        $guest_phoneNo = trim($_POST['guest_phoneNo'] ?? '');
        $guest_gender = trim($_POST['guest_gender'] ?? '');
        $guest_address = trim($_POST['guest_address'] ?? '');
        
        if (empty($guest_name) || empty($guest_email)) {
            $update_error = 'Name and email are required.';
        } else {
            // Convert to uppercase (except email)
            $guest_name = strtoupper($guest_name);
            $guest_email = strtolower($guest_email); // Ensure email is lowercase
            $guest_gender = strtoupper($guest_gender);
            $guest_address = strtoupper($guest_address);
            
            $update_sql = "UPDATE GUEST 
                          SET guest_name = :guest_name, 
                              guest_email = :guest_email,
                              guest_phoneNo = :guest_phoneNo,
                              guest_gender = :guest_gender,
                              guest_address = :guest_address
                          WHERE guestID = :guestID";
            $update_stmt = oci_parse($conn, $update_sql);
            
            oci_bind_by_name($update_stmt, ':guest_name', $guest_name);
            oci_bind_by_name($update_stmt, ':guest_email', $guest_email);
            oci_bind_by_name($update_stmt, ':guest_phoneNo', $guest_phoneNo);
            oci_bind_by_name($update_stmt, ':guest_gender', $guest_gender);
            oci_bind_by_name($update_stmt, ':guest_address', $guest_address);
            oci_bind_by_name($update_stmt, ':guestID', $guestID);
            
            if (oci_execute($update_stmt)) {
                oci_commit($conn);
                $update_success = true;
                // Update session
                $_SESSION['guest_name'] = $guest_name;
                $_SESSION['guest_email'] = $guest_email;
            } else {
                $error = oci_error($update_stmt);
                $update_error = 'Update failed: ' . $error['message'];
            }
            oci_free_statement($update_stmt);
        }
    }
    
    // Get current guest data
    $sql = "SELECT guest_name, guest_email, guest_phoneNo, guest_gender, guest_address, guest_type 
            FROM GUEST 
            WHERE guestID = :guestID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':guestID', $guestID);
    
    if (oci_execute($stmt)) {
        $row = oci_fetch_array($stmt, OCI_ASSOC);
        if ($row) {
            $guest = [
                'name' => $row['GUEST_NAME'] ?? '',
                'email' => $row['GUEST_EMAIL'] ?? '',
                'phoneNo' => $row['GUEST_PHONENO'] ?? '',
                'gender' => $row['GUEST_GENDER'] ?? '',
                'address' => $row['GUEST_ADDRESS'] ?? '',
                'type' => $row['GUEST_TYPE'] ?? 'Regular'
            ];
        }
    }
    oci_free_statement($stmt);
    closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Profile - Serena Sanctuary</title>
    <link rel="stylesheet" href="../../css/phpStyle/guestStyle/profileStyle.css">
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
        <li><a href="booking.php" class="nav-link">Booking</a></li>
        <li><a href="homestay.php" class="nav-link">Homestay</a></li>
        <li><a href="profile.php" class="nav-link active">Profile</a></li>
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
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Manage your personal information</p>
      </div>
    </section>

    <section class="content-section">
      <div class="container">
        <?php if ($update_success): ?>
          <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px; background: #d4edda; color: #155724; border-radius: 5px;">
            Profile updated successfully!
          </div>
        <?php endif; ?>
        <?php if ($update_error): ?>
          <div class="alert alert-error" style="margin-bottom: 20px; padding: 12px; background: #f8d7da; color: #721c24; border-radius: 5px;">
            <?php echo htmlspecialchars($update_error); ?>
          </div>
        <?php endif; ?>
        
        <?php if ($guest): ?>
        <div class="profile-container">
          <div class="profile-header">
            <div class="profile-info">
              <h2><?php echo htmlspecialchars($guest['name']); ?></h2>
              <p class="profile-email"><?php echo htmlspecialchars($guest['email']); ?></p>
              <?php if (!empty($guest['phoneNo'])): ?>
              <p class="profile-phone"><?php echo htmlspecialchars($guest['phoneNo']); ?></p>
              <?php endif; ?>
            </div>
          </div>
          <div class="profile-details-section">
            <h3>Personal Information</h3>
            <form method="POST" action="profile.php" class="profile-form">
              <div class="form-row">
                <div class="form-group">
                  <label>Full Name</label>
                  <input type="text" name="guest_name" value="<?php echo htmlspecialchars($guest['name']); ?>" class="form-input" required>
                </div>
                <div class="form-group">
                  <label>Email</label>
                  <input type="email" name="guest_email" value="<?php echo htmlspecialchars($guest['email']); ?>" class="form-input" required>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Phone Number</label>
                  <input type="tel" name="guest_phoneNo" value="<?php echo htmlspecialchars($guest['phoneNo']); ?>" class="form-input">
                </div>
                <div class="form-group">
                  <label>Gender</label>
                  <select name="guest_gender" class="form-input">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo $guest['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $guest['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Guest Type</label>
                  <select name="guest_type" class="form-input" disabled>
                    <option value="Regular" <?php echo $guest['type'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                    <option value="Membership" <?php echo $guest['type'] === 'Membership' ? 'selected' : ''; ?>>Membership</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label>Address</label>
                <textarea name="guest_address" class="form-input" rows="3"><?php echo htmlspecialchars($guest['address']); ?></textarea>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.reload()">Cancel</button>
              </div>
            </form>
          </div>
        </div>
        <?php else: ?>
          <p style="text-align: center; color: #666; padding: 40px;">Unable to load profile data.</p>
        <?php endif; ?>
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
