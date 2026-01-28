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
    $guestID = $_POST['guestId'];
    $name = $_POST['guestName'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];
    $type = $_POST['type'];

    $sql = "UPDATE GUEST SET 
                guest_name = :name, 
                guest_phoneNo = :phone, 
                guest_gender = :gender, 
                guest_email = :email, 
                guest_type = :type 
                WHERE guestID = :id";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':gender', $gender);
    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':type', $type);
    oci_bind_by_name($stmt, ':id', $guestID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Guest updated successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }

  if ($_POST['action'] === 'delete') {
    $guestID = $_POST['guestId'];

    // Check if guest has bookings
    $checkSql = "SELECT COUNT(*) as count FROM BOOKING WHERE guestID = :id";
    $checkStmt = oci_parse($conn, $checkSql);
    oci_bind_by_name($checkStmt, ':id', $guestID);
    oci_execute($checkStmt);
    $row = oci_fetch_array($checkStmt, OCI_ASSOC);

    if ($row['COUNT'] > 0) {
      echo json_encode(['success' => false, 'message' => 'Cannot delete guest with existing bookings']);
      oci_free_statement($checkStmt);
      exit;
    }
    oci_free_statement($checkStmt);

    // Delete membership first if exists
    $delMemSql = "DELETE FROM MEMBERSHIP WHERE guestID = :id";
    $delMemStmt = oci_parse($conn, $delMemSql);
    oci_bind_by_name($delMemStmt, ':id', $guestID);
    oci_execute($delMemStmt);
    oci_free_statement($delMemStmt);

    // Delete guest
    $sql = "DELETE FROM GUEST WHERE guestID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $guestID);

    if (oci_execute($stmt)) {
      echo json_encode(['success' => true, 'message' => 'Guest deleted successfully']);
    } else {
      $error = oci_error($stmt);
      echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    }
    oci_free_statement($stmt);
    exit;
  }
}

