<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

// Initialize variables
$totalGuests = 0;
$totalStaff = 0;
$pendingBookings = 0;
$totalHomestays = 0;
$recentGuests = [];
$homestayOccupancy = [];
$monthlyRevenue = 0;
$lastMonthRevenue = 0;
$highestMonthBookings = 0;
$highestMonthIndex = 0;
$highestYear = date('Y');
$bookingsByMonth = [];
$currentYearRevenue = array_fill(0, 12, 0);
$lastYearRevenue = array_fill(0, 12, 0);

// Connect to database
$conn = getDBConnection();

// SQL queries for dashboard stats (used in UI info tooltips)
$sqlCurrentGuests = "SELECT COUNT(*) as total FROM GUEST";
$sqlTotalStaff = "SELECT COUNT(*) as total FROM STAFF";
$sqlPendingBookings = "SELECT COUNT(*) as total \n            FROM BOOKING b\n            LEFT JOIN BILL bl ON b.billNo = bl.billNo\n            WHERE b.billNo IS NULL OR UPPER(bl.bill_status) <> 'PAID'";
$sqlTotalHomestays = "SELECT COUNT(*) as total FROM HOMESTAY";
$sqlOccupancyCurrentYear = "SELECT h.homestayID, h.homestay_name, \n                   COUNT(DISTINCT b.bookingID) as current_bookings\n            FROM HOMESTAY h\n            LEFT JOIN BOOKING b ON h.homestayID = b.homestayID \n                AND EXTRACT(YEAR FROM b.checkin_date) = EXTRACT(YEAR FROM SYSDATE)\n            GROUP BY h.homestayID, h.homestay_name\n            ORDER BY h.homestayID";
$sqlOccupancyLastYear = "SELECT h.homestayID, COUNT(DISTINCT b.bookingID) as last_bookings\n            FROM HOMESTAY h\n            LEFT JOIN BOOKING b ON h.homestayID = b.homestayID \n                AND EXTRACT(YEAR FROM b.checkin_date) = EXTRACT(YEAR FROM SYSDATE) - 1\n            GROUP BY h.homestayID\n            ORDER BY h.homestayID";
$sqlMonthlyRevenueCurrent = "SELECT NVL(SUM(total_amount), 0) as revenue\n            FROM BILL\n            WHERE EXTRACT(MONTH FROM bill_date) = EXTRACT(MONTH FROM SYSDATE)\n            AND EXTRACT(YEAR FROM bill_date) = EXTRACT(YEAR FROM SYSDATE)\n            AND UPPER(bill_status) = 'PAID'";
$sqlMonthlyRevenueLast = "SELECT NVL(SUM(total_amount), 0) as revenue\n            FROM BILL\n            WHERE EXTRACT(MONTH FROM bill_date) = EXTRACT(MONTH FROM ADD_MONTHS(SYSDATE, -1))\n            AND EXTRACT(YEAR FROM bill_date) = EXTRACT(YEAR FROM ADD_MONTHS(SYSDATE, -1))\n            AND UPPER(bill_status) = 'PAID'";
$sqlHighestMonthBookings = "SELECT EXTRACT(YEAR FROM checkin_date) as year,\n                   EXTRACT(MONTH FROM checkin_date) as month,\n                   COUNT(*) as booking_count\n            FROM BOOKING\n            WHERE EXTRACT(YEAR FROM checkin_date) = EXTRACT(YEAR FROM SYSDATE)\n            GROUP BY EXTRACT(YEAR FROM checkin_date), EXTRACT(MONTH FROM checkin_date)\n            ORDER BY month";
$sqlRevenueTrendCurrent = "SELECT EXTRACT(MONTH FROM bill_date) as month,\n                   NVL(SUM(total_amount), 0) as revenue\n            FROM BILL\n            WHERE EXTRACT(YEAR FROM bill_date) = :currentYear\n            AND UPPER(bill_status) = 'PAID'\n            GROUP BY EXTRACT(MONTH FROM bill_date)\n            ORDER BY month";
$sqlRevenueTrendLast = "SELECT EXTRACT(MONTH FROM bill_date) as month,\n                   NVL(SUM(total_amount), 0) as revenue\n            FROM BILL\n            WHERE EXTRACT(YEAR FROM bill_date) = :lastYear\n            AND UPPER(bill_status) = 'PAID'\n            GROUP BY EXTRACT(MONTH FROM bill_date)\n            ORDER BY month";
$sqlRecentGuests = "SELECT * FROM (\n                SELECT guestID, guest_name, guest_phoneNo, guest_gender, guest_email, guest_type\n                FROM GUEST\n                ORDER BY guestID DESC\n            ) WHERE ROWNUM <= 5";

