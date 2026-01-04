<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="UTF-8">
    <title>Homestay - Serena Sanctuary</title>
    <link rel="stylesheet" href="../../css/phpStyle/guestStyle/homestayStyle.css">
    <link href='https://cdn.boxicons.com/3.0.5/fonts/basic/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        <li><a href="membership.php" class="nav-link">Membership</a></li>
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
          <div class="homestay-card" onclick="scrollToDetail('homestay-detail-1')">
            <div class="homestay-image">
              <img src="../../images/homestay1/homestay1.jpg" alt="The Grand Haven">
            </div>
            <div class="homestay-details">
              <h3>The Grand Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 11 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 8 Bathrooms</span>
                <span><i class='bx bx-group'></i> 16-30 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 500</span>
                <span class="period">/ night</span>
              </div>
              <a href="booking.php" class="btn btn-primary" onclick="event.stopPropagation()">Book Now</a>
            </div>
          </div>
          <div class="homestay-card" onclick="scrollToDetail('homestay-detail-2')">
            <div class="homestay-image">
              <img src="../../images/homestay2/homestay2.jpg" alt="Twin Haven">
            </div>
            <div class="homestay-details">
              <h3>Twin Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 4 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 4 Bathrooms</span>
                <span><i class='bx bx-group'></i> 10-20 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 450</span>
                <span class="period">/ night</span>
              </div>
              <a href="booking.php" class="btn btn-primary" onclick="event.stopPropagation()">Book Now</a>
            </div>
          </div>
          <div class="homestay-card" onclick="scrollToDetail('homestay-detail-3')">
            <div class="homestay-image">
              <img src="../../images/homestay3/homestay3.jpg" alt="The Riverside Retreat">
            </div>
            <div class="homestay-details">
              <h3>The Riverside Retreat</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 8 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 6 Bathrooms</span>
                <span><i class='bx bx-group'></i> 20-36 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 400</span>
                <span class="period">/ night</span>
              </div>
              <a href="booking.php" class="btn btn-primary" onclick="event.stopPropagation()">Book Now</a>
            </div>
          </div>
          <div class="homestay-card" onclick="scrollToDetail('homestay-detail-4')">
            <div class="homestay-image">
              <img src="../../images/homestay4/homestay4.jpg" alt="Hilltop Haven">
            </div>
            <div class="homestay-details">
              <h3>Hilltop Haven</h3>
              <p class="homestay-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
              <div class="homestay-features">
                <span><i class='bx bx-bed'></i> 5 Bedrooms</span>
                <span><i class='bx bx-bath'></i> 5 Bathrooms</span>
                <span><i class='bx bx-group'></i> Up to 10 Guests</span>
              </div>
              <div class="homestay-price">
                <span class="price">RM 550</span>
                <span class="period">/ night</span>
              </div>
              <a href="booking.php" class="btn btn-primary" onclick="event.stopPropagation()">Book Now</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Homestay Details Section -->
    <section class="homestay-details-section">
      <div class="container">
        <!-- Homestay 1 Details -->
        <div class="homestay-detail-card" id="homestay-detail-1">
          <div class="detail-image-carousel">
            <div class="carousel-container">
              <img src="../../images/homestay1/homestay1.jpg" alt="Serene Villa" class="carousel-image active">
              <img src="../../images/homestay1/homestay1(1).jpg" alt="Serene Villa" class="carousel-image">
              <img src="../../images/homestay1/homestay1(2).jpg" alt="Serene Villa" class="carousel-image">
            </div>
            <button class="carousel-arrow carousel-arrow-left" aria-label="Previous image">
              <i class='bx bx-chevron-left'></i>
            </button>
            <button class="carousel-arrow carousel-arrow-right" aria-label="Next image">
              <i class='bx bx-chevron-right'></i>
            </button>
            <div class="carousel-indicators">
              <span class="indicator active" data-slide="0"></span>
              <span class="indicator" data-slide="1"></span>
              <span class="indicator" data-slide="2"></span>
            </div>
          </div>
          <div class="detail-content">
            <h2>The Grand Haven</h2>
            <p class="detail-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
            <p class="detail-description">
              This is the largest unit, designed for significant gatherings. It features 11 bedrooms and can accommodate between 16 to 30 guests. Perfect for large family reunions, corporate retreats, and special events. Features modern amenities, fully equipped kitchen, and spacious common areas.
            </p>
            <div class="detail-features">
              <div class="feature-item">
                <i class='bx bx-bed'></i>
                <span>11 Bedrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-bath'></i>
                <span>8 Bathrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-group'></i>
                <span>16-30 Guests</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-wifi'></i>
                <span>Free WiFi</span>
              </div>
            </div>
            <div class="detail-price">
              <span class="price">RM 500</span>
              <span class="period">/ night</span>
            </div>
            <a href="booking.php" class="btn btn-primary">Book Now</a>
          </div>
        </div>

        <!-- Homestay 2 Details -->
        <div class="homestay-detail-card" id="homestay-detail-2">
          <div class="detail-image-carousel">
            <div class="carousel-container">
              <img src="../../images/homestay2/homestay2.jpg" alt="Twin Haven" class="carousel-image active">
              <img src="../../images/homestay2/homestay2(1).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(2).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(3).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(4).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(5).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(6).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(7).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(8).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(9).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(10).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(11).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(12).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(13).jpg" alt="Twin Haven" class="carousel-image">
              <img src="../../images/homestay2/homestay2(14).jpg" alt="Twin Haven" class="carousel-image">
            </div>
            <button class="carousel-arrow carousel-arrow-left" aria-label="Previous image">
              <i class='bx bx-chevron-left'></i>
            </button>
            <button class="carousel-arrow carousel-arrow-right" aria-label="Next image">
              <i class='bx bx-chevron-right'></i>
            </button>
            <div class="carousel-indicators">
              <span class="indicator active" data-slide="0"></span>
              <span class="indicator" data-slide="1"></span>
              <span class="indicator" data-slide="2"></span>
              <span class="indicator" data-slide="3"></span>
              <span class="indicator" data-slide="4"></span>
              <span class="indicator" data-slide="5"></span>
              <span class="indicator" data-slide="6"></span>
              <span class="indicator" data-slide="7"></span>
              <span class="indicator" data-slide="8"></span>
              <span class="indicator" data-slide="9"></span>
              <span class="indicator" data-slide="10"></span>
              <span class="indicator" data-slide="11"></span>
              <span class="indicator" data-slide="12"></span>
              <span class="indicator" data-slide="13"></span>
              <span class="indicator" data-slide="14"></span>
            </div>
          </div>
          <div class="detail-content">
            <h2>Twin Haven</h2>
            <p class="detail-location"><i class='bx bx-map'></i> Hulu Langat, Selangor</p>
            <p class="detail-description">
              A mid-sized option suitable for smaller groups or families, consisting of 4 bedrooms and accommodating 10 to 20 guests. Perfect for family gatherings and group retreats. Features modern amenities, comfortable living spaces, and a welcoming atmosphere.
            </p>
            <div class="detail-features">
              <div class="feature-item">
                <i class='bx bx-bed'></i>
                <span>4 Bedrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-bath'></i>
                <span>4 Bathrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-group'></i>
                <span>10-20 Guests</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-wifi'></i>
                <span>Free WiFi</span>
              </div>
            </div>
            <div class="detail-price">
              <span class="price">RM 450</span>
              <span class="period">/ night</span>
            </div>
            <a href="booking.php" class="btn btn-primary">Book Now</a>
          </div>
        </div>

        <!-- Homestay 3 Details -->
        <div class="homestay-detail-card" id="homestay-detail-3">
          <div class="detail-image-carousel">
            <div class="carousel-container">
              <img src="../../images/homestay3/homestay3.jpg" alt="The Riverside Retreat" class="carousel-image active">
              <img src="../../images/homestay3/homestay3(1).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(2).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(3).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(4).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(5).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(6).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(7).webp" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(8).jpg" alt="The Riverside Retreat" class="carousel-image">
              <img src="../../images/homestay3/homestay3(9).jpg" alt="The Riverside Retreat" class="carousel-image">
            </div>
            <button class="carousel-arrow carousel-arrow-left" aria-label="Previous image">
              <i class='bx bx-chevron-left'></i>
            </button>
            <button class="carousel-arrow carousel-arrow-right" aria-label="Next image">
              <i class='bx bx-chevron-right'></i>
            </button>
            <div class="carousel-indicators">
              <span class="indicator active" data-slide="0"></span>
              <span class="indicator" data-slide="1"></span>
              <span class="indicator" data-slide="2"></span>
              <span class="indicator" data-slide="3"></span>
              <span class="indicator" data-slide="4"></span>
              <span class="indicator" data-slide="5"></span>
              <span class="indicator" data-slide="6"></span>
              <span class="indicator" data-slide="7"></span>
              <span class="indicator" data-slide="8"></span>
              <span class="indicator" data-slide="9"></span>
            </div>
          </div>
          <div class="detail-content">
            <h2>The Riverside Retreat</h2>
            <p class="detail-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
            <p class="detail-description">
              This unit is situated near a natural river stream (Sungai Selaru). It offers 8 bedrooms with a capacity for 20 to 36 guests. Perfect for nature lovers and large groups seeking a peaceful riverside experience. Features modern amenities and stunning natural surroundings.
            </p>
            <div class="detail-features">
              <div class="feature-item">
                <i class='bx bx-bed'></i>
                <span>8 Bedrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-bath'></i>
                <span>6 Bathrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-group'></i>
                <span>20-36 Guests</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-wifi'></i>
                <span>Free WiFi</span>
              </div>
            </div>
            <div class="detail-price">
              <span class="price">RM 400</span>
              <span class="period">/ night</span>
            </div>
            <a href="booking.php" class="btn btn-primary">Book Now</a>
          </div>
        </div>

        <!-- Homestay 4 Details -->
        <div class="homestay-detail-card" id="homestay-detail-4">
          <div class="detail-image-carousel">
            <div class="carousel-container">
              <img src="../../images/homestay4/homestay4.jpg" alt="Hilltop Haven" class="carousel-image active">
              <img src="../../images/homestay4/homestay4(1).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(2).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(3).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(4).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(5).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(6).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(7).jpg" alt="Hilltop Haven" class="carousel-image">
              <img src="../../images/homestay4/homestay4(8).jpg" alt="Hilltop Haven" class="carousel-image">
            </div>
            <button class="carousel-arrow carousel-arrow-left" aria-label="Previous image">
              <i class='bx bx-chevron-left'></i>
            </button>
            <button class="carousel-arrow carousel-arrow-right" aria-label="Next image">
              <i class='bx bx-chevron-right'></i>
            </button>
            <div class="carousel-indicators">
              <span class="indicator active" data-slide="0"></span>
              <span class="indicator" data-slide="1"></span>
              <span class="indicator" data-slide="2"></span>
              <span class="indicator" data-slide="3"></span>
              <span class="indicator" data-slide="4"></span>
              <span class="indicator" data-slide="5"></span>
              <span class="indicator" data-slide="6"></span>
              <span class="indicator" data-slide="7"></span>
              <span class="indicator" data-slide="8"></span>
            </div>
          </div>
          <div class="detail-content">
            <h2>Hilltop Haven</h2>
            <p class="detail-location"><i class='bx bx-map'></i> Gopeng, Perak</p>
            <p class="detail-description">
              An aristocratic-style villa located on a hillside. It is laid out over two floors with 3 main bedrooms in the main complex (sleeping 6) and two additional dependencies ("Villa Serenella" and "Villa Limonaia") that each sleep two people. Perfect for those seeking elegance and privacy with stunning hillside views.
            </p>
            <div class="detail-features">
              <div class="feature-item">
                <i class='bx bx-bed'></i>
                <span>5 Bedrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-bath'></i>
                <span>5 Bathrooms</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-group'></i>
                <span>Up to 10 Guests</span>
              </div>
              <div class="feature-item">
                <i class='bx bx-wifi'></i>
                <span>Free WiFi</span>
              </div>
            </div>
            <div class="detail-price">
              <span class="price">RM 550</span>
              <span class="period">/ night</span>
            </div>
            <a href="booking.php" class="btn btn-primary">Book Now</a>
          </div>
        </div>
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
  <script src="https://sofowfweidqzxgaojsdq.supabase.co/storage/v1/object/public/widget-scripts/widget.js" data-widget-id="wa_d4nbxppub" async></script>
</body>
</html>
