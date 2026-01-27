<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireStaffLogin();

$conn = getDBConnection();
if (!$conn) {
  die('Database connection failed. Please try again later.');
}

$bookings = [];
$bookingSql = "SELECT b.bookingID, TO_CHAR(b.checkin_date, 'YYYY-MM-DD') AS checkin_date, TO_CHAR(b.checkout_date, 'YYYY-MM-DD') AS checkout_date,
  b.homestayID, h.homestay_name, b.guestID, g.guest_name, NVL(TO_CHAR(b.billNo), '-') AS billNo
    FROM BOOKING b
    LEFT JOIN HOMESTAY h ON b.homestayID = h.homestayID
    LEFT JOIN GUEST g ON b.guestID = g.guestID
    ORDER BY b.checkin_date";
$bookingStmt = oci_parse($conn, $bookingSql);
oci_execute($bookingStmt);
while ($row = oci_fetch_array($bookingStmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
  $bookings[] = $row;
}
oci_free_statement($bookingStmt);

$today = new DateTime('today');
$monthStart = new DateTime('first day of this month');
$monthEnd = new DateTime('last day of this month 23:59:59');

foreach ($bookings as $row) {
  $checkIn = new DateTime($row['CHECKIN_DATE']);
  $checkOut = new DateTime($row['CHECKOUT_DATE']);
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Calendar</title>
    <link rel="stylesheet" href="../../css/phpStyle/staff_managerStyle/calendarStyle.css">
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
      <div>
        <h1>Calendar</h1>
        <p class="subdued">See booking occupancy by day.</p>
      </div>
    </div>

    <div class="calendar-wrapper">
      <div class="calendar-header">
        <div class="calendar-title">
          <button class="ghost-btn" id="prevMonth" aria-label="Previous month"><i class='bx bx-chevron-left'></i></button>
          <h2 id="monthLabel"></h2>
          <button class="ghost-btn" id="nextMonth" aria-label="Next month"><i class='bx bx-chevron-right'></i></button>
        </div>
        <div class="calendar-actions">
          <button class="pill ghost" id="todayBtn">Today</button>
        </div>
      </div>

      <div class="calendar-legend">
        <span class="legend-dot busy"></span> <span>Occupied</span>
        <span class="legend-dot light"></span> <span>Available</span>
      </div>

      <div class="calendar-body">
        <div class="month-grid" id="monthGrid"></div>
        <div class="day-panel">
          <div class="day-panel-header">
            <div>
              <p class="day-label" id="selectedDateLabel"></p>
              <p class="day-count" id="selectedDateCount"></p>
            </div>
          </div>
          <div id="dayBookings" class="day-bookings"></div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <div class="footer-content">
        <p>&copy; 2025 Serena Sanctuary. All rights reserved.</p>
      </div>
    </footer>
  </section>

  <script>
  const bookings = <?php echo json_encode($bookings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  let arrow = document.querySelectorAll('.arrow');
  for (let i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener('click', (e) => {
      const arrowParent = e.target.parentElement.parentElement;
      arrowParent.classList.toggle('showMenu');
    });
  }
  const sidebar = document.querySelector('.sidebar');
  const sidebarBtn = document.querySelector('.bx-menu');
  sidebarBtn.addEventListener('click', () => {
    sidebar.classList.toggle('close');
  });

  const parsedBookings = (bookings || []).map((item) => ({
    id: item.BOOKINGID,
    checkIn: new Date(item.CHECKIN_DATE + 'T00:00:00'),
    checkOut: new Date(item.CHECKOUT_DATE + 'T00:00:00'),
    homestay: item.HOMESTAY_NAME || item.HOMESTAYID,
    guest: item.GUEST_NAME || `Guest ${item.GUESTID || ''}`.trim(),
    billNo: item.BILLNO || '-',
  }));

  const colorPalette = ['#c5814b', '#3f72af', '#4caf50', '#8e44ad', '#e67e22', '#16a085', '#d35400', '#2c3e50'];
  const homestayColor = new Map();
  const getColorForHomestay = (name) => {
    if (!homestayColor.has(name)) {
      const idx = homestayColor.size % colorPalette.length;
      homestayColor.set(name, colorPalette[idx]);
    }
    return homestayColor.get(name);
  };

  const monthLabel = document.getElementById('monthLabel');
  const monthGrid = document.getElementById('monthGrid');
  const dayBookingsEl = document.getElementById('dayBookings');
  const selectedDateLabel = document.getElementById('selectedDateLabel');
  const selectedDateCount = document.getElementById('selectedDateCount');

  let focusDate = new Date();
  let selectedDate = new Date();

  const formatDateLabel = (date) => {
    const opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
    return date.toLocaleDateString(undefined, opts);
  };

  const getBookingsForDate = (date) => {
    const time = date.setHours(0, 0, 0, 0);
    return parsedBookings.filter((b) => {
      const start = b.checkIn.getTime();
      const end = b.checkOut.getTime();
      return time >= start && time < end;
    });
  };

  const renderDayPanel = (date) => {
    const events = getBookingsForDate(new Date(date));
    selectedDateLabel.textContent = formatDateLabel(date);
    selectedDateCount.textContent = `${events.length} booking${events.length === 1 ? '' : 's'}`;

    if (!events.length) {
      dayBookingsEl.innerHTML = '<p class="muted">No bookings on this day.</p>';
      return;
    }

    dayBookingsEl.innerHTML = '';
    events.forEach((ev) => {
      const card = document.createElement('div');
      card.className = 'booking-card';
      const nights = Math.max(1, Math.round((ev.checkOut - ev.checkIn) / 86400000));
      card.innerHTML = `
        <div class="booking-top">
          <div>
            <p class="booking-id">${ev.id}</p>
            <p class="booking-guest">${ev.guest}</p>
          </div>
          <span class="pill small">${ev.billNo === '-' ? 'Unbilled' : `Bill ${ev.billNo}`}</span>
        </div>
        <p class="booking-home">${ev.homestay}</p>
        <p class="booking-dates">${ev.checkIn.toISOString().slice(0, 10)} → ${ev.checkOut.toISOString().slice(0, 10)} • ${nights} night${nights === 1 ? '' : 's'}</p>
      `;
      dayBookingsEl.appendChild(card);
    });
  };

  const buildWeeks = (year, month) => {
    const firstDay = new Date(year, month, 1);
    const start = new Date(firstDay);
    start.setDate(start.getDate() - start.getDay());

    const weeks = [];
    let cursor = new Date(start);
    while (true) {
      const days = [];
      for (let d = 0; d < 7; d++) {
        days.push({
          date: new Date(cursor),
          inMonth: cursor.getMonth() === month,
        });
        cursor.setDate(cursor.getDate() + 1);
      }
      weeks.push(days);
      if (cursor.getMonth() !== month && cursor.getDate() <= 7) {
        break;
      }
      if (weeks.length >= 6) break;
    }
    return weeks;
  };

  const renderCalendar = () => {
    const year = focusDate.getFullYear();
    const month = focusDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    monthLabel.textContent = firstDay.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    monthGrid.innerHTML = '';

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const headerRow = document.createElement('div');
    headerRow.className = 'week-row header';
    const headerGrid = document.createElement('div');
    headerGrid.className = 'week-grid';
    dayNames.forEach((name) => {
      const label = document.createElement('div');
      label.className = 'day-name';
      label.textContent = name;
      headerGrid.appendChild(label);
    });
    headerRow.appendChild(headerGrid);
    monthGrid.appendChild(headerRow);

    const weeks = buildWeeks(year, month);

    weeks.forEach((days) => {
      const weekRow = document.createElement('div');
      weekRow.className = 'week-row';

      const weekGrid = document.createElement('div');
      weekGrid.className = 'week-grid';

      days.forEach((dayObj) => {
        const cellDate = dayObj.date;
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.className = 'day-cell';
        if (!dayObj.inMonth) {
          cell.classList.add('muted-day');
        }
        if (cellDate.getTime() === today.getTime()) {
          cell.classList.add('today');
        }
        if (cellDate.getTime() === selectedDate.getTime()) {
          cell.classList.add('selected');
        }

        const events = getBookingsForDate(new Date(cellDate));
        cell.innerHTML = `
          <div class="day-header">
            <span class="day-number">${cellDate.getDate()}</span>
            ${events.length ? `<span class="pill tiny">${events.length}</span>` : ''}
          </div>
          <p class="day-meta">${events.length ? 'Booked' : 'Available'}</p>
        `;

        cell.addEventListener('click', () => {
          selectedDate = new Date(cellDate);
          renderDayPanel(selectedDate);
          renderCalendar();
        });

        weekGrid.appendChild(cell);
      });

      const barLayer = document.createElement('div');
      barLayer.className = 'bar-layer';
      barLayer.style.gridTemplateColumns = 'repeat(7, 1fr)';

      const weekStart = new Date(days[0].date);
      const weekEnd = new Date(days[6].date);
      weekEnd.setHours(23, 59, 59, 999);

      parsedBookings.forEach((b) => {
        const overlapStart = new Date(Math.max(b.checkIn.getTime(), weekStart.getTime()));
        const overlapEnd = new Date(Math.min(b.checkOut.getTime(), weekEnd.getTime()));
        if (overlapStart >= overlapEnd) return;

        const startCol = overlapStart.getDay() + 1;
        const spanDays = Math.max(1, Math.ceil((overlapEnd - overlapStart) / 86400000));
        const endCol = Math.min(8, startCol + spanDays);

        const bar = document.createElement('div');
        bar.className = 'bar';
        bar.style.gridColumn = `${startCol} / ${endCol}`;
        bar.style.background = getColorForHomestay(b.homestay);
        bar.title = `${b.homestay} • ${b.guest}`;
        bar.setAttribute('aria-label', `${b.homestay} • ${b.guest}`);
        barLayer.appendChild(bar);
      });

      weekRow.appendChild(weekGrid);
      weekRow.appendChild(barLayer);
      monthGrid.appendChild(weekRow);
    });
  };

  document.getElementById('prevMonth').addEventListener('click', () => {
    focusDate = new Date(focusDate.getFullYear(), focusDate.getMonth() - 1, 1);
    renderCalendar();
  });

  document.getElementById('nextMonth').addEventListener('click', () => {
    focusDate = new Date(focusDate.getFullYear(), focusDate.getMonth() + 1, 1);
    renderCalendar();
  });

  document.getElementById('todayBtn').addEventListener('click', () => {
    focusDate = new Date();
    focusDate.setDate(1);
    selectedDate = new Date();
    renderDayPanel(selectedDate);
    renderCalendar();
  });

  selectedDate.setHours(0, 0, 0, 0);
  renderDayPanel(selectedDate);
  renderCalendar();
  </script>
</body>
</html>
