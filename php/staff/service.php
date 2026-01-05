<?php
session_start();
require_once '../config/session_check.php';
requireRegularStaffLogin();
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
      <li class="logout-item">
        <a href="../logout.php">
          <i class='bxr  bx-arrow-out-left-square-half'></i>
          <span class="link_name">Logout</span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="../logout.php">Logout</a></li>
        </ul>
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
          <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Staff'); ?></div>
          <div class="header-profile-job"><?php echo htmlspecialchars($_SESSION['staff_type'] ?? 'Staff'); ?></div>
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
            <tr data-type="Housekeeping" data-cost="50">
              <td>SRV001</td>
              <td>Housekeeping</td>
              <td>RM 50</td>
              <td>Daily room cleaning and maintenance</td>
              <td>Active</td>
              <td>S001</td>
              <td>
                <button class="btn-update" onclick="updateService('SRV001')">Update</button>
                </td>
            </tr>
            <tr data-type="Laundry Service" data-cost="30">
              <td>SRV002</td>
              <td>Laundry Service</td>
              <td>RM 30</td>
              <td>Washing and ironing services</td>
              <td>Active</td>
              <td>S002</td>
              <td>
                <button class="btn-update" onclick="updateService('SRV002')">Update</button>
                </td>
            </tr>
            <tr data-type="Airport Transfer" data-cost="80">
              <td>SRV003</td>
              <td>Airport Transfer</td>
              <td>RM 80</td>
              <td>Pickup and drop-off service</td>
              <td>Active</td>
              <td>S003</td>
              <td>
                <button class="btn-update" onclick="updateService('SRV003')">Update</button>
                </td>
            </tr>
            <tr data-type="Breakfast Service" data-cost="25">
              <td>SRV004</td>
              <td>Breakfast Service</td>
              <td>RM 25</td>
              <td>Daily breakfast meal</td>
              <td>Active</td>
              <td>S001</td>
              <td>
                <button class="btn-update" onclick="updateService('SRV004')">Update</button>
                </td>
            </tr>
            <tr data-type="WiFi Access" data-cost="10">
              <td>SRV005</td>
              <td>WiFi Access</td>
              <td>RM 10</td>
              <td>High-speed internet connection</td>
              <td>Active</td>
              <td>S004</td>
              <td>
                <button class="btn-update" onclick="updateService('SRV005')">Update</button>
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
          <input type="text" id="addStaffId" name="staffId" required>
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
      serviceCost: cells[2].textContent.trim().replace('RM ', '').trim(),
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
    
    const newService = {
      serviceId: document.getElementById('addServiceId').value.trim(),
      serviceType: document.getElementById('addServiceType').value.trim(),
      serviceCost: parseFloat(document.getElementById('addServiceCost').value),
      serviceRemark: document.getElementById('addServiceRemark').value.trim(),
      serviceStatus: document.getElementById('addServiceStatus').value,
      staffId: document.getElementById('addStaffId').value.trim()
    };
    
    // Check if service ID already exists
    const existingRows = tableBody.querySelectorAll('tr');
    let idExists = false;
    existingRows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === newService.serviceId) {
        idExists = true;
      }
    });
    
    if (idExists) {
      alert('Service ID already exists! Please use a different ID.');
      return;
    }
    
    // Create new table row
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-type', newService.serviceType);
    newRow.setAttribute('data-cost', newService.serviceCost);
    
    newRow.innerHTML = `
      <td>${newService.serviceId}</td>
      <td>${newService.serviceType}</td>
      <td>RM ${newService.serviceCost.toFixed(0)}</td>
      <td>${newService.serviceRemark}</td>
      <td>${newService.serviceStatus}</td>
      <td>${newService.staffId}</td>
      <td>
        <button class="btn-update" onclick="updateService('${newService.serviceId}')">Update</button>
        </td>
    `;
    
    // Add the new row to the table
    tableBody.appendChild(newRow);
    
    // Update allRows array
    allRows = Array.from(tableBody.querySelectorAll('tr'));
    
    // Close modal and show success message
    closeAddModal();
    alert('Service added successfully!');
  });

  // Handle update service form submission
  document.getElementById('updateServiceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const serviceId = document.getElementById('updateServiceId').value;
    const updatedData = {
      serviceType: document.getElementById('updateServiceType').value.trim(),
      serviceCost: parseFloat(document.getElementById('updateServiceCost').value),
      serviceRemark: document.getElementById('updateServiceRemark').value.trim(),
      serviceStatus: document.getElementById('updateServiceStatus').value,
      staffId: document.getElementById('updateStaffId').value.trim()
    };
    
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach(row => {
      const idCell = row.querySelector('td:first-child');
      if (idCell && idCell.textContent.trim() === serviceId) {
        const cells = row.querySelectorAll('td');
        cells[1].textContent = updatedData.serviceType;
        cells[2].textContent = 'RM ' + updatedData.serviceCost.toFixed(0);
        cells[3].textContent = updatedData.serviceRemark;
        cells[4].textContent = updatedData.serviceStatus;
        cells[5].textContent = updatedData.staffId;
        
        row.setAttribute('data-type', updatedData.serviceType);
        row.setAttribute('data-cost', updatedData.serviceCost);
        
        allRows = Array.from(tableBody.querySelectorAll('tr'));
        closeUpdateModal();
        alert('Service details updated successfully!');
        return;
      }
    });
  });
  </script>
</body>
</html>

