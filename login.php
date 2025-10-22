<?php
session_start();

// Include database configuration
require_once 'database.php';

$error_message = '';
$success_message = '';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (!empty($username) && !empty($password)) {
        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                $success_message = 'Inloggen succesvol! Je wordt doorgestuurd...';
                // JavaScript redirect will handle the forwarding
            } else {
                $error_message = 'Onjuiste gebruikersnaam of wachtwoord';
            }
        } catch (PDOException $e) {
            $error_message = 'Database fout. Probeer later opnieuw.';
            error_log("Login error: " . $e->getMessage());
        }
    } else {
        $error_message = 'Vul alle velden in';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Tiebo Croons Portfolio</title>
  <meta name="description" content="Secure login voor portfolio beheer">
  
  <!-- Favicon -->
  <link rel="icon" type="image" href="img/testi-1.png" />

  <!-- Critical resource preloading -->
  <link rel="preload" href="img/testi-1.png" as="image" fetchpriority="high">
  <link rel="preload" href="css/style.css" as="style">
  <link rel="preload" href="vendor/bootstrap/bootstrap.min.css" as="style">
  
  <!-- DNS prefetching for external domains -->
  <link rel="dns-prefetch" href="//fonts.googleapis.com">
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link rel="dns-prefetch" href="//cdn.linearicons.com">
  <link rel="dns-prefetch" href="//unpkg.com">
  <link rel="dns-prefetch" href="//use.fontawesome.com">
  <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
  
  <!-- Preconnect to critical origins -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Critical CSS loaded with high priority -->
  <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css" />
  <link rel="stylesheet" href="css/style.css">
  
  <!-- Non-critical CSS loaded asynchronously -->
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" media="print" onload="this.media='all'" />
  
  <!-- Fonts with optimized loading strategy -->
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400|Work+Sans:300,400,700&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
  
  <!-- Fallback for non-supporting browsers -->
  <noscript>
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" />
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400|Work+Sans:300,400,700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700&display=swap" rel="stylesheet" />
  </noscript>

    <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-KBKMP25R');</script>
<!-- End Google Tag Manager -->
  
  <!-- Critical inline CSS for above-the-fold rendering -->
  <style>
    /* Minimale kritieke CSS voor immediate rendering */
    .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .login-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; max-width: 450px; width: 100%; }
    .login-header { background: hsl(312, 100%, 50%); color: white; padding: 40px 30px; text-align: center; }
    .brand-logo { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; }
    .brand-logo img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; }
  </style>
