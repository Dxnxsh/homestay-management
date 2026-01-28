<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if ($_POST['action'] === 'schedule') {
    $serviceID = $_POST['serviceId'];
    $homestayID = $_POST['homestayId'];
    $scheduleDate = $_POST['scheduleDate'];

    // Default status for new schedules
    $status = 'pending';

    // Validate inputs
    if (empty($serviceID) || empty($homestayID) || empty($scheduleDate)) {
      echo json_encode(['success' => false, 'message' => 'All fields are required.']);
      exit;
    }

    // Insert into HOMESTAY_SERVICE
    // Note: MAIN_DATE is DATE type in Oracle
    $sql = "INSERT INTO HOMESTAY_SERVICE (homestayID, serviceID, main_date, main_status)
            VALUES (:homestayID, :serviceID, TO_DATE(:scheduleDate, 'YYYY-MM-DD'), :status)";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);
    oci_bind_by_name($stmt, ':serviceID', $serviceID);
    oci_bind_by_name($stmt, ':scheduleDate', $scheduleDate);
    oci_bind_by_name($stmt, ':status', $status);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Service scheduled successfully']);
    } else {
      $error = oci_error($stmt);
      // Check for unique constraint violation (ORA-00001)
      if ($error['code'] == 1) {
        echo json_encode(['success' => false, 'message' => 'This service is already scheduled for this homestay on the selected date.']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
      }
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete_schedule') {
    $serviceID = $_POST['serviceId'];
    $homestayID = $_POST['homestayId'];
    $scheduleDate = $_POST['scheduleDate']; // Format YYYY-MM-DD from JS

    $sql = "DELETE FROM HOMESTAY_SERVICE 
            WHERE homestayID = :homestayID 
            AND serviceID = :serviceID 
            AND main_date = TO_DATE(:scheduleDate, 'YYYY-MM-DD')";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);
    oci_bind_by_name($stmt, ':serviceID', $serviceID);
    oci_bind_by_name($stmt, ':scheduleDate', $scheduleDate);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Scheduled service deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  // Optional: Update status
  if ($_POST['action'] === 'update_status') {
    $serviceID = $_POST['serviceId'];
    $homestayID = $_POST['homestayId'];
    $scheduleDate = $_POST['scheduleDate'];
    $newStatus = $_POST['status'];

    $sql = "UPDATE HOMESTAY_SERVICE 
            SET main_status = :status
            WHERE homestayID = :homestayID 
            AND serviceID = :serviceID 
            AND main_date = TO_DATE(:scheduleDate, 'YYYY-MM-DD')";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':status', $newStatus);
    oci_bind_by_name($stmt, ':homestayID', $homestayID);
    oci_bind_by_name($stmt, ':serviceID', $serviceID);
    oci_bind_by_name($stmt, ':scheduleDate', $scheduleDate);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

// Fetch Data for View

// 1. Get List of Services for Dropdown
$servicesList = [];
$serviceSql = "SELECT serviceID, service_type, service_cost FROM SERVICE ORDER BY service_type";
$serviceStmt = oci_parse($conn, $serviceSql);
oci_execute($serviceStmt);
while ($row = oci_fetch_array($serviceStmt, OCI_ASSOC)) {
  $servicesList[] = $row;
}
oci_free_statement($serviceStmt);

// 2. Get List of Homestays for Dropdown
$homestaysList = [];
$homestaySql = "SELECT homestayID, homestay_name FROM HOMESTAY ORDER BY homestay_name";
$homestayStmt = oci_parse($conn, $homestaySql);
oci_execute($homestayStmt);
while ($row = oci_fetch_array($homestayStmt, OCI_ASSOC)) {
  $homestaysList[] = $row;
}
oci_free_statement($homestayStmt);

// 3. Get List of Scheduled Services
$scheduledServices = [];
$schedSql = "SELECT hs.homestayID, h.homestay_name, 
                    hs.serviceID, s.service_type, s.service_cost,
                    TO_CHAR(hs.main_date, 'YYYY-MM-DD') as main_date_formatted,
                    hs.main_status
             FROM HOMESTAY_SERVICE hs
             JOIN HOMESTAY h ON hs.homestayID = h.homestayID
             JOIN SERVICE s ON hs.serviceID = s.serviceID
             ORDER BY hs.main_date DESC";

$schedStmt = oci_parse($conn, $schedSql);
oci_execute($schedStmt);
while ($row = oci_fetch_array($schedStmt, OCI_ASSOC)) {
  $scheduledServices[] = $row;
}
oci_free_statement($schedStmt);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Service Scheduling</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/serviceStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Add some specific styles for the scheduling interface if needed */
    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.85em;
      font-weight: 500;
      text-transform: capitalize;
    }

    .status-pending {
      background-color: #ffeeba;
      color: #856404;
    }

    .status-done {
      background-color: #d4edda;
      color: #155724;
    }

    .status-cancelled {
      background-color: #f8d7da;
      color: #721c24;
    }
  </style>
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
      <h1>Service Scheduling</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add-service" onclick="openScheduleModal()">Schedule Service</button>
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
              <option value="homestay">By Homestay</option>
              <option value="status">By Status</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="homestay">Homestay</option>
                <option value="service">Service</option>
                <option value="status">Status</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Schedules" />
            </div>
            <div class="search-button">
              <button class="btn-search" type="button" aria-label="Search">
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="table-container">
        <table class="services-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Homestay</th>
              <th>Service Type</th>
              <th>Cost</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="servicesTableBody">
            <?php if (empty($scheduledServices)): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:14px;">No scheduled services found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($scheduledServices as $sched): ?>
                <tr data-homestay="<?php echo htmlspecialchars($sched['HOMESTAY_NAME']); ?>"
                  data-service="<?php echo htmlspecialchars($sched['SERVICE_TYPE']); ?>"
                  data-date="<?php echo htmlspecialchars($sched['MAIN_DATE_FORMATTED']); ?>"
                  data-status="<?php echo htmlspecialchars($sched['MAIN_STATUS']); ?>">
                  <td><?php echo htmlspecialchars($sched['MAIN_DATE_FORMATTED']); ?></td>
                  <td><?php echo htmlspecialchars($sched['HOMESTAY_NAME']); ?></td>
                  <td><?php echo htmlspecialchars($sched['SERVICE_TYPE']); ?></td>
                  <td>RM <?php echo number_format((float) $sched['SERVICE_COST'], 2, '.', ''); ?></td>
                  <td>
                    <span
                      class="status-badge status-<?php echo strtolower(htmlspecialchars($sched['MAIN_STATUS'] ?? 'pending')); ?>">
                      <?php echo htmlspecialchars($sched['MAIN_STATUS'] ?? 'Pending'); ?>
                    </span>
                  </td>
                  <td>
                    <?php if (strtolower($sched['MAIN_STATUS'] ?? '') !== 'done'): ?>
                      <button class="btn-update"
                        onclick="markAsDone('<?php echo $sched['HOMESTAYID']; ?>', '<?php echo $sched['SERVICEID']; ?>', '<?php echo $sched['MAIN_DATE_FORMATTED']; ?>')">Mark
                        Done</button>
                    <?php endif; ?>
                    <button class="btn-delete"
                      onclick="deleteSchedule('<?php echo $sched['HOMESTAYID']; ?>', '<?php echo $sched['SERVICEID']; ?>', '<?php echo $sched['MAIN_DATE_FORMATTED']; ?>')">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
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

  <!-- Schedule Service Modal -->
  <div id="scheduleModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Schedule New Service</h2>
        <span class="close-modal" onclick="closeScheduleModal()">&times;</span>
      </div>
      <form id="scheduleServiceForm" class="modal-form">
        <div class="form-group">
          <label for="scheduleHomestayId">Homestay</label>
          <select id="scheduleHomestayId" name="homestayId" required>
            <option value="">Select Homestay</option>
            <?php foreach ($homestaysList as $homestay): ?>
              <option value="<?php echo htmlspecialchars($homestay['HOMESTAYID']); ?>">
                <?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="scheduleServiceId">Service</label>
          <select id="scheduleServiceId" name="serviceId" required>
            <option value="">Select Service</option>
            <?php foreach ($servicesList as $service): ?>
              <option value="<?php echo htmlspecialchars($service['SERVICEID']); ?>">
                <?php echo htmlspecialchars($service['SERVICE_TYPE']); ?> (RM <?php echo $service['SERVICE_COST']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="scheduleDate">Date</label>
          <input type="date" id="scheduleDate" name="scheduleDate" required min="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeScheduleModal()">Cancel</button>
          <button type="submit" class="btn-save">Schedule</button>
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
    sidebarBtn.addEventListener("click", () => {
      sidebar.classList.toggle("close");
    });

    // Sorting functionality
    const sortOrderSelect = document.getElementById('sortOrder');
    const sortBySelect = document.getElementById('sortBy');
    const tableBody = document.getElementById('servicesTableBody');

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
            // Date comparison
            const dateA = new Date(a.getAttribute('data-date'));
            const dateB = new Date(b.getAttribute('data-date'));
            comparison = dateA - dateB;
            break;
          case 'homestay':
            const hA = a.getAttribute('data-homestay').toLowerCase();
            const hB = b.getAttribute('data-homestay').toLowerCase();
            comparison = hA.localeCompare(hB);
            break;
          case 'status':
            const sA = a.getAttribute('data-status').toLowerCase();
            const sB = b.getAttribute('data-status').toLowerCase();
            comparison = sA.localeCompare(sB);
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

        if (searchType === 'homestay') {
          const text = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
          matches = text.includes(searchTerm);
        } else if (searchType === 'service') {
          const text = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
          matches = text.includes(searchTerm);
        } else if (searchType === 'status') {
          const text = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
          matches = text.includes(searchTerm);
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

    // Modal Functions
    function openScheduleModal() {
      document.getElementById('scheduleServiceForm').reset();
      document.getElementById('scheduleModal').style.display = 'block';
    }

    function closeScheduleModal() {
      document.getElementById('scheduleModal').style.display = 'none';
      document.getElementById('scheduleServiceForm').reset();
    }

    window.onclick = function (event) {
      const modal = document.getElementById('scheduleModal');
      if (event.target === modal) {
        closeScheduleModal();
      }
    }

    // Handle schedule form submission
    document.getElementById('scheduleServiceForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'schedule');
      formData.append('homestayId', document.getElementById('scheduleHomestayId').value);
      formData.append('serviceId', document.getElementById('scheduleServiceId').value);
      formData.append('scheduleDate', document.getElementById('scheduleDate').value);

      fetch('service.php', {
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
        .catch(error => alert('Error scheduling service: ' + error));
    });

    function deleteSchedule(homestayId, serviceId, scheduleDate) {
      if (confirm('Are you sure you want to delete this scheduled service?')) {
        const formData = new FormData();
        formData.append('action', 'delete_schedule');
        formData.append('homestayId', homestayId);
        formData.append('serviceId', serviceId);
        formData.append('scheduleDate', scheduleDate);

        fetch('service.php', {
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
          .catch(error => alert('Error deleting schedule: ' + error));
      }
    }

    function markAsDone(homestayId, serviceId, scheduleDate) {
      if (confirm('Mark this service as done?')) {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('homestayId', homestayId);
        formData.append('serviceId', serviceId);
        formData.append('scheduleDate', scheduleDate);
        formData.append('status', 'done');

        fetch('service.php', {
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
          .catch(error => alert('Error updating status: ' + error));
      }
    }
  </script>
</body>

</html>