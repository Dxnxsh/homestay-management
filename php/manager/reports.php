<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

function fetchOne($conn, $sql) {
  $stmt = oci_parse($conn, $sql);
  oci_execute($stmt);
  $row = oci_fetch_array($stmt, OCI_ASSOC);
  oci_free_statement($stmt);
  return $row ?: [];
}

$bookingStats = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM BOOKING");
$guestStats = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM GUEST");
$homestayStats = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM HOMESTAY");
$billStats = fetchOne($conn, "SELECT COUNT(*) AS CNT, NVL(SUM(total_amount),0) AS TOTAL_AMT, NVL(SUM(CASE WHEN bill_status = 'Paid' THEN total_amount ELSE 0 END),0) AS PAID_AMT FROM BILL");

$recentBills = [];
$billSql = "SELECT billNo, bill_status, total_amount, TO_CHAR(bill_date, 'YYYY-MM-DD') AS bill_date FROM BILL ORDER BY billNo DESC FETCH NEXT 5 ROWS ONLY";
$billStmt = oci_parse($conn, $billSql);
oci_execute($billStmt);
while ($row = oci_fetch_array($billStmt, OCI_ASSOC)) {
  $recentBills[] = $row;
}
oci_free_statement($billStmt);

$recentBookings = [];
$bookingSql = "SELECT bookingID, homestayID, guestID, TO_CHAR(checkin_date, 'YYYY-MM-DD') AS checkin_date, TO_CHAR(checkout_date, 'YYYY-MM-DD') AS checkout_date FROM BOOKING ORDER BY bookingID DESC FETCH NEXT 5 ROWS ONLY";
$bookingStmt = oci_parse($conn, $bookingSql);
oci_execute($bookingStmt);
while ($row = oci_fetch_array($bookingStmt, OCI_ASSOC)) {
  $recentBookings[] = $row;
}
oci_free_statement($bookingStmt);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/reportsStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
   </head>
