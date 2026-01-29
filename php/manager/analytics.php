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

// Staff salary analytics
// Full-time: monthly salary * 12 = annual
$fullTimeSalaryRow = fetchOne($conn, "SELECT NVL(SUM(FULL_TIME_SALARY), 0) AS TOTAL FROM FULL_TIME");
$fullTimeAnnualSalary = 12 * (float)($fullTimeSalaryRow['TOTAL'] ?? 0);

// Part-time: (hourly rate * 8 hours) per person, sum total, then * 12 months = annual
$partTimeSalaryRow = fetchOne($conn, "SELECT NVL(SUM(HOURLY_RATE * 8) * 12, 0) AS TOTAL FROM PART_TIME");
$partTimeAnnualSalary = (float)($partTimeSalaryRow['TOTAL'] ?? 0);
$totalAnnualStaffSalary = $fullTimeAnnualSalary + $partTimeAnnualSalary;

// Annual revenue (current calendar year, paid bills only)
$annualRevenueRow = fetchOne($conn, "
  SELECT NVL(SUM(total_amount), 0) AS TOTAL
  FROM BILL
  WHERE bill_status = 'Paid'
    AND EXTRACT(YEAR FROM bill_date) = EXTRACT(YEAR FROM SYSDATE)
");
$annualRevenue = (float)($annualRevenueRow['TOTAL'] ?? 0);

// Profit / Loss = Annual Revenue - Total Annual Staff Salary
$annualProfit = $annualRevenue - $totalAnnualStaffSalary;

// Last year – same staff salary (current roster), revenue filtered by previous calendar year (DB year)
$lastYearRow = fetchOne($conn, "SELECT EXTRACT(YEAR FROM SYSDATE) - 1 AS YR FROM DUAL");
$lastYear = (int)($lastYearRow['YR'] ?? (date('Y') - 1));
$lastYearRevenueRow = fetchOne($conn, "
  SELECT NVL(SUM(total_amount), 0) AS TOTAL
  FROM BILL
  WHERE bill_status = 'Paid'
    AND EXTRACT(YEAR FROM bill_date) = " . $lastYear . "
");
$lastYearRevenue = (float)($lastYearRevenueRow['TOTAL'] ?? 0);
// Staff cost: use current annual total as proxy (no historical payroll in schema)
$lastYearProfit = $lastYearRevenue - $totalAnnualStaffSalary;

// Container 2: highest/lowest month and homestay by booking count
$highestMonthRow = fetchOne($conn, "
  SELECT TO_CHAR(checkin_date, 'Mon YYYY') AS MONTH_LABEL, COUNT(*) AS CNT
  FROM BOOKING
  GROUP BY TO_CHAR(checkin_date, 'YYYY-MM'), TO_CHAR(checkin_date, 'Mon YYYY')
  ORDER BY COUNT(*) DESC
  FETCH FIRST 1 ROW ONLY
");
$lowestMonthRow = fetchOne($conn, "
  SELECT TO_CHAR(checkin_date, 'Mon YYYY') AS MONTH_LABEL, COUNT(*) AS CNT
  FROM BOOKING
  GROUP BY TO_CHAR(checkin_date, 'YYYY-MM'), TO_CHAR(checkin_date, 'Mon YYYY')
  ORDER BY COUNT(*) ASC
  FETCH FIRST 1 ROW ONLY
");
$highestHomestayRow = fetchOne($conn, "
  SELECT h.homestay_name AS NAME, COUNT(b.bookingID) AS CNT
  FROM HOMESTAY h
  INNER JOIN BOOKING b ON h.homestayID = b.homestayID
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY COUNT(b.bookingID) DESC
  FETCH FIRST 1 ROW ONLY
");
$lowestHomestayRow = fetchOne($conn, "
  SELECT h.homestay_name AS NAME, COUNT(b.bookingID) AS CNT
  FROM HOMESTAY h
  LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY COUNT(b.bookingID) ASC
  FETCH FIRST 1 ROW ONLY
");

// Annually (current year only)
$currentYearRow = fetchOne($conn, "SELECT EXTRACT(YEAR FROM SYSDATE) AS YR FROM DUAL");
$currentYear = (int)($currentYearRow['YR'] ?? date('Y'));
$highestMonthAnnualRow = fetchOne($conn, "
  SELECT TO_CHAR(checkin_date, 'Mon YYYY') AS MONTH_LABEL, COUNT(*) AS CNT
  FROM BOOKING
  WHERE EXTRACT(YEAR FROM checkin_date) = EXTRACT(YEAR FROM SYSDATE)
  GROUP BY TO_CHAR(checkin_date, 'YYYY-MM'), TO_CHAR(checkin_date, 'Mon YYYY')
  ORDER BY COUNT(*) DESC
  FETCH FIRST 1 ROW ONLY
");
$lowestMonthAnnualRow = fetchOne($conn, "
  SELECT TO_CHAR(checkin_date, 'Mon YYYY') AS MONTH_LABEL, COUNT(*) AS CNT
  FROM BOOKING
  WHERE EXTRACT(YEAR FROM checkin_date) = EXTRACT(YEAR FROM SYSDATE)
  GROUP BY TO_CHAR(checkin_date, 'YYYY-MM'), TO_CHAR(checkin_date, 'Mon YYYY')
  ORDER BY COUNT(*) ASC
  FETCH FIRST 1 ROW ONLY
");
$highestHomestayAnnualRow = fetchOne($conn, "
  SELECT h.homestay_name AS NAME, COUNT(b.bookingID) AS CNT
  FROM HOMESTAY h
  INNER JOIN BOOKING b ON h.homestayID = b.homestayID AND EXTRACT(YEAR FROM b.checkin_date) = EXTRACT(YEAR FROM SYSDATE)
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY COUNT(b.bookingID) DESC
  FETCH FIRST 1 ROW ONLY
");
$lowestHomestayAnnualRow = fetchOne($conn, "
  SELECT h.homestay_name AS NAME, COUNT(CASE WHEN EXTRACT(YEAR FROM b.checkin_date) = EXTRACT(YEAR FROM SYSDATE) THEN 1 END) AS CNT
  FROM HOMESTAY h
  LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
  GROUP BY h.homestayID, h.homestay_name
  ORDER BY COUNT(CASE WHEN EXTRACT(YEAR FROM b.checkin_date) = EXTRACT(YEAR FROM SYSDATE) THEN 1 END) ASC
  FETCH FIRST 1 ROW ONLY
");

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Analytics</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/analyticsStyle.css?v=1">
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
      <div>
        <h1>Analytics</h1>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card">
        <p class="stat-label">Avg. Stay</p>
        <p class="stat-value"><?php echo $avgStay['AVG_DAYS'] ?? 0; ?> days</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Repeat Guests</p>
        <p class="stat-value"><?php echo $repeatRate; ?>%</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Revenue Growth</p>
        <p class="stat-value"><?php echo $revGrowth >= 0 ? '+' : ''; ?><?php echo $revGrowth; ?>%</p>
      </div>
      <div class="stat-card">
        <p class="stat-label">Adults / Children</p>
        <p class="stat-value"><?php echo $demographics['ADULTS'] ?? 0; ?> / <?php echo $demographics['CHILDREN'] ?? 0; ?></p>
      </div>
    </div>

    <div class="grid-2">
      <section class="panel">
        <div class="panel-header">
          <h3>Staff Salary vs Revenue (Annual)</h3>
        </div>
        <div class="salary-block">
          <h4 class="salary-period">Current year (<?php echo (int)date('Y'); ?>)</h4>
          <div class="salary-list">
            <div class="salary-row">
              <span class="salary-label">Full-time annual salary</span>
              <span class="salary-value">RM <?php echo number_format($fullTimeAnnualSalary, 0); ?></span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Part-time annual salary</span>
              <span class="salary-value">RM <?php echo number_format($partTimeAnnualSalary, 0); ?></span>
            </div>
            <div class="salary-row salary-row-total">
              <span class="salary-label">Total annual staff salary</span>
              <span class="salary-value">RM <?php echo number_format($totalAnnualStaffSalary, 0); ?></span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Annual revenue (paid)</span>
              <span class="salary-value">RM <?php echo number_format($annualRevenue, 0); ?></span>
            </div>
            <div class="salary-row salary-row-profit <?php echo $annualProfit >= 0 ? 'profit' : 'loss'; ?>">
              <span class="salary-label"><?php echo $annualProfit >= 0 ? 'Profit' : 'Loss'; ?></span>
              <span class="salary-value">
                RM <?php echo number_format(abs($annualProfit), 0); ?>
              </span>
            </div>
          </div>
        </div>
        <div class="salary-block">
          <h4 class="salary-period">Last year (<?php echo $lastYear; ?>)</h4>
          <div class="salary-list">
            <div class="salary-row">
              <span class="salary-label">Full-time annual salary</span>
              <span class="salary-value">RM <?php echo number_format($fullTimeAnnualSalary, 0); ?></span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Part-time annual salary</span>
              <span class="salary-value">RM <?php echo number_format($partTimeAnnualSalary, 0); ?></span>
            </div>
            <div class="salary-row salary-row-total">
              <span class="salary-label">Total annual staff salary</span>
              <span class="salary-value">RM <?php echo number_format($totalAnnualStaffSalary, 0); ?></span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Annual revenue (paid)</span>
              <span class="salary-value">RM <?php echo number_format($lastYearRevenue, 0); ?></span>
            </div>
            <div class="salary-row salary-row-profit <?php echo $lastYearProfit >= 0 ? 'profit' : 'loss'; ?>">
              <span class="salary-label"><?php echo $lastYearProfit >= 0 ? 'Profit' : 'Loss'; ?></span>
              <span class="salary-value">
                RM <?php echo number_format(abs($lastYearProfit), 0); ?>
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header">
          <h3>Booking highlights</h3>
        </div>
        <div class="salary-block">
          <h4 class="salary-period">All time</h4>
          <div class="salary-list">
            <div class="salary-row">
              <span class="salary-label">Highest month (bookings)</span>
              <span class="salary-value"><?php echo htmlspecialchars($highestMonthRow['MONTH_LABEL'] ?? '—'); ?> (<?php echo (int)($highestMonthRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Lowest month (bookings)</span>
              <span class="salary-value"><?php echo htmlspecialchars($lowestMonthRow['MONTH_LABEL'] ?? '—'); ?> (<?php echo (int)($lowestMonthRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Highest booking homestay</span>
              <span class="salary-value"><?php echo htmlspecialchars($highestHomestayRow['NAME'] ?? '—'); ?> (<?php echo (int)($highestHomestayRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Lowest booking homestay</span>
              <span class="salary-value"><?php echo htmlspecialchars($lowestHomestayRow['NAME'] ?? '—'); ?> (<?php echo (int)($lowestHomestayRow['CNT'] ?? 0); ?>)</span>
            </div>
          </div>
        </div>
        <div class="salary-block">
          <h4 class="salary-period">Annually (<?php echo $currentYear; ?>)</h4>
          <div class="salary-list">
            <div class="salary-row">
              <span class="salary-label">Highest month (bookings)</span>
              <span class="salary-value"><?php echo htmlspecialchars($highestMonthAnnualRow['MONTH_LABEL'] ?? '—'); ?> (<?php echo (int)($highestMonthAnnualRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Lowest month (bookings)</span>
              <span class="salary-value"><?php echo htmlspecialchars($lowestMonthAnnualRow['MONTH_LABEL'] ?? '—'); ?> (<?php echo (int)($lowestMonthAnnualRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Highest booking homestay</span>
              <span class="salary-value"><?php echo htmlspecialchars($highestHomestayAnnualRow['NAME'] ?? '—'); ?> (<?php echo (int)($highestHomestayAnnualRow['CNT'] ?? 0); ?>)</span>
            </div>
            <div class="salary-row">
              <span class="salary-label">Lowest booking homestay</span>
              <span class="salary-value"><?php echo htmlspecialchars($lowestHomestayAnnualRow['NAME'] ?? '—'); ?> (<?php echo (int)($lowestHomestayAnnualRow['CNT'] ?? 0); ?>)</span>
            </div>
          </div>
        </div>
      </section>
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