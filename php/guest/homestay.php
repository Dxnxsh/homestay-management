<?php
session_start();
require_once '../config/session_check.php';
require_once '../config/db_connection.php';
requireGuestLogin();

$conn = getDBConnection();
$homestays = [];

// Metadata for static content not in DB
$metadata = [
  'HM101' => [
    'folder' => 'homestay1',
    'address' => 'Hulu Langat, Selangor',
    'features' => [
      ['icon' => 'bx-bed', 'text' => '11 Bedrooms'],
      ['icon' => 'bx-bath', 'text' => '8 Bathrooms'],
      ['icon' => 'bx-group', 'text' => 'Up to 30 Guests']
    ],
    'description' => 'This is the largest unit, designed for significant gatherings. It features 11 bedrooms and can accommodate between 16 to 30 guests. Perfect for large family reunions, corporate retreats, and special events. Features modern amenities, fully equipped kitchen, and spacious common areas.',
    'images' => ['homestay1.jpg', 'homestay1(1).jpg', 'homestay1(2).jpg']
  ],
  'HM102' => [
    'folder' => 'homestay2',
    'address' => 'Hulu Langat, Selangor',
    'features' => [
      ['icon' => 'bx-bed', 'text' => '4 Bedrooms'],
      ['icon' => 'bx-bath', 'text' => '4 Bathrooms'],
      ['icon' => 'bx-group', 'text' => 'Up to 20 Guests']
    ],
    'description' => 'A mid-sized option suitable for smaller groups or families, consisting of 4 bedrooms and accommodating 10 to 20 guests. Perfect for family gatherings and group retreats. Features modern amenities, comfortable living spaces, and a welcoming atmosphere.',
    'images' => array_merge(['homestay2.jpg'], array_map(function ($i) {
      return "homestay2($i).jpg";
    }, range(1, 14)))
  ],
  'HM103' => [
    'folder' => 'homestay3',
    'address' => 'Gopeng, Perak',
    'features' => [
      ['icon' => 'bx-bed', 'text' => '8 Bedrooms'],
      ['icon' => 'bx-bath', 'text' => '6 Bathrooms'],
      ['icon' => 'bx-group', 'text' => 'Up to 36 Guests']
    ],
    'description' => 'This unit is situated near a natural river stream (Sungai Selaru). It offers 8 bedrooms with a capacity for 20 to 36 guests. Perfect for nature lovers and large groups seeking a peaceful riverside experience. Features modern amenities and stunning natural surroundings.',
    'images' => array_merge(['homestay3.jpg'], array_map(function ($i) {
      return "homestay3($i).jpg";
    }, range(1, 6)), ['homestay3(7).webp'], array_map(function ($i) {
      return "homestay3($i).jpg";
    }, range(8, 9)))
  ],
  'HM104' => [
    'folder' => 'homestay4',
    'address' => 'Gopeng, Perak',
    'features' => [
      ['icon' => 'bx-bed', 'text' => '5 Bedrooms'],
      ['icon' => 'bx-bath', 'text' => '5 Bathrooms'],
      ['icon' => 'bx-group', 'text' => 'Up to 10 Guests']
    ],
    'description' => 'An aristocratic-style villa located on a hillside. It is laid out over two floors with 3 main bedrooms in the main complex (sleeping 6) and two additional dependencies ("Villa Serenella" and "Villa Limonaia") that each sleep two people. Perfect for those seeking elegance and privacy with stunning hillside views.',
    'images' => array_merge(['homestay4.jpg'], array_map(function ($i) {
      return "homestay4($i).jpg";
    }, range(1, 8)))
  ]
];

