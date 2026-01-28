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
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $rentPrice = $_POST['rentPrice'];
    $staffID = $_POST['staffId'];

    $sql = "INSERT INTO HOMESTAY (homestay_name, homestay_address, office_phoneNo, rent_price, staffID) 
                VALUES (:name, :address, :phone, :rentPrice, :staffID)";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':address', $address);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':rentPrice', $rentPrice);
    oci_bind_by_name($stmt, ':staffID', $staffID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Homestay added successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'update') {
    $homestayID = $_POST['homestayId'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $rentPrice = $_POST['rentPrice'];
    $staffID = $_POST['staffId'];

    $sql = "UPDATE HOMESTAY SET 
                homestay_name = :name, 
                homestay_address = :address, 
                office_phoneNo = :phone, 
                rent_price = :rentPrice, 
                staffID = :staffID 
                WHERE homestayID = :id";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':address', $address);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':rentPrice', $rentPrice);
    oci_bind_by_name($stmt, ':staffID', $staffID);
    oci_bind_by_name($stmt, ':id', $homestayID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Homestay updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $homestayID = $_POST['homestayId'];

    // Check if homestay has bookings
    $checkSql = "SELECT COUNT(*) as count FROM BOOKING WHERE homestayID = :id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':id', $homestayID);
    oci_execute($checkStmt);
    $row = oci_fetch_array($checkStmt, OCI_ASSOC);

    if ($row['COUNT'] > 0) {
      echo json_encode(['success' => false, 'message' => 'Cannot delete homestay with existing bookings']);
      oci_free_statement($checkStmt);
      exit;
    }
    oci_free_statement($checkStmt);

    // Delete homestay
    $sql = "DELETE FROM HOMESTAY WHERE homestayID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $homestayID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Homestay deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

// Fetch all homestays
$sql = "SELECT homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID FROM HOMESTAY ORDER BY homestayID";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$homestays = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  $homestays[] = $row;
}
oci_free_statement($stmt);

// Fetch all staff for dropdown
$staffSql = "SELECT staffID, staff_name FROM STAFF ORDER BY staffID";
$staffStmt = oci_parse($conn, $staffSql);
oci_execute($staffStmt);

$staff = [];
while ($row = oci_fetch_array($staffStmt, OCI_ASSOC)) {
  $staff[] = $row;
}
oci_free_statement($staffStmt);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Homestay</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/homestayStyle.css">
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
      <h1>Homestay</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add" onclick="openAddModal()">
          <i class='bx bx-plus'></i> Add Homestay
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
              <option value="alphabetical">By Alphabetical</option>
              <option value="rentPrice">By Rent Price</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="id">ID</option>
                <option value="name">Name</option>
                <option value="address">Address</option>
                <option value="phone">Office Phone</option>
                <option value="staffId">Staff ID</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Homestay" />
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
              <th>Homestay ID</th>
              <th>Homestay Name</th>
              <th>Address</th>
              <th>Office Phone No.</th>
              <th>Rent Price (per night)</th>
              <th>Staff ID</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="propertiesTableBody">
            <?php foreach ($homestays as $homestay): ?>
              <tr data-name="<?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?>"
                data-rent-price="<?php echo htmlspecialchars($homestay['RENT_PRICE']); ?>">
                <td><?php echo htmlspecialchars($homestay['HOMESTAYID']); ?></td>
                <td><?php echo htmlspecialchars($homestay['HOMESTAY_NAME']); ?></td>
                <td><?php echo htmlspecialchars($homestay['HOMESTAY_ADDRESS']); ?></td>
                <td><?php echo htmlspecialchars($homestay['OFFICE_PHONENO']); ?></td>
                <td>RM <?php echo number_format($homestay['RENT_PRICE'], 0); ?></td>
                <td><?php echo htmlspecialchars($homestay['STAFFID']); ?></td>
                <td>
                  <button class="btn-update"
                    onclick="updateProperty('<?php echo $homestay['HOMESTAYID']; ?>')">Update</button>
                  <button class="btn-delete"
                    onclick="deleteProperty('<?php echo $homestay['HOMESTAYID']; ?>')">Delete</button>
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

  <!-- Update Property Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Homestay Details</h2>
        <span class="close-modal">&times;</span>
      </div>
      <form id="updatePropertyForm" class="modal-form">
        <div class="form-group">
          <label for="updatePropertyId">Homestay ID</label>
          <input type="text" id="updatePropertyId" name="propertyId" readonly>
        </div>
        <div class="form-group">
          <label for="updatePropertyName">Homestay Name</label>
          <input type="text" id="updatePropertyName" name="propertyName" required>
        </div>
        <div class="form-group">
          <label for="updateAddress">Address</label>
          <input type="text" id="updateAddress" name="address" required>
        </div>
        <div class="form-group">
          <label for="updateOfficePhone">Office Phone No.</label>
          <input type="text" id="updateOfficePhone" name="officePhone" required>
        </div>
        <div class="form-group">
          <label for="updateRentPrice">Rent Price (per night)</label>
          <input type="number" id="updateRentPrice" name="rentPrice" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="updateStaffId">Staff ID</label>
          <select id="updateStaffId" name="staffId" required>
            <?php foreach ($staff as $s): ?>
              <option value="<?php echo htmlspecialchars($s['STAFFID']); ?>">
                <?php echo htmlspecialchars($s['STAFFID']); ?> - <?php echo htmlspecialchars($s['STAFF_NAME']); ?>
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

  <!-- Add Property Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Homestay</h2>
        <span class="close-add-modal">&times;</span>
      </div>
      <form id="addPropertyForm" class="modal-form">
        <div class="form-group">
          <label for="addPropertyName">Homestay Name</label>
          <input type="text" id="addPropertyName" name="propertyName" required>
        </div>
        <div class="form-group">
          <label for="addAddress">Address</label>
          <input type="text" id="addAddress" name="address" required>
        </div>
        <div class="form-group">
          <label for="addOfficePhone">Office Phone No.</label>
          <input type="text" id="addOfficePhone" name="officePhone" required>
        </div>
        <div class="form-group">
          <label for="addRentPrice">Rent Price (per night)</label>
          <input type="number" id="addRentPrice" name="rentPrice" required min="0" step="0.01">
        </div>
        <div class="form-group">
          <label for="addStaffId">Staff ID</label>
          <select id="addStaffId" name="staffId" required>
            <option value="">Select Staff</option>
            <?php foreach ($staff as $s): ?>
              <option value="<?php echo htmlspecialchars($s['STAFFID']); ?>">
                <?php echo htmlspecialchars($s['STAFFID']); ?> - <?php echo htmlspecialchars($s['STAFF_NAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="btn-save">Add Homestay</button>
        </div>
      </form>
    </div>
  </div>

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

    // Sorting functionality
    const sortOrderSelect = document.getElementById('sortOrder');
    const sortBySelect = document.getElementById('sortBy');
    const tableBody = document.getElementById('propertiesTableBody');

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
          case 'alphabetical':
            const nameA = a.getAttribute('data-name').toLowerCase();
            const nameB = b.getAttribute('data-name').toLowerCase();
            comparison = nameA.localeCompare(nameB);
            break;
          case 'rentPrice':
            const priceA = parseFloat(a.getAttribute('data-rent-price'));
            const priceB = parseFloat(b.getAttribute('data-rent-price'));
            comparison = priceA - priceB;
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
        } else if (searchType === 'name') {
          const name = row.getAttribute('data-name').toLowerCase();
          matches = name.includes(searchTerm);
        } else if (searchType === 'address') {
          const address = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
          matches = address.includes(searchTerm);
        } else if (searchType === 'phone') {
          const phone = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
          matches = phone.includes(searchTerm);
        } else if (searchType === 'staffId') {
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
    function updateProperty(propertyId) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === propertyId) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Homestay not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      const currentData = {
        id: cells[0].textContent.trim(),
        name: cells[1].textContent.trim(),
        address: cells[2].textContent.trim(),
        officePhone: cells[3].textContent.trim(),
        rentPrice: targetRow.getAttribute('data-rent-price'),
        staffId: cells[5].textContent.trim()
      };

      document.getElementById('updatePropertyId').value = currentData.id;
      document.getElementById('updatePropertyName').value = currentData.name;
      document.getElementById('updateAddress').value = currentData.address;
      document.getElementById('updateOfficePhone').value = currentData.officePhone;
      document.getElementById('updateRentPrice').value = currentData.rentPrice;
      document.getElementById('updateStaffId').value = currentData.staffId;

      document.getElementById('updateModal').setAttribute('data-target-row', propertyId);
      document.getElementById('updateModal').style.display = 'block';
    }

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updatePropertyForm').reset();
    }

    document.getElementById('updatePropertyForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('homestayId', document.getElementById('updatePropertyId').value);
      formData.append('name', document.getElementById('updatePropertyName').value.trim());
      formData.append('address', document.getElementById('updateAddress').value.trim());
      formData.append('phone', document.getElementById('updateOfficePhone').value.trim());
      formData.append('rentPrice', document.getElementById('updateRentPrice').value);
      formData.append('staffId', document.getElementById('updateStaffId').value);

      fetch('homestay.php', {
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
          alert('Error updating homestay: ' + error);
        });
    });

    document.querySelector('.close-modal').addEventListener('click', closeUpdateModal);

    // Add Modal Functions
    function openAddModal() {
      document.getElementById('addModal').style.display = 'block';
    }

    function closeAddModal() {
      document.getElementById('addModal').style.display = 'none';
      document.getElementById('addPropertyForm').reset();
    }

    document.querySelector('.close-add-modal').addEventListener('click', closeAddModal);

    document.getElementById('addPropertyForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'add');
      formData.append('name', document.getElementById('addPropertyName').value.trim());
      formData.append('address', document.getElementById('addAddress').value.trim());
      formData.append('phone', document.getElementById('addOfficePhone').value.trim());
      formData.append('rentPrice', document.getElementById('addRentPrice').value);
      formData.append('staffId', document.getElementById('addStaffId').value);

      fetch('homestay.php', {
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
          alert('Error adding homestay: ' + error);
        });
    });

    window.onclick = function (event) {
      const updateModal = document.getElementById('updateModal');
      const addModal = document.getElementById('addModal');
      if (event.target === updateModal) {
        closeUpdateModal();
      }
      if (event.target === addModal) {
        closeAddModal();
      }
    }

    function deleteProperty(propertyId) {
      if (confirm('Are you sure you want to delete homestay ' + propertyId + '? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('homestayId', propertyId);

        fetch('homestay.php', {
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
            alert('Error deleting homestay: ' + error);
          });
      }
    }
  </script>
</body>

</html>