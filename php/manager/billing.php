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

  if ($_POST['action'] === 'update') {
    $billNo = $_POST['billNo'];
    $billDate = $_POST['billDate'];
    $subtotal = $_POST['subtotal'];
    $discAmount = $_POST['discAmount'];
    $taxAmount = $_POST['taxAmount'];
    $totalAmount = $_POST['totalAmount'];
    $lateCharges = $_POST['lateCharges'];
    $status = $_POST['status'];
    $paymentDate = !empty($_POST['paymentDate']) ? $_POST['paymentDate'] : null;
    $paymentMethod = $_POST['paymentMethod'];
    $guestID = $_POST['guestId'];
    $staffID = $_POST['staffId'];

    $sql = "UPDATE BILL SET 
                bill_date = TO_DATE(:billDate, 'YYYY-MM-DD'),
                bill_subtotal = :subtotal,
                disc_amount = :discAmount,
                tax_amount = :taxAmount,
                total_amount = :totalAmount,
                late_charges = :lateCharges,
                bill_status = :status,
                payment_date = " . ($paymentDate ? "TO_DATE(:paymentDate, 'YYYY-MM-DD')" : "NULL") . ",
                payment_method = :paymentMethod,
                guestID = :guestID,
                staffID = :staffID
                WHERE billNo = :billNo";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':billDate', $billDate);
    oci_bind_by_name($stmt, ':subtotal', $subtotal);
    oci_bind_by_name($stmt, ':discAmount', $discAmount);
    oci_bind_by_name($stmt, ':taxAmount', $taxAmount);
    oci_bind_by_name($stmt, ':totalAmount', $totalAmount);
    oci_bind_by_name($stmt, ':lateCharges', $lateCharges);
    oci_bind_by_name($stmt, ':status', $status);
    if ($paymentDate) {
      oci_bind_by_name($stmt, ':paymentDate', $paymentDate);
    }
    oci_bind_by_name($stmt, ':paymentMethod', $paymentMethod);
    oci_bind_by_name($stmt, ':guestID', $guestID);
    oci_bind_by_name($stmt, ':staffID', $staffID);
    oci_bind_by_name($stmt, ':billNo', $billNo);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Bill updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $billNo = $_POST['billNo'];

    // Check if bill is linked to a booking
    $checkSql = "SELECT bookingID FROM BOOKING WHERE billNo = :billNo";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':billNo', $billNo);
    oci_execute($checkStmt);
    $row = oci_fetch_array($checkStmt, OCI_ASSOC);

    if ($row) {
      // Cascade delete: Delete the linked booking first
      $deleteBookingSql = "DELETE FROM BOOKING WHERE bookingID = :bookingID";
      $deleteBookingStmt = oci_parse($conn, $deleteBookingSql);
      oci_bind_by_name($deleteBookingStmt, ':bookingID', $row['BOOKINGID']);

      if (!oci_execute($deleteBookingStmt)) {
        $error = oci_error($deleteBookingStmt);
        echo json_encode(['success' => false, 'message' => 'Error deleting linked booking: ' . $error['message']]);
        oci_free_statement($deleteBookingStmt);
        oci_free_statement($checkStmt);
        exit;
      }
      oci_free_statement($deleteBookingStmt);
    }
    oci_free_statement($checkStmt);

    $sql = "DELETE FROM BILL WHERE billNo = :billNo";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':billNo', $billNo);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Bill and linked booking (if any) deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'updateStatus') {
    $billNo = $_POST['billNo'];
    $status = $_POST['status'];
    $paymentDate = ($status === 'Paid') ? date('Y-m-d') : null;

    $sql = "UPDATE BILL SET 
                bill_status = :status,
                payment_date = " . ($paymentDate ? "TO_DATE(:paymentDate, 'YYYY-MM-DD')" : "NULL") . "
                WHERE billNo = :billNo";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':status', $status);
    if ($paymentDate) {
      oci_bind_by_name($stmt, ':paymentDate', $paymentDate);
    }
    oci_bind_by_name($stmt, ':billNo', $billNo);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Bill status updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

// Fetch all bills
$sql = "SELECT billNo, TO_CHAR(bill_date, 'YYYY-MM-DD') as bill_date, bill_subtotal, disc_amount, tax_amount, total_amount, late_charges, bill_status, TO_CHAR(payment_date, 'YYYY-MM-DD') as payment_date, payment_method, guestID, staffID FROM BILL ORDER BY billNo DESC";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$bills = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $bills[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Billing</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/billingStyle.css">
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
      <h1>Billing</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
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
              <option value="date">By Date</option>
              <option value="amount">By Amount</option>
              <option value="status">By Status</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="billNo">Bill No.</option>
                <option value="guestId">Guest ID</option>
                <option value="staffId">Staff ID</option>
                <option value="status">Status</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Bills" />
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
              <th>Bill No.</th>
              <th>Bill Date</th>
              <th>Total Amount</th>
              <th>Bill Status</th>
              <th>Payment Method</th>
              <th>Guest ID</th>
              <th>Staff ID</th>
              <th>Details</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="billingTableBody">
            <?php foreach ($bills as $bill): ?>
              <tr data-date="<?php echo htmlspecialchars($bill['BILL_DATE']); ?>"
                data-amount="<?php echo htmlspecialchars($bill['TOTAL_AMOUNT']); ?>"
                data-status="<?php echo htmlspecialchars($bill['BILL_STATUS']); ?>"
                data-subtotal="<?php echo htmlspecialchars($bill['BILL_SUBTOTAL']); ?>"
                data-disc="<?php echo htmlspecialchars($bill['DISC_AMOUNT']); ?>"
                data-tax="<?php echo htmlspecialchars($bill['TAX_AMOUNT']); ?>"
                data-late="<?php echo htmlspecialchars($bill['LATE_CHARGES']); ?>"
                data-payment-date="<?php echo htmlspecialchars($bill['PAYMENT_DATE'] ?? ''); ?>">
                <td><?php echo htmlspecialchars($bill['BILLNO']); ?></td>
                <td><?php echo htmlspecialchars($bill['BILL_DATE']); ?></td>
                <td>RM <?php echo number_format($bill['TOTAL_AMOUNT'], 2); ?></td>
                <td>
                  <select class="status-select" data-status="<?php echo htmlspecialchars($bill['BILL_STATUS']); ?>"
                    style="background: <?php echo $bill['BILL_STATUS'] === 'Paid' ? '#e8f5e9' : '#fff3e0'; ?>; color: <?php echo $bill['BILL_STATUS'] === 'Paid' ? '#00bf63' : '#ff9800'; ?>;"
                    onchange="quickChangeStatus('<?php echo $bill['BILLNO']; ?>', this.value)">
                    <option value="Paid" <?php echo $bill['BILL_STATUS'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Pending" <?php echo $bill['BILL_STATUS'] === 'Pending' ? 'selected' : ''; ?>>Pending
                    </option>
                  </select>
                </td>
                <td><?php echo htmlspecialchars($bill['PAYMENT_METHOD'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($bill['GUESTID'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($bill['STAFFID'] ?? 'N/A'); ?></td>
                <td>
                  <button class="btn-details" onclick="viewBillDetails('<?php echo $bill['BILLNO']; ?>')">Details</button>
                </td>
                <td>
                  <button class="btn-update" onclick="updateBill('<?php echo $bill['BILLNO']; ?>')">Update</button>
                  <button class="btn-delete" onclick="deleteBill('<?php echo $bill['BILLNO']; ?>')">Delete</button>
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

  <!-- Bill Details Modal -->
  <div id="detailsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Bill Details</h2>
        <span class="close-modal" onclick="closeDetailsModal()">&times;</span>
      </div>
      <div class="modal-form">
        <div class="form-group">
          <label>Bill No.</label>
          <input type="text" id="detailsBillNo" readonly>
        </div>
        <div class="form-group">
          <label>Bill Date</label>
          <input type="text" id="detailsBillDate" readonly>
        </div>
        <div class="form-group">
          <label>Bill Subtotal</label>
          <input type="text" id="detailsSubtotal" readonly>
        </div>
        <div class="form-group">
          <label>Discount Amount</label>
          <input type="text" id="detailsDiscAmount" readonly>
        </div>
        <div class="form-group">
          <label>Tax Amount</label>
          <input type="text" id="detailsTaxAmount" readonly>
        </div>
        <div class="form-group">
          <label>Late Charges</label>
          <input type="text" id="detailsLateCharges" readonly>
        </div>
        <div class="form-group">
          <label>Total Amount</label>
          <input type="text" id="detailsTotalAmount" readonly>
        </div>
        <div class="form-group">
          <label>Bill Status</label>
          <input type="text" id="detailsBillStatus" readonly>
        </div>
        <div class="form-group">
          <label>Payment Date</label>
          <input type="text" id="detailsPaymentDate" readonly>
        </div>
        <div class="form-group">
          <label>Payment Method</label>
          <input type="text" id="detailsPaymentMethod" readonly>
        </div>
        <div class="form-group">
          <label>Guest ID</label>
          <input type="text" id="detailsGuestId" readonly>
        </div>
        <div class="form-group">
          <label>Staff ID</label>
          <input type="text" id="detailsStaffId" readonly>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeDetailsModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Update Bill Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Bill Details</h2>
        <span class="close-modal" onclick="closeUpdateModal()">&times;</span>
      </div>
      <form id="updateBillForm" class="modal-form">
        <div class="form-group">
          <label for="updateBillNo">Bill No.</label>
          <input type="text" id="updateBillNo" name="billNo" readonly>
        </div>
        <div class="form-group">
          <label for="updateBillDate">Bill Date</label>
          <input type="date" id="updateBillDate" name="billDate" required>
        </div>
        <div class="form-group">
          <label for="updateSubtotal">Bill Subtotal</label>
          <input type="number" id="updateSubtotal" name="subtotal" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateDiscAmount">Discount Amount</label>
          <input type="number" id="updateDiscAmount" name="discAmount" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateTaxAmount">Tax Amount</label>
          <input type="number" id="updateTaxAmount" name="taxAmount" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateLateCharges">Late Charges</label>
          <input type="number" id="updateLateCharges" name="lateCharges" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateAmount">Total Amount</label>
          <input type="number" id="updateAmount" name="amount" required min="0" step="0.01" readonly>
        </div>
        <div class="form-group">
          <label for="updateStatus">Bill Status</label>
          <select id="updateStatus" name="status" required onchange="updateStatusInModal(this.value)">
            <option value="Paid">Paid</option>
            <option value="Pending">Pending</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updatePaymentDate">Payment Date</label>
          <input type="date" id="updatePaymentDate" name="paymentDate">
        </div>
        <div class="form-group">
          <label for="updatePaymentMethod">Payment Method</label>
          <select id="updatePaymentMethod" name="paymentMethod" required>
            <option value="Credit/Debit Card">Credit/Debit Card</option>
            <option value="QR Payment">QR Payment</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updateGuestId">Guest ID</label>
          <input type="text" id="updateGuestId" name="guestId" required>
        </div>
        <div class="form-group">
          <label for="updateStaffId">Staff ID</label>
          <input type="text" id="updateStaffId" name="staffId" required>
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
    const tableBody = document.getElementById('billingTableBody');

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
          case 'amount':
            const amountA = parseFloat(a.getAttribute('data-amount'));
            const amountB = parseFloat(b.getAttribute('data-amount'));
            comparison = amountA - amountB;
            break;
          case 'status':
            const statusA = a.getAttribute('data-status');
            const statusB = b.getAttribute('data-status');
            comparison = statusA.localeCompare(statusB);
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

        if (searchType === 'billNo') {
          const billNo = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
          matches = billNo.includes(searchTerm);
        } else if (searchType === 'guestId') {
          const guestId = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
          matches = guestId.includes(searchTerm);
        } else if (searchType === 'staffId') {
          const staffId = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
          matches = staffId.includes(searchTerm);
        } else if (searchType === 'status') {
          const status = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
          matches = status.includes(searchTerm);
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
    function updateBill(billNo) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === billNo) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Bill not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      const statusSelect = cells[3].querySelector('.status-select');
      const currentStatus = statusSelect ? statusSelect.value : cells[3].textContent.trim();

      const currentData = {
        billNo: cells[0].textContent.trim(),
        billDate: cells[1].textContent.trim(),
        amount: cells[2].textContent.trim().replace('RM ', '').replace(',', '').trim(),
        status: currentStatus,
        paymentMethod: cells[4].textContent.trim(),
        guestId: cells[5].textContent.trim(),
        staffId: cells[6].textContent.trim(),
        subtotal: targetRow.getAttribute('data-subtotal') || '0',
        discAmount: targetRow.getAttribute('data-disc') || '0',
        taxAmount: targetRow.getAttribute('data-tax') || '0',
        lateCharges: targetRow.getAttribute('data-late') || '0',
        paymentDate: targetRow.getAttribute('data-payment-date') || ''
      };

      document.getElementById('updateBillNo').value = currentData.billNo;
      document.getElementById('updateBillDate').value = currentData.billDate;
      document.getElementById('updateSubtotal').value = currentData.subtotal;
      document.getElementById('updateDiscAmount').value = currentData.discAmount;
      document.getElementById('updateTaxAmount').value = currentData.taxAmount;
      document.getElementById('updateLateCharges').value = currentData.lateCharges;
      document.getElementById('updateAmount').value = currentData.amount;
      document.getElementById('updateStatus').value = currentData.status;
      document.getElementById('updatePaymentDate').value = currentData.paymentDate;
      document.getElementById('updatePaymentMethod').value = currentData.paymentMethod;
      document.getElementById('updateGuestId').value = currentData.guestId;
      document.getElementById('updateStaffId').value = currentData.staffId;

      // Calculate initial total
      calculateTotal();

      document.getElementById('updateModal').setAttribute('data-target-row', billNo);
      document.getElementById('updateModal').style.display = 'block';
    }

    function calculateTotal() {
      const subtotal = parseFloat(document.getElementById('updateSubtotal').value) || 0;
      const discAmount = parseFloat(document.getElementById('updateDiscAmount').value) || 0;
      const taxAmount = parseFloat(document.getElementById('updateTaxAmount').value) || 0;
      const lateCharges = parseFloat(document.getElementById('updateLateCharges').value) || 0;
      const total = subtotal - discAmount + taxAmount + lateCharges;
      document.getElementById('updateAmount').value = total.toFixed(2);
    }

    // Set up event listeners for total calculation
    document.addEventListener('DOMContentLoaded', function () {
      const subtotalInput = document.getElementById('updateSubtotal');
      const discInput = document.getElementById('updateDiscAmount');
      const taxInput = document.getElementById('updateTaxAmount');
      const lateInput = document.getElementById('updateLateCharges');

      if (subtotalInput) subtotalInput.addEventListener('input', calculateTotal);
      if (discInput) discInput.addEventListener('input', calculateTotal);
      if (taxInput) taxInput.addEventListener('input', calculateTotal);
      if (lateInput) lateInput.addEventListener('input', calculateTotal);
    });

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updateBillForm').reset();
    }

    function viewBillDetails(billNo) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === billNo) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Bill not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      const subtotal = parseFloat(targetRow.getAttribute('data-subtotal')) || 0;
      const discAmount = parseFloat(targetRow.getAttribute('data-disc')) || 0;
      const taxAmount = parseFloat(targetRow.getAttribute('data-tax')) || 0;
      const lateCharges = parseFloat(targetRow.getAttribute('data-late')) || 0;
      const paymentDate = targetRow.getAttribute('data-payment-date') || '';

      document.getElementById('detailsBillNo').value = cells[0].textContent.trim();
      document.getElementById('detailsBillDate').value = cells[1].textContent.trim();
      document.getElementById('detailsSubtotal').value = 'RM ' + subtotal.toLocaleString();
      document.getElementById('detailsDiscAmount').value = 'RM ' + discAmount.toLocaleString();
      document.getElementById('detailsTaxAmount').value = 'RM ' + taxAmount.toLocaleString();
      document.getElementById('detailsLateCharges').value = 'RM ' + lateCharges.toLocaleString();
      document.getElementById('detailsTotalAmount').value = cells[2].textContent.trim();
      const statusSelect = cells[3].querySelector('.status-select');
      document.getElementById('detailsBillStatus').value = statusSelect ? statusSelect.value : cells[3].textContent.trim();
      document.getElementById('detailsPaymentDate').value = paymentDate || 'Not paid yet';
      document.getElementById('detailsPaymentMethod').value = cells[4].textContent.trim();
      document.getElementById('detailsGuestId').value = cells[5].textContent.trim();
      document.getElementById('detailsStaffId').value = cells[6].textContent.trim();

      document.getElementById('detailsModal').style.display = 'block';
    }

    function closeDetailsModal() {
      document.getElementById('detailsModal').style.display = 'none';
    }

    document.getElementById('updateBillForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('billNo', document.getElementById('updateBillNo').value);
      formData.append('billDate', document.getElementById('updateBillDate').value);
      formData.append('subtotal', document.getElementById('updateSubtotal').value);
      formData.append('discAmount', document.getElementById('updateDiscAmount').value);
      formData.append('taxAmount', document.getElementById('updateTaxAmount').value);
      formData.append('lateCharges', document.getElementById('updateLateCharges').value);
      formData.append('totalAmount', document.getElementById('updateAmount').value);
      formData.append('status', document.getElementById('updateStatus').value);
      formData.append('paymentDate', document.getElementById('updatePaymentDate').value);
      formData.append('paymentMethod', document.getElementById('updatePaymentMethod').value);
      formData.append('guestId', document.getElementById('updateGuestId').value.trim());
      formData.append('staffId', document.getElementById('updateStaffId').value.trim());

      fetch('billing.php', {
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
          alert('Error updating bill: ' + error);
        });
    });

    window.onclick = function (event) {
      const updateModal = document.getElementById('updateModal');
      const detailsModal = document.getElementById('detailsModal');
      if (event.target === updateModal) {
        closeUpdateModal();
      }
      if (event.target === detailsModal) {
        closeDetailsModal();
      }
    }

    function deleteBill(billNo) {
      if (confirm('Are you sure you want to delete bill ' + billNo + '? WARNING: If this bill is linked to a booking, the booking will also be deleted. This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('billNo', billNo);

        fetch('billing.php', {
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
            alert('Error deleting bill: ' + error);
          });
      }
    }

    function quickChangeStatus(billNo, newStatus) {
      const formData = new FormData();
      formData.append('action', 'updateStatus');
      formData.append('billNo', billNo);
      formData.append('status', newStatus);

      fetch('billing.php', {
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
          alert('Error updating status: ' + error);
        });
    }
  </script>
</body>

</html>