// Fetch all guests
$sql = "SELECT guestID, guest_name, guest_phoneNo, guest_gender, guest_email, guest_type FROM GUEST ORDER BY guestID";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$guests = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
  // Sanitize guest type
  $type = $row['GUEST_TYPE'] ?? '';
  if (empty($type) || strtolower($type) === 'null') {
    $row['GUEST_TYPE'] = 'Regular';
  }
  $guests[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <title>Guests</title>
  <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/guestsStyle.css">
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
      <h1>Guests</h1>
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
              <option value="gender">By Gender</option>
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
              <input id="search" type="text" placeholder="Search Guests" />
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
              <th>Guest ID</th>
              <th>Guest Name</th>
              <th>Phone No.</th>
              <th>Gender</th>
              <th>Email</th>
              <th>Type</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="guestsTableBody">
            <?php foreach ($guests as $guest): ?>
              <tr data-name="<?php echo htmlspecialchars($guest['GUEST_NAME']); ?>"
                data-gender="<?php echo htmlspecialchars($guest['GUEST_GENDER']); ?>"
                data-type="<?php echo htmlspecialchars($guest['GUEST_TYPE']); ?>">
                <td><?php echo htmlspecialchars($guest['GUESTID']); ?></td>
                <td><?php echo htmlspecialchars($guest['GUEST_NAME']); ?></td>
                <td><?php echo htmlspecialchars($guest['GUEST_PHONENO']); ?></td>
                <td><?php echo htmlspecialchars($guest['GUEST_GENDER']); ?></td>
                <td><?php echo htmlspecialchars($guest['GUEST_EMAIL']); ?></td>
                <td><?php echo htmlspecialchars($guest['GUEST_TYPE']); ?></td>
                <td>
                  <button class="btn-update" onclick="updateGuest('<?php echo $guest['GUESTID']; ?>')">Update</button>
                  <button class="btn-delete" onclick="deleteGuest('<?php echo $guest['GUESTID']; ?>')">Delete</button>
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

  <!-- Update Guest Modal -->
  <div id="updateModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Update Guest Details</h2>
        <span class="close-modal">&times;</span>
      </div>
      <form id="updateGuestForm" class="modal-form">
        <div class="form-group">
          <label for="updateGuestId">Guest ID</label>
          <input type="text" id="updateGuestId" name="guestId" readonly>
        </div>
        <div class="form-group">
          <label for="updateGuestName">Guest Name</label>
          <input type="text" id="updateGuestName" name="guestName" required>
        </div>
        <div class="form-group">
          <label for="updatePhone">Phone No.</label>
          <input type="text" id="updatePhone" name="phone" required>
        </div>
        <div class="form-group">
          <label for="updateGender">Gender</label>
          <select id="updateGender" name="gender" required>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div class="form-group">
          <label for="updateEmail">Email</label>
          <input type="email" id="updateEmail" name="email" required>
        </div>
        <div class="form-group">
          <label for="updateType">Type</label>
          <select id="updateType" name="type" required>
            <option value="Regular">Regular</option>
            <option value="Member">Member</option>
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
    const tableBody = document.getElementById('guestsTableBody');

    function sortTable() {
      const sortBy = sortBySelect.value;
      const sortOrder = sortOrderSelect.value;

      if (!sortBy || !sortOrder) {
        return; // Don't sort if either option is not selected
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
          case 'alphabetical':
            const nameA = a.getAttribute('data-name').toLowerCase();
            const nameB = b.getAttribute('data-name').toLowerCase();
            comparison = nameA.localeCompare(nameB);
            break;
          case 'gender':
            const genderA = a.getAttribute('data-gender');
            const genderB = b.getAttribute('data-gender');
            comparison = genderA.localeCompare(genderB);
            break;
          case 'type':
            const typeA = a.getAttribute('data-type');
            const typeB = b.getAttribute('data-type');
            comparison = typeA.localeCompare(typeB);
            break;
        }

        // Apply sort order (ascending or descending)
        return sortOrder === 'ascending' ? comparison : -comparison;
      });

      // Clear the table body and append sorted rows
      tableBody.innerHTML = '';
      rows.forEach(row => tableBody.appendChild(row));
    }

    // Add event listeners to both spinners
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
          const email = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
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

    // Store original rows for search
    allRows = Array.from(tableBody.querySelectorAll('tr'));

    // Update and Delete functions
    function updateGuest(guestId) {
      // Find the row with the matching guest ID
      const rows = tableBody.querySelectorAll('tr');
      let targetRow = null;

      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === guestId) {
          targetRow = row;
        }
      });

      if (!targetRow) {
        alert('Guest not found!');
        return;
      }

      // Get current values from the row
      const cells = targetRow.querySelectorAll('td');
      const currentData = {
        id: cells[0].textContent.trim(),
        name: cells[1].textContent.trim(),
        phone: cells[2].textContent.trim(),
        gender: cells[3].textContent.trim(),
        email: cells[4].textContent.trim(),
        type: cells[5].textContent.trim()
      };

      // Populate the form with current data
      document.getElementById('updateGuestId').value = currentData.id;
      document.getElementById('updateGuestName').value = currentData.name;
      document.getElementById('updatePhone').value = currentData.phone;
      // Handle case sensitivity for gender
      const genderValue = currentData.gender.charAt(0).toUpperCase() + currentData.gender.slice(1).toLowerCase();
      document.getElementById('updateGender').value = genderValue;
      document.getElementById('updateEmail').value = currentData.email;
      document.getElementById('updateType').value = currentData.type;

      // Store the target row for later use
      document.getElementById('updateModal').setAttribute('data-target-row', guestId);

      // Show the modal
      document.getElementById('updateModal').style.display = 'block';
    }

    function closeUpdateModal() {
      document.getElementById('updateModal').style.display = 'none';
      document.getElementById('updateGuestForm').reset();
    }

    // Handle form submission
    document.getElementById('updateGuestForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('guestId', document.getElementById('updateGuestId').value);
      formData.append('guestName', document.getElementById('updateGuestName').value.trim());
      formData.append('phone', document.getElementById('updatePhone').value.trim());
      formData.append('gender', document.getElementById('updateGender').value);
      formData.append('email', document.getElementById('updateEmail').value.trim());
      formData.append('type', document.getElementById('updateType').value);

      fetch('guests.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload(); // Reload page to show updated data
          } else {
            alert(data.message);
          }
        })
        .catch(error => {
          alert('Error updating guest: ' + error);
        });
    });

    // Close modal when clicking outside of it
    window.onclick = function (event) {
      const modal = document.getElementById('updateModal');
      if (event.target === modal) {
        closeUpdateModal();
      }
    }

    // Close modal with X button
    document.querySelector('.close-modal').addEventListener('click', closeUpdateModal);

    function deleteGuest(guestId) {
      if (confirm('Are you sure you want to delete guest ' + guestId + '? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('guestId', guestId);

        fetch('guests.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            alert(data.message);
            if (data.success) {
              location.reload(); // Reload page to show updated data
            }
          })
          .catch(error => {
            alert('Error deleting guest: ' + error);
          });
      }
    }
  </script>
</body>

</html>