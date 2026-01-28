<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();
if (!isManager()) {
  header('Location: dashboard.php');
  exit();
}

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

function fetchOne($conn, $sql)
{
  $stmt = oci_parse($conn, $sql);
  oci_execute($stmt);
  $row = oci_fetch_array($stmt, OCI_ASSOC);
  oci_free_statement($stmt);
  return $row ?: [];
}

function fetchAll($conn, $sql)
{
  $result = [];
  $stmt = oci_parse($conn, $sql);
  if ($stmt && @oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $result[] = $row;
    }
  }
  if ($stmt) {
    oci_free_statement($stmt);
  }
  return $result;
}

// Guest Analytics
$guestSql = "SELECT TO_CHAR(first_date, 'YYYY-MM') AS MONTH,
                    TO_CHAR(first_date, 'Mon') AS MONTH_NAME,
                    COUNT(*) AS CNT
             FROM (
               SELECT guestID, MIN(checkin_date) AS first_date
               FROM BOOKING
               GROUP BY guestID
             ) g
             WHERE g.first_date >= ADD_MONTHS(TRUNC(SYSDATE, 'YEAR'), -11)
             GROUP BY TO_CHAR(first_date, 'YYYY-MM'), TO_CHAR(first_date, 'Mon')
             ORDER BY TO_CHAR(first_date, 'YYYY-MM')";
$guestGrowth = fetchAll($conn, $guestSql);

// Booking Trends - Daily bookings for current month
$bookingTrends = [];
$trendSql = "SELECT TO_CHAR(checkin_date, 'DD') AS DAY, COUNT(*) AS CNT 
  FROM BOOKING 
  WHERE TO_CHAR(checkin_date, 'YYYY-MM') = TO_CHAR(SYSDATE, 'YYYY-MM')
  GROUP BY TO_CHAR(checkin_date, 'DD')
  ORDER BY TO_CHAR(checkin_date, 'DD')";
$stmt = oci_parse($conn, $trendSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $bookingTrends[] = $row;
}
oci_free_statement($stmt);

// Revenue by Property
$revenueByProperty = [];
$revPropSql = "SELECT h.homestay_name, NVL(SUM(bi.total_amount), 0) AS REVENUE
  FROM HOMESTAY h
  LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
  LEFT JOIN BILL bi ON b.billNo = bi.billNo
  WHERE bi.bill_status = 'Paid' OR bi.billNo IS NULL
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY REVENUE DESC";
$stmt = oci_parse($conn, $revPropSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $revenueByProperty[] = $row;
}
oci_free_statement($stmt);

// Average Stay Duration
$avgStay = fetchOne($conn, "SELECT ROUND(AVG(checkout_date - checkin_date), 1) AS AVG_DAYS FROM BOOKING");

// Repeat Guest Rate
$repeatGuests = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM (SELECT guestID FROM BOOKING GROUP BY guestID HAVING COUNT(*) > 1)");
$totalGuestsBooked = fetchOne($conn, "SELECT COUNT(DISTINCT guestID) AS CNT FROM BOOKING");
$repeatRate = $totalGuestsBooked['CNT'] > 0 ? round(($repeatGuests['CNT'] / $totalGuestsBooked['CNT']) * 100, 1) : 0;

// Revenue Growth (current vs previous month)
$currentMonthRev = fetchOne($conn, "SELECT NVL(SUM(total_amount), 0) AS AMT FROM BILL WHERE TO_CHAR(bill_date, 'YYYY-MM') = TO_CHAR(SYSDATE, 'YYYY-MM') AND bill_status = 'Paid'");
$prevMonthRev = fetchOne($conn, "SELECT NVL(SUM(total_amount), 0) AS AMT FROM BILL WHERE TO_CHAR(bill_date, 'YYYY-MM') = TO_CHAR(ADD_MONTHS(SYSDATE, -1), 'YYYY-MM') AND bill_status = 'Paid'");
$revGrowth = $prevMonthRev['AMT'] > 0 ? round((($currentMonthRev['AMT'] - $prevMonthRev['AMT']) / $prevMonthRev['AMT']) * 100, 1) : 0;

// Occupancy Rate by Month
$occupancyRate = [];
$occSql = "SELECT TO_CHAR(checkin_date, 'YYYY-MM') AS MONTH, TO_CHAR(checkin_date, 'Mon') AS MONTH_NAME, 
  COUNT(*) AS booking_count,
  ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM HOMESTAY)), 1) AS occupancy_pct
  FROM BOOKING 
  WHERE checkin_date >= ADD_MONTHS(TRUNC(SYSDATE, 'YEAR'), -11)
  GROUP BY TO_CHAR(checkin_date, 'YYYY-MM'), TO_CHAR(checkin_date, 'Mon')
  ORDER BY TO_CHAR(checkin_date, 'YYYY-MM')";
$stmt = oci_parse($conn, $occSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $occupancyRate[] = $row;
}
oci_free_statement($stmt);