<body>
  <div class="sidebar close">
    <div class="logo-details">
      <img src="../../images/logo.png" alt="Serena Sanctuary logo" class="logo-icon">
      <span class="logo_name">Serena Sanctuary</span>
    </div>
    <ul class="nav-links">
      <li>
        <a href="dashboard.php">
          <i class='bxr  bx-dashboard'></i> 
          <span class="link_name">Dashboard</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="dashboard.php">Dashboard</a></li>
        </ul>
      </li>
      <li>
        <div class="icon-link">
          <a href="manage.php">
            <i class='bxr  bx-list-square'></i>
            <span class="link_name">Manage</span>
          </a>
          <i class='bx bxs-chevron-down arrow' ></i>
        </div>
        <ul class="sub-menu">
          <li><a class="link_name" href="manage.php">Manage</a></li>
          <li><a href="guests.php">Guests</a></li>
          <?php if (isManager()): ?>
          <li><a href="staff.php">Staff</a></li>
          <?php endif; ?>
          <li><a href="homestay.php">Homestay</a></li>
        </ul>
      </li>
      <li>
        <a href="billing.php">
          <i class='bxr  bx-print-dollar'></i>
          <span class="link_name">Billing</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="billing.php">Billing</a></li>
        </ul>
      </li>
      <li>
        <a href="bookings.php">
          <i class='bxr  bx-home-add'></i>
          <span class="link_name">Bookings</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="bookings.php">Bookings</a></li>
        </ul>
      </li>
      <li>
        <a href="service.php">
          <i class='bxr  bx-spanner'></i>
          <span class="link_name">Services</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="service.php">Services</a></li>
        </ul>
      </li>
      <li>
        <a href="calendar.php">
          <i class='bxr  bx-calendar-alt'></i> 
          <span class="link_name">Calendar</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="calendar.php">Calendar</a></li>
        </ul>
      </li>
      <li>
        <div class="icon-link">
          <a href="reports.php">
            <i class='bxr  bx-file-report'></i> 
            <span class="link_name">Reports</span>
          </a>
          <i class='bx bxs-chevron-down arrow' ></i>
        </div>
        <ul class="sub-menu">
          <li><a class="link_name" href="reports.php">Reports</a></li>
          <li><a href="fullReports.php">Full Reports</a></li>
          <li><a href="summary.php">Summary</a></li>
          <li><a href="analytics.php">Analytics</a></li>
        </ul>
      </li>
            <li>
              <div class="profile-details">
                <a href="../logout.php" class="profile-content" style="display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit;">
                  <i class='bx bx-arrow-out-right-square-half' style="font-size: 24px; margin-right: 10px;"></i>
                  <span class="link_name">Logout</span>
                </a>
              </div>
            </li>
          </ul>
  </div>
  <section class="home-section">
    <div class="home-content">
      <i class='bx bx-menu' ></i>
      <span class="text">Serena Sanctuary</span>
      <div class="header-profile">
        <i class='bxr  bx-user-circle'></i>
        <div class="header-profile-info">
          <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Staff'); ?></div>
          <div class="header-profile-job"><?php
            if (isManager()) {
              echo 'Manager';
            } else {
              $st = $_SESSION['staff_type'] ?? '';
              if (stripos($st, 'part') !== false) {
                echo 'Part-time';
              } elseif (stripos($st, 'full') !== false) {
                echo 'Full-time';
              } else {
                echo htmlspecialchars($st ?: 'Staff');
              }
            }
          ?></div>
        </div>
      </div>
    </div>
    <div class="page-heading">
      <div>
        <h1>Reports</h1>
        <p class="subdued">Overview of occupancy, guests, and billing.</p>
      </div>
    </div>
    <div class="stat-grid">
      <div class="stat-card">
        <p class="stat-label">Total Bookings</p>
        <p class="stat-value"><?php echo (int)($bookingStats['CNT'] ?? 0); ?></p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Guests</p>
        <p class="stat-value"><?php echo (int)($guestStats['CNT'] ?? 0); ?></p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Homestays</p>
        <p class="stat-value"><?php echo (int)($homestayStats['CNT'] ?? 0); ?></p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Revenue (all time)</p>
        <p class="stat-value">RM <?php echo number_format((float)($billStats['TOTAL_AMT'] ?? 0), 2, '.', ''); ?></p>
        <p class="stat-sub">Paid: RM <?php echo number_format((float)($billStats['PAID_AMT'] ?? 0), 2, '.', ''); ?></p>
      </div>
    </div>

    <div class="reports-grid">
      <div class="panel">
        <div class="panel-header">
          <h2>Recent Bookings</h2>
          <a class="link" href="bookings.php">View all</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Homestay</th><th>Guest</th><th>Check-in</th><th>Check-out</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentBookings)): ?>
                <tr><td colspan="5" class="empty">No bookings yet.</td></tr>
              <?php else: ?>
                <?php foreach ($recentBookings as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['BOOKINGID']); ?></td>
                    <td><?php echo htmlspecialchars($row['HOMESTAYID']); ?></td>
                    <td><?php echo htmlspecialchars($row['GUESTID']); ?></td>
                    <td><?php echo htmlspecialchars($row['CHECKIN_DATE']); ?></td>
                    <td><?php echo htmlspecialchars($row['CHECKOUT_DATE']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h2>Recent Bills</h2>
          <a class="link" href="billing.php">View all</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Bill</th><th>Status</th><th>Date</th><th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentBills)): ?>
                <tr><td colspan="4" class="empty">No bills yet.</td></tr>
              <?php else: ?>
                <?php foreach ($recentBills as $bill): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($bill['BILLNO']); ?></td>
                    <td><span class="pill tiny <?php echo $bill['BILL_STATUS'] === 'Paid' ? 'paid' : 'unpaid'; ?>"><?php echo htmlspecialchars($bill['BILL_STATUS']); ?></span></td>
                    <td><?php echo htmlspecialchars($bill['BILL_DATE']); ?></td>
                    <td>RM <?php echo number_format((float)$bill['TOTAL_AMOUNT'], 2, '.', ''); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-content">
        <p>&copy; 2025 Serena Sanctuary. All rights reserved.</p>
      </div>
    </footer>
  </section>

  <script>
  let arrow = document.querySelectorAll(".arrow");
  for (var i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener("click", (e)=>{
   let arrowParent = e.target.parentElement.parentElement;//selecting main parent of arrow
   arrowParent.classList.toggle("showMenu");
    });
  }
  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".bx-menu");
  sidebarBtn.addEventListener("click", ()=>{
    sidebar.classList.toggle("close");
  });
  </script>
</body>
</html>
