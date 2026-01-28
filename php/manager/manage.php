<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

// Get database connection
$conn = getDBConnection();
if (!$conn) {
  die("Database connection failed. Please try again later.");
}

// Fetch statistics
// Total Guests
$guestCountSql = "SELECT COUNT(*) as total FROM GUEST";
$guestStmt = oci_parse($conn, $guestCountSql);
oci_execute($guestStmt);
$guestRow = oci_fetch_array($guestStmt, OCI_ASSOC);
$totalGuests = $guestRow['TOTAL'];
oci_free_statement($guestStmt);

// Total Staff
$staffCountSql = "SELECT COUNT(*) as total FROM STAFF";
$staffStmt = oci_parse($conn, $staffCountSql);
oci_execute($staffStmt);
$staffRow = oci_fetch_array($staffStmt, OCI_ASSOC);
$totalStaff = $staffRow['TOTAL'];
oci_free_statement($staffStmt);

// Total Homestays
$homestayCountSql = "SELECT COUNT(*) as total FROM HOMESTAY";
$homestayStmt = oci_parse($conn, $homestayCountSql);
oci_execute($homestayStmt);
$homestayRow = oci_fetch_array($homestayStmt, OCI_ASSOC);
$totalHomestays = $homestayRow['TOTAL'];
oci_free_statement($homestayStmt);



// Full Time and Part Time Staff counts
$fullTimeSql = "SELECT COUNT(*) as total FROM FULL_TIME";
$fullTimeStmt = oci_parse($conn, $fullTimeSql);
oci_execute($fullTimeStmt);
$fullTimeRow = oci_fetch_array($fullTimeStmt, OCI_ASSOC);
$fullTimeStaff = $fullTimeRow['TOTAL'];
oci_free_statement($fullTimeStmt);

$partTimeSql = "SELECT COUNT(*) as total FROM PART_TIME";
$partTimeStmt = oci_parse($conn, $partTimeSql);
oci_execute($partTimeStmt);
$partTimeRow = oci_fetch_array($partTimeStmt, OCI_ASSOC);
$partTimeStaff = $partTimeRow['TOTAL'];
oci_free_statement($partTimeStmt);

// Recent 5 Guests
$recentGuestsSql = "SELECT * FROM (SELECT guestID, guest_name, guest_phoneNo, guest_gender, guest_email, guest_type FROM GUEST ORDER BY guestID DESC) WHERE ROWNUM <= 5";
$recentGuestsStmt = oci_parse($conn, $recentGuestsSql);
oci_execute($recentGuestsStmt);
$recentGuests = [];
while ($row = oci_fetch_array($recentGuestsStmt, OCI_ASSOC)) {
  $type = $row['GUEST_TYPE'] ?? '';
  if (empty($type) || strtolower($type) === 'null') {
    $row['GUEST_TYPE'] = 'Regular';
  }
  $recentGuests[] = $row;
}
oci_free_statement($recentGuestsStmt);

// Recent 5 Staff
$recentStaffSql = "SELECT * FROM (SELECT staffID, staff_name, staff_phoneNo, staff_email, staff_type, managerID FROM STAFF ORDER BY staffID DESC) WHERE ROWNUM <= 5";
$recentStaffStmt = oci_parse($conn, $recentStaffSql);
oci_execute($recentStaffStmt);
$recentStaff = [];
while ($row = oci_fetch_array($recentStaffStmt, OCI_ASSOC)) {
  $recentStaff[] = $row;
}
oci_free_statement($recentStaffStmt);

// All Homestays
$homestaysSql = "SELECT homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID FROM HOMESTAY";
$homestaysStmt = oci_parse($conn, $homestaysSql);
oci_execute($homestaysStmt);
$homestays = [];
while ($row = oci_fetch_array($homestaysStmt, OCI_ASSOC)) {
  $homestays[] = $row;
}
oci_free_statement($homestaysStmt);