if ($conn) {
  $sql = "SELECT homestayID, homestay_name, homestay_address, rent_price FROM HOMESTAY ORDER BY homestayID ASC";
  $stmt = oci_parse($conn, $sql);
  if (oci_execute($stmt)) {
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
      $id = $row['HOMESTAYID'];
      $homestays[$id] = [
        'id' => $id,
        'name' => $row['HOMESTAY_NAME'],
        'address' => $metadata[$id]['address'] ?? $row['HOMESTAY_ADDRESS'],
        'price' => (float) $row['RENT_PRICE'],
        'meta' => $metadata[$id] ?? [
          'folder' => 'homestay1',
          'address' => 'Address unavailable',
          'features' => [],
          'description' => 'Description unavailable.',
          'images' => ['homestay1.jpg']
        ]
      ];
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
  <title>Homestay - Serena Sanctuary</title>
  <link rel="stylesheet" href="../../css/phpStyle/guestStyle/homestayStyle.css">
  <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="icon" type="image/png" href="../../images/logoNbg.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <div class="nav-logo">
        <img src="../../images/logoNbg.png" alt="Serena Sanctuary logo" class="logo-icon">
        <span class="logo-name">Serena Sanctuary</span>
      </div>
      <ul class="nav-menu" id="navMenu">
        <li><a href="home.php" class="nav-link">Home</a></li>
        <li><a href="booking.php" class="nav-link">Booking</a></li>
        <li><a href="homestay.php" class="nav-link active">Homestay</a></li>
        <li><a href="profile.php" class="nav-link">Profile</a></li>
        <li><a href="../logout.php" class="nav-link btn-logout">Logout</a></li>
      </ul>
      <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="main-content">
    <section class="page-header">
      <div class="container">
        <h1 class="page-title">Available Homestays</h1>
        <p class="page-subtitle">Discover your perfect getaway</p>
      </div>
    </section>

    <section class="content-section">
      <div class="container">
        <div class="homestays-grid">
          <?php foreach ($homestays as $hs): ?>
              <div class="homestay-card"
                onclick="scrollToDetail('homestay-detail-<?php echo htmlspecialchars($hs['id']); ?>')">
                <div class="homestay-image">
                  <img
                    src="../../images/<?php echo htmlspecialchars($hs['meta']['folder']); ?>/<?php echo htmlspecialchars($hs['meta']['images'][0] ?? 'homestay1.jpg'); ?>"
                    alt="<?php echo htmlspecialchars($hs['name']); ?>">
                </div>
                <div class="homestay-details">
                  <h3>
                    <?php echo htmlspecialchars($hs['name']); ?>
                  </h3>
                  <p class="homestay-location"><i class='bx bx-map'></i>
                    <?php echo htmlspecialchars($hs['address']); ?>
                  </p>
                  <div class="homestay-features">
                    <?php foreach ($hs['meta']['features'] as $feature): ?>
                        <span><i class='bx <?php echo htmlspecialchars($feature['icon']); ?>'></i>
                          <?php echo htmlspecialchars($feature['text']); ?>
                        </span>
                    <?php endforeach; ?>
                  </div>
                  <div class="homestay-price">
                    <span class="price">RM
                      <?php echo number_format($hs['price'], 0); ?>
                    </span>
                    <span class="period">/ night</span>
                  </div>
                  <a href="booking.php" class="btn btn-primary" onclick="event.stopPropagation()">Book Now</a>
                </div>
              </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Homestay Details Section -->
    <section class="homestay-details-section">
      <div class="container">
        <?php foreach ($homestays as $hs): ?>
            <div class="homestay-detail-card" id="homestay-detail-<?php echo htmlspecialchars($hs['id']); ?>">
              <div class="detail-image-carousel">
                <div class="carousel-container">
                  <?php foreach ($hs['meta']['images'] as $index => $img): ?>
                      <img
                        src="../../images/<?php echo htmlspecialchars($hs['meta']['folder']); ?>/<?php echo htmlspecialchars($img); ?>"
                        alt="<?php echo htmlspecialchars($hs['name']); ?>"
                        class="carousel-image <?php echo $index === 0 ? 'active' : ''; ?>">
                  <?php endforeach; ?>
                </div>
                <button class="carousel-arrow carousel-arrow-left" aria-label="Previous image">
                  <i class='bx bx-chevron-left'></i>
                </button>
                <button class="carousel-arrow carousel-arrow-right" aria-label="Next image">
                  <i class='bx bx-chevron-right'></i>
                </button>
                <div class="carousel-indicators">
                  <?php foreach ($hs['meta']['images'] as $index => $img): ?>
                      <span class="indicator <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-slide="<?php echo $index; ?>"></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="detail-content">
                <h2>
                  <?php echo htmlspecialchars($hs['name']); ?>
                </h2>
                <p class="detail-location"><i class='bx bx-map'></i>
                  <?php echo htmlspecialchars($hs['address']); ?>
                </p>
                <p class="detail-description">
                  <?php echo htmlspecialchars($hs['meta']['description']); ?>
                </p>
                <div class="detail-features">
                  <?php foreach ($hs['meta']['features'] as $feature): ?>
                      <div class="feature-item">
                        <i class='bx <?php echo htmlspecialchars($feature['icon']); ?>'></i>
                        <span>
                          <?php echo htmlspecialchars($feature['text']); ?>
                        </span>
                      </div>
                  <?php endforeach; ?>
                  <div class="feature-item">
                    <i class='bx bx-wifi'></i>
                    <span>Free WiFi</span>
                  </div>
                </div>
                <div class="detail-price">
                  <span class="price">RM
                    <?php echo number_format($hs['price'], 0); ?>
                  </span>
                  <span class="period">/ night</span>
                </div>
                <a href="booking.php" class="btn btn-primary">Book Now</a>
              </div>
            </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <div class="footer-logo">
            <img src="../../images/logoNbg.png" alt="Serena Sanctuary logo" class="logo-icon">
            <span class="logo-name">Serena Sanctuary</span>
          </div>
          <p class="footer-description">Your trusted partner for exceptional homestay experiences.</p>
        </div>
        <div class="footer-section">
          <h4 class="footer-title">Quick Links</h4>
          <ul class="footer-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="homestay.php">Homestay</a></li>
            <li><a href="membership.php">Membership</a></li>
            <li><a href="profile.php">Profile</a></li>
          </ul>
        </div>
        <div class="footer-section">
          <h4 class="footer-title">Contact</h4>
          <ul class="footer-contact">
            <li><i class='bx bx-envelope'></i> info@serenasanctuary.com</li>
            <li><i class='bx bx-phone'></i> +60 17-204 2390</li>
            <li><i class='bx bx-map'></i> Malaysia</li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2025 Serena Sanctuary. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script>
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
      hamburger.classList.toggle('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
      });
    });

    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // Image Carousel Auto-Slide
    function initCarousels() {
      const carousels = document.querySelectorAll('.detail-image-carousel');

      carousels.forEach(carousel => {
        const images = carousel.querySelectorAll('.carousel-image');
        const indicators = carousel.querySelectorAll('.indicator');
        const leftArrow = carousel.querySelector('.carousel-arrow-left');
        const rightArrow = carousel.querySelector('.carousel-arrow-right');
        let currentIndex = 0;
        let autoSlideInterval;

        if (images.length <= 1) return;

        function showSlide(index) {
          images.forEach((img, i) => {
            img.classList.toggle('active', i === index);
          });
          indicators.forEach((ind, i) => {
            ind.classList.toggle('active', i === index);
          });
        }

        function nextSlide() {
          currentIndex = (currentIndex + 1) % images.length;
          showSlide(currentIndex);
        }

        function prevSlide() {
          currentIndex = (currentIndex - 1 + images.length) % images.length;
          showSlide(currentIndex);
        }

        function startAutoSlide() {
          autoSlideInterval = setInterval(nextSlide, 4000);
        }

        function stopAutoSlide() {
          clearInterval(autoSlideInterval);
        }

        function resetAutoSlide() {
          stopAutoSlide();
          startAutoSlide();
        }

        // Auto-slide every 4 seconds
        startAutoSlide();

        // Click on indicators to navigate
        indicators.forEach((indicator, index) => {
          indicator.addEventListener('click', () => {
            currentIndex = index;
            showSlide(currentIndex);
            resetAutoSlide();
          });
        });

        // Arrow navigation
        if (rightArrow) {
          rightArrow.addEventListener('click', () => {
            nextSlide();
            resetAutoSlide();
          });
        }

        if (leftArrow) {
          leftArrow.addEventListener('click', () => {
            prevSlide();
            resetAutoSlide();
          });
        }

        // Pause auto-slide on hover
        carousel.addEventListener('mouseenter', stopAutoSlide);
        carousel.addEventListener('mouseleave', startAutoSlide);
      });
    }

    // Initialize carousels when page loads
    initCarousels();

    // Smooth scroll to homestay detail section
    function scrollToDetail(detailId) {
      const detailSection = document.getElementById(detailId);
      if (detailSection) {
        const offset = 80; // Account for fixed navbar
        const elementPosition = detailSection.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        });
      }
    }
  </script>

  <!-- WhatsApp Chat Widget -->
  <script src="https://sofowfweidqzxgaojsdq.supabase.co/storage/v1/object/public/widget-scripts/widget.js"
    data-widget-id="wa_d4nbxppub" async></script>
</body>

</html>