</head>
<body class="login-page">
  <!-- Background shapes -->
  <div class="bg-shapes"></div>
  
  <div class="container-fluid login-container">
    <div class="login-card" data-aos="fade-up" data-aos-duration="800">
      
      <!-- Login Header -->
      <div class="login-header">
        <div class="brand-logo">
          <img src="img/testi-1.png" alt="Tiebo Croons" class="brand-image">
        </div>
        <h2 class="login-title">Welkom Terug</h2>
        <p class="login-subtitle">Log in om je portfolio te beheren</p>
      </div>
      
      <!-- Login Form -->
      <div class="login-body">
        <!-- Alert container -->
        <div id="alertContainer">
          <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
              <?php echo htmlspecialchars($error_message); ?>
            </div>
          <?php endif; ?>
          
          <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
              <?php echo htmlspecialchars($success_message); ?>
            </div>
          <?php endif; ?>
        </div>
        
        <form method="POST" action="" id="loginForm" novalidate>
          
          <!-- Username Field -->
          <div class="form-group">
            <label for="username" class="form-label">
              <i class="lnr lnr-user"></i>
              Gebruikersnaam
            </label>
            <input 
              type="text" 
              class="form-control" 
              id="username" 
              name="username"
              placeholder="Voer je gebruikersnaam in"
              required
              autocomplete="username"
              value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : ''); ?>"
            >
            <div class="invalid-feedback"></div>
          </div>
          
          <!-- Password Field -->
          <div class="form-group">
            <label for="password" class="form-label">
              <i class="lnr lnr-lock"></i>
              Wachtwoord
            </label>
            <div class="password-container">
              <input 
                type="password" 
                class="form-control" 
                id="password" 
                name="password"
                placeholder="Voer je wachtwoord in"
                required
                autocomplete="current-password"
              >
              <button type="button" class="password-toggle" id="passwordToggle" tabindex="-1">
                <i class="lnr lnr-eye"></i>
              </button>
            </div>
            <div class="invalid-feedback"></div>
          </div>
          
          <!-- Remember Me -->
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
            <label class="form-check-label" for="rememberMe">
              Onthoud mijn gegevens
            </label>
          </div>
          
          <!-- Login Button -->
          <button type="submit" class="btn btn-login" id="loginBtn">
            <i class="lnr lnr-enter"></i>
            Inloggen
          </button>
          
          <!-- Security Info -->
          <div class="security-info">
            <i class="lnr lnr-shield"></i>
            Je gegevens worden veilig versleuteld en beschermd
          </div>
          
        </form>
      </div>
      
      <!-- Login Footer -->
      <div class="login-footer">
        <a href="index.php" class="back-to-portfolio">
          <i class="lnr lnr-arrow-left"></i>
          Terug naar Portfolio
        </a>
      </div>
      
    </div>
  </div>
  <!-- JavaScript Libraries - Progressive Loading -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
  <script src="vendor/bootstrap/bootstrap.min.js" defer></script>
  
  <!-- Non-critical JS loaded with lower priority -->
  <script>
    // Progressive script loading for login page
    function loadNonCriticalScripts() {
      const scripts = [
        'https://unpkg.com/aos@next/dist/aos.js'
      ];
      
      scripts.forEach((src, index) => {
        const script = document.createElement('script');
        script.src = src;
        script.defer = true;
        if (index === scripts.length - 1) {
          script.onload = () => initializeAOS();
        }
        document.head.appendChild(script);
      });
    }
    
    // Initialize AOS when available
    function initializeAOS() {
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 800,
          once: true,
          disable: window.innerWidth < 768 // Disable on mobile for performance
        });
      }
    }
    
    // Load non-critical scripts after DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        setTimeout(loadNonCriticalScripts, 100);
      });
    } else {
      setTimeout(loadNonCriticalScripts, 100);
    }
  </script>
  
  <script>
    // Initialize AOS animations
    function initAOSWhenReady() {
      if (typeof AOS !== 'undefined') {
        AOS.init({
          duration: 800,
          once: true
        });
      } else {
        setTimeout(initAOSWhenReady, 100);
      }
    }
    
    // Start AOS when scripts are loaded
    initAOSWhenReady();

    class LoginManager {
      constructor() {
        this.init();
      }

      init() {
        // Wait for jQuery to be available
        if (typeof $ === 'undefined') {
          setTimeout(() => this.init(), 50);
          return;
        }
        
        this.bindEvents();
        this.loadRememberedCredentials();
        
        // Performance monitoring
        this.monitorPerformance();
        
        // Check for PHP success and redirect
        <?php if ($success_message): ?>
        setTimeout(() => {
          window.location.href = 'admin.php';
        }, 2000);
        <?php endif; ?>
      }
      
      monitorPerformance() {
        // Monitor login page performance
        if ('PerformanceObserver' in window) {
          const observer = new PerformanceObserver((entryList) => {
            const entries = entryList.getEntries();
            const lastEntry = entries[entries.length - 1];
            if (console && console.log) {
              console.log('Login LCP:', Math.round(lastEntry.startTime), 'ms');
            }
          });
          try {
            observer.observe({ entryTypes: ['largest-contentful-paint'] });
          } catch (e) {
            // Ignore if not supported
          }
        }
      }

      bindEvents() {
        // Form submission
        $('#loginForm').on('submit', (e) => {
          if (!this.validateForm()) {
            e.preventDefault();
            this.showAlert('Controleer je invoer en probeer opnieuw', 'danger');
            return false;
          }
          
          this.setLoading(true);
        });

        // Password toggle
        $('#passwordToggle').on('click', () => {
          this.togglePassword();
        });

        // Real-time validation
        $('#username, #password').on('input', (e) => {
          this.validateField(e.target);
        });

        // Clear alerts on input
        $('#username, #password').on('focus', () => {
          this.clearJSAlerts();
        });
      }

      validateField(field) {
        const $field = $(field);
        const value = $field.val().trim();
        let isValid = true;
        let message = '';

        if (field.id === 'username') {
          if (value.length < 3) {
            isValid = false;
            message = 'Gebruikersnaam moet minimaal 3 karakters bevatten';
          }
        }

        if (field.id === 'password') {
          if (value.length < 6) {
            isValid = false;
            message = 'Wachtwoord moet minimaal 6 karakters bevatten';
          }
        }

        // Update field state
        if (isValid) {
          $field.removeClass('is-invalid').addClass('is-valid');
        } else {
          $field.removeClass('is-valid').addClass('is-invalid');
          $field.siblings('.invalid-feedback').text(message);
        }

        return isValid;
      }

      validateForm() {
        const username = this.validateField(document.getElementById('username'));
        const password = this.validateField(document.getElementById('password'));
        return username && password;
      }

      togglePassword() {
        const $password = $('#password');
        const $toggle = $('#passwordToggle i');
        const type = $password.attr('type');

        if (type === 'password') {
          $password.attr('type', 'text');
          $toggle.removeClass('lnr-eye').addClass('lnr-eye-off');
        } else {
          $password.attr('type', 'password');
          $toggle.removeClass('lnr-eye-off').addClass('lnr-eye');
        }
      }

      loadRememberedCredentials() {
        if (localStorage.getItem('rememberMe') === 'true') {
          const username = localStorage.getItem('username');
          if (username) {
            $('#username').val(username);
            $('#rememberMe').prop('checked', true);
          }
        }
      }

      setLoading(loading) {
        const $form = $('#loginForm');
        const $btn = $('#loginBtn');

        if (loading) {
          $form.addClass('loading');
          $btn.prop('disabled', true).text('');
        } else {
          $form.removeClass('loading');
          $btn.prop('disabled', false).html('<i class="lnr lnr-enter"></i> Inloggen');
        }
      }

      showAlert(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'danger' ? 'alert-danger' : 'alert-info';
        
        const html = `
          <div class="alert ${alertClass} alert-dismissible fade show js-alert" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        `;
        
        $('#alertContainer').append(html);
        
        // Auto hide success messages
        if (type === 'success') {
          setTimeout(() => {
            $('.js-alert').fadeOut();
          }, 3000);
        }
      }

      clearJSAlerts() {
        $('.js-alert').remove();
      }
    }

    // Initialize when jQuery is available
    function initializeWhenReady() {
      if (typeof $ !== 'undefined') {
        $(document).ready(() => {
          new LoginManager();
          
          // Handle remember me functionality
          $('#loginForm').on('submit', function() {
            const rememberMe = $('#rememberMe').is(':checked');
            const username = $('#username').val();
            
            if (rememberMe) {
              localStorage.setItem('rememberMe', 'true');
              localStorage.setItem('username', username);
            } else {
              localStorage.removeItem('rememberMe');
              localStorage.removeItem('username');
            }
          });
        });
      } else {
        setTimeout(initializeWhenReady, 50);
      }
    }
    
    // Start initialization
    initializeWhenReady();
  </script>
</body>
</html>