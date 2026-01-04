<?php
session_start();
require_once '../config/session_check.php';
requireManagerLogin();
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
      <h1>Homestay</h1>
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
            <tr data-name="The Grand Haven" data-rent-price="500">
              <td>P001</td>
              <td>The Grand Haven</td>
              <td>Hulu Langat, Selangor</td>
              <td>+603-8734-1234</td>
              <td>RM 500</td>
              <td>S001</td>
              <td>
                <button class="btn-update" onclick="updateProperty('P001')">Update</button>
                <button class="btn-delete" onclick="deleteProperty('P001')">Delete</button>
              </td>
            </tr>
            <tr data-name="Twin Haven" data-rent-price="450">
              <td>P002</td>
              <td>Twin Haven</td>
              <td>Hulu Langat, Selangor</td>
              <td>+603-8734-5678</td>
              <td>RM 450</td>
              <td>S002</td>
              <td>
                <button class="btn-update" onclick="updateProperty('P002')">Update</button>
                <button class="btn-delete" onclick="deleteProperty('P002')">Delete</button>
              </td>
            </tr>
            <tr data-name="The Riverside Retreat" data-rent-price="400">
              <td>P003</td>
              <td>The Riverside Retreat</td>
              <td>Gopeng, Perak</td>
              <td>+605-312-9012</td>
              <td>RM 400</td>
              <td>S001</td>
              <td>
                <button class="btn-update" onclick="updateProperty('P003')">Update</button>
                <button class="btn-delete" onclick="deleteProperty('P003')">Delete</button>
              </td>
            </tr>
            <tr data-name="Hilltop Haven" data-rent-price="550">
              <td>P004</td>
              <td>Hilltop Haven</td>
              <td>Gopeng, Perak</td>
              <td>+605-312-3456</td>
              <td>RM 550</td>
              <td>S002</td>
              <td>
                <button class="btn-update" onclick="updateProperty('P004')">Update</button>
                <button class="btn-delete" onclick="deleteProperty('P004')">Delete</button>
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
            <option value="S001">S001 - Ahmad Zulkifli bin Hassan</option>
            <option value="S002">S002 - Nurul Aina binti Mohd Ali</option>
            <option value="S003">S003 - Lim Chee Keong</option>
            <option value="S004">S004 - Siti Fatimah binti Abdullah</option>
            <option value="S005">S005 - Muhammad Farhan bin Ismail</option>
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
   let arrowParent = e.target.parentElement.parentElement;//selecting main parent of arrow
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
      
      switch(sortBy) {
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
      rentPrice: cells[4].textContent.trim().replace('RM ', '').trim(),
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

  document.getElementById('updatePropertyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const propertyId = document.getElementById('updatePropertyId').value;
    const updatedData = {
      name: document.getElementById('updatePropertyName').value.trim(),
      address: document.getElementById('updateAddress').value.trim(),
      officePhone: document.getElementById('updateOfficePhone').value.trim(),
      rentPrice: parseFloat(document.getElementById('updateRentPrice').value),
      staffId: document.getElementById('updateStaffId').value
    };
    
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === propertyId) {
        const cells = row.querySelectorAll('td');
        cells[1].textContent = updatedData.name;
        cells[2].textContent = updatedData.address;
        cells[3].textContent = updatedData.officePhone;
        cells[4].textContent = 'RM ' + updatedData.rentPrice.toFixed(0);
        cells[5].textContent = updatedData.staffId;
        
        row.setAttribute('data-name', updatedData.name);
        row.setAttribute('data-rent-price', updatedData.rentPrice);
        
        allRows = Array.from(tableBody.querySelectorAll('tr'));
        closeUpdateModal();
        alert('Homestay details updated successfully!');
        return;
      }
    });
  });

  window.onclick = function(event) {
    const modal = document.getElementById('updateModal');
    if (event.target === modal) {
      closeUpdateModal();
    }
  }

  document.querySelector('.close-modal').addEventListener('click', closeUpdateModal);

  function deleteProperty(propertyId) {
    if (confirm('Are you sure you want to delete homestay ' + propertyId + '?')) {
      const rows = tableBody.querySelectorAll('tr');
      rows.forEach(row => {
        const idCell = row.querySelector('td:first-child');
        if (idCell && idCell.textContent.trim() === propertyId) {
          row.remove();
          allRows = Array.from(tableBody.querySelectorAll('tr'));
          alert('Homestay deleted successfully!');
          return;
        }
      });
    }
  }
  </script>
</body>
</html>

