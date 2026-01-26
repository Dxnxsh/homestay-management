<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');

  if ($_POST['action'] === 'add') {
    $serviceID = $_POST['serviceId'];
    $serviceType = $_POST['serviceType'];
    $serviceCost = $_POST['serviceCost'];
    $serviceRemark = $_POST['serviceRemark'];
    $serviceStatus = $_POST['serviceStatus'];
    $staffID = !empty($_POST['staffId']) ? $_POST['staffId'] : null;

    $sql = "INSERT INTO SERVICE (serviceID, service_type, service_cost, service_remark, service_status, staffID)
            VALUES (:id, :type, :cost, :remark, :status, :staffID)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $serviceID);
    oci_bind_by_name($stmt, ':type', $serviceType);
    oci_bind_by_name($stmt, ':cost', $serviceCost);
    oci_bind_by_name($stmt, ':remark', $serviceRemark);
    oci_bind_by_name($stmt, ':status', $serviceStatus);
    oci_bind_by_name($stmt, ':staffID', $staffID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Service added successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'update') {
    $serviceID = $_POST['serviceId'];
    $serviceType = $_POST['serviceType'];
    $serviceCost = $_POST['serviceCost'];
    $serviceRemark = $_POST['serviceRemark'];
    $serviceStatus = $_POST['serviceStatus'];
    $staffID = !empty($_POST['staffId']) ? $_POST['staffId'] : null;

    $sql = "UPDATE SERVICE SET service_type = :type, service_cost = :cost, service_remark = :remark,
            service_status = :status, staffID = :staffID WHERE serviceID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':type', $serviceType);
    oci_bind_by_name($stmt, ':cost', $serviceCost);
    oci_bind_by_name($stmt, ':remark', $serviceRemark);
    oci_bind_by_name($stmt, ':status', $serviceStatus);
    oci_bind_by_name($stmt, ':staffID', $staffID);
    oci_bind_by_name($stmt, ':id', $serviceID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $serviceID = $_POST['serviceId'];

    $checkSql = "SELECT COUNT(*) AS cnt FROM HOMESTAY_SERVICE WHERE serviceID = :id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':id', $serviceID);
    oci_execute($checkStmt);
    $checkRow = oci_fetch_array($checkStmt, OCI_ASSOC);
    oci_free_statement($checkStmt);

    if ($checkRow && $checkRow['CNT'] > 0) {
      echo json_encode(['success' => false, 'message' => 'Cannot delete service linked to homestays']);
      exit;
    }

    $sql = "DELETE FROM SERVICE WHERE serviceID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $serviceID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

$services = [];
$serviceSql = "SELECT serviceID, service_type, service_cost, service_remark, service_status, staffID FROM SERVICE ORDER BY serviceID DESC";
$serviceStmt = oci_parse($conn, $serviceSql);
oci_execute($serviceStmt);
while ($row = oci_fetch_array($serviceStmt, OCI_ASSOC)) {
  $services[] = $row;
}
oci_free_statement($serviceStmt);

$staffList = [];
$staffSql = "SELECT staffID, staff_name FROM STAFF ORDER BY staffID";
$staffStmt = oci_parse($conn, $staffSql);
oci_execute($staffStmt);
while ($row = oci_fetch_array($staffStmt, OCI_ASSOC)) {
  $staffList[] = $row;
}
oci_free_statement($staffStmt);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Services</title>
    <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/serviceStyle.css">
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
          <i class='bx bxs-chevron-down arrow' ></i>
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
          <i class='bx bxs-chevron-down arrow' ></i>
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
                <a href="../logout.php" class="profile-content" style="display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit;">
                  <i class='bx bx-log-out' style="font-size: 24px; margin-right: 10px;"></i>
                  <span class="link_name">Logout</span>
                </a>
              </div>
            </li>
          </ul>
  </div>
  <section class="home-section">
    <div class="home-content">
      <i class='bx bx-menu' ></i>
      <span class="text">Serena Sanctuary</span>
      <div class="header-profile">
        <i class='bxr  bx-user-circle'></i>
        <div class="header-profile-info">
          <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Manager'); ?></div>
          <div class="header-profile-job"><?php echo isManager() ? 'Manager' : 'Staff'; ?></div>
        </div>
      </div>
    </div>
    <div class="page-heading">
      <h1>Services</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add-service" onclick="addService()">Add Service</button>
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
              <option value="type">By Service Type</option>
              <option value="cost">By Cost</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="id">Service ID</option>
                <option value="type">Service Type</option>
                <option value="staff">Staff ID</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Services" />
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
              <th>Service ID</th>
              <th>Service Type</th>
              <th>Service Cost</th>
              <th>Service Remark</th>
              <th>Service Status</th>
              <th>Staff ID</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="servicesTableBody">
            <?php if (empty($services)): ?>
              <tr><td colspan="7" style="text-align:center; padding:14px;">No services found.</td></tr>
            <?php else: ?>
              <?php foreach ($services as $service): ?>
                <tr data-type="<?php echo htmlspecialchars($service['SERVICE_TYPE']); ?>" data-cost="<?php echo htmlspecialchars($service['SERVICE_COST']); ?>">
                  <td><?php echo htmlspecialchars($service['SERVICEID']); ?></td>
                  <td><?php echo htmlspecialchars($service['SERVICE_TYPE']); ?></td>
                  <td>RM <?php echo number_format((float)$service['SERVICE_COST'], 2, '.', ''); ?></td>
                  <td><?php echo htmlspecialchars($service['SERVICE_REMARK']); ?></td>
                  <td><?php echo htmlspecialchars($service['SERVICE_STATUS']); ?></td>
                  <td><?php echo htmlspecialchars($service['STAFFID'] ?? 'N/A'); ?></td>
                  <td>
                    <button class="btn-update" onclick="updateService('<?php echo htmlspecialchars($service['SERVICEID']); ?>')">Update</button>
                    <button class="btn-delete" onclick="deleteService('<?php echo htmlspecialchars($service['SERVICEID']); ?>')">Delete</button>
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

  <!-- Add Service Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Service</h2>
        <span class="close-modal" onclick="closeAddModal()">&times;</span>
      </div>
      <form id="addServiceForm" class="modal-form">
        <div class="form-group">
          <label for="addServiceId">Service ID</label>
          <input type="text" id="addServiceId" name="serviceId" required>
        </div>
        <div class="form-group">
          <label for="addServiceType">Service Type</label>
          <input type="text" id="addServiceType" name="serviceType" required>
        </div>
        <div class="form-group">
          <label for="addServiceCost">Service Cost</label>
          <input type="number" id="addServiceCost" name="serviceCost" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="addServiceRemark">Service Remark</label>
          <textarea id="addServiceRemark" name="serviceRemark" rows="3" required></textarea>
        </div>
        <div class="form-group">
          <label for="addServiceStatus">Service Status</label>
          <select id="addServiceStatus" name="serviceStatus" required>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label for="addStaffId">Staff ID</label>
          <select id="addStaffId" name="staffId" required>
            <option value="">Select Staff</option>
            <?php foreach ($staffList as $staff): ?>
              <option value="<?php echo htmlspecialchars($staff['STAFFID']); ?>"><?php echo htmlspecialchars($staff['STAFFID'] . ' - ' . $staff['STAFF_NAME']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="btn-save">Add Service</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Update Service Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Service Details</h2>
        <span class="close-modal" onclick="closeUpdateModal()">&times;</span>
      </div>
      <form id="updateServiceForm" class="modal-form">
        <div class="form-group">
          <label for="updateServiceId">Service ID</label>
          <input type="text" id="updateServiceId" name="serviceId" readonly>
        </div>
        <div class="form-group">
          <label for="updateServiceType">Service Type</label>
          <input type="text" id="updateServiceType" name="serviceType" required>
        </div>
        <div class="form-group">
          <label for="updateServiceCost">Service Cost</label>
          <input type="number" id="updateServiceCost" name="serviceCost" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateServiceRemark">Service Remark</label>
          <textarea id="updateServiceRemark" name="serviceRemark" rows="3" required></textarea>
        </div>
        <div class="form-group">
          <label for="updateServiceStatus">Service Status</label>
          <select id="updateServiceStatus" name="serviceStatus" required>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updateStaffId">Staff ID</label>
          <select id="updateStaffId" name="staffId" required>
            <option value="">Select Staff</option>
            <?php foreach ($staffList as $staff): ?>
              <option value="<?php echo htmlspecialchars($staff['STAFFID']); ?>"><?php echo htmlspecialchars($staff['STAFFID'] . ' - ' . $staff['STAFF_NAME']); ?></option>
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
    arrow[i].addEventListener("click", (e)=>{
   let arrowParent = e.target.parentElement.parentElement;
   arrowParent.classList.toggle("showMenu");
    });
  }
  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".bx-menu");
  console.log(sidebarBtn);
  sidebarBtn.addEventListener("click", ()=>{
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
      
      switch(sortBy) {
        case 'type':
          const typeA = a.getAttribute('data-type').toLowerCase();
          const typeB = b.getAttribute('data-type').toLowerCase();
          comparison = typeA.localeCompare(typeB);
          break;
        case 'cost':
          const costA = parseFloat(a.getAttribute('data-cost'));
          const costB = parseFloat(b.getAttribute('data-cost'));
          comparison = costA - costB;
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
      } else if (searchType === 'type') {
        const type = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        matches = type.includes(searchTerm);
      } else if (searchType === 'staff') {
        const staffId = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
        matches = staffId.includes(searchTerm);
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
  function updateService(serviceId) {
    const rows = tableBody.querySelectorAll('tr');
    let targetRow = null;
    
    rows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === serviceId) {
        targetRow = row;
      }
    });
    
    if (!targetRow) {
      alert('Service not found!');
      return;
    }
    
    const cells = targetRow.querySelectorAll('td');
    const currentData = {
      serviceId: cells[0].textContent.trim(),
      serviceType: cells[1].textContent.trim(),
      serviceCost: cells[2].textContent.trim().replace('RM', '').trim(),
      serviceRemark: cells[3].textContent.trim(),
      serviceStatus: cells[4].textContent.trim(),
      staffId: cells[5].textContent.trim()
    };
    
    document.getElementById('updateServiceId').value = currentData.serviceId;
    document.getElementById('updateServiceType').value = currentData.serviceType;
    document.getElementById('updateServiceCost').value = currentData.serviceCost;
    document.getElementById('updateServiceRemark').value = currentData.serviceRemark;
    document.getElementById('updateServiceStatus').value = currentData.serviceStatus;
    document.getElementById('updateStaffId').value = currentData.staffId;
    
    document.getElementById('updateModal').setAttribute('data-target-row', serviceId);
    document.getElementById('updateModal').style.display = 'block';
  }

  function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
    document.getElementById('updateServiceForm').reset();
  }

  window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const updateModal = document.getElementById('updateModal');
    if (event.target === addModal) {
      closeAddModal();
    }
    if (event.target === updateModal) {
      closeUpdateModal();
    }
  }

  function deleteService(serviceId) {
    if (confirm('Are you sure you want to delete service ' + serviceId + '? This cannot be undone.')) {
      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('serviceId', serviceId);

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
      .catch(error => alert('Error deleting service: ' + error));
    }
  }

  function addService() {
    // Clear the form
    document.getElementById('addServiceForm').reset();
    // Show the modal
    document.getElementById('addModal').style.display = 'block';
  }

  function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('addServiceForm').reset();
  }

  // Handle add service form submission
  document.getElementById('addServiceForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('serviceId', document.getElementById('addServiceId').value.trim());
    formData.append('serviceType', document.getElementById('addServiceType').value.trim());
    formData.append('serviceCost', document.getElementById('addServiceCost').value);
    formData.append('serviceRemark', document.getElementById('addServiceRemark').value.trim());
    formData.append('serviceStatus', document.getElementById('addServiceStatus').value);
    formData.append('staffId', document.getElementById('addStaffId').value);

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
    .catch(error => alert('Error adding service: ' + error));
  });

  // Handle update service form submission
  document.getElementById('updateServiceForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('serviceId', document.getElementById('updateServiceId').value);
    formData.append('serviceType', document.getElementById('updateServiceType').value.trim());
    formData.append('serviceCost', document.getElementById('updateServiceCost').value);
    formData.append('serviceRemark', document.getElementById('updateServiceRemark').value.trim());
    formData.append('serviceStatus', document.getElementById('updateServiceStatus').value);
    formData.append('staffId', document.getElementById('updateStaffId').value);

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
    .catch(error => alert('Error updating service: ' + error));
  });
  </script>
</body>
</html>

