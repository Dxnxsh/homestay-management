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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if ($_POST['action'] === 'add') {
    $checkIn = $_POST['checkIn'];
    $checkOut = $_POST['checkOut'];
    $numAdults = $_POST['numAdults'];
    $numChildren = $_POST['numChildren'];
    $deposit = $_POST['deposit'];
    $homestayID = $_POST['homestayId'];
    $guestID = $_POST['guestId'];
    $staffID = $_POST['staffId'];
    $billNo = !empty($_POST['billNo']) ? $_POST['billNo'] : null;

    $sql = "INSERT INTO BOOKING (checkin_date, checkout_date, num_adults, num_children, deposit_amount, homestayID, guestID, staffID, billNo)
        VALUES (TO_DATE(:checkIn, 'YYYY-MM-DD'), TO_DATE(:checkOut, 'YYYY-MM-DD'), :numAdults, :numChildren, :deposit, :homestayID, :guestID, :staffID, :billNo)";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ':checkIn', $checkIn);
    oci_bind_by_name($stmt, ':checkOut', $checkOut);
    oci_bind_by_name($stmt, ':numAdults', $numAdults);
    oci_bind_by_name($stmt, ':numChildren', $numChildren);
    oci_bind_by_name($stmt, ':deposit', $deposit);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);
    oci_bind_by_name($stmt, ':guestID', $guestID);
    oci_bind_by_name($stmt, ':staffID', $staffID);
    oci_bind_by_name($stmt, ':billNo', $billNo);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Booking added successfully']);
    } else {
      $error = oci_error($stmt);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'update') {
    $bookingID = $_POST['bookingId'];
    $checkIn = $_POST['checkIn'];
    $checkOut = $_POST['checkOut'];
    $numAdults = $_POST['numAdults'];
    $numChildren = $_POST['numChildren'];
    $deposit = $_POST['deposit'];
    $homestayID = $_POST['homestayId'];
    $guestID = $_POST['guestId'];
    $staffID = $_POST['staffId'];
    $billNo = !empty($_POST['billNo']) ? $_POST['billNo'] : null;

    $sql = "UPDATE BOOKING SET 
        checkin_date = TO_DATE(:checkIn, 'YYYY-MM-DD'),
        checkout_date = TO_DATE(:checkOut, 'YYYY-MM-DD'),
        num_adults = :numAdults,
        num_children = :numChildren,
        deposit_amount = :deposit,
        homestayID = :homestayID,
        guestID = :guestID,
        staffID = :staffID,
        billNo = :billNo
        WHERE bookingID = :id";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':checkIn', $checkIn);
    oci_bind_by_name($stmt, ':checkOut', $checkOut);
    oci_bind_by_name($stmt, ':numAdults', $numAdults);
    oci_bind_by_name($stmt, ':numChildren', $numChildren);
    oci_bind_by_name($stmt, ':deposit', $deposit);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);
    oci_bind_by_name($stmt, ':guestID', $guestID);
    oci_bind_by_name($stmt, ':staffID', $staffID);
    oci_bind_by_name($stmt, ':billNo', $billNo);
    oci_bind_by_name($stmt, ':id', $bookingID);


    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $bookingID = $_POST['bookingId'];

    // Check if booking is linked to a bill
    $checkSql = "SELECT billNo FROM BOOKING WHERE bookingID = :id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':id', $bookingID);
    oci_execute($checkStmt);
    $row = oci_fetch_array($checkStmt, OCI_ASSOC);
    $linkedBillNo = ($row && $row['BILLNO']) ? $row['BILLNO'] : null;
    oci_free_statement($checkStmt);

    // Delete the booking first
    $sql = "DELETE FROM BOOKING WHERE bookingID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $bookingID);

    if (oci_execute($stmt)) {
      // If booking deletion successful, check if we need to delete a linked bill
      if ($linkedBillNo) {
        $deleteBillSql = "DELETE FROM BILL WHERE billNo = :billNo";
        $deleteBillStmt = oci_parse($conn, $deleteBillSql);
        oci_bind_by_name($deleteBillStmt, ':billNo', $linkedBillNo);
        if (!oci_execute($deleteBillStmt)) {
          // Ideally we should log this, but for now we just proceed as the main action (booking delete) succeeded.
          // Or we could return a warning message.
        }
        oci_free_statement($deleteBillStmt);
      }

      echo json_encode(['success' => true, 'message' => 'Booking and linked bill (if any) deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

// Fetch all bookings
$sql = "SELECT bookingID, TO_CHAR(checkin_date, 'YYYY-MM-DD') AS checkin_date, TO_CHAR(checkout_date, 'YYYY-MM-DD') AS checkout_date,
    num_adults, num_children, deposit_amount, homestayID, guestID, staffID, billNo
    FROM BOOKING ORDER BY bookingID DESC";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$bookings = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $bookings[] = $row;
}
oci_free_statement($stmt);

// Fetch dropdown data
$guests = [];
$guestSql = "SELECT guestID, guest_name FROM GUEST ORDER BY guestID";
$guestStmt = oci_parse($conn, $guestSql);
oci_execute($guestStmt);
while ($row = oci_fetch_array($guestStmt, OCI_ASSOC)) {
  $guests[] = $row;
}
oci_free_statement($guestStmt);

$homestays = [];
$homestaySql = "SELECT homestayID, homestay_name FROM HOMESTAY ORDER BY homestayID";
$homestayStmt = oci_parse($conn, $homestaySql);
oci_execute($homestayStmt);
while ($row = oci_fetch_array($homestayStmt, OCI_ASSOC)) {
  $homestays[] = $row;
}
oci_free_statement($homestayStmt);

$staff = [];
$staffSql = "SELECT staffID, staff_name FROM STAFF ORDER BY staffID";
$staffStmt = oci_parse($conn, $staffSql);
oci_execute($staffStmt);
while ($row = oci_fetch_array($staffStmt, OCI_ASSOC)) {
  $staff[] = $row;
}
oci_free_statement($staffStmt);

$bills = [];
$billSql = "SELECT billNo FROM BILL ORDER BY billNo";
$billStmt = oci_parse($conn, $billSql);
oci_execute($billStmt);
while ($row = oci_fetch_array($billStmt, OCI_ASSOC)) {
  $bills[] = $row;
}
oci_free_statement($billStmt);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Bookings</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/bookingsStyle.css">
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
      <h1>Bookings</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add-booking" onclick="addBooking()"><i class='bx bx-plus'></i> Add Booking</button>
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
              <option value="date">By Check-in Date</option>
              <option value="deposit">By Deposit Amount</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="id">Booking ID</option>
                <option value="guestId">Guest ID</option>
                <option value="homestayId">Homestay ID</option>
                <option value="billNo">Bill No.</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Bookings" />
            </div>
            <div class="search-button">
              <button class="btn-search" type="button" aria-label="Search">
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="table-container">
        <table class="guests-table">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Check-in Date</th>
              <th>Check-out Date</th>
              <th>Deposit Amount</th>
              <th>Homestay ID</th>
              <th>Guest ID</th>
              <th>Bill No.</th>
              <th>Details</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="bookingsTableBody">
            <?php foreach ($bookings as $booking): ?>
              <tr data-date="<?php echo htmlspecialchars($booking['CHECKIN_DATE']); ?>"
                data-deposit="<?php echo htmlspecialchars($booking['DEPOSIT_AMOUNT']); ?>"
                data-adults="<?php echo htmlspecialchars($booking['NUM_ADULTS']); ?>"
                data-children="<?php echo htmlspecialchars($booking['NUM_CHILDREN']); ?>"
                data-staff="<?php echo htmlspecialchars($booking['STAFFID'] ?? ''); ?>">
                <td><?php echo htmlspecialchars($booking['BOOKINGID']); ?></td>
                <td><?php echo htmlspecialchars($booking['CHECKIN_DATE']); ?></td>
                <td><?php echo htmlspecialchars($booking['CHECKOUT_DATE']); ?></td>
                <td>RM <?php echo number_format($booking['DEPOSIT_AMOUNT'], 2); ?></td>
                <td><?php echo htmlspecialchars($booking['HOMESTAYID']); ?></td>
                <td><?php echo htmlspecialchars($booking['GUESTID']); ?></td>
                <td><?php echo htmlspecialchars($booking['BILLNO'] ?? '-'); ?></td>
                <td>
                  <button class="btn-details"
                    onclick="viewBookingDetails('<?php echo $booking['BOOKINGID']; ?>')">Details</button>
                </td>
                <td>
                  <button class="btn-update"
                    onclick="updateBooking('<?php echo $booking['BOOKINGID']; ?>')">Update</button>
                  <button class="btn-delete"
                    onclick="deleteBooking('<?php echo $booking['BOOKINGID']; ?>')">Delete</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <p>&copy; 2025 Serena Sanctuary. All rights reserved.</p>
      </div>
    </footer>
  </section>

  <!-- Add Booking Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Booking</h2>
        <span class="close-modal" onclick="closeAddModal()">&times;</span>
      </div>
      <form id="addBookingForm" class="modal-form">
        <div class="form-group">
          <label for="addBookingId">Booking ID</label>
          <input type="text" id="addBookingId" name="bookingId" required>
        </div>
        <div class="form-group">
          <label for="addCheckIn">Check-in Date</label>
          <input type="date" id="addCheckIn" name="checkIn" required>
        </div>
        <div class="form-group">
          <label for="addCheckOut">Check-out Date</label>
          <input type="date" id="addCheckOut" name="checkOut" required>
        </div>
        <div class="form-group">
          <label for="addNumAdults">Number of Adults</label>
          <input type="number" id="addNumAdults" name="numAdults" required min="1">
        </div>
        <div class="form-group">
          <label for="addNumChildren">Number of Children</label>
          <input type="number" id="addNumChildren" name="numChildren" required min="0">
        </div>
        <div class="form-group">
          <label for="addDeposit">Deposit Amount</label>
          <input type="number" id="addDeposit" name="deposit" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="addHomestayId">Homestay</label>
          <select id="addHomestayId" name="homestayId" required>
            <?php foreach ($homestays as $h): ?>
              <option value="<?php echo htmlspecialchars($h['HOMESTAYID']); ?>">
                <?php echo htmlspecialchars($h['HOMESTAYID'] . ' - ' . $h['HOMESTAY_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="addGuestId">Guest</label>
          <select id="addGuestId" name="guestId" required>
            <?php foreach ($guests as $g): ?>
              <option value="<?php echo htmlspecialchars($g['GUESTID']); ?>">
                <?php echo htmlspecialchars($g['GUESTID'] . ' - ' . $g['GUEST_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="addStaffId">Staff</label>
          <select id="addStaffId" name="staffId" required>
            <?php foreach ($staff as $s): ?>
              <option value="<?php echo htmlspecialchars($s['STAFFID']); ?>">
                <?php echo htmlspecialchars($s['STAFFID'] . ' - ' . $s['STAFF_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="addBillNo">Bill No.</label>
          <select id="addBillNo" name="billNo">
            <option value="">None</option>
            <?php foreach ($bills as $b): ?>
              <option value="<?php echo htmlspecialchars($b['BILLNO']); ?>"><?php echo htmlspecialchars($b['BILLNO']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="btn-save">Add Booking</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Booking Details Modal -->
  <div id="detailsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Booking Details</h2>
        <span class="close-modal" onclick="closeDetailsModal()">&times;</span>
      </div>
      <div class="modal-form">
        <div class="form-group">
          <label>Booking ID</label>
          <input type="text" id="detailsBookingId" readonly>
        </div>
        <div class="form-group">
          <label>Check-in Date</label>
          <input type="text" id="detailsCheckIn" readonly>
        </div>
        <div class="form-group">
          <label>Check-out Date</label>
          <input type="text" id="detailsCheckOut" readonly>
        </div>
        <div class="form-group">
          <label>Number of Adults</label>
          <input type="text" id="detailsNumAdults" readonly>
        </div>
        <div class="form-group">
          <label>Number of Children</label>
          <input type="text" id="detailsNumChildren" readonly>
        </div>
        <div class="form-group">
          <label>Deposit Amount</label>
          <input type="text" id="detailsDeposit" readonly>
        </div>
        <div class="form-group">
          <label>Homestay ID</label>
          <input type="text" id="detailsHomestayId" readonly>
        </div>
        <div class="form-group">
          <label>Guest ID</label>
          <input type="text" id="detailsGuestId" readonly>
        </div>
        <div class="form-group">
          <label>Staff ID</label>
          <input type="text" id="detailsStaffId" readonly>
        </div>
        <div class="form-group">
          <label>Bill No.</label>
          <input type="text" id="detailsBillNo" readonly>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeDetailsModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Update Booking Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Booking Details</h2>
        <span class="close-modal" onclick="closeUpdateModal()">&times;</span>
      </div>
      <form id="updateBookingForm" class="modal-form">
        <div class="form-group">
          <label for="updateBookingId">Booking ID</label>
          <input type="text" id="updateBookingId" name="bookingId" readonly>
        </div>
        <div class="form-group">
          <label for="updateCheckIn">Check-in Date</label>
          <input type="date" id="updateCheckIn" name="checkIn" required>
        </div>
        <div class="form-group">
          <label for="updateCheckOut">Check-out Date</label>
          <input type="date" id="updateCheckOut" name="checkOut" required>
        </div>
        <div class="form-group">
          <label for="updateNumAdults">Number of Adults</label>
          <input type="number" id="updateNumAdults" name="numAdults" required min="1">
        </div>
        <div class="form-group">
          <label for="updateNumChildren">Number of Children</label>
          <input type="number" id="updateNumChildren" name="numChildren" required min="0">
        </div>
        <div class="form-group">
          <label for="updateDeposit">Deposit Amount</label>
          <input type="number" id="updateDeposit" name="deposit" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateHomestayId">Homestay</label>
          <select id="updateHomestayId" name="homestayId" required>
            <?php foreach ($homestays as $h): ?>
              <option value="<?php echo htmlspecialchars($h['HOMESTAYID']); ?>">
                <?php echo htmlspecialchars($h['HOMESTAYID'] . ' - ' . $h['HOMESTAY_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="updateGuestId">Guest</label>
          <select id="updateGuestId" name="guestId" required>
            <?php foreach ($guests as $g): ?>
              <option value="<?php echo htmlspecialchars($g['GUESTID']); ?>">
                <?php echo htmlspecialchars($g['GUESTID'] . ' - ' . $g['GUEST_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="updateStaffId">Staff</label>
          <select id="updateStaffId" name="staffId" required>
            <?php foreach ($staff as $s): ?>
              <option value="<?php echo htmlspecialchars($s['STAFFID']); ?>">
                <?php echo htmlspecialchars($s['STAFFID'] . ' - ' . $s['STAFF_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="updateBillNo">Bill No.</label>
          <select id="updateBillNo" name="billNo">
            <option value="">None</option>
            <?php foreach ($bills as $b): ?>
              <option value="<?php echo htmlspecialchars($b['BILLNO']); ?>"><?php echo htmlspecialchars($b['BILLNO']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeUpdateModal()">Cancel</button>
          <button type="submit" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let arrow = document.querySelectorAll(".arrow");
    for (var i = 0; i < arrow.length; i++) {
      arrow[i].addEventListener("click", (e) => {
        let arrowParent = e.target.parentElement.parentElement;
        arrowParent.classList.toggle("showMenu");
      });
    }
    let sidebar = document.querySelector(".sidebar");
    let sidebarBtn = document.querySelector(".bx-menu");
    console.log(sidebarBtn);
    sidebarBtn.addEventListener("click", () => {
      sidebar.classList.toggle("close");
    });

    // Sorting functionality
    const sortOrderSelect = document.getElementById('sortOrder');
    const sortBySelect = document.getElementById('sortBy');
    const tableBody = document.getElementById('bookingsTableBody');

    function sortTable() {
      const sortBy = sortBySelect.value;
      const sortOrder = sortOrderSelect.value;

      if (!sortBy || !sortOrder) {
        return;
      }

      const rows = Array.from(tableBody.querySelectorAll('tr'));

      rows.sort((a, b) => {
        let comparison = 0;

        switch (sortBy) {
          case 'date':
            const dateA = new Date(a.getAttribute('data-date'));
            const dateB = new Date(b.getAttribute('data-date'));
            comparison = dateA - dateB;
            break;
          case 'deposit':
            const depositA = parseFloat(a.getAttribute('data-deposit'));
            const depositB = parseFloat(b.getAttribute('data-deposit'));
            comparison = depositA - depositB;
            break;
        }

        return sortOrder === 'ascending' ? comparison : -comparison;
      });

      tableBody.innerHTML = '';
      rows.forEach(row => tableBody.appendChild(row));
    }

    sortOrderSelect.addEventListener('change', sortTable);
    sortBySelect.addEventListener('change', sortTable);

    // Search functionality
    const searchInput = document.getElementById('search');
    const searchButton = document.querySelector('.btn-search');
    const searchTypeSelect = document.getElementById('searchType');
    let allRows = Array.from(tableBody.querySelectorAll('tr'));

    function performSearch() {
      const searchTerm = searchInput.value.toLowerCase().trim();
      const searchType = searchTypeSelect.value;

      allRows.forEach(row => {
        let matches = false;

        if (searchType === 'id') {
          const id = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
          matches = id.includes(searchTerm);
        } else if (searchType === 'guestId') {
          const guestId = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
          matches = guestId.includes(searchTerm);
        } else if (searchType === 'homestayId') {
          const homestayId = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
          matches = homestayId.includes(searchTerm);
        } else if (searchType === 'billNo') {
          const billNo = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
          matches = billNo.includes(searchTerm);
        }

        row.style.display = matches ? '' : 'none';
      });
    }

    searchButton.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
      }
    });

    allRows = Array.from(tableBody.querySelectorAll('tr'));

    // Update and Delete functions
    function updateBooking(bookingId) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === bookingId) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Booking not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      const currentData = {
        bookingId: cells[0].textContent.trim(),
        checkIn: cells[1].textContent.trim(),
        checkOut: cells[2].textContent.trim(),
        deposit: cells[3].textContent.trim().replace('RM ', '').replace(/,/g, '').trim(),
        homestayId: cells[4].textContent.trim(),
        guestId: cells[5].textContent.trim(),
        billNo: cells[6].textContent.trim() === '-' ? '' : cells[6].textContent.trim(),
        numAdults: targetRow.getAttribute('data-adults') || '2',
        numChildren: targetRow.getAttribute('data-children') || '0',
        staffId: targetRow.getAttribute('data-staff') || 'S001'
      };

      document.getElementById('updateBookingId').value = currentData.bookingId;
      document.getElementById('updateCheckIn').value = currentData.checkIn;
      document.getElementById('updateCheckOut').value = currentData.checkOut;
      document.getElementById('updateNumAdults').value = currentData.numAdults;
      document.getElementById('updateNumChildren').value = currentData.numChildren;
      document.getElementById('updateDeposit').value = currentData.deposit;
      document.getElementById('updateHomestayId').value = currentData.homestayId;
      document.getElementById('updateGuestId').value = currentData.guestId;
      document.getElementById('updateStaffId').value = currentData.staffId;
      document.getElementById('updateBillNo').value = currentData.billNo;

      document.getElementById('updateModal').setAttribute('data-target-row', bookingId);
      document.getElementById('updateModal').style.display = 'block';
    }

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updateBookingForm').reset();
    }

    function viewBookingDetails(bookingId) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === bookingId) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Booking not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      document.getElementById('detailsBookingId').value = cells[0].textContent.trim();
      document.getElementById('detailsCheckIn').value = cells[1].textContent.trim();
      document.getElementById('detailsCheckOut').value = cells[2].textContent.trim();
      document.getElementById('detailsNumAdults').value = targetRow.getAttribute('data-adults') || 'N/A';
      document.getElementById('detailsNumChildren').value = targetRow.getAttribute('data-children') || '0';
      document.getElementById('detailsDeposit').value = cells[3].textContent.trim();
      document.getElementById('detailsHomestayId').value = cells[4].textContent.trim();
      document.getElementById('detailsGuestId').value = cells[5].textContent.trim();
      document.getElementById('detailsStaffId').value = targetRow.getAttribute('data-staff') || 'N/A';
      document.getElementById('detailsBillNo').value = cells[6].textContent.trim() === '-' ? 'Not assigned' : cells[6].textContent.trim();

      document.getElementById('detailsModal').style.display = 'block';
    }

    function closeDetailsModal() {
      document.getElementById('detailsModal').style.display = 'none';
    }

    document.getElementById('updateBookingForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('bookingId', document.getElementById('updateBookingId').value);
      formData.append('checkIn', document.getElementById('updateCheckIn').value);
      formData.append('checkOut', document.getElementById('updateCheckOut').value);
      formData.append('numAdults', document.getElementById('updateNumAdults').value);
      formData.append('numChildren', document.getElementById('updateNumChildren').value);
      formData.append('deposit', document.getElementById('updateDeposit').value);
      formData.append('homestayId', document.getElementById('updateHomestayId').value);
      formData.append('guestId', document.getElementById('updateGuestId').value);
      formData.append('staffId', document.getElementById('updateStaffId').value);
      formData.append('billNo', document.getElementById('updateBillNo').value);

      fetch('bookings.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert(data.message);
          }
        })
        .catch(error => {
          alert('Error updating booking: ' + error);
        });
    });

    window.onclick = function (event) {
      const addModal = document.getElementById('addModal');
      const updateModal = document.getElementById('updateModal');
      const detailsModal = document.getElementById('detailsModal');
      if (event.target === addModal) {
        closeAddModal();
      }
      if (event.target === updateModal) {
        closeUpdateModal();
      }
      if (event.target === detailsModal) {
        closeDetailsModal();
      }
    }

    function deleteBooking(bookingId) {
      if (confirm('Are you sure you want to delete booking ' + bookingId + '? WARNING: If this booking is linked to a bill, the bill will also be deleted. This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('bookingId', bookingId);

        fetch('bookings.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            alert(data.message);
            if (data.success) {
              location.reload();
            }
          })
          .catch(error => {
            alert('Error deleting booking: ' + error);
          });
      }
    }

    function addBooking() {
      // Clear the form
      document.getElementById('addBookingForm').reset();
      // Show the modal
      document.getElementById('addModal').style.display = 'block';
    }

    function closeAddModal() {
      document.getElementById('addModal').style.display = 'none';
      document.getElementById('addBookingForm').reset();
    }

    // Handle add booking form submission
    document.getElementById('addBookingForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'add');
      formData.append('bookingId', document.getElementById('addBookingId').value.trim());
      formData.append('checkIn', document.getElementById('addCheckIn').value);
      formData.append('checkOut', document.getElementById('addCheckOut').value);
      formData.append('numAdults', document.getElementById('addNumAdults').value);
      formData.append('numChildren', document.getElementById('addNumChildren').value);
      formData.append('deposit', document.getElementById('addDeposit').value);
      formData.append('homestayId', document.getElementById('addHomestayId').value);
      formData.append('guestId', document.getElementById('addGuestId').value);
      formData.append('staffId', document.getElementById('addStaffId').value);
      formData.append('billNo', document.getElementById('addBillNo').value);

      fetch('bookings.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert(data.message);
          }
        })
        .catch(error => {
          alert('Error adding booking: ' + error);
        });
    });
  </script>
</body>

</html>