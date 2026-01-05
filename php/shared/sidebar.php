<?php
$sidebarRole = $sidebarRole ?? 'manager';

$sidebarConfigs = [
  'manager' => [
    'logo' => '../../images/logo.png',
    'brand' => 'Serena Sanctuary',
    'menu' => [
      [
        'label' => 'Dashboard',
        'href' => 'dashboard.php',
        'icon' => 'bxr  bx-dashboard'
      ],
      [
        'label' => 'Manage',
        'href' => 'manage.php',
        'icon' => 'bxr  bx-list-square',
        'children' => [
          ['label' => 'Manage', 'href' => 'manage.php'],
          ['label' => 'Guests', 'href' => 'guests.php'],
          ['label' => 'Staff', 'href' => 'staff.php'],
          ['label' => 'Homestay', 'href' => 'homestay.php']
        ]
      ],
      [
        'label' => 'Billing',
        'href' => 'billing.php',
        'icon' => 'bxr  bx-print-dollar'
      ],
      [
        'label' => 'Bookings',
        'href' => 'bookings.php',
        'icon' => 'bxr  bx-home-add'
      ],
      [
        'label' => 'Services',
        'href' => 'service.php',
        'icon' => 'bxr  bx-spanner'
      ],
      [
        'label' => 'Calendar',
        'href' => 'calendar.php',
        'icon' => 'bxr  bx-calendar-alt'
      ],
      [
        'label' => 'Reports',
        'href' => 'reports.php',
        'icon' => 'bxr  bx-file-report',
        'children' => [
          ['label' => 'Reports', 'href' => 'reports.php'],
          ['label' => 'Full Reports', 'href' => 'fullReports.php'],
          ['label' => 'Summary', 'href' => 'summary.php'],
          ['label' => 'Analytics', 'href' => 'analytics.php']
        ]
      ]
    ],
    'profileLogout' => [
      'href' => '../logout.php',
      'icon' => 'bx bx-log-out',
      'label' => 'Logout'
    ]
  ],
  'staff' => [
    'logo' => '../../images/logo.png',
    'brand' => 'Serena Sanctuary',
    'menu' => [
      [
        'label' => 'Dashboard',
        'href' => 'dashboard.php',
        'icon' => 'bxr  bx-dashboard'
      ],
      [
        'label' => 'Manage',
        'href' => 'manage.php',
        'icon' => 'bxr  bx-list-square',
        'children' => [
          ['label' => 'Manage', 'href' => 'manage.php'],
          ['label' => 'Guests', 'href' => 'guests.php'],
          ['label' => 'Homestay', 'href' => 'homestay.php']
        ]
      ],
      [
        'label' => 'Billing',
        'href' => 'billing.php',
        'icon' => 'bxr  bx-print-dollar'
      ],
      [
        'label' => 'Bookings',
        'href' => 'bookings.php',
        'icon' => 'bxr  bx-home-add'
      ],
      [
        'label' => 'Services',
        'href' => 'service.php',
        'icon' => 'bxr  bx-spanner'
      ],
      [
        'label' => 'Calendar',
        'href' => 'calendar.php',
        'icon' => 'bxr  bx-calendar-alt'
      ],
      [
        'label' => 'Reports',
        'href' => 'reports.php',
        'icon' => 'bxr  bx-file-report',
        'children' => [
          ['label' => 'Reports', 'href' => 'reports.php'],
          ['label' => 'Full Reports', 'href' => 'fullReports.php'],
          ['label' => 'Summary', 'href' => 'summary.php'],
          ['label' => 'Analytics', 'href' => 'analytics.php']
        ]
      ]
    ],
    'logoutNav' => [
      'href' => '../logout.php',
      'icon' => 'bxr  bx-arrow-out-left-square-half',
      'label' => 'Logout',
      'class' => 'logout-item'
    ]
  ]
];

$config = $sidebarConfigs[$sidebarRole] ?? $sidebarConfigs['manager'];
$menuItems = $config['menu'] ?? [];
$logoPath = $config['logo'] ?? '../../images/logo.png';
$brandName = $config['brand'] ?? 'Serena Sanctuary';
$logoutNav = $config['logoutNav'] ?? null;
$profileLogout = $config['profileLogout'] ?? null;
?>
<div class="sidebar close">
  <div class="logo-details">
    <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="<?php echo htmlspecialchars($brandName); ?> logo" class="logo-icon">
    <span class="logo_name"><?php echo htmlspecialchars($brandName); ?></span>
  </div>
  <ul class="nav-links">
    <?php foreach ($menuItems as $item): ?>
      <li class="<?php echo htmlspecialchars($item['class'] ?? ''); ?>">
        <?php if (!empty($item['children'])): ?>
          <div class="icon-link">
            <a href="<?php echo htmlspecialchars($item['href']); ?>">
              <i class='<?php echo htmlspecialchars($item['icon']); ?>'></i>
              <span class="link_name"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <i class='bx bxs-chevron-down arrow'></i>
          </div>
          <ul class="sub-menu">
            <li><a class="link_name" href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a></li>
            <?php foreach ($item['children'] as $child): ?>
              <li><a href="<?php echo htmlspecialchars($child['href']); ?>"><?php echo htmlspecialchars($child['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <a href="<?php echo htmlspecialchars($item['href']); ?>">
            <i class='<?php echo htmlspecialchars($item['icon']); ?>'></i>
            <span class="link_name"><?php echo htmlspecialchars($item['label']); ?></span>
          </a>
          <ul class="sub-menu blank">
            <li><a class="link_name" href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a></li>
          </ul>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>

    <?php if ($logoutNav): ?>
      <li class="<?php echo htmlspecialchars($logoutNav['class'] ?? ''); ?>">
        <a href="<?php echo htmlspecialchars($logoutNav['href']); ?>">
          <i class='<?php echo htmlspecialchars($logoutNav['icon']); ?>'></i>
          <span class="link_name"><?php echo htmlspecialchars($logoutNav['label']); ?></span>
        </a>
        <ul class="sub-menu blank">
          <li><a class="link_name" href="<?php echo htmlspecialchars($logoutNav['href']); ?>"><?php echo htmlspecialchars($logoutNav['label']); ?></a></li>
        </ul>
      </li>
    <?php endif; ?>
  </ul>
  <?php if ($profileLogout): ?>
    <div class="profile-details">
      <a href="<?php echo htmlspecialchars($profileLogout['href']); ?>" class="profile-content" style="display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit;">
        <i class='<?php echo htmlspecialchars($profileLogout['icon']); ?>' style="font-size: 24px; margin-right: 10px;"></i>
        <span class="link_name"><?php echo htmlspecialchars($profileLogout['label']); ?></span>
      </a>
    </div>
  <?php endif; ?>
</div>
