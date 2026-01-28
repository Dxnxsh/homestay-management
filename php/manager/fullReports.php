<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

// Fetch all bookings
$bookingsData = [];
$bookingSql = "SELECT b.bookingID, g.guest_name, h.homestay_name, 
                TO_CHAR(b.checkin_date, 'YYYY-MM-DD') AS checkin_date,
                TO_CHAR(b.checkout_date, 'YYYY-MM-DD') AS checkout_date,
                TRUNC(b.checkout_date - b.checkin_date) AS stay_nights,
                NVL(bi.total_amount, 0) AS amount,
                NVL(bi.bill_status, 'Pending') AS status
             FROM BOOKING b
             LEFT JOIN GUEST g ON b.guestID = g.guestID
             LEFT JOIN HOMESTAY h ON b.homestayID = h.homestayID
             LEFT JOIN BILL bi ON b.billNo = bi.billNo
             ORDER BY b.checkin_date DESC";
$stmt = oci_parse($conn, $bookingSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $bookingsData[] = $row;
}
oci_free_statement($stmt);

// Fetch all guests
$guestsData = [];
$guestSql = "SELECT g.guestID, g.guest_name, COUNT(b.bookingID) AS total_bookings
             FROM GUEST g
             LEFT JOIN BOOKING b ON g.guestID = b.guestID
             GROUP BY g.guestID, g.guest_name
             ORDER BY g.guest_name ASC";
$stmt = oci_parse($conn, $guestSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $guestsData[] = $row;
}
oci_free_statement($stmt);

// Fetch all bills
$billsData = [];
$billSql = "SELECT bi.billNo, g.guest_name, h.homestay_name, 
                   TO_CHAR(bi.bill_date, 'YYYY-MM-DD') AS bill_date,
                   bi.total_amount, bi.bill_status, bi.payment_method
            FROM BILL bi
            LEFT JOIN BOOKING b ON bi.billNo = b.billNo
            LEFT JOIN GUEST g ON b.guestID = g.guestID
            LEFT JOIN HOMESTAY h ON b.homestayID = h.homestayID
            ORDER BY bi.bill_date DESC";
$stmt = oci_parse($conn, $billSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $billsData[] = $row;
}
oci_free_statement($stmt);

// Fetch all properties
$propertiesData = [];
$propSql = "SELECT h.homestayID, h.homestay_name,
         COUNT(DISTINCT b.bookingID) AS total_bookings,
         NVL(SUM(bi.total_amount), 0) AS total_revenue
       FROM HOMESTAY h
       LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
       LEFT JOIN BILL bi ON b.billNo = bi.billNo AND bi.bill_status = 'Paid'
       GROUP BY h.homestayID, h.homestay_name
       ORDER BY total_revenue DESC";
$stmt = oci_parse($conn, $propSql);
oci_execute($stmt);
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $propertiesData[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Full Reports</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/summaryStyle.css?v=3">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .report-section {
      background: #fff;
      border: 1px solid #f0d5be;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.05);
      margin-bottom: 16px;
    }

    .report-section h2 {
      margin: 0 0 12px 0;
      font-size: 18px;
      font-weight: 700;
      color: #2d2a32;
    }

    .report-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .report-table thead {
      background: #faf3ec;
      border-bottom: 2px solid #f0d5be;
    }

    .report-table th {
      padding: 10px;
      text-align: left;
      font-weight: 700;
      color: #8a4d1c;
    }

    .report-table td {
      padding: 8px 10px;
      border-bottom: 1px solid #f4e1d1;
    }

    .report-table tbody tr:hover {
      background: #fffcf8;
    }

    .report-wrapper {
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 16px 24px 16px;
    }
  </style>
</head>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

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
          <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Manager'); ?></div>
          <div class="header-profile-job">Manager</div>
        </div>
      </div>
    </div>
    <div class="page-heading">
      <div>
        <h1>Full Reports</h1>
        <p class="subdued">Complete data export for all bookings, guests, billing, and properties</p>
      </div>
      <button onclick="generatePDF()" class="pill"
        style="cursor: pointer; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 2px solid #8a4d1c;">
        <i class='bx bx-download' style="font-size: 18px;"></i> Download PDF
      </button>
    </div><br>

    <div class="report-wrapper">
      <!-- Bookings Report -->
      <div class="report-section">
        <h2>All Bookings</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Guest</th>
              <th>Property</th>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>Nights</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($bookingsData)): ?>
              <?php foreach ($bookingsData as $b): ?>
                <tr>
                  <td><?php echo htmlspecialchars($b['BOOKINGID']); ?></td>
                  <td><?php echo htmlspecialchars($b['GUEST_NAME'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($b['HOMESTAY_NAME'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($b['CHECKIN_DATE']); ?></td>
                  <td><?php echo htmlspecialchars($b['CHECKOUT_DATE']); ?></td>
                  <td><?php echo (int) $b['STAY_NIGHTS']; ?></td>
                  <td>RM <?php echo number_format($b['AMOUNT'], 2); ?></td>
                  <td><span class="pill-sm"><?php echo htmlspecialchars($b['STATUS']); ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" style="text-align:center; color:#999;">No bookings found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Guests Report -->
      <div class="report-section">
        <h2>All Guests</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>Guest ID</th>
              <th>Name</th>
              <th>Total Bookings</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($guestsData)): ?>
              <?php foreach ($guestsData as $g): ?>
                <tr>
                  <td><?php echo htmlspecialchars($g['GUESTID']); ?></td>
                  <td><?php echo htmlspecialchars($g['GUEST_NAME']); ?></td>
                  <td><?php echo (int) $g['TOTAL_BOOKINGS']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align:center; color:#999;">No guests found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Billing Report -->
      <div class="report-section">
        <h2>All Bills</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>Bill No.</th>
              <th>Guest</th>
              <th>Property</th>
              <th>Bill Date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment Method</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($billsData)): ?>
              <?php foreach ($billsData as $bi): ?>
                <tr>
                  <td><?php echo htmlspecialchars($bi['BILLNO']); ?></td>
                  <td><?php echo htmlspecialchars($bi['GUEST_NAME'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($bi['HOMESTAY_NAME'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($bi['BILL_DATE']); ?></td>
                  <td>$<?php echo number_format($bi['TOTAL_AMOUNT'], 2); ?></td>
                  <td><span class="pill-sm"><?php echo htmlspecialchars($bi['BILL_STATUS']); ?></span></td>
                  <td><?php echo htmlspecialchars($bi['PAYMENT_METHOD'] ?? 'N/A'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align:center; color:#999;">No bills found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Properties Report -->
      <div class="report-section">
        <h2>All Properties</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>Property ID</th>
              <th>Name</th>
              <th>Location</th>
              <th>Total Bookings</th>
              <th>Total Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($propertiesData)): ?>
              <?php foreach ($propertiesData as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['HOMESTAYID']); ?></td>
                  <td><?php echo htmlspecialchars($p['HOMESTAY_NAME']); ?></td>
                  <td><?php echo htmlspecialchars($p['LOCATION'] ?? 'N/A'); ?></td>
                  <td><?php echo (int) $p['TOTAL_BOOKINGS']; ?></td>
                  <td>$<?php echo number_format($p['TOTAL_REVENUE'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align:center; color:#999;">No properties found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
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
        let arrowParent = e.target.parentElement.parentElement; //selecting main parent of arrow
        arrowParent.classList.toggle("showMenu");
      });
    }
    let sidebar = document.querySelector(".sidebar");
    let sidebarBtn = document.querySelector(".bx-menu");
    console.log(sidebarBtn);
    sidebarBtn.addEventListener("click", () => {
      sidebar.classList.toggle("close");
    });

    function generatePDF() {
      const element = document.querySelector('.report-wrapper');

      // Clone the element to modify it for PDF generation without affecting the UI
      const clone = element.cloneNode(true);

      // Add some specific print styles to the clone if needed
      clone.style.padding = '20px';
      clone.style.background = '#fff';

      const opt = {
        margin: [0.5, 0.5],
        filename: 'Full_Report_' + new Date().toISOString().slice(0, 10) + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
      };

      // Add a title to the clone for the PDF
      const titleDiv = document.createElement('div');
      titleDiv.style.marginBottom = '20px';
      titleDiv.style.textAlign = 'center';
      titleDiv.innerHTML = '<h1 style="color: #c5814b; margin-bottom: 5px;">Serena Sanctuary - Full Report</h1><p style="color: #666;">Generated on ' + new Date().toLocaleDateString() + '</p>';
      clone.insertBefore(titleDiv, clone.firstChild);

      html2pdf().set(opt).from(clone).toPdf().get('pdf').then(function (pdf) {
        window.open(pdf.output('bloburl'), '_blank');
      });
    }
  </script>
</body>

</html>