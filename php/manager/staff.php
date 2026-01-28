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
      <h1>Staff Management</h1>
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
              if ($staff['STAFF_TYPE'] === 'Full Time') {
                $details = "Salary: RM " . $staff['FULL_TIME_SALARY'] . " | Vacation: " . $staff['VACATION_DAYS'] . " days";
              } elseif ($staff['STAFF_TYPE'] === 'Part Time') {
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
                  <?php echo htmlspecialchars($staff['STAFF_PASSWORD']); ?></td>
                <td style="display:none;" class="hidden-manager-id">
                  <?php echo htmlspecialchars($staff['MANAGERID'] ?? '-'); ?></td>
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
            <option value="Full Time">Full Time</option>
            <option value="Part Time">Part Time</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updateManagerId">Manager ID</label>
          <select id="updateManagerId" name="managerId">
            <option value="">-</option>
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

      document.getElementById('updateStaffId').value = currentData.id;
      document.getElementById('updateStaffName').value = currentData.name;
      document.getElementById('updatePhone').value = currentData.phone;
      document.getElementById('updateEmail').value = currentData.email;
      document.getElementById('updatePassword').value = currentData.password;
      document.getElementById('updateType').value = currentData.type;

      // Populate manager dropdown first, then set value
      toggleManagerId();
      document.getElementById('updateManagerId').value = currentData.managerId;

      document.getElementById('updateModal').setAttribute('data-target-row', staffId);
      document.getElementById('updateModal').style.display = 'block';
    }

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updateStaffForm').reset();
    }

    function toggleManagerId() {
      const typeSelect = document.getElementById('updateType');
      const managerIdSelect = document.getElementById('updateManagerId');
      const currentStaffId = document.getElementById('updateStaffId').value;

      if (typeSelect.value === 'Full Time') {
        managerIdSelect.value = '';
        managerIdSelect.disabled = true;
        managerIdSelect.required = false;
      } else {
        managerIdSelect.disabled = false;
        managerIdSelect.required = true;

        // Populate Manager ID dropdown with Full Time staff (excluding current staff)
        // Ideally this should come from a separate list of full-time staff
        managerIdSelect.innerHTML = '<option value="">-</option>';
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
          const idCell = row.querySelector('td:first-child');
          const typeAttr = row.getAttribute('data-type');
          const nameCell = row.querySelector('td:nth-child(2)');

          if (idCell && typeAttr && nameCell) {
            const staffId = idCell.textContent.trim();
            const staffType = typeAttr;
            const staffName = nameCell.textContent.trim();

            if (staffType === 'Full Time' && staffId !== currentStaffId) {
              const option = document.createElement('option');
              option.value = staffId;
              option.textContent = staffId + ' - ' + staffName;
              managerIdSelect.appendChild(option);
            }
          }
        });
      }
    }

    document.getElementById('updateStaffForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const staffId = document.getElementById('updateStaffId').value;
      const typeValue = document.getElementById('updateType').value;
      const managerIdValue = document.getElementById('updateManagerId').value;

      // Validate: Full Time should have no Manager ID
      if (typeValue === 'Full Time' && managerIdValue !== '') {
        alert('Full Time staff cannot have a Manager ID. Please set Manager ID to "-"');
        return;
      }

      // Validate: Part Time must have a Manager ID
      if (typeValue === 'Part Time' && managerIdValue === '') {
        alert('Part Time staff must have a Manager ID');
        return;
      }

      // In a real application, submit to backend here
      alert('This is a demo. Backend update logic would go here.');
      closeUpdateModal();
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
        // In a real application, call backend API here
        // For now, remove from table
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
          const idCell = row.querySelector('td:first-child');
          if (idCell && idCell.textContent.trim() === staffId) {
            row.remove();
            allRows = Array.from(tableBody.querySelectorAll('tr'));
            alert('Staff deleted successfully (Frontend only)!');
            return;
          }
        });
      }
    }
  </script>
</body>

</html>