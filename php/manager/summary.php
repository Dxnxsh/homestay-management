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

$totalRevenue = fetchOne($conn, "SELECT NVL(SUM(total_amount), 0) AS TOTAL FROM BILL WHERE bill_status = 'Paid'");
$totalBookings = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM BOOKING");
$totalGuests = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM GUEST");
$totalHomestays = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM HOMESTAY");
$avgOccupancy = fetchOne($conn, "SELECT ROUND(NVL(AVG(num_adults + num_children), 0), 2) AS AVG_OCC FROM BOOKING");

$monthlyRevenue = [];
$monthSql = "SELECT TO_CHAR(bill_date, 'YYYY-MM') AS MONTH, TO_CHAR(bill_date, 'Month') AS MONTH_NAME, NVL(SUM(total_amount), 0) AS AMT 
  FROM BILL 
  WHERE bill_date >= TRUNC(SYSDATE, 'YEAR') AND bill_status = 'Paid'
  GROUP BY TO_CHAR(bill_date, 'YYYY-MM'), TO_CHAR(bill_date, 'Month')
  ORDER BY TO_CHAR(bill_date, 'YYYY-MM')";
$stmt = oci_parse($conn, $monthSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $monthlyRevenue[] = $row;
}
oci_free_statement($stmt);

$occupancyByHomestay = [];
$homestySql = "SELECT h.homestay_name, COUNT(b.bookingID) AS booking_count FROM HOMESTAY h LEFT JOIN BOOKING b ON h.homestayID = b.homestayID GROUP BY h.homestayID, h.homestay_name ORDER BY booking_count DESC FETCH NEXT 8 ROWS ONLY";
$stmt = oci_parse($conn, $homestySql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $occupancyByHomestay[] = $row;
}
oci_free_statement($stmt);

$bookingStatus = [];
$statusSql = "SELECT 'Completed' as status_name, COUNT(*) AS CNT FROM BOOKING WHERE checkout_date < TRUNC(SYSDATE) 
UNION ALL
SELECT 'Active' as status_name, COUNT(*) AS CNT FROM BOOKING WHERE checkin_date <= TRUNC(SYSDATE) AND checkout_date >= TRUNC(SYSDATE)
UNION ALL
SELECT 'Upcoming' as status_name, COUNT(*) AS CNT FROM BOOKING WHERE checkin_date > TRUNC(SYSDATE)";
$stmt = oci_parse($conn, $statusSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $bookingStatus[] = $row;
}
oci_free_statement($stmt);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Summary</title>
    <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/summaryStyle.css?v=3">
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
          <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Manager'); ?></div>
          <div class="header-profile-job">Manager</div>
        </div>
      </div>
    </div>
    <div class="page-heading">
      <div>
        <h1>Summary</h1>
        <p class="subdued">Annual business performance overview.</p>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card">
        <p class="kpi-label">Total Revenue</p>
        <p class="kpi-value">$<?php echo number_format($totalRevenue['TOTAL'] ?? 0, 0); ?></p>
      </div>
      <div class="kpi-card">
        <p class="kpi-label">Total Bookings</p>
        <p class="kpi-value"><?php echo (int)($totalBookings['CNT'] ?? 0); ?></p>
      </div>
      <div class="kpi-card">
        <p class="kpi-label">Registered Guests</p>
        <p class="kpi-value"><?php echo (int)($totalGuests['CNT'] ?? 0); ?></p>
      </div>
      <div class="kpi-card">
        <p class="kpi-label">Properties</p>
        <p class="kpi-value"><?php echo (int)($totalHomestays['CNT'] ?? 0); ?></p>
      </div>
      <div class="kpi-card">
        <p class="kpi-label">Avg. Guest Count/Booking</p>
        <p class="kpi-value"><?php echo (float)($avgOccupancy['AVG_OCC'] ?? 0); ?></p>
      </div>
    </div>

    <div class="chart-grid">
      <div class="chart-card">
        <h3>Monthly Revenue</h3>
        <div class="bar-chart" id="revenueChart">
          <?php foreach ($monthlyRevenue as $month): ?>
            <div class="bar-item">
              <div class="bar" style="height: <?php echo min(100, max(1, ($month['AMT'] / 50000) * 100)); ?>%;" title="$<?php echo number_format($month['AMT'], 0); ?>"></div>
              <span><?php echo substr(trim($month['MONTH_NAME']), 0, 3); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="chart-card">
        <h3>Top Properties by Bookings</h3>
        <p class="chart-subtitle">All time</p>
        <div class="revenue-list compact">
          <?php $maxBookings = !empty($occupancyByHomestay) ? max(array_column($occupancyByHomestay, 'BOOKING_COUNT')) : 0; ?>
          <?php if (!empty($occupancyByHomestay)): ?>
            <?php foreach ($occupancyByHomestay as $idx => $h): ?>
              <?php $pct = $maxBookings > 0 ? ($h['BOOKING_COUNT'] / $maxBookings) * 100 : 0; ?>
              <div class="revenue-item">
                <div class="revenue-rank"><?php echo $idx + 1; ?></div>
                <div class="revenue-name"><?php echo htmlspecialchars($h['HOMESTAY_NAME']); ?></div>
                <div class="revenue-bar-container">
                  <div class="revenue-bar" style="width: <?php echo max(6, $pct); ?>%;"></div>
                </div>
                <div class="revenue-amount"><?php echo (int)$h['BOOKING_COUNT']; ?> bookings</div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="no-data">No booking data available</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="status-grid">
      <div class="status-card">
        <h3>Booking Status Distribution</h3>
        <div class="status-list">
          <?php foreach ($bookingStatus as $bs): ?>
            <div class="status-row">
              <span class="status-label"><?php echo htmlspecialchars($bs['STATUS_NAME']); ?></span>
              <span class="status-count"><?php echo (int)$bs['CNT']; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Footer -->
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
  console.log(sidebarBtn);
  sidebarBtn.addEventListener("click", ()=>{
    sidebar.classList.toggle("close");
  });
  </script>
</body>
</html>
