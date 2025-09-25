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

  <!-- External CSS -->
  <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css" />
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
  
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Lato:300,400|Work+Sans:300,400,700" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,600,700" rel="stylesheet" />
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css" />
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" />
  
  <!-- Custom CSS -->
  <link href="css/style.min.css" rel="stylesheet">
  
  <style>
    /* Login page specific styles matching portfolio design */
    body {
      background: #f5f8fd url("img/intro-bg.jpg") center top no-repeat;
      background-size: cover;
      font-family: "Open Sans", sans-serif;
      color: #444;
      min-height: 100vh;
      position: relative;
    }
    
    /* Background overlay */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(245, 248, 253, 0.9);
      z-index: -1;
    }
    
    /* Login container */
    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 0;
    }
    
    /* Login card matching visual-card style */
    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      border: none;
      overflow: hidden;
      max-width: 450px;
      width: 100%;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .login-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
    }
    
    /* Login header */
    .login-header {
      background: hsl(312, 100%, 50%);
      color: white;
      padding: 40px 30px;
      text-align: center;
      position: relative;
    }
    
    .login-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, hsl(312, 100%, 50%) 0%, hsl(312, 100%, 45%) 100%);
      z-index: -1;
    }
    
    .brand-logo {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      margin: 0 auto 20px;
      padding: 3px;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .brand-logo img {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      object-fit: cover;
    }
    
    .login-title {
      color: white !important;
      font-family: "Montserrat", sans-serif;
      font-weight: 600;
      margin-bottom: 10px;
    }
    
    .login-subtitle {
      color: rgba(255, 255, 255, 0.8);
      font-size: 0.95rem;
      margin: 0;
    }
    
    /* Login body */
    .login-body {
      padding: 40px 30px;
    }
    
    /* Form elements matching portfolio style */
    .form-group {
      margin-bottom: 25px;
    }
    
    .form-label {
      color: #413e66;
      font-weight: 600;
      font-family: "Montserrat", sans-serif;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-label i {
      color: hsl(312, 100%, 50%);
      font-size: 0.9rem;
    }
    
    .form-control {
      border-radius: 8px;
      border: 2px solid #e9ecef;
      padding: 15px 18px;
      font-size: 1rem;
      transition: all 0.3s ease;
      font-family: "Open Sans", sans-serif;
      background: rgba(255, 255, 255, 0.8);
    }
    
    .form-control:focus {
      border-color: #1bb1dc;
      box-shadow: 0 0 0 0.2rem rgba(27, 177, 220, 0.25);
      outline: none;
      background: rgba(255, 255, 255, 0.95);
    }
    
    .form-control::placeholder {
      color: #999;
      font-style: italic;
    }
    
    /* Password toggle */
    .password-container {
      position: relative;
    }
    
    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      font-size: 1.1rem;
      transition: color 0.3s ease;
    }
    
    .password-toggle:hover {
      color: hsl(312, 100%, 50%);
    }
    
    /* Login button matching portfolio buttons */
    .btn-login {
      background: #1bb1dc;
      border-color: #1bb1dc;
      color: white;
      border-radius: 8px;
      padding: 15px 25px;
      font-weight: 600;
      font-family: "Montserrat", sans-serif;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 14px;
      width: 100%;
      transition: all 0.3s ease;
      border: none;
    }
    
    .btn-login:hover {
      background: #0a98c0;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(27, 177, 220, 0.4);
    }
    
    .btn-login:focus {
      box-shadow: 0 0 0 0.2rem rgba(27, 177, 220, 0.25);
    }
    
    .btn-login:disabled {
      background: #6c757d;
      cursor: not-allowed;
      transform: none;
    }
    
    /* Remember me checkbox */
    .form-check {
      margin: 20px 0;
    }
    
    .form-check-input {
      background-color: rgba(255, 255, 255, 0.8);
      border-color: #e9ecef;
      border-width: 2px;
    }
    
    .form-check-input:checked {
      background-color: hsl(312, 100%, 50%);
      border-color: hsl(312, 100%, 50%);
    }
    
    .form-check-input:focus {
      border-color: #1bb1dc;
      box-shadow: 0 0 0 0.25rem rgba(27, 177, 220, 0.25);
    }
    
    .form-check-label {
      color: #666;
      font-size: 0.9rem;
      cursor: pointer;
    }
    
    /* Alert messages */
    .alert {
      border-radius: 8px;
      border: none;
      padding: 15px 20px;
      margin-bottom: 20px;
      font-size: 0.95rem;
    }
    
    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border-left: 4px solid #dc3545;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }
    
    /* Loading state */
    .loading {
      opacity: 0.7;
      pointer-events: none;
    }
    
    .loading .btn-login {
      position: relative;
    }
    
    .loading .btn-login::after {
      content: '';
      position: absolute;
      width: 20px;
      height: 20px;
      top: 50%;
      left: 50%;
      margin-left: -10px;
      margin-top: -10px;
      border: 2px solid transparent;
      border-top-color: white;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Footer links */
    .login-footer {
      padding: 20px 30px;
      background: rgba(245, 248, 253, 0.5);
      text-align: center;
      border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .back-to-portfolio {
      color: #1bb1dc;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: color 0.3s ease;
    }
    
    .back-to-portfolio:hover {
      color: #0a98c0;
      text-decoration: none;
    }
    
    /* Security info */
    .security-info {
      background: rgba(27, 177, 220, 0.1);
      border-radius: 8px;
      padding: 15px;
      margin-top: 20px;
      font-size: 0.85rem;
      color: #666;
      text-align: center;
    }
    
    .security-info i {
      color: #1bb1dc;
      margin-right: 5px;
    }
    
    /* Responsive design */
    @media (max-width: 576px) {
      .login-container {
        padding: 20px 15px;
      }
      
      .login-card {
        margin: 0 10px;
      }
      
      .login-header {
        padding: 30px 20px;
      }
      
      .login-body {
        padding: 30px 20px;
      }
      
      .brand-logo {
        width: 70px;
        height: 70px;
      }
      
      .brand-logo img {
        width: 60px;
        height: 60px;
      }
      
      .login-title {
        font-size: 1.5rem;
      }
    }
    
    /* Animated background elements (subtle) */
    .bg-shapes {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -2;
      overflow: hidden;
      pointer-events: none;
    }
    
    .bg-shapes::before,
    .bg-shapes::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      opacity: 0.1;
      animation: float 6s ease-in-out infinite;
    }
    
    .bg-shapes::before {
      width: 200px;
      height: 200px;
      background: hsl(312, 100%, 50%);
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }
    
    .bg-shapes::after {
      width: 300px;
      height: 300px;
      background: #1bb1dc;
      bottom: 20%;
      right: 10%;
      animation-delay: 3s;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }
  </style>
</head>
<body>
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
          
          <!-- Debug info (remove in production) -->
          <div class="text-center mt-3">
            <small style="color: #999;">
              Standaard: admin / admin123
            </small>
          </div>
          
        </form>
      </div>
      
      <!-- Login Footer -->
      <div class="login-footer">
        <a href="index.html" class="back-to-portfolio">
          <i class="lnr lnr-arrow-left"></i>
          Terug naar Portfolio
        </a>
      </div>
      
    </div>
  </div>
  <!-- JavaScript Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="vendor/bootstrap/bootstrap.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  
  <script>
    // Initialize AOS animations
    AOS.init({
      duration: 800,
      once: true
    });

    class LoginManager {
      constructor() {
        this.init();
      }

      init() {
        this.bindEvents();
        this.loadRememberedCredentials();
        
        // Check for PHP success and redirect
        <?php if ($success_message): ?>
        setTimeout(() => {
          window.location.href = 'admin.php';
        }, 2000);
        <?php endif; ?>
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

    // Initialize when document is ready
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
  </script>
</body>
</html>