// Guest Demographics - Adults vs Children
$demographics = fetchOne($conn, "SELECT SUM(num_adults) AS ADULTS, SUM(num_children) AS CHILDREN FROM BOOKING");

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Analytics</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/summaryStyle.css?v=3">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
  <!-- Sidebar -->
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
          <i class='bx bxs-chevron-down arrow'></i>
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
          <i class='bx bxs-chevron-down arrow'></i>
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
          <a href="../logout.php" class="profile-content"
            style="display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit;">
            <i class='bx bx-arrow-out-right-square-half' style="font-size: 24px; margin-right: 10px;"></i>
            <span class="link_name">Logout</span>
          </a>
        </div>
      </li>
    </ul>
  </div>

  <section class="home-section">
    <div class="home-content">
      <i class='bx bx-menu'></i>
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
      <h1>Analytics</h1>
    </div><br>

    <div class="analytics-wrapper">
      <section class="panel kpi-panel">
        <div class="panel-header">
          <h3>At a Glance</h3>
        </div>
        <div class="kpi-row">
          <div class="kpi-card">
            <h4 class="kpi-label">Avg. Stay</h4>
            <p class="kpi-value"><?php echo $avgStay['AVG_DAYS'] ?? 0; ?> days</p>
          </div>
          <div class="kpi-card">
            <h4 class="kpi-label">Repeat Guests</h4>
            <p class="kpi-value"><?php echo $repeatRate; ?>%</p>
          </div>
          <div class="kpi-card">
            <h4 class="kpi-label">Revenue Growth</h4>
            <p class="kpi-value <?php echo $revGrowth >= 0 ? 'positive' : 'negative'; ?>">
              <?php echo $revGrowth >= 0 ? '+' : ''; ?><?php echo $revGrowth; ?>%</p>
          </div>
          <div class="kpi-card">
            <h4 class="kpi-label">Adults / Children</h4>
            <p class="kpi-value"><?php echo $demographics['ADULTS'] ?? 0; ?> /
              <?php echo $demographics['CHILDREN'] ?? 0; ?></p>
          </div>
        </div>
      </section>

      <div class="grid-2">
        <section class="panel">
          <div class="panel-header">
            <h3>Guest Growth</h3>
            <span class="pill-sm">Last 12 months</span>
          </div>
          <div class="data-list compact">
            <?php if (!empty($guestGrowth)): ?>
              <?php $maxGuests = max(array_column($guestGrowth, 'CNT')); ?>
              <?php foreach ($guestGrowth as $data): ?>
                <?php $pct = $maxGuests > 0 ? ($data['CNT'] / $maxGuests) * 100 : 0; ?>
                <div class="data-row">
                  <span class="data-label"><?php echo $data['MONTH_NAME']; ?></span>
                  <div class="data-bar"><span style="width: <?php echo max(4, $pct); ?>%;"></span></div>
                  <span class="data-value"><?php echo $data['CNT']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="no-data">No guest data available</p>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h3>Bookings (This Month)</h3>
            <span class="pill-sm">Daily</span>
          </div>
          <div class="data-list compact">
            <?php if (!empty($bookingTrends)): ?>
              <?php $maxBookings = max(array_column($bookingTrends, 'CNT')); ?>
              <?php foreach ($bookingTrends as $data): ?>
                <?php $pct = $maxBookings > 0 ? ($data['CNT'] / $maxBookings) * 100 : 0; ?>
                <div class="data-row">
                  <span class="data-label">Day <?php echo $data['DAY']; ?></span>
                  <div class="data-bar"><span style="width: <?php echo max(4, $pct); ?>%;"></span></div>
                  <span class="data-value"><?php echo $data['CNT']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="no-data">No booking data for current month</p>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <div class="grid-2">
        <section class="panel">
          <div class="panel-header">
            <h3>Revenue by Property</h3>
            <span class="pill-sm">All time</span>
          </div>
          <div class="revenue-list compact">
            <?php $maxRev = !empty($revenueByProperty) ? max(array_column($revenueByProperty, 'REVENUE')) : 0; ?>
            <?php if (!empty($revenueByProperty)): ?>
              <?php foreach ($revenueByProperty as $idx => $data): ?>
                <?php $pct = $maxRev > 0 ? ($data['REVENUE'] / $maxRev) * 100 : 0; ?>
                <div class="revenue-item">
                  <div class="revenue-rank"><?php echo $idx + 1; ?></div>
                  <div class="revenue-name"><?php echo htmlspecialchars($data['HOMESTAY_NAME']); ?></div>
                  <div class="revenue-bar-container">
                    <div class="revenue-bar" style="width: <?php echo max(6, $pct); ?>%;"></div>
                  </div>
                  <div class="revenue-amount">RM <?php echo number_format($data['REVENUE'], 0); ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="no-data">No revenue data available</p>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h3>Occupancy Rate</h3>
            <span class="pill-sm">By month</span>
          </div>
          <div class="data-list compact">
            <?php if (!empty($occupancyRate)): ?>
              <?php foreach ($occupancyRate as $data): ?>
                <?php $pct = max(0, $data['OCCUPANCY_PCT']); ?>
                <div class="data-row">
                  <span class="data-label"><?php echo $data['MONTH_NAME']; ?></span>
                  <div class="data-bar"><span style="width: <?php echo max(4, $pct); ?>%;"></span></div>
                  <span class="data-value"><?php echo $data['OCCUPANCY_PCT']; ?>%</span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="no-data">No occupancy data available</p>
            <?php endif; ?>
          </div>
        </section>
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
      arrow[i].addEventListener("click", (e) => {
        let arrowParent = e.target.parentElement.parentElement;//selecting main parent of arrow
        arrowParent.classList.toggle("showMenu");
      });
    }
    let sidebar = document.querySelector(".sidebar");
    let sidebarBtn = document.querySelector(".bx-menu");
    console.log(sidebarBtn);
    sidebarBtn.addEventListener("click", () => {
      sidebar.classList.toggle("close");
    });
  </script>
</body>

</html>