if ($conn) {
  // Get total guests
  $stmt = oci_parse($conn, $sqlCurrentGuests);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $totalGuests = $row['TOTAL'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get total staff
  $stmt = oci_parse($conn, $sqlTotalStaff);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $totalStaff = $row['TOTAL'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get pending bookings (bills not paid)
  $stmt = oci_parse($conn, $sqlPendingBookings);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $pendingBookings = $row['TOTAL'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get total homestays
  $stmt = oci_parse($conn, $sqlTotalHomestays);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $totalHomestays = $row['TOTAL'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get recent 5 guests (newest first)
  $stmt = oci_parse($conn, $sqlRecentGuests);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $type = $row['GUEST_TYPE'] ?? '';
      if (empty($type) || strtolower($type) === 'null') {
        $type = 'Regular';
      }

      $recentGuests[] = [
        'id' => $row['GUESTID'],
        'name' => $row['GUEST_NAME'],
        'phone' => $row['GUEST_PHONENO'] ?? 'N/A',
        'gender' => $row['GUEST_GENDER'] ?? 'N/A',
        'email' => $row['GUEST_EMAIL'],
        'type' => $type
      ];
    }
  }
  oci_free_statement($stmt);

  // Get homestay occupancy for current year
  $stmt = oci_parse($conn, $sqlOccupancyCurrentYear);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $homestayOccupancy[] = [
        'id' => $row['HOMESTAYID'],
        'name' => $row['HOMESTAY_NAME'],
        'current' => $row['CURRENT_BOOKINGS'] ?? 0,
        'last' => 0  // Initialize last year value
      ];
    }
  }
  oci_free_statement($stmt);

  // Get last year occupancy for comparison
  $stmt = oci_parse($conn, $sqlOccupancyLastYear);
  if (oci_execute($stmt)) {
    $lastYearData = [];
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $lastYearData[$row['HOMESTAYID']] = $row['LAST_BOOKINGS'] ?? 0;
    }
    // Update homestayOccupancy with last year data
    foreach ($homestayOccupancy as &$homestay) {
      if (isset($lastYearData[$homestay['id']])) {
        $homestay['last'] = $lastYearData[$homestay['id']];
      }
    }
    unset($homestay); // Break reference
  }
  oci_free_statement($stmt);

  // Get current month revenue
  $stmt = oci_parse($conn, $sqlMonthlyRevenueCurrent);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $monthlyRevenue = $row['REVENUE'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get last month revenue
  $stmt = oci_parse($conn, $sqlMonthlyRevenueLast);
  if (oci_execute($stmt)) {
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $lastMonthRevenue = $row['REVENUE'] ?? 0;
  }
  oci_free_statement($stmt);

  // Get highest month bookings data
  $highestMonthBookings = 0;
  $highestMonthIndex = 0;
  $highestYear = date('Y');
  $bookingsByMonth = [];

  // Get bookings count by month for all available years
  $stmt = oci_parse($conn, $sqlHighestMonthBookings);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $year = (int) $row['YEAR'];
      $month = (int) $row['MONTH'] - 1; // JavaScript months are 0-indexed
      $count = (int) $row['BOOKING_COUNT'];

      if (!isset($bookingsByMonth[$year])) {
        $bookingsByMonth[$year] = [];
      }
      $bookingsByMonth[$year][$month] = $count;

      // Track highest month
      if ($count > $highestMonthBookings) {
        $highestMonthBookings = $count;
        $highestMonthIndex = $month;
        $highestYear = $year;
      }
    }
  }
  oci_free_statement($stmt);

  // Get monthly revenue data for current year and last year
  $currentYear = (int) date('Y');
  $lastYear = $currentYear - 1;
  $currentYearRevenue = array_fill(0, 12, 0);
  $lastYearRevenue = array_fill(0, 12, 0);

  // Get current year revenue by month
  $stmt = oci_parse($conn, $sqlRevenueTrendCurrent);
  oci_bind_by_name($stmt, ':currentYear', $currentYear);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $month = (int) $row['MONTH'] - 1; // JavaScript months are 0-indexed
      $currentYearRevenue[$month] = (float) $row['REVENUE'];
    }
  }
  oci_free_statement($stmt);

  // Get last year revenue by month
  $stmt = oci_parse($conn, $sqlRevenueTrendLast);
  oci_bind_by_name($stmt, ':lastYear', $lastYear);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $month = (int) $row['MONTH'] - 1; // JavaScript months are 0-indexed
      $lastYearRevenue[$month] = (float) $row['REVENUE'];
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
  <title>Dashboard</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/dashboardStyle.css">
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
      <?php if (isManager()): ?>
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
      <?php endif; ?>
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
    <!-- Header -->
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
      <h1>Dashboard</h1>
      <div class="date-card">
        <i class='bxr  bx-calendar-alt'></i>
        <div class="date-line" aria-live="polite">Loading date…</div>
      </div>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="content1">
        <a href="guests.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number">
              <?php echo $totalGuests; ?>
            </div>
            <div class="subcard-text-line">
              <span class="subcard-text">Current Guests</span>
              <span class="info-icon-wrap">
                <button type="button" class="info-icon" aria-label="Show SQL query"
                  onclick="event.preventDefault(); event.stopPropagation();"><i
                    class='bxr  bx-info-circle'></i></button>
                <span class="sql-tooltip">
                  <pre><?php echo htmlspecialchars($sqlCurrentGuests); ?></pre>
                </span>
              </span>
            </div>
          </div>
          <i class='bxr  bxs-user'></i>
        </a>
        <a href="staff.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number">
              <?php echo $totalStaff; ?>
            </div>
            <div class="subcard-text-line">
              <span class="subcard-text">Total Staff</span>
              <span class="info-icon-wrap">
                <button type="button" class="info-icon" aria-label="Show SQL query"
                  onclick="event.preventDefault(); event.stopPropagation();"><i
                    class='bxr  bx-info-circle'></i></button>
                <span class="sql-tooltip">
                  <pre><?php echo htmlspecialchars($sqlTotalStaff); ?></pre>
                </span>
              </span>
            </div>
          </div>
          <i class='bxr  bxs-community'></i>
        </a>
        <a href="bookings.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number">
              <?php echo $pendingBookings; ?>
            </div>
            <div class="subcard-text-line">
              <span class="subcard-text">Pending Bookings</span>
              <span class="info-icon-wrap">
                <button type="button" class="info-icon" aria-label="Show SQL query"
                  onclick="event.preventDefault(); event.stopPropagation();"><i
                    class='bxr  bx-info-circle'></i></button>
                <span class="sql-tooltip">
                  <pre><?php echo htmlspecialchars($sqlPendingBookings); ?></pre>
                </span>
              </span>
            </div>
          </div>
          <i class='bxr  bxs-calendar-heart'></i>
        </a>
        <a href="homestay.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number">
              <?php echo $totalHomestays; ?>
            </div>
            <div class="subcard-text-line">
              <span class="subcard-text">Total Homestay</span>
              <span class="info-icon-wrap">
                <button type="button" class="info-icon" aria-label="Show SQL query"
                  onclick="event.preventDefault(); event.stopPropagation();"><i
                    class='bxr  bx-info-circle'></i></button>
                <span class="sql-tooltip">
                  <pre><?php echo htmlspecialchars($sqlTotalHomestays); ?></pre>
                </span>
              </span>
            </div>
          </div>
          <i class='bxr  bxs-home-circle'></i>
        </a>
      </div>
      <div class="content2">
        <div class="sub-content2-1">
          <div class="chart-header">
            <p>Occupancy by Homestay</p>
            <span class="info-icon-wrap">
              <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
              <span class="sql-tooltip"><pre>Current year:
