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

$totalRevenue = fetchOne($conn, "SELECT NVL(SUM(total_amount), 0) AS TOTAL FROM BILL WHERE bill_status = 'Paid'");
$totalBookings = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM BOOKING");
$totalGuests = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM GUEST");
$totalStaff = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM STAFF");
$totalHomestays = fetchOne($conn, "SELECT COUNT(*) AS CNT FROM HOMESTAY");
$avgOccupancy = fetchOne($conn, "SELECT ROUND(NVL(AVG(num_adults + num_children), 0), 2) AS AVG_OCC FROM BOOKING");

// Detail breakdowns for mini KPI container
// Guests: treat NULL/empty as Regular; Member includes MEMBER or MEMBERSHIP (case-insensitive)
$guestTypeCounts = fetchOne($conn, "
  SELECT
    SUM(CASE
          WHEN guest_type IS NULL OR TRIM(guest_type) IS NULL OR UPPER(TRIM(guest_type)) IN ('REGULAR','NULL')
            THEN 1
          ELSE 0
        END) AS REGULAR_CNT,
    SUM(CASE
          WHEN UPPER(TRIM(guest_type)) IN ('MEMBER','MEMBERSHIP')
            THEN 1
          ELSE 0
        END) AS MEMBER_CNT
  FROM GUEST
");

// Staff: inheritance tables
$staffTypeCounts = fetchOne($conn, "SELECT (SELECT COUNT(*) FROM FULL_TIME) AS FULL_CNT, (SELECT COUNT(*) FROM PART_TIME) AS PART_CNT FROM DUAL");

// Homestay: with / without bookings (all time)
$homestayBookingCounts = fetchOne($conn, "
  SELECT
    COUNT(DISTINCT CASE WHEN b.bookingID IS NOT NULL THEN h.homestayID END) AS WITH_BOOKINGS,
    COUNT(DISTINCT CASE WHEN b.bookingID IS NULL THEN h.homestayID END) AS WITHOUT_BOOKINGS
  FROM HOMESTAY h
  LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
");

// Top 4 homestays by total bookings (all time)
$topHomestays = [];
$topHomestaySql = "
  SELECT h.homestay_name, COUNT(b.bookingID) AS booking_count
  FROM HOMESTAY h
  LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY booking_count DESC
  FETCH NEXT 4 ROWS ONLY
";
$stmt = oci_parse($conn, $topHomestaySql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $topHomestays[] = $row;
}
oci_free_statement($stmt);

// Revenue: paid / pending / total (all time)
$revenueBreakdown = fetchOne($conn, "
  SELECT
    NVL(SUM(total_amount), 0) AS TOTAL_ALL,
    NVL(SUM(CASE WHEN bill_status = 'Paid' THEN total_amount ELSE 0 END), 0) AS PAID_AMT,
    NVL(SUM(CASE WHEN bill_status = 'Pending' THEN total_amount ELSE 0 END), 0) AS PENDING_AMT
  FROM BILL
");

// Monthly summary table: bookings by month (all time, grouped by month-of-year)
// Counts ALL bookings (including pending/unpaid), grouped by booking check-in month
$monthlySummary = [];
$monthlySummaryTotalBookings = 0;
$monthlySummarySql = "
  SELECT
    TO_CHAR(b.checkin_date, 'MM') AS MONTH_KEY,
    TO_CHAR(b.checkin_date, 'Mon') AS MONTH_LABEL,
    COUNT(*) AS TOTAL_BOOKINGS
  FROM BOOKING b
  GROUP BY TO_CHAR(b.checkin_date, 'MM'), TO_CHAR(b.checkin_date, 'Mon')
  ORDER BY MONTH_KEY
";
$stmt = oci_parse($conn, $monthlySummarySql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $monthlySummary[] = $row;
  $monthlySummaryTotalBookings += (int)($row['TOTAL_BOOKINGS'] ?? 0);
}
oci_free_statement($stmt);

// Ensure we always have all 12 months, even if zero
$filledMonthlySummary = [];
for ($m = 1; $m <= 12; $m++) {
  $key = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
  $label = date('M', mktime(0, 0, 0, $m, 1));
  $filledMonthlySummary[$key] = [
    'MONTH_KEY' => $key,
    'MONTH_LABEL' => $label,
    'TOTAL_BOOKINGS' => 0,
  ];
}
foreach ($monthlySummary as $row) {
  $key = $row['MONTH_KEY'];
  if (isset($filledMonthlySummary[$key])) {
    $filledMonthlySummary[$key]['TOTAL_BOOKINGS'] = (int)($row['TOTAL_BOOKINGS'] ?? 0);
    $filledMonthlySummary[$key]['TOTAL_REVENUE'] = (float)($row['TOTAL_REVENUE'] ?? 0);
  }
}
$monthlySummary = array_values($filledMonthlySummary);
// Recompute total bookings to include all 12 months explicitly
$monthlySummaryTotalBookings = 0;
foreach ($monthlySummary as $row) {
  $monthlySummaryTotalBookings += (int)$row['TOTAL_BOOKINGS'];
}

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

// Booking status map for mini KPI details
$bookingCounts = ['Completed' => 0, 'Active' => 0, 'Upcoming' => 0];
foreach ($bookingStatus as $bs) {
  $k = $bs['STATUS_NAME'] ?? '';
  if (isset($bookingCounts[$k])) {
    $bookingCounts[$k] = (int)($bs['CNT'] ?? 0);
  }
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Summary</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/summaryStyle.css?v=5">
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
      <div>
        <h1>Summary</h1>
        <p class="subdued">Key metrics condensed into a overview</p>
      </div>
    </div>

    <div class="mini-kpi-wrap">
      <div class="mini-kpi-panel">
        <div class="mini-kpi-grid">
          <div class="mini-kpi-item">
            <div class="mini-kpi-main">
              <div class="mini-kpi-label">Total Guests</div>
              <div class="mini-kpi-value"><?php echo (int) ($totalGuests['CNT'] ?? 0); ?></div>
            </div>
            <div class="mini-kpi-detail">
              <div class="mini-kpi-detail-row">
                <span>Total Regular</span>
                <span><?php echo (int)($guestTypeCounts['REGULAR_CNT'] ?? 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Total Member</span>
                <span><?php echo (int)($guestTypeCounts['MEMBER_CNT'] ?? 0); ?></span>
              </div>
            </div>
          </div>

          <div class="mini-kpi-item">
            <div class="mini-kpi-main">
              <div class="mini-kpi-label">Total Staff</div>
              <div class="mini-kpi-value"><?php echo (int) ($totalStaff['CNT'] ?? 0); ?></div>
            </div>
            <div class="mini-kpi-detail">
              <div class="mini-kpi-detail-row">
                <span>Total Full-time</span>
                <span><?php echo (int)($staffTypeCounts['FULL_CNT'] ?? 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Total Part-time</span>
                <span><?php echo (int)($staffTypeCounts['PART_CNT'] ?? 0); ?></span>
              </div>
            </div>
          </div>

          <div class="mini-kpi-item">
            <div class="mini-kpi-main">
              <div class="mini-kpi-label">Homestay</div>
              <div class="mini-kpi-value"><?php echo (int) ($totalHomestays['CNT'] ?? 0); ?></div>
            </div>
            <div class="mini-kpi-detail">
              <?php if (!empty($topHomestays)): ?>
                <?php foreach ($topHomestays as $h): ?>
                  <div class="mini-kpi-detail-row mini-kpi-subrow">
                    <span><?php echo htmlspecialchars($h['HOMESTAY_NAME']); ?></span>
                    <span><?php echo (int)($h['BOOKING_COUNT'] ?? 0); ?> bookings</span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="mini-kpi-item">
            <div class="mini-kpi-main">
              <div class="mini-kpi-label">Revenue</div>
              <div class="mini-kpi-value">RM <?php echo number_format($totalRevenue['TOTAL'] ?? 0, 0); ?></div>
            </div>
            <div class="mini-kpi-detail">
              <div class="mini-kpi-detail-row">
                <span>Paid</span>
                <span>RM <?php echo number_format((float)($revenueBreakdown['PAID_AMT'] ?? 0), 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Pending</span>
                <span>RM <?php echo number_format((float)($revenueBreakdown['PENDING_AMT'] ?? 0), 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Total</span>
                <span>RM <?php echo number_format((float)($revenueBreakdown['TOTAL_ALL'] ?? 0), 0); ?></span>
              </div>
            </div>
          </div>

          <div class="mini-kpi-item">
            <div class="mini-kpi-main">
              <div class="mini-kpi-label">Bookings</div>
              <div class="mini-kpi-value"><?php echo (int) ($totalBookings['CNT'] ?? 0); ?></div>
            </div>
            <div class="mini-kpi-detail">
              <div class="mini-kpi-detail-row">
                <span>Completed</span>
                <span><?php echo (int)($bookingCounts['Completed'] ?? 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Active</span>
                <span><?php echo (int)($bookingCounts['Active'] ?? 0); ?></span>
              </div>
              <div class="mini-kpi-detail-row">
                <span>Upcoming</span>
                <span><?php echo (int)($bookingCounts['Upcoming'] ?? 0); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mini-kpi-table-panel">
        <h3 class="mini-kpi-table-title">Monthly Bookings (All Time)</h3>
        <div class="mini-kpi-table-wrap">
          <table class="mini-kpi-table">
            <thead>
              <tr>
                <th>Month</th>
                <th>Total Bookings</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($monthlySummary)): ?>
                <tr>
                  <td colspan="2">No data available.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($monthlySummary as $m): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($m['MONTH_LABEL']); ?></td>
                    <td><?php echo (int)($m['TOTAL_BOOKINGS'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($monthlySummary)): ?>
              <tfoot>
                <tr>
                  <td>Total Bookings</td>
                  <td><?php echo (int)$monthlySummaryTotalBookings; ?></td>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
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