// Homestay Revenue (total from bills per homestay)
$homestayRevenue = [];
foreach ($homestays as $homestay) {
  $revenueSql = "SELECT NVL(SUM(b.total_amount), 0) as revenue 
                   FROM BILL b 
                   JOIN BOOKING bk ON b.guestID = bk.guestID 
                   WHERE bk.homestayID = :homestayID";
  $revenueStmt = oci_parse($conn, $revenueSql);
  $hId = $homestay['HOMESTAYID'];
  oci_bind_by_name($revenueStmt, ':homestayID', $hId);
  oci_execute($revenueStmt);
  $revenueRow = oci_fetch_array($revenueStmt, OCI_ASSOC);
  $homestayRevenue[$homestay['HOMESTAYID']] = $revenueRow['REVENUE'];
  oci_free_statement($revenueStmt);
}

// Homestay total guests this year
$homestayGuestCount = [];
foreach ($homestays as $homestay) {
  $guestCountSql = "SELECT COUNT(*) as total 
                      FROM BOOKING 
                      WHERE homestayID = :homestayID 
                      AND EXTRACT(YEAR FROM checkin_date) = EXTRACT(YEAR FROM SYSDATE)";
  $guestCountStmt = oci_parse($conn, $guestCountSql);
  $hId = $homestay['HOMESTAYID'];
  oci_bind_by_name($guestCountStmt, ':homestayID', $hId);
  oci_execute($guestCountStmt);
  $guestCountRow = oci_fetch_array($guestCountStmt, OCI_ASSOC);
  $homestayGuestCount[$homestay['HOMESTAYID']] = $guestCountRow['TOTAL'];
  oci_free_statement($guestCountStmt);
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Manage</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/manageStyle.css">
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
    <!-- Header -->
    <div class="home-content">
      <i class='bx bx-menu'></i>
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
      <h1>Manage</h1>
      <div class="date-card">
        <i class='bxr  bx-calendar-alt'></i>
        <div class="date-line" aria-live="polite">Loading date…</div>
      </div>
    </div>
    <!-- Content -->
    <div class="content">
      <!-- Overview Stats -->
      <div class="content1">
        <a href="guests.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number"><?php echo $totalGuests; ?></div>
            <div class="subcard-text">Total Guests</div>
          </div>
          <i class='bxr  bxs-user'></i>
        </a>
        <?php if (isManager()): ?>
          <a href="staff.php" class="sub-content1">
            <div class="subcard">
              <div class="subcard-number"><?php echo $totalStaff; ?></div>
              <div class="subcard-text">Total Staff</div>
            </div>
            <i class='bxr  bxs-community'></i>
          </a>
        <?php endif; ?>
        <a href="homestay.php" class="sub-content1">
          <div class="subcard">
            <div class="subcard-number"><?php echo $totalHomestays; ?></div>
            <div class="subcard-text">Total Homestay</div>
          </div>
          <i class='bxr  bxs-home-circle'></i>
        </a>
      </div>

      <!-- Guests Section -->
      <div class="content2">
        <div class="sub-content2-2">

          <?php if (isManager()): ?>
            <a href="staff.php" class="sub-content2-card">
              <div class="subcard">
                <div class="subcard-number"><?php echo $fullTimeStaff; ?></div>
                <div class="subcard-text">Full Time Staff</div>
              </div>
              <div class="mini-chart-container">
                <canvas id="miniChart2"></canvas>
              </div>
            </a>
            <a href="staff.php" class="sub-content2-card">
              <div class="subcard">
                <div class="subcard-number"><?php echo $partTimeStaff; ?></div>
                <div class="subcard-text">Part Time Staff</div>
              </div>
              <div class="mini-chart-container">
                <canvas id="miniChart3"></canvas>
              </div>
            </a>
          <?php endif; ?>
        </div>
        <div class="sub-content2-1">
          <p>Total Monthly Guests</p>
          <div class="chart-container">
            <canvas id="guestsChart" aria-label="Guests distribution chart"></canvas>
          </div>
        </div>
      </div>

      <!-- Staff & Homestay Section -->
      <div class="content3">
        <div class="sub-content3-1">
          <p>Staff Distribution</p>
          <div class="revenue-chart-container">
            <canvas id="staffChart" aria-label="Staff distribution chart"></canvas>
            <div class="revenue-center-text">
              <div class="revenue-amount"><span id="totalStaff"><?php echo $totalStaff; ?></span></div>
              <div class="revenue-label">Total Staff</div>
            </div>
          </div>
        </div>
        <div class="sub-content3-3-wrapper">
          <div class="sub-content3-3">
            <p>Homestay Revenue</p>
            <div class="revenue-cards-container">
              <?php foreach ($homestays as $homestay): ?>
                <div class="revenue-card">
                  <div class="revenue-card-content">
                    <div class="revenue-image-container">
                      <img src="../../images/houseIcon.png" alt="House Icon" class="revenue-house-icon">
                      <div class="revenue-overlay">
                        <div class="revenue-card-amount">RM <span class="revenue-value"
                            data-target="<?php echo number_format($homestayRevenue[$homestay['HOMESTAYID']], 0, '', ''); ?>">0</span>
                        </div>
                      </div>
                    </div>
                    <div class="revenue-card-label"><?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="sub-content3-4">
            <p>Total Guests (This Year)</p>
            <div class="guests-cards-container">
              <?php foreach ($homestays as $homestay): ?>
                <div class="guests-card">
                  <div class="guests-card-content">
                    <i class='bx bx-group guests-icon'></i>
                    <div class="guests-card-amount"><span class="guests-value"
                        data-target="<?php echo $homestayGuestCount[$homestay['HOMESTAYID']]; ?>">0</span></div>
                    <div class="guests-card-label"><?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Guests Table -->
      <div class="content4">
        <div class="new-guests-table-container">
          <div class="table-header-row">
            <p>Recent Guests</p>
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
                  <td colspan="6" style="text-align: center;">No guests found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentGuests as $guest): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($guest['GUESTID']); ?></td>
                    <td><?php echo htmlspecialchars($guest['GUEST_NAME']); ?></td>
                    <td><?php echo htmlspecialchars($guest['GUEST_PHONENO']); ?></td>
                    <td><?php echo htmlspecialchars($guest['GUEST_GENDER']); ?></td>
                    <td><?php echo htmlspecialchars($guest['GUEST_EMAIL']); ?></td>
                    <td><?php echo htmlspecialchars($guest['GUEST_TYPE']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Staff Table -->
      <?php if (isManager()): ?>
        <div class="content4">
          <div class="new-guests-table-container">
            <div class="table-header-row">
              <p>Recent Staff</p>
              <a href="staff.php" class="btn-manage">Manage Staff</a>
            </div>
            <table class="new-guests-table">
              <thead>
                <tr>
                  <th>Staff ID</th>
                  <th>Name</th>
                  <th>Phone No.</th>
                  <th>Email</th>
                  <th>Type</th>
                  <th>Manager ID</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recentStaff)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center;">No staff found</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentStaff as $staff): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($staff['STAFFID']); ?></td>
                      <td><?php echo htmlspecialchars($staff['STAFF_NAME']); ?></td>
                      <td><?php echo htmlspecialchars($staff['STAFF_PHONENO']); ?></td>
                      <td><?php echo htmlspecialchars($staff['STAFF_EMAIL']); ?></td>
                      <td><?php echo htmlspecialchars($staff['STAFF_TYPE']); ?></td>
                      <td><?php echo !empty($staff['MANAGERID']) ? htmlspecialchars($staff['MANAGERID']) : '-'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Homestay Table -->
      <div class="content4">
        <div class="new-guests-table-container">
          <div class="table-header-row">
            <p>Homestay</p>
            <a href="homestay.php" class="btn-manage">Manage Homestay</a>
          </div>
          <table class="new-guests-table">
            <thead>
              <tr>
                <th>Homestay ID</th>
                <th>Homestay Name</th>
                <th>Address</th>
                <th>Office Phone</th>
                <th>Rent Price (per night)</th>
                <th>Staff ID</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($homestays)): ?>
                <tr>
                  <td colspan="6" style="text-align: center;">No homestays found</td>
                </tr>
              <?php else: ?>
                <?php foreach ($homestays as $homestay): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($homestay['HOMESTAYID']); ?></td>
                    <td><?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?></td>
                    <td><?php echo htmlspecialchars($homestay['HOMESTAY_ADDRESS']); ?></td>
                    <td><?php echo htmlspecialchars($homestay['OFFICE_PHONENO']); ?></td>
                    <td>RM <?php echo number_format($homestay['RENT_PRICE'], 2); ?></td>
                    <td><?php echo htmlspecialchars($homestay['STAFFID']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
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

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    // Date display
    const dateLine = document.querySelector(".date-line");
    if (dateLine) {
      const now = new Date();
      const format = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "long", year: "numeric" });
      const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
      const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      dateLine.textContent = `${format.format(monthStart)} - ${format.format(monthEnd)}`;
    }

    // Guests Chart - Bar Chart
    const guestsChartCanvas = document.getElementById("guestsChart");
    if (guestsChartCanvas) {
      const monthLabels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
      const guestsData = [45, 52, 48, 61, 58, 67, 73, 65, 59, 54, 49, 42];

      new Chart(guestsChartCanvas, {
        type: "bar",
        data: {
          labels: monthLabels,
          datasets: [{
            label: "New Guests",
            data: guestsData,
            backgroundColor: "#00bf63",
            barThickness: 24,
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
    }



    const miniChart2 = document.getElementById("miniChart2");
    if (miniChart2) {
      new Chart(miniChart2, {
        type: "line",
        data: {
          labels: ["", "", "", "", "", "", ""],
          datasets: [{
            data: [8, 3, 5, 4, 3, 10, 7],
            borderColor: "#38b6ff",
            backgroundColor: "rgba(56, 182, 255, 0.1)",
            borderWidth: 2,
            fill: true,
            tension: 0,
            pointRadius: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false },
          },
          scales: {
            x: { display: false },
            y: { display: false },
          },
        },
      });
    }

    const miniChart3 = document.getElementById("miniChart3");
    if (miniChart3) {
      new Chart(miniChart3, {
        type: "line",
        data: {
          labels: ["", "", "", "", "", "", ""],
          datasets: [{
            data: [4, 5, 14, 2, 13, 3, 12],
            borderColor: "#ffde59",
            backgroundColor: "rgba(255, 222, 89, 0.1)",
            borderWidth: 2,
            fill: true,
            tension: 0,
            pointRadius: 0,
          }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false },
          },
          scales: {
            x: { display: false },
            y: { display: false },
          },
        },
      });
    }

    // Staff Chart - Doughnut Chart
    const staffChartCanvas = document.getElementById("staffChart");
    if (staffChartCanvas) {
      const staffTypes = ["Full Time", "Part Time"];
      const staffValues = [<?php echo $fullTimeStaff; ?>, <?php echo $partTimeStaff; ?>];

      new Chart(staffChartCanvas, {
        type: 'doughnut',
        data: {
          labels: staffTypes,
          datasets: [{
            data: staffValues,
            backgroundColor: ['#38b6ff', '#ffde59'],
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
          },
          cutout: '75%',
        }
      });
    }

    // Animated Revenue Cards
    function animateValue(element, start, end, duration) {
      let startTimestamp = null;
      const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current.toLocaleString();
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      };
      window.requestAnimationFrame(step);
    }

    const revenueValues = document.querySelectorAll('.revenue-value');
    revenueValues.forEach((element) => {
      const target = parseInt(element.getAttribute('data-target'));
      animateValue(element, 0, target, 2000);
    });

    const guestsValues = document.querySelectorAll('.guests-value');
    guestsValues.forEach((element) => {
      const target = parseInt(element.getAttribute('data-target'));
      animateValue(element, 0, target, 2000);
    });
  </script>
</body>

</html>