<?php echo htmlspecialchars($sqlOccupancyCurrentYear); ?>

Last year:
<?php echo htmlspecialchars($sqlOccupancyLastYear); ?></pre></span>
            </span>
          </div>
          <div class="bar-chart-wrapper">
            <canvas id="houseComparisonChart" aria-label="House occupancy comparison chart"></canvas>
          </div>
        </div>
        <div class="sub-content2-2">
          <div class="chart-header">
            <p>House Distribution</p>
            <span class="info-icon-wrap">
              <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
              <span class="sql-tooltip"><pre>Current year:
<?php echo htmlspecialchars($sqlOccupancyCurrentYear); ?>

Last year:
<?php echo htmlspecialchars($sqlOccupancyLastYear); ?></pre></span>
            </span>
          </div>
          <div class="pie-chart-wrapper">
            <div class="pie-chart">
              <canvas id="housePieChart" aria-label="House distribution pie chart"></canvas>
            </div>
            <ul class="pie-legend" id="housePieLegend" aria-label="House legend"></ul>
          </div>
        </div>
      </div>
      <div class="content3">
        <div class="sub-content3-1">
          <div class="chart-header">
            <p>Monthly Revenue</p>
            <span class="info-icon-wrap">
              <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
              <span class="sql-tooltip"><pre>Current month:
