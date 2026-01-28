<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';

// Only managers can access staff management
if (!isManager()) {
  header('Location: dashboard.php');
  exit();
}

requireStaffLogin();

// Fetch all staff
$staffMembers = [];
$conn = getDBConnection();

if ($conn) {
  // Join with self for Manager Name (Recursive)
  // Join with FULL_TIME and PART_TIME for specific details (Inheritance)
  $sql = "SELECT s.STAFFID, s.STAFF_NAME, s.STAFF_PHONENO, s.STAFF_EMAIL, s.STAFF_PASSWORD, s.STAFF_TYPE,
                   m.STAFF_NAME as MANAGER_NAME, s.MANAGERID,
                   ft.FULL_TIME_SALARY, ft.VACATION_DAYS, ft.BONUS,
                   pt.HOURLY_RATE, pt.SHIFT_TIME
            FROM STAFF s
            LEFT JOIN STAFF m ON s.MANAGERID = m.STAFFID
            LEFT JOIN FULL_TIME ft ON s.STAFFID = ft.STAFFID
            LEFT JOIN PART_TIME pt ON s.STAFFID = pt.STAFFID
            ORDER BY s.STAFFID ASC";

  $stmt = oci_parse($conn, $sql);

  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
      $staffMembers[] = $row;
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
  <title>Staff</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/staffStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    /* Tooltip for extra details */
    .detail-tooltip {
      position: relative;
      cursor: help;
      border-bottom: 1px dotted #333;
    }

    .detail-tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #333;
      color: #fff;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      z-index: 10;
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
      <h1>Staff Management</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add" onclick="openAddModal()">
          <i class='bx bx-plus'></i> Add Staff
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
              <option value="type">By Type</option>
            </select>
          </div>
        </div>
        <form class="search-form">
          <div class="search-container">
            <div class="search-by">
              <select id="searchType" name="search-type">
                <option value="id">ID</option>
                <option value="name">Name</option>
                <option value="phone">Phone No.</option>
                <option value="email">Email</option>
              </select>
            </div>
            <div class="search-input">
              <input id="search" type="text" placeholder="Search Staff" />
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
              <th>Staff ID</th>
              <th>Staff Name</th>
              <th>Phone No.</th>
              <th>Email</th>
              <!-- Password hidden for security, but kept in data attributes if needed -->
              <th>Type</th>
              <th>Reports To (Manager)</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="staffTableBody">
            <?php foreach ($staffMembers as $staff): ?>
              <?php
              // Prepare tooltip details based on type
              $details = "";
              if ($staff['STAFF_TYPE'] === 'Full Time' || $staff['STAFF_TYPE'] === 'Full-time') {
                $details = "Salary: RM " . $staff['FULL_TIME_SALARY'] . " | Vacation: " . $staff['VACATION_DAYS'] . " days";
              } elseif ($staff['STAFF_TYPE'] === 'Part Time' || $staff['STAFF_TYPE'] === 'Part-time') {
                $details = "Hourly: RM " . $staff['HOURLY_RATE'] . "/hr | Shift: " . $staff['SHIFT_TIME'];
              }

              $managerDisplay = $staff['MANAGERID'] ? htmlspecialchars($staff['MANAGER_NAME'] . " (" . $staff['MANAGERID'] . ")") : "-";
              ?>
              <tr data-name="<?php echo htmlspecialchars($staff['STAFF_NAME']); ?>"
                data-type="<?php echo htmlspecialchars($staff['STAFF_TYPE']); ?>"
                data-id="<?php echo htmlspecialchars($staff['STAFFID']); ?>">
                <td><?php echo htmlspecialchars($staff['STAFFID']); ?></td>
                <td><?php echo htmlspecialchars($staff['STAFF_NAME']); ?></td>
                <td><?php echo htmlspecialchars($staff['STAFF_PHONENO']); ?></td>
                <td><?php echo htmlspecialchars($staff['STAFF_EMAIL']); ?></td>
                <td>
                  <span class="detail-tooltip" data-tooltip="<?php echo htmlspecialchars($details); ?>">
                    <?php echo htmlspecialchars($staff['STAFF_TYPE']); ?>
                  </span>
                </td>
                <td><?php echo $managerDisplay; ?></td>
                <td>
                  <button class="btn-update" onclick="updateStaff('<?php echo $staff['STAFFID']; ?>')">Update</button>
                  <button class="btn-delete" onclick="deleteStaff('<?php echo $staff['STAFFID']; ?>')">Delete</button>
                </td>
                <!-- Hidden fields for JS use -->
                <td style="display:none;" class="hidden-password">
                  <?php echo htmlspecialchars($staff['STAFF_PASSWORD']); ?>
                </td>
                <td style="display:none;" class="hidden-manager-id">
                  <?php echo htmlspecialchars($staff['MANAGERID'] ?? '-'); ?>
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

  <!-- Update Staff Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Staff Details</h2>
        <span class="close-modal">&times;</span>
      </div>
      <form id="updateStaffForm" class="modal-form">
        <div class="form-group">
          <label for="updateStaffId">Staff ID</label>
          <input type="text" id="updateStaffId" name="staffId" readonly>
        </div>
        <div class="form-group">
          <label for="updateStaffName">Staff Name</label>
          <input type="text" id="updateStaffName" name="staffName" required>
        </div>
        <div class="form-group">
          <label for="updatePhone">Phone No.</label>
          <input type="text" id="updatePhone" name="phone" required>
        </div>
        <div class="form-group">
          <label for="updateEmail">Email</label>
          <input type="email" id="updateEmail" name="email" required>
        </div>
        <div class="form-group">
          <label for="updatePassword">Password</label>
          <input type="password" id="updatePassword" name="password" required>
        </div>
        <div class="form-group">
          <label for="updateType">Type</label>
          <select id="updateType" name="type" required onchange="toggleManagerId()">
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updateManagerId">Manager ID</label>
          <select id="updateManagerId" name="managerId">
            <option value="">-</option>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-save">Save Changes</button>
          <button type="button" class="btn-cancel" onclick="closeUpdateModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Staff Modal -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Staff</h2>
        <span class="close-modal" onclick="closeAddModal()">&times;</span>
      </div>
      <form id="addStaffForm" class="modal-form">
        <div class="form-group">
          <label for="addStaffName">Name</label>
          <input type="text" id="addStaffName" required>
        </div>
        <div class="form-group">
          <label for="addPhone">Phone No.</label>
          <input type="text" id="addPhone" required>
        </div>
        <div class="form-group">
          <label for="addEmail">Email</label>
          <input type="email" id="addEmail" required>
        </div>
        <div class="form-group">
          <label for="addPassword">Password</label>
          <input type="password" id="addPassword" value="pass123" required>
        </div>
        <div class="form-group">
          <label for="addType">Type</label>
          <select id="addType" onchange="toggleAddManagerId()" required>
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
          </select>
        </div>
        <div class="form-group">
          <label for="addManagerId">Reports To (Manager)</label>
          <select id="addManagerId" disabled>
            <option value="">-</option>
            <!-- Populated dynamically -->
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-save">Add Staff</button>
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
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
    const tableBody = document.getElementById('staffTableBody');

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
          case 'type':
            const typeA = a.getAttribute('data-type');
            const typeB = b.getAttribute('data-type');
            comparison = typeA.localeCompare(typeB);
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
        } else if (searchType === 'phone') {
          const phone = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
          matches = phone.includes(searchTerm);
        } else if (searchType === 'email') {
          const email = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
          matches = email.includes(searchTerm);
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
    function updateStaff(staffId) {
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === staffId) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Staff not found!');
        return;
      }

      const cells = targetRow.querySelectorAll('td');
      // Note: Adjust indices because of "Reports To" column and hidden columns
      // 0: ID, 1: Name, 2: Phone, 3: Email, 4: Type, 5: Reports To, 6: Actions, 7: Password, 8: ManagerID
      const hiddenPassword = targetRow.querySelector('.hidden-password').textContent.trim();
      const hiddenManagerId = targetRow.querySelector('.hidden-manager-id').textContent.trim();

      const currentData = {
        id: cells[0].textContent.trim(),
        name: cells[1].textContent.trim(),
        phone: cells[2].textContent.trim(),
        email: cells[3].textContent.trim(),
        password: hiddenPassword,
        type: targetRow.getAttribute('data-type'), // Get raw type from attribute
        managerId: hiddenManagerId === '-' ? '' : hiddenManagerId
      };

      let normalizedType = currentData.type;
      if (normalizedType === 'Full Time') normalizedType = 'Full-time';
      if (normalizedType === 'Part Time') normalizedType = 'Part-time';

      openUpdateModal(currentData.id, currentData.name, currentData.phone, currentData.email, currentData.password, normalizedType, currentData.managerId);
    }

    function openUpdateModal(staffId, name, phone, email, password, type, managerId) {
      document.getElementById('updateStaffId').value = staffId;
      document.getElementById('updateStaffName').value = name;
      document.getElementById('updatePhone').value = phone;
      document.getElementById('updateEmail').value = email;
      document.getElementById('updatePassword').value = password;
      document.getElementById('updateType').value = type;

      // Populate valid managers first, excluding self
      populateManagerDropdown('updateManagerId', staffId);

      document.getElementById('updateManagerId').value = managerId ? managerId : '';

      toggleManagerId(); // Updates disabled state

      document.getElementById('updateModal').setAttribute('data-target-row', staffId);
      document.getElementById('updateModal').style.display = 'block';
    }

    function openAddModal() {
      document.getElementById('addStaffForm').reset();
      populateManagerDropdown('addManagerId', null);
      toggleAddManagerId();
      document.getElementById('addModal').style.display = 'block';
    }

    function closeAddModal() {
      document.getElementById('addModal').style.display = 'none';
    }

    // New Helper to populate Manager Dropdown from existing table data (or fetch from API ideally)
    function populateManagerDropdown(selectId, excludeStaffId) {
      const select = document.getElementById(selectId);
      select.innerHTML = '<option value="">-</option>';

      // Use the rows already rendered
      const rows = document.querySelectorAll('#staffTableBody tr');
      rows.forEach(row => {
        const id = row.getAttribute('data-id');

        // Prevent assigning self as manager
        if (excludeStaffId && id === excludeStaffId) {
            return;
        }

        const name = row.getAttribute('data-name');
        // Find the hidden manager ID cell
        // It is the last td, but safer to query class
        const hiddenManagerIdCell = row.querySelector('.hidden-manager-id');
        const managerIdOfStaff = hiddenManagerIdCell ? hiddenManagerIdCell.textContent.trim() : '-';

        // A staff member can be a manager for others if they are a Manager themselves (i.e., they have NO manager)
        // Check if their manager ID is empty or '-'
        if (managerIdOfStaff === '-' || managerIdOfStaff === '') {
          const opt = document.createElement('option');
          opt.value = id;
          opt.textContent = name + ' (' + id + ')';
          select.appendChild(opt);
        }
      });
    }

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updateStaffForm').reset();
    }

    function toggleManagerId() {
      const typeSelect = document.getElementById('updateType');
      const managerIdSelect = document.getElementById('updateManagerId');

      if (typeSelect.value === 'Full-time') {
        // Full-time staff CAN have a manager (be a subordinate) OR not (be a manager)
        // So we do NOT disable it, but it is not required.
        managerIdSelect.disabled = false;
        managerIdSelect.required = false;
      } else {
        // Part-time staff MUST have a manager (business rule assumed/enforced in submit)
        managerIdSelect.disabled = false;
        managerIdSelect.required = true;
      }
    }

    function toggleAddManagerId() {
      const typeSelect = document.getElementById('addType');
      const managerIdSelect = document.getElementById('addManagerId');

      if (typeSelect.value === 'Full-time') {
        // Full-time staff CAN have a manager (be a subordinate) OR not (be a manager)
        managerIdSelect.disabled = false;
        managerIdSelect.required = false;
      } else {
        managerIdSelect.disabled = false;
        managerIdSelect.required = true;
      }
    }

    document.getElementById('updateStaffForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const staffId = document.getElementById('updateStaffId').value;
      const typeValue = document.getElementById('updateType').value;
      const managerIdValue = document.getElementById('updateManagerId').value;

      const payload = {
        staffId: staffId,
        name: document.getElementById('updateStaffName').value,
        phone: document.getElementById('updatePhone').value,
        email: document.getElementById('updateEmail').value,
        password: document.getElementById('updatePassword').value,
        type: typeValue,
        managerId: managerIdValue
      };

      fetch('update_staff.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Staff updated successfully!');
            closeUpdateModal();
            location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while updating staff.');
        });
    });

    document.getElementById('addStaffForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const typeValue = document.getElementById('addType').value;
      const managerIdValue = document.getElementById('addManagerId').value;

      // Validate: Part Time must have a Manager ID
      if (typeValue === 'Part-time' && managerIdValue === '') {
        alert('Part Time staff must have a Manager ID');
        return;
      }

      const payload = {
        name: document.getElementById('addStaffName').value,
        phone: document.getElementById('addPhone').value,
        email: document.getElementById('addEmail').value,
        password: document.getElementById('addPassword').value,
        type: typeValue,
        managerId: managerIdValue
      };

      fetch('add_staff.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Staff added successfully!');
            closeAddModal();
            location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while adding staff.');
        });
    });

    window.onclick = function (event) {
      const modal = document.getElementById('updateModal');
      if (event.target === modal) {
        closeUpdateModal();
      }
    }

    document.querySelector('.close-modal').addEventListener('click', closeUpdateModal);

    function deleteStaff(staffId) {
      if (confirm('Are you sure you want to delete staff ' + staffId + '?')) {
        fetch('delete_staff.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ staffId: staffId })
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Staff deleted successfully!');
              location.reload();
            } else {
              console.error('Server reported error:', data.message);
              alert('Error: ' + (data.message || 'Failed to delete staff.'));
            }
          })
          .catch(error => {
            console.error('Fetch Error:', error);
            alert('An error occurred while deleting staff. Check console for details.');
          });
      }
    }

    // Window click to close modals
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
  </script>
</body>

</html>