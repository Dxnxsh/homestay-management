<?php
session_start();
require_once '../config/session_check.php';
requireManagerLogin();
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
          <i class='bx bxs-chevron-down arrow' ></i>
        </div>
        <ul class="sub-menu">
          <li><a class="link_name" href="manage.php">Manage</a></li>
          <li><a href="guests.php">Guests</a></li>
          <li><a href="staff.php">Staff</a></li>
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
          <div class="header-profile-job">Manager</div>
        </div>
      </div>
    </div>
    <div class="page-heading">
      <h1>Bookings</h1>
    </div>
    <!-- Content -->
    <div class="content">
      <div class="button-container">
        <button class="btn-add-booking" onclick="addBooking()">Add Booking</button>
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
            <tr data-date="2025-01-15" data-deposit="300" data-adults="2" data-children="1" data-staff="S001">
              <td>BK001</td>
              <td>2025-01-15</td>
              <td>2025-01-18</td>
              <td>RM 300</td>
              <td>P001</td>
              <td>G001</td>
              <td>B001</td>
              <td>
                <button class="btn-details" onclick="viewBookingDetails('BK001')">Details</button>
              </td>
              <td>
                <button class="btn-update" onclick="updateBooking('BK001')">Update</button>
                <button class="btn-delete" onclick="deleteBooking('BK001')">Delete</button>
              </td>
            </tr>
            <tr data-date="2025-01-20" data-deposit="270" data-adults="2" data-children="0" data-staff="S002">
              <td>BK002</td>
              <td>2025-01-20</td>
              <td>2025-01-23</td>
              <td>RM 270</td>
              <td>P002</td>
              <td>G002</td>
              <td>B002</td>
              <td>
                <button class="btn-details" onclick="viewBookingDetails('BK002')">Details</button>
              </td>
              <td>
                <button class="btn-update" onclick="updateBooking('BK002')">Update</button>
                <button class="btn-delete" onclick="deleteBooking('BK002')">Delete</button>
              </td>
            </tr>
            <tr data-date="2025-01-25" data-deposit="240" data-adults="1" data-children="2" data-staff="S001">
              <td>BK003</td>
              <td>2025-01-25</td>
              <td>2025-01-28</td>
              <td>RM 240</td>
              <td>P003</td>
              <td>G003</td>
              <td>B003</td>
              <td>
                <button class="btn-details" onclick="viewBookingDetails('BK003')">Details</button>
              </td>
              <td>
                <button class="btn-update" onclick="updateBooking('BK003')">Update</button>
                <button class="btn-delete" onclick="deleteBooking('BK003')">Delete</button>
              </td>
            </tr>
            <tr data-date="2025-02-01" data-deposit="330" data-adults="4" data-children="0" data-staff="S002">
              <td>BK004</td>
              <td>2025-02-01</td>
              <td>2025-02-04</td>
              <td>RM 330</td>
              <td>P004</td>
              <td>G004</td>
              <td>B004</td>
              <td>
                <button class="btn-details" onclick="viewBookingDetails('BK004')">Details</button>
              </td>
              <td>
                <button class="btn-update" onclick="updateBooking('BK004')">Update</button>
                <button class="btn-delete" onclick="deleteBooking('BK004')">Delete</button>
              </td>
            </tr>
            <tr data-date="2025-02-05" data-deposit="300" data-adults="2" data-children="1" data-staff="S001">
              <td>BK005</td>
              <td>2025-02-05</td>
              <td>2025-02-08</td>
              <td>RM 300</td>
              <td>P001</td>
              <td>G005</td>
              <td>-</td>
              <td>
                <button class="btn-details" onclick="viewBookingDetails('BK005')">Details</button>
              </td>
              <td>
                <button class="btn-update" onclick="updateBooking('BK005')">Update</button>
                <button class="btn-delete" onclick="deleteBooking('BK005')">Delete</button>
              </td>
            </tr>
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
          <label for="addHomestayId">Homestay ID</label>
          <input type="text" id="addHomestayId" name="homestayId" required>
        </div>
        <div class="form-group">
          <label for="addGuestId">Guest ID</label>
          <input type="text" id="addGuestId" name="guestId" required>
        </div>
        <div class="form-group">
          <label for="addStaffId">Staff ID</label>
          <input type="text" id="addStaffId" name="staffId" required>
        </div>
        <div class="form-group">
          <label for="addBillNo">Bill No.</label>
          <input type="text" id="addBillNo" name="billNo">
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
          <label for="updateHomestayId">Homestay ID</label>
          <input type="text" id="updateHomestayId" name="homestayId" required>
        </div>
        <div class="form-group">
          <label for="updateGuestId">Guest ID</label>
          <input type="text" id="updateGuestId" name="guestId" required>
        </div>
        <div class="form-group">
          <label for="updateStaffId">Staff ID</label>
          <input type="text" id="updateStaffId" name="staffId" required>
        </div>
        <div class="form-group">
          <label for="updateBillNo">Bill No.</label>
          <input type="text" id="updateBillNo" name="billNo">
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
      
      switch(sortBy) {
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
      deposit: cells[3].textContent.trim().replace('RM ', '').trim(),
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

  document.getElementById('updateBookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const bookingId = document.getElementById('updateBookingId').value;
    const updatedData = {
      checkIn: document.getElementById('updateCheckIn').value,
      checkOut: document.getElementById('updateCheckOut').value,
      numAdults: document.getElementById('updateNumAdults').value,
      numChildren: document.getElementById('updateNumChildren').value,
      deposit: parseFloat(document.getElementById('updateDeposit').value),
      homestayId: document.getElementById('updateHomestayId').value.trim(),
      guestId: document.getElementById('updateGuestId').value.trim(),
      staffId: document.getElementById('updateStaffId').value.trim(),
      billNo: document.getElementById('updateBillNo').value.trim() || '-'
    };
    
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === bookingId) {
        const cells = row.querySelectorAll('td');
        cells[1].textContent = updatedData.checkIn;
        cells[2].textContent = updatedData.checkOut;
        cells[3].textContent = 'RM ' + updatedData.deposit.toFixed(0);
        cells[4].textContent = updatedData.homestayId;
        cells[5].textContent = updatedData.guestId;
        cells[6].textContent = updatedData.billNo;
        
        row.setAttribute('data-date', updatedData.checkIn);
        row.setAttribute('data-deposit', updatedData.deposit);
        row.setAttribute('data-adults', updatedData.numAdults);
        row.setAttribute('data-children', updatedData.numChildren);
        row.setAttribute('data-staff', updatedData.staffId);
        
        allRows = Array.from(tableBody.querySelectorAll('tr'));
        closeUpdateModal();
        alert('Booking details updated successfully!');
        return;
      }
    });
  });

  window.onclick = function(event) {
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
    if (confirm('Are you sure you want to delete booking ' + bookingId + '?')) {
      const rows = tableBody.querySelectorAll('tr');
      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === bookingId) {
          row.remove();
          allRows = Array.from(tableBody.querySelectorAll('tr'));
          alert('Booking deleted successfully!');
          return;
        }
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
  document.getElementById('addBookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const newBooking = {
      bookingId: document.getElementById('addBookingId').value.trim(),
      checkIn: document.getElementById('addCheckIn').value,
      checkOut: document.getElementById('addCheckOut').value,
      numAdults: document.getElementById('addNumAdults').value,
      numChildren: document.getElementById('addNumChildren').value,
      deposit: parseFloat(document.getElementById('addDeposit').value),
      homestayId: document.getElementById('addHomestayId').value.trim(),
      guestId: document.getElementById('addGuestId').value.trim(),
      staffId: document.getElementById('addStaffId').value.trim(),
      billNo: document.getElementById('addBillNo').value.trim() || '-'
    };
    
    // Check if booking ID already exists
    const existingRows = tableBody.querySelectorAll('tr');
    let idExists = false;
    existingRows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === newBooking.bookingId) {
        idExists = true;
      }
    });
    
    if (idExists) {
      alert('Booking ID already exists! Please use a different ID.');
      return;
    }
    
    // Create new table row
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-date', newBooking.checkIn);
    newRow.setAttribute('data-deposit', newBooking.deposit);
    newRow.setAttribute('data-adults', newBooking.numAdults);
    newRow.setAttribute('data-children', newBooking.numChildren);
    newRow.setAttribute('data-staff', newBooking.staffId);
    
    newRow.innerHTML = `
      <td>${newBooking.bookingId}</td>
      <td>${newBooking.checkIn}</td>
      <td>${newBooking.checkOut}</td>
      <td>RM ${newBooking.deposit.toFixed(0)}</td>
      <td>${newBooking.homestayId}</td>
      <td>${newBooking.guestId}</td>
      <td>${newBooking.billNo}</td>
      <td>
        <button class="btn-details" onclick="viewBookingDetails('${newBooking.bookingId}')">Details</button>
      </td>
      <td>
        <button class="btn-update" onclick="updateBooking('${newBooking.bookingId}')">Update</button>
        <button class="btn-delete" onclick="deleteBooking('${newBooking.bookingId}')">Delete</button>
      </td>
    `;
    
    // Add the new row to the table
    tableBody.appendChild(newRow);
    
    // Update allRows array
    allRows = Array.from(tableBody.querySelectorAll('tr'));
    
    // Close modal and show success message
    closeAddModal();
    alert('Booking added successfully!');
  });
  </script>
</body>
</html>