<?php echo htmlspecialchars($sqlMonthlyRevenueCurrent); ?>

Last month:
<?php echo htmlspecialchars($sqlMonthlyRevenueLast); ?></pre></span>
            </span>
          </div>
          <div class="revenue-chart-container">
            <canvas id="monthlyRevenueChartOuter" aria-label="Current month revenue chart"></canvas>
            <canvas id="monthlyRevenueChartInner" aria-label="Last month revenue chart"></canvas>
            <div class="revenue-center-text">
              <div class="revenue-amount">RM <span id="totalRevenue">0</span></div>
              <div class="revenue-label">Current Month</div>
            </div>
          </div>
        </div>
        <div class="sub-content3-1">
          <div class="chart-header">
            <p>Highest Month Bookings</p>
            <span class="info-icon-wrap">
              <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
              <span class="sql-tooltip"><pre><?php echo htmlspecialchars($sqlHighestMonthBookings); ?></pre></span>
            </span>
          </div>
          <div class="highest-bookings-container">
            <img src="../../images/calendarShape.png" alt="Calendar shape" class="calendar-shape-image">
            <div class="highest-bookings-content">
              <div class="highest-month-name" id="highestMonthName">Loading...</div>
              <div class="highest-bookings-count" id="highestBookingsCount">0</div>
              <div class="highest-bookings-label">Total Bookings</div>
            </div>
          </div>
        </div>
        <div class="sub-content3-2">
          <div class="chart-header">
            <p>Revenue Trend</p>
            <span class="info-icon-wrap">
              <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
              <span class="sql-tooltip"><pre>Current year:
<?php echo htmlspecialchars($sqlRevenueTrendCurrent); ?>

Last year:
<?php echo htmlspecialchars($sqlRevenueTrendLast); ?></pre></span>
            </span>
          </div>
          <div class="revenue-line-chart-container">
            <canvas id="revenueLineChart" aria-label="Revenue by month line chart"></canvas>
          </div>
        </div>
      </div>
      <div class="content4">
        <div class="new-guests-table-container">
          <div class="table-header-row">
            <div class="chart-header">
              <p>Recent Guests</p>
              <span class="info-icon-wrap">
                <button type="button" class="info-icon" aria-label="Show SQL query" onclick="event.preventDefault(); event.stopPropagation();"><i class='bxr  bx-info-circle'></i></button>
                <span class="sql-tooltip"><pre><?php echo htmlspecialchars($sqlRecentGuests); ?></pre></span>
              </span>
            </div>
            <a href="guests.php" class="btn-manage">Manage Guests</a>
          </div>
          <table class="new-guests-table">
            <thead>
              <tr>
                <th>Guest ID</th>
                <th>Name</th>
                <th>Phone Number</th>
                <th>Gender</th>
                <th>Email</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentGuests)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 20px;">No guests found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentGuests as $guest): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($guest['id']); ?></td>
                    <td><?php echo htmlspecialchars($guest['name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['phone']); ?></td>
                    <td><?php echo htmlspecialchars($guest['gender']); ?></td>
                    <td><?php echo htmlspecialchars($guest['email']); ?></td>
                    <td><?php echo htmlspecialchars($guest['type']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
          </div>
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

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // PHP data to JavaScript
    const homestayOccupancy = <?php echo json_encode($homestayOccupancy); ?>;
    const currentMonthRevenue = <?php echo $monthlyRevenue; ?>;
    const lastMonthRevenue = <?php echo $lastMonthRevenue; ?>;
    const highestMonthIndex = <?php echo $highestMonthIndex; ?>;
    const highestMonthBookings = <?php echo $highestMonthBookings; ?>;
    const highestYear = <?php echo $highestYear; ?>;
    const currentYearRevenue = <?php echo json_encode($currentYearRevenue); ?>;
    const lastYearRevenue = <?php echo json_encode($lastYearRevenue); ?>;

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

    const dateLine = document.querySelector(".date-line");
    if (dateLine) {
      const now = new Date();
      const format = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "long", year: "numeric" });
      const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
      const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      dateLine.textContent = `${format.format(monthStart)} - ${format.format(monthEnd)}`;
    }

    const chartCanvas = document.getElementById("houseComparisonChart");
    if (chartCanvas && homestayOccupancy && homestayOccupancy.length > 0) {
      const houseLabels = homestayOccupancy.map(h => h.name || 'Unknown');
      const currentYearValues = homestayOccupancy.map(h => parseInt(h.current) || 0);
      const lastYearValues = homestayOccupancy.map(h => parseInt(h.last) || 0);
      const pieColors = ["#00bf63", "#ffde59", "#38b6ff", "#8c52ff", "#ff3131"];

      new Chart(chartCanvas, {
        type: "bar",
        data: {
          labels: houseLabels,
          datasets: [
            {
              label: "Current Year",
              data: currentYearValues,
              backgroundColor: "#00bf63",
              barThickness: 24,
            },
            {
              label: "Last Year",
              data: lastYearValues,
              backgroundColor: "#d9d9d9",
              barThickness: 24,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "top",
              labels: {
                boxWidth: 12,
                usePointStyle: true,
              },
            },
          },
          scales: {
            x: {
              grid: {
                display: false,
              },
            },
            y: {
              beginAtZero: true,
              grid: {
                color: "rgba(0,0,0,0.05)",
              },
            },
          },
        },
      });

      const pieCanvas = document.getElementById("housePieChart");
      const pieLegend = document.getElementById("housePieLegend");

      if (pieCanvas) {
        new Chart(pieCanvas, {
          type: "pie",
          data: {
            labels: houseLabels,
            datasets: [{
              data: currentYearValues,
              backgroundColor: pieColors.slice(0, houseLabels.length),
              borderColor: "#ffffff",
              borderWidth: 2,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false,
              },
            },
          },
        });

        // Update legend to show all homestays with current year data
        if (pieLegend) {
          pieLegend.innerHTML = houseLabels.map((label, index) => `
          <li>
            <span class="legend-dot" style="background-color:${pieColors[index]}"></span>
            <div class="legend-info">
              <strong>${label}</strong>
              <span>${currentYearValues[index]} bookings</span>
            </div>
          </li>
        `).join("");
        }
      }
    }

    // Monthly Revenue Double Circle Chart
    const monthlyRevenueOuterCanvas = document.getElementById("monthlyRevenueChartOuter");
    const monthlyRevenueInnerCanvas = document.getElementById("monthlyRevenueChartInner");
    const totalRevenueSpan = document.getElementById("totalRevenue");

    if (monthlyRevenueOuterCanvas && monthlyRevenueInnerCanvas) {
      // Update the center text with current month revenue
      if (totalRevenueSpan) {
        totalRevenueSpan.textContent = currentMonthRevenue.toLocaleString();
      }

      // Calculate max value for scaling (use the larger value)
      const maxValue = Math.max(currentMonthRevenue, lastMonthRevenue) * 1.2;

      // Outer ring - Current Month
      new Chart(monthlyRevenueOuterCanvas, {
        type: 'doughnut',
        data: {
          datasets: [{
            label: 'Current Month',
            data: [currentMonthRevenue, Math.max(0, maxValue - currentMonthRevenue)],
            backgroundColor: ['#00bf63', 'rgba(0, 191, 99, 0.15)'],
            borderWidth: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              display: false,
            },
            tooltip: {
              enabled: true,
              callbacks: {
                label: function (context) {
                  if (context.dataIndex === 0) {
                    return 'Current Month: RM ' + currentMonthRevenue.toLocaleString();
                  }
                  return '';
                },
                filter: function (tooltipItem) {
                  return tooltipItem.dataIndex === 0;
                }
              }
            }
          },
          cutout: '75%',
        }
      });

      // Inner ring - Last Month (scaled smaller to appear inside)
      new Chart(monthlyRevenueInnerCanvas, {
        type: 'doughnut',
        data: {
          datasets: [{
            label: 'Last Month',
            data: [lastMonthRevenue, Math.max(0, maxValue - lastMonthRevenue)],
            backgroundColor: ['#38b6ff', 'rgba(217, 217, 217, 0.15)'],
            borderWidth: 0,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              display: false,
            },
            tooltip: {
              enabled: true,
              callbacks: {
                label: function (context) {
                  if (context.dataIndex === 0) {
                    return 'Last Month: RM ' + lastMonthRevenue.toLocaleString();
                  }
                  return '';
                },
                filter: function (tooltipItem) {
                  return tooltipItem.dataIndex === 0;
                }
              }
            }
          },
          cutout: '80%',
        }
      });
    }

    // Highest Month Bookings
    const highestMonthName = document.getElementById("highestMonthName");
    const highestBookingsCount = document.getElementById("highestBookingsCount");

    if (highestMonthName && highestBookingsCount) {
      // Format month name
      const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
      ];

      const highestMonthNameText = monthNames[highestMonthIndex] || "N/A";

      // Update the display
      highestMonthName.textContent = highestMonthNameText;
      highestBookingsCount.textContent = highestMonthBookings || 0;
    }

    // Revenue-Month Line Chart
    const revenueLineChartCanvas = document.getElementById("revenueLineChart");

    if (revenueLineChartCanvas) {
      const currentYear = new Date().getFullYear();
      const lastYear = currentYear - 1;

      // Month labels
      const monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

      new Chart(revenueLineChartCanvas, {
        type: "line",
        data: {
          labels: monthLabels,
          datasets: [
            {
              label: currentYear.toString(),
              data: currentYearRevenue,
              borderColor: "#00bf63",
              backgroundColor: "rgba(0, 191, 99, 0.1)",
              borderWidth: 3,
              fill: false,
              tension: 0,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: "#00bf63",
              pointBorderColor: "#fff",
              pointBorderWidth: 2,
            },
            {
              label: lastYear.toString(),
              data: lastYearRevenue,
              borderColor: "#ff3131",
              backgroundColor: "rgba(255, 49, 49, 0.1)",
              borderWidth: 3,
              fill: false,
              tension: 0,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: "#ff3131",
              pointBorderColor: "#fff",
              pointBorderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "top",
              labels: {
                boxWidth: 12,
                usePointStyle: true,
                padding: 15,
                font: {
                  size: 12,
                },
              },
            },
            tooltip: {
              mode: "index",
              intersect: false,
              callbacks: {
                label: function (context) {
                  return context.dataset.label + ": RM " + context.parsed.y.toLocaleString();
                },
              },
            },
          },
          scales: {
            x: {
              grid: {
                display: false,
              },
              ticks: {
                font: {
                  size: 11,
                },
              },
            },
            y: {
              beginAtZero: false,
              grid: {
                color: "rgba(0,0,0,0.05)",
              },
              ticks: {
                callback: function (value) {
                  return "RM " + (value / 1000) + "k";
                },
                font: {
                  size: 11,
                },
              },
            },
          },
          interaction: {
            mode: "nearest",
            axis: "x",
            intersect: false,
          },
        },
      });
    }
  </script>
</body>

</html>