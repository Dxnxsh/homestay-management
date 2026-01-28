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

// Fetch all homestays with aggregated booking and revenue info
// Note: revenue only includes PAID bills to match accounting
$propertiesData = [];
$propSql = "SELECT h.homestayID,
                   h.homestay_name,
                   h.homestay_address,
                   h.office_phoneNo,
                   h.rent_price,
                   h.staffID,
                   COUNT(DISTINCT b.bookingID) AS total_bookings,
                   NVL(SUM(bi.total_amount), 0) AS total_revenue
            FROM HOMESTAY h
            LEFT JOIN BOOKING b ON h.homestayID = b.homestayID
            LEFT JOIN BILL bi ON b.billNo = bi.billNo
                               AND bi.bill_status = 'Paid'
            GROUP BY h.homestayID,
                     h.homestay_name,
                     h.homestay_address,
                     h.office_phoneNo,
                     h.rent_price,
                     h.staffID
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
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/fullReportsStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .report-section {
      background: #fff;
      border: 1px solid #f0d5be;
      padding: 24px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.05);
      margin-bottom: 24px;
    }

    .report-section h2 {
      margin: 0 0 16px 0;
      font-size: 20px;
      font-weight: 700;
      color: #2d2a32;
    }

    .report-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 15px;
    }

    .report-table thead {
      background: #faf3ec;
      border-bottom: 2px solid #f0d5be;
    }

    .report-table th {
      padding: 14px 16px;
      text-align: left;
      font-weight: 700;
      font-size: 15px;
      color: #8a4d1c;
    }

    .report-table td {
      padding: 12px 16px;
      border-bottom: 1px solid #f4e1d1;
    }

    .report-table tbody tr:hover {
      background: #fffcf8;
    }

    .report-wrapper {
      width: 100%;
      padding: 0 24px 32px 24px;
      margin-bottom: 80px;
      box-sizing: border-box;
    }

    .content-reports {
      padding: 0 24px 12px 24px;
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
        <h1>Full Reports</h1>
        <p class="subdued">Complete data export for all bookings, guests, billing, and properties</p>
      </div>
    </div>

    <div class="content-reports">
      <div class="button-container">
        <button type="button" class="btn-add" onclick="generatePDF()">
          <i class='bx bx-download'></i> Download PDF
        </button>
        <div class="spinner-container">
          <div class="spinner-select-wrapper">
            <select class="spinner-select" id="sortOrder">
              <option value="">Select Order</option>
              <option value="ascending">Ascending</option>
              <option value="descending">Descending</option>
            </select>
          </div>
          <div class="spinner-select-wrapper">
            <select class="spinner-select" id="sortBy">
              <option value="">Sort By</option>
              <option value="id">By ID</option>
              <option value="name">By Name</option>
              <option value="date">By Date</option>
            </select>
          </div>
        </div>
        <div class="filter-search-row">
          <div class="filter-block">
            <div class="filter-container">
              <select id="reportFilter" name="report-filter" class="filter-select">
                <option value="all">All Reports</option>
                <option value="bookings">Bookings</option>
                <option value="guests">Guests</option>
                <option value="bills">Bills</option>
                <option value="properties">Homestay</option>
              </select>
            </div>
          </div>
          <form class="search-form" onsubmit="return false;">
            <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="id">ID</option>
                <option value="name">Name</option>
              </select>
            </div>
            <div class="search-input">
              <input id="searchReports" type="text" placeholder="Search Reports" />
            </div>
            <div class="search-button">
              <button class="btn-search" type="button" aria-label="Search"></button>
            </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="report-wrapper">
      <!-- Bookings Report -->
      <div class="report-section" data-report="bookings">
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
      <div class="report-section" data-report="guests">
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
      <div class="report-section" data-report="bills">
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
                  <td>RM <?php echo number_format($bi['TOTAL_AMOUNT'], 2); ?></td>
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

      <!-- Homestay Report -->
      <div class="report-section" data-report="properties">
        <h2>All Homestay</h2>
        <table class="report-table">
          <thead>
            <tr>
              <th>Homestay ID</th>
              <th>Name</th>
              <th>Address</th>
              <th>Office Phone No.</th>
              <th>Rent Price (per night)</th>
              <th>Staff ID</th>
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
                  <td><?php echo htmlspecialchars($p['HOMESTAY_ADDRESS']); ?></td>
                  <td><?php echo htmlspecialchars($p['OFFICE_PHONENO']); ?></td>
                  <td>RM <?php echo number_format($p['RENT_PRICE'], 2); ?></td>
                  <td><?php echo htmlspecialchars($p['STAFFID']); ?></td>
                  <td><?php echo (int) $p['TOTAL_BOOKINGS']; ?></td>
                  <td>RM <?php echo number_format($p['TOTAL_REVENUE'], 2); ?></td>
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

    // Filter and sort report tables (like guests page)
    const sortOrderSelect = document.getElementById("sortOrder");
    const sortBySelect = document.getElementById("sortBy");
    const reportFilterSelect = document.getElementById("reportFilter");
    const searchTypeSelect = document.getElementById("searchType");
    const searchInput = document.getElementById("searchReports");
    const searchBtn = document.querySelector(".content-reports .btn-search");

    function getFilteredReportSections() {
      const selected = (reportFilterSelect && reportFilterSelect.value) ? reportFilterSelect.value : "all";
      const sections = Array.from(document.querySelectorAll(".report-wrapper .report-section"));
      if (selected === "all") return sections;
      return sections.filter(function (sec) { return (sec.dataset && sec.dataset.report) === selected; });
    }

    function applyReportFilter() {
      const selected = (reportFilterSelect && reportFilterSelect.value) ? reportFilterSelect.value : "all";
      document.querySelectorAll(".report-wrapper .report-section").forEach(function (sec) {
        const key = (sec.dataset && sec.dataset.report) || "";
        sec.style.display = (selected === "all" || key === selected) ? "" : "none";
      });
      // Re-apply search and sort to the currently visible section(s)
      applySearch();
      applySort();
    }

    function getSortColumnIndex(sortBy, thCount) {
      if (sortBy === "id") return 0;
      if (sortBy === "name") return 1;
      if (sortBy === "date") {
        if (thCount >= 4) return 3;
        if (thCount >= 3) return 2;
        return 0;
      }
      return 0;
    }

    function applySort() {
      const order = sortOrderSelect.value;
      const sortBy = sortBySelect.value;
      if (!order || !sortBy) return;
      getFilteredReportSections().forEach(function (section) {
        if (section.style.display === "none") return;
        const table = section.querySelector(".report-table");
        if (!table) return;
        const tbody = table.tbody || table.querySelector("tbody");
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll("tr")).filter(function (tr) {
          return !tr.cells.length || tr.cells[0].colSpan !== tr.cells.length;
        });
        const colCount = rows[0] ? rows[0].cells.length : 0;
        const colIndex = getSortColumnIndex(sortBy, colCount);
        rows.sort(function (a, b) {
          const aVal = (a.cells[colIndex] && a.cells[colIndex].textContent.trim()) || "";
          const bVal = (b.cells[colIndex] && b.cells[colIndex].textContent.trim()) || "";
          let cmp = 0;
          if (aVal !== bVal) cmp = aVal.localeCompare(bVal, undefined, { numeric: true });
          return order === "descending" ? -cmp : cmp;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
      });
    }

    function applySearch() {
      const term = (searchInput.value || "").toLowerCase().trim();
      const searchBy = searchTypeSelect.value;
      getFilteredReportSections().forEach(function (section) {
        if (section.style.display === "none") return;
        const tbody = section.querySelector(".report-table tbody");
        if (!tbody) return;
        Array.from(tbody.querySelectorAll("tr")).forEach(function (tr) {
        if (tr.cells.length === 0 || (tr.cells.length === 1 && tr.cells[0].colSpan > 1)) {
          tr.style.display = term ? "none" : "";
          return;
        }
        if (!term) {
          tr.style.display = "";
          return;
        }
        let match = false;
        if (searchBy === "all") {
          for (let i = 0; i < tr.cells.length; i++) {
            if (tr.cells[i].textContent.toLowerCase().indexOf(term) !== -1) { match = true; break; }
          }
        } else {
          let colIndex = 0;
          if (searchBy === "id") colIndex = 0;
          else if (searchBy === "name" || searchBy === "guest") colIndex = 1;
          else if (searchBy === "property") colIndex = Math.min(2, tr.cells.length - 1);
          if (tr.cells[colIndex] && tr.cells[colIndex].textContent.toLowerCase().indexOf(term) !== -1) match = true;
        }
        tr.style.display = match ? "" : "none";
        });
      });
    }

    if (sortOrderSelect) sortOrderSelect.addEventListener("change", applySort);
    if (sortBySelect) sortBySelect.addEventListener("change", applySort);
    if (reportFilterSelect) reportFilterSelect.addEventListener("change", applyReportFilter);
    if (searchInput) searchInput.addEventListener("input", applySearch);
    if (searchInput) searchInput.addEventListener("keyup", function (e) { if (e.key === "Enter") applySearch(); });
    if (searchBtn) searchBtn.addEventListener("click", applySearch);

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

      // Add PDF header: logo top left, generated date top right
      const pdfHeader = document.createElement('div');
      pdfHeader.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; width: 100%;';
      const logoImg = document.createElement('img');
      logoImg.src = '../../images/logoTitle.png';
      logoImg.alt = 'Serena Sanctuary';
      logoImg.style.cssText = 'height: 64px; width: auto;';
      const dateSpan = document.createElement('span');
      dateSpan.style.cssText = 'color: #666; font-size: 14px; font-weight: 500;';
      dateSpan.textContent = 'Generated on ' + new Date().toLocaleDateString();
      pdfHeader.appendChild(logoImg);
      pdfHeader.appendChild(dateSpan);
      clone.insertBefore(pdfHeader, clone.firstChild);

      // Title div for PDF
      const titleDiv = document.createElement('div');
      titleDiv.style.marginBottom = '20px';
      titleDiv.style.textAlign = 'center';
      titleDiv.innerHTML = '<h1 style="color: #c5814b; margin-bottom: 5px;">Serena Sanctuary - Full Report</h1>';
      clone.insertBefore(titleDiv, clone.firstChild.nextSibling);

      html2pdf().set(opt).from(clone).toPdf().get('pdf').then(function (pdf) {
        window.open(pdf.output('bloburl'), '_blank');
      });
    }
  </script>
</body>

</html>