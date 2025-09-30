<?php
// Start session for potential login functionality
session_start();

// Include database configuration
require_once 'database.php';

// Set PHP configuration
ini_set('display_errors', 0); // Hide errors in production
error_reporting(E_ALL);

// Get project ID from URL parameter
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Function to get settings from database
function getSiteSettings() {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
        return [];
    }
}

// Function to get dynamic stats
function getDynamicStats() {
    try {
        $pdo = getDatabaseConnection();
        
        // Get total active projects count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE is_deleted = 0");
        $totalProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'total_projects' => $totalProjects,
            'years_experience' => 3, // You could add this to settings table
            'passion_percentage' => 100
        ];
    } catch (Exception $e) {
        error_log("Error fetching dynamic stats: " . $e->getMessage());
        return [
            'total_projects' => 18,
            'years_experience' => 3,
            'passion_percentage' => 100
        ];
    }
}

// Function to get project details from database
function getProjectById($id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM projects 
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching project: " . $e->getMessage());
        return null;
    }
}

// Function to get related projects (same category, different ID)
function getRelatedProjects($categoryId, $currentId, $limit = 3) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM projects 
            WHERE category = ? AND id != ? AND is_deleted = 0 
            ORDER BY is_featured DESC, updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$categoryId, $currentId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching related projects: " . $e->getMessage());
        return [];
    }
}

// Function to get timeline phases for a project
function getTimelinePhases($projectId) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM timeline_phases 
            WHERE project_id = ? 
            ORDER BY phase_order ASC, created_at ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching timeline phases: " . $e->getMessage());
        return [];
    }
}

// Get site settings
$siteSettings = getSiteSettings();

// Get dynamic stats
$dynamicStats = getDynamicStats();

// Get project data
$project = getProjectById($projectId);

// If project not found, redirect to homepage
if (!$project) {
    header('Location: index.php');
    exit();
}

// Decode JSON fields for easier use
$project['tools'] = json_decode($project['tools'] ?: '[]', true);
$project['features'] = json_decode($project['features'] ?: '[]', true);
$project['timeline'] = json_decode($project['timeline'] ?: '[]', true);
$project['gallery_images'] = json_decode($project['gallery_images'] ?: '[]', true);

// Get timeline phases from admin panel
$timelinePhases = getTimelinePhases($project['id']);

// Debug timeline phases (comment out in production)
if (count($timelinePhases) > 0) {
    error_log("Timeline phases loaded: " . count($timelinePhases) . " phases found");
} else {
    error_log("No timeline phases found for project ID: " . $project['id']);
}

// Get related projects
$relatedProjects = getRelatedProjects($project['category'], $project['id']);

// Check if user is logged in (for admin features)
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Set page title and meta description dynamically
$siteTitle = isset($siteSettings['site_title']) ? $siteSettings['site_title'] : 'Portfolio | Tiebo Croons';
$pageTitle = htmlspecialchars($project['title']) . " | " . (isset($siteSettings['site_title']) ? $siteSettings['site_title'] : 'Tiebo Croons');
$metaDescription = isset($siteSettings['meta_description']) ? $siteSettings['meta_description'] : htmlspecialchars($project['short_description'] ?: $project['description']);

// Extract brand information from settings
$brandName = explode(' | ', $siteTitle);
$brandName = isset($brandName[1]) ? $brandName[1] : 'Tiebo Croons';
$brandSubtitle = 'Digital Designer'; // You can add this to settings table if needed

?>
<!DOCTYPE html>
<html lang="be-nl">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $metaDescription; ?>" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1, shrink-to-fit=no"
    />
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-Y6MVVR1W8Q"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-Y6MVVR1W8Q');
</script>
    <link rel="icon" type="image" href="img/testi-1.png" />

    <!-- External CSS -->
    <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css" />
    <link rel="stylesheet" href="vendor/select2/select2.min.css" />
    <link rel="stylesheet" href="vendor/owlcarousel/owl.carousel.min.css" />
    <link rel="stylesheet" href="vendor/lightcase/lightcase.css" />

  <!-- Custom breadcrumb styling -->
  <style>
    .breadcrumb-item a {
      color: #007bff;
    }
    .breadcrumb-item.active {
      color: #6c757d;
    }
    .admin-actions {
      border-top: 1px solid #dee2e6;
      padding-top: 10px;
    }
    
    /* Social Links Styling */
    .social-links {
      margin-top: 1.5rem;
    }
    
    .social-icons {
      display: flex;
      justify-content: center;
      gap: 1rem;
    }
    
    .social-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      color: #6c757d;
      text-decoration: none;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .social-link:hover {
      background: #007bff;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
    }
    
    .social-link i {
      font-size: 16px;
    }
  </style>
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

    <!-- Fonts -->
    <link
      href="https://fonts.googleapis.com/css?family=Lato:300,400|Work+Sans:300,400,700"
      rel="stylesheet"
    />

    <!-- CSS -->
    <link rel="stylesheet" href="css/style.min.css" />
    <link
      rel="stylesheet"
      href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css"
    />
    <!-- Font Awesome as backup for icons -->
    <link
      rel="stylesheet" 
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
    <link
      rel="stylesheet"
      href="https://use.fontawesome.com/releases/v5.8.1/css/all.css"
    />
    
    <style>
      /* Dynamic layout adjustments */
      .design-project-layout .row.justify-content-center > [class*="col-"] {
        flex: 0 0 100%;
        max-width: 100%;
      }
      
      .design-project-layout .col-lg-8 {
        flex: 0 0 66.666667%;
        max-width: 66.666667%;
      }
      
      /* Design highlights styling */
      .design-highlights .highlight-item {
        display: flex;
        align-items: flex-start;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
      }
      
      .design-highlights .highlight-item:last-child {
        border-bottom: none;
      }
      
      .highlight-icon {
        margin-right: 15px;
        font-size: 1.5rem;
        margin-top: 5px;
      }
      
      .highlight-content h6 {
        margin-bottom: 8px;
        color: #333;
        font-weight: 600;
      }
      
      .highlight-desc {
        margin: 0;
        color: #666;
        line-height: 1.6;
      }
      
      .timeline-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
      }
      
      .progress-timeline .timeline-item {
        position: relative;
      }
      
      .progress-timeline .timeline-item:not(:last-child):before {
        content: '';
        position: absolute;
        left: 5px;
        top: 20px;
        height: calc(100% + 10px);
        width: 2px;
        background-color: #e9ecef;
      }
      
      .progress-timeline .timeline-item.completed:not(:last-child):before {
        background-color: #28a745;
      }
      
      .resource-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
      }
      
      .resource-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      }
      
      .badge-success,
      .badge-primary {
        border: none !important;
      }
      
      /* Gallery Grid Styles */
      .gallery-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 15px;
      }
      
      .gallery-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
      }
      
      .gallery-image-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        cursor: pointer;
      }
      
      .gallery-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
      }
      
      .gallery-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
      }
      
      .gallery-item:hover .gallery-overlay {
        opacity: 1;
      }
      
      .gallery-item:hover .gallery-image {
        transform: scale(1.1);
      }
      
      .gallery-zoom {
        color: white;
        font-size: 1.5rem;
        text-decoration: none;
      }
      
      .gallery-more {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      
      .gallery-more:hover {
        background: #e9ecef;
        border-color: #adb5bd;
      }
      
      .gallery-more-content {
        text-align: center;
        color: #6c757d;
      }
      
      .gallery-more-content i {
        font-size: 1.5rem;
        margin-bottom: 5px;
        display: block;
      }
      
      .gallery-more-content span {
        font-size: 0.875rem;
        font-weight: 500;
      }
      
      /* Project Avatar/Logo Styles */
      .project-avatar .avatar-wrapper {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
      }
      
      .project-image-circle {
        position: relative;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 3px solid #fff;
        background: #fff;
        z-index: 2;
      }
      
      .project-main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
      }
      
      .avatar-ring {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 140px;
        height: 140px;
        border: 2px solid rgba(27, 177, 220, 0.3);
        border-radius: 50%;
        animation: pulse-ring 3s ease-in-out infinite;
        z-index: 1;
      }
      
      @keyframes pulse-ring {
        0%, 100% {
          transform: translate(-50%, -50%) scale(1);
          opacity: 0.8;
        }
        50% {
          transform: translate(-50%, -50%) scale(1.1);
          opacity: 0.4;
        }
      }
      
      /* Year badge styling */
      .badge-date {
        background: linear-gradient(45deg, #1bb1dc, #0a98c0);
        color: white;
        font-weight: 600;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
      }
    </style>
  </head>

  <body data-spy="scroll" data-target="#navbar" class="static-layout <?php echo !in_array($project['category'], ['development', 'web', 'mobile']) ? 'design-project-layout' : 'development-project-layout'; ?>">
    <!-- Modern Navbar -->
    <nav id="header-navbar" class="navbar navbar-expand-lg navbar-transparent modern-navbar">
      <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand modern-brand" href="index.php">
          <div class="brand-container">
            <div class="brand-icon">
              <img src="img/testi-1.png" alt="<?php echo htmlspecialchars($brandName); ?>" class="brand-image">
            </div>
            <div class="brand-text">
              <h4 class="brand-name mb-0"><?php echo htmlspecialchars($brandName); ?></h4>
              <small class="brand-subtitle"><?php echo htmlspecialchars($brandSubtitle); ?></small>
            </div>
          </div>
        </a>

        <!-- Mobile Toggle Button -->
        <button
          class="navbar-toggler modern-toggler"
          type="button"
          data-toggle="collapse"
          data-target="#navbar-nav-header"
          aria-controls="navbar-nav-header"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="toggler-icon"></span>
          <span class="toggler-icon"></span>
          <span class="toggler-icon"></span>
        </button>

        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbar-nav-header">
          <ul class="navbar-nav ms-auto modern-nav">
            <li class="nav-item">
              <a class="nav-link modern-link" href="index.php">
                <i class="lnr lnr-home nav-icon"></i>
                <span class="nav-text">Home</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link modern-link" href="index.php#who-we-are">
                <i class="lnr lnr-user nav-icon"></i>
                <span class="nav-text">Over Mij</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link modern-link" href="index.php#portfolio">
                <i class="lnr lnr-briefcase nav-icon"></i>
                <span class="nav-text">Portfolio</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link modern-link" href="index.php#contact">
                <i class="lnr lnr-envelope nav-icon"></i>
                <span class="nav-text">Contact</span>
              </a>
            </li>
            
            <?php if ($isLoggedIn): ?>
            <!-- Admin Panel Link (only visible when logged in) -->
            <li class="nav-item">
              <a class="nav-link modern-link" href="admin.php" style="color: #ff6b6b;">
                <i class="lnr lnr-cog nav-icon"></i>
                <span class="nav-text">Admin</span>
              </a>
            </li>
            <?php else: ?>
            <!-- Login Link (only visible when not logged in) -->
            <li class="nav-item">
              <a class="nav-link modern-link" href="login.php">
                <i class="lnr lnr-enter nav-icon"></i>
                <span class="nav-text">Login</span>
              </a>
            </li>
            <?php endif; ?>
            
            <!-- CTA Button -->
            <li class="nav-item cta-item">
              <a class="nav-link cta-button" href="#contact">
                <i class="lnr lnr-rocket me-2"></i>
                Start Project
              </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <div id="side-nav" class="sidenav">
      <a href="javascript:void(0)" id="side-nav-close">&times;</a>
    </div>
    <div id="side-search" class="sidenav">
      <a href="javascript:void(0)" id="side-search-close">&times;</a>
      <div class="sidenav-content">
        <form action="">
          <div class="input-group md-form form-sm form-2 pl-0">
            <input
              class="form-control my-0 py-1 red-border"
              type="text"
              placeholder="Search"
              aria-label="Search"
            />
            <div class="input-group-append">
              <button class="input-group-text red lighten-3" id="basic-text1">
                <span class="lnr lnr-magnifier"></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div id="home" class="jumbotron d-flex align-items-center">
      <div class="container text-center">
        <h1 class="display-1 mb-4"><?php echo htmlspecialchars($project['title']); ?></h1>
        <p class="lead text-white mb-4"><?php echo htmlspecialchars($project['short_description'] ?: 'Een gedetailleerde blik op dit project'); ?></p>
        
        <!-- Project Navigation Quick Links -->
        <div class="project-hero mt-4" data-aos="fade-up" data-aos-delay="300">
          <div class="project-nav d-flex justify-content-center">
            <a href="#project-overview" class="btn btn-outline-light mx-2" aria-label="Project Overzicht">
              <i class="lnr lnr-eye"></i> Overzicht
            </a>
            <a href="#project-details" class="btn btn-outline-light mx-2" aria-label="Project Details">
              <i class="lnr lnr-layers"></i> Details
            </a>
            <a href="#related-projects" class="btn btn-outline-light mx-2" aria-label="Gerelateerde Projecten">
              <i class="lnr lnr-briefcase"></i> Meer Projecten
            </a>
          </div>
        </div>
      </div>
      <div class="rectangle-1"></div>
      <div class="rectangle-2"></div>
      <div class="rectangle-transparent-1"></div>
      <div class="rectangle-transparent-2"></div>
      <div class="circle-1"></div>
      <div class="circle-2"></div>
      <div class="circle-3"></div>
      <div class="triangle triangle-1">
        <img src="img/obj_triangle.png" alt="" />
      </div>
      <div class="triangle triangle-2">
        <img src="img/obj_triangle.png" alt="" />
      </div>
      <div class="triangle triangle-3">
        <img src="img/obj_triangle.png" alt="" />
      </div>
      <div class="triangle triangle-4">
        <img src="img/obj_triangle.png" alt="" />
      </div>
    </div>

    <!-- Project Overview Section -->
    <section id="project-overview" class="bg-white">
      <div class="container">
        
        <!-- Breadcrumb Navigation -->
        <div class="row mb-4">
          <div class="col-12">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb bg-transparent p-0 mb-0">
                <li class="breadcrumb-item">
                  <a href="index.php" class="text-decoration-none">
                    <i class="lnr lnr-home"></i> Portfolio
                  </a>
                </li>
                <li class="breadcrumb-item">
                  <a href="index.php#portfolio" class="text-decoration-none"><?php echo ucfirst($project['category']); ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                  <?php echo htmlspecialchars($project['title']); ?>
                </li>
              </ol>
            </nav>
            <?php if ($isLoggedIn): ?>
              <div class="admin-actions mt-2">
                <a href="admin.php?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="lnr lnr-pencil"></i> Bewerk Project
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="section-content">
          
          <!-- Project Header -->
          <div class="row mb-5">
            <div class="col-12 text-center" data-aos="fade-up">
              <div class="project-header">
                <div class="project-avatar mb-4">
                  <div class="avatar-wrapper">
                    <img src="<?php echo htmlspecialchars($project['image_url'] ?: 'img/app-profile-mockup.png'); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" class="project-main-image">
                    <div class="avatar-ring"></div>
                  </div>
                  <h2 class="mt-3 mb-2"><?php echo htmlspecialchars($project['title']); ?></h2>
                  <p class="text-muted"><?php echo ucfirst(htmlspecialchars($project['category'])); ?> Project</p>
                  <div class="project-badges">
                    <span class="badge badge-<?php echo $project['category']; ?> mx-1"><?php echo ucfirst($project['category']); ?></span>
                    <?php if (!empty($project['tools'])): ?>
                      <?php foreach (array_slice($project['tools'], 0, 5) as $tool): ?>
                        <span class="badge badge-<?php echo strtolower(str_replace([' ', '.', '+'], '', $tool)); ?> mx-1"><?php echo htmlspecialchars($tool); ?></span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($project['year'] || $project['completion_date']): ?>
                      <span class="badge badge-date mx-1">
                        <?php 
                        // Use year field first, fallback to completion_date year
                        echo $project['year'] ? $project['year'] : date('Y', strtotime($project['completion_date'])); 
                        ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Project Overview Cards -->
          <div class="row d-flex align-items-stretch mb-5">
            
            <!-- Project Description Card -->
            <div class="col-lg-8 mb-4" data-aos="fade-right" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-primary">
                    <i class="lnr lnr-laptop text-white"></i>
                  </div>
                  <h4 class="card-title">Project Beschrijving</h4>
                </div>
                <div class="card-body p-4">
                  <p class="card-text mb-4">
                    <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                  </p>
                  
                  <!-- Project Stats -->
                  <div class="stats-row row text-center mb-4">
                    <div class="col-3">
                      <div class="stat-item">
                        <div class="stat-number"><?php echo $project['status'] === 'completed' ? '100%' : '80%'; ?></div>
                        <div class="stat-label">Voltooid</div>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="stat-item">
                        <div class="stat-number"><?php echo count($project['tools']); ?>+</div>
                        <div class="stat-label">Tools</div>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="stat-item">
                        <div class="stat-number"><?php echo count($project['features']); ?>+</div>
                        <div class="stat-label">Features</div>
                      </div>
                    </div>
                    <div class="col-3">
                      <div class="stat-item">
                        <div class="stat-number"><?php echo $project['development_weeks'] ? $project['development_weeks'] . ' weken' : ($project['project_duration'] ?: 'N/A'); ?></div>
                        <div class="stat-label">Duur</div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Key Features List -->
                  <div class="key-features">
                    <h6 class="text-muted mb-3">
                      <i class="lnr lnr-star text-primary"></i>
                      Belangrijkste Kenmerken
                    </h6>
                    <div class="feature-items">
                      <?php if (!empty($project['features'])): ?>
                        <?php foreach (array_slice($project['features'], 0, 6) as $feature): ?>
                          <div class="feature-item">
                            <i class="lnr lnr-checkmark-circle text-success"></i>
                            <span><?php echo htmlspecialchars($feature); ?></span>
                          </div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="feature-item">
                          <i class="lnr lnr-checkmark-circle text-success"></i>
                          <span>Professioneel ontwerp en uitvoering</span>
                        </div>
                        <div class="feature-item">
                          <i class="lnr lnr-checkmark-circle text-success"></i>
                          <span>Moderne technologieën en best practices</span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
            
            <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
            <!-- Technical Info Card -->
            <div class="col-lg-4 mb-4" data-aos="fade-left" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-success">
                    <i class="lnr lnr-cog text-white"></i>
                  </div>
                  <h4 class="card-title">Technische Informatie</h4>
                </div>
                <div class="card-body p-4">
                  
                  <!-- Tech Stack -->
                  <div class="tech-stack mb-4">
                    <?php if (!empty($project['tools'])): ?>
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="lnr lnr-code text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">Technologieën & Frameworks</h6>
                        <div class="tech-badges">
                          <?php foreach (array_slice($project['tools'], 0, 4) as $tool): ?>
                          <span class="badge badge-primary me-1 mb-1"><?php echo htmlspecialchars(trim($tool)); ?></span>
                          <?php endforeach; ?>
                        </div>
                        <span class="timeline-date badge badge-info">Tech Stack</span>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['github_url'])): ?>
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="fab fa-github text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">Source Code</h6>
                        <p class="tech-desc">Bekijk de volledige broncode op GitHub</p>
                        <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" class="timeline-date badge badge-dark">
                          <i class="fab fa-github"></i> GitHub Repository
                        </a>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['api_docs_url'])): ?>
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="450">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="lnr lnr-book text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">API Documentatie</h6>
                        <p class="tech-desc">Technische documentatie en API referentie</p>
                        <a href="<?php echo htmlspecialchars($project['api_docs_url']); ?>" target="_blank" class="timeline-date badge badge-warning">
                          <i class="lnr lnr-book"></i> Documentatie
                        </a>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['challenges'])): ?>
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="lnr lnr-warning text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">Technische Uitdagingen</h6>
                        <p class="tech-desc"><?php echo nl2br(htmlspecialchars($project['challenges'])); ?></p>
                        <span class="timeline-date badge badge-danger">Opgelost</span>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Default tech info if no admin data -->
                    <?php if (empty($project['tools']) && empty($project['github_url']) && empty($project['challenges'])): ?>
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="lnr lnr-code text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">Frontend Technologies</h6>
                        <p class="tech-desc">HTML5, CSS3, JavaScript ES6+</p>
                        <span class="timeline-date badge badge-primary">Modern Standards</span>
                      </div>
                    </div>
                    
                    <div class="tech-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="tech-marker">
                        <?php if (!empty($timelinePhases)): ?>
                        <div class="timeline-marker completed">
                          <i class="lnr lnr-database text-white"></i>
                        </div>
                        <?php endif; ?>
                      </div>
                      <div class="tech-content">
                        <h6 class="tech-title">Backend & Database</h6>
                        <p class="tech-desc">PHP 8.0, MySQL Database</p>
                        <span class="timeline-date badge badge-success">Server-side</span>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Project Actions -->
                  <div class="project-actions mt-4" data-aos="fade-up" data-aos-delay="300">
                    <?php if (!empty($project['live_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['live_url']); ?>" target="_blank" class="btn btn-primary btn-block mb-2">
                      <i class="lnr lnr-eye me-2"></i>
                      Bekijk Live Website
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demo_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" target="_blank" class="btn btn-outline-primary btn-block mb-2">
                      <i class="lnr lnr-rocket me-2"></i>
                      Live Demo
                    </a>
                    <?php endif; ?>
                    
                    <a href="index.php#portfolio" class="btn btn-outline-secondary btn-block">
                      <i class="lnr lnr-arrow-left me-2"></i>
                      Terug naar Portfolio
                    </a>
                  </div>
                  
                </div>
              </div>
            </div>
            <?php else: ?>
            <!-- Design Project: Creative Highlights -->
            <div class="col-lg-4 mb-4" data-aos="fade-left" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-success">
                    <?php if (!empty($timelinePhases)): ?>
                    <i class="lnr lnr-magic-wand text-white"></i>
                    <?php endif; ?>
                  </div>
                  <h4 class="card-title">Creatieve Highlights</h4>
                </div>
                <div class="card-body p-4">
                  
                  <div class="design-highlights">
                    <?php if (!empty($project['design_concept'])): ?>
                    <div class="highlight-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-magic-wand text-primary"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Design Concept</h6>
                        <p class="highlight-desc"><?php echo htmlspecialchars($project['design_concept']); ?></p>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['color_palette'])): ?>
                    <div class="highlight-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-drop text-success"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Kleurenpalet</h6>
                        <div class="color-palette-display">
                          <?php 
                          $colors = explode(',', $project['color_palette']);
                          foreach ($colors as $color): 
                            $color = trim($color);
                            if (!empty($color)):
                          ?>
                          <span class="color-swatch" style="background-color: <?php echo htmlspecialchars($color); ?>;" title="<?php echo htmlspecialchars($color); ?>"></span>
                          <?php 
                            endif;
                          endforeach; 
                          ?>
                        </div>
                        <p class="highlight-desc"><?php echo htmlspecialchars($project['color_palette']); ?></p>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['typography'])): ?>
                    <div class="highlight-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-text-format text-warning"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Typografie</h6>
                        <p class="highlight-desc"><?php echo htmlspecialchars($project['typography']); ?></p>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['design_style'])): ?>
                    <div class="highlight-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-star text-info"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Design Stijl</h6>
                        <p class="highlight-desc"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($project['design_style']))); ?></p>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Default highlights if no admin data -->
                    <?php if (empty($project['design_concept']) && empty($project['color_palette']) && empty($project['typography']) && empty($project['design_style'])): ?>
                    <div class="highlight-item mb-3">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-magic-wand text-primary"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Creatief Concept</h6>
                        <p class="highlight-desc">Unieke visuele benadering met aandacht voor detail en artistieke expressie</p>
                      </div>
                    </div>
                    
                    <div class="highlight-item">
                      <div class="highlight-icon">
                        <?php if (!empty($timelinePhases)): ?>
                        <i class="lnr lnr-star text-info"></i>
                        <?php endif; ?>
                      </div>
                      <div class="highlight-content">
                        <h6 class="highlight-title">Professionele Uitvoering</h6>
                        <p class="highlight-desc">Hoogwaardige afwerking en aandacht voor alle creatieve aspecten</p>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                  
                </div>
              </div>
            </div>
            
            <!-- Design Project: Gallery Images -->
            <?php if (($project['category'] == 'design' || $project['category'] == 'vintage') && !empty($project['gallery_images'])): ?>
            <div class="col-lg-4 mb-4" data-aos="fade-left" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-warning">
                    <i class="lnr lnr-picture text-white"></i>
                  </div>
                  <h4 class="card-title">Project Galerij</h4>
                </div>
                <div class="card-body p-4">
                  
                  <!-- Gallery Grid -->
                  <div class="gallery-grid">
                    <?php 
                    $galleryImages = explode(',', $project['gallery_images']);
                    $imageCount = count($galleryImages);
                    foreach (array_slice($galleryImages, 0, 6) as $index => $image): 
                      $trimmedImage = trim($image);
                      if (!empty($trimmedImage)):
                    ?>
                    <div class="gallery-item" data-aos="zoom-in" data-aos-delay="<?php echo 300 + ($index * 100); ?>">
                      <div class="gallery-image-wrapper">
                        <img src="<?php echo htmlspecialchars($trimmedImage); ?>" alt="Project afbeelding <?php echo $index + 1; ?>" class="gallery-image">
                        <div class="gallery-overlay">
                          <div class="overlay-content">
                            <a href="<?php echo htmlspecialchars($trimmedImage); ?>" class="gallery-zoom" data-lightbox="project-gallery">
                              <i class="lnr lnr-eye"></i>
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php 
                      endif;
                    endforeach; 
                    ?>
                    
                    <?php if ($imageCount > 6): ?>
                    <div class="gallery-more" data-aos="zoom-in" data-aos-delay="300">
                      <div class="gallery-more-content">
                        <i class="lnr lnr-plus-circle text-primary"></i>
                        <span>+<?php echo $imageCount - 6; ?> meer</span>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Gallery Stats -->
                  <div class="gallery-stats mt-4 pt-3 border-top">
                    <div class="row text-center">
                      <div class="col-6">
                        <div class="stat-number text-primary"><?php echo $imageCount; ?></div>
                        <div class="stat-label small text-muted">Afbeeldingen</div>
                      </div>
                      <div class="col-6">
                        <div class="stat-number text-success">HD</div>
                        <div class="stat-label small text-muted">Kwaliteit</div>
                      </div>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
            <?php endif; ?>
            
          </div>
          <!-- End Project Overview Cards -->

        </div>
      </div>
    </section>

    <!-- Project Details Section -->
    <section id="project-details" class="bg-light">
      <div class="container">
        <div class="section-content">
          
          <!-- Section Header -->
          <div class="row mb-5" data-aos="fade-up">
            <div class="col-12 text-center">
              <h2 class="section-title mb-3">
                <span class="text-primary">Project</span> <b>Details</b>
              </h2>
              <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
              <p class="text-muted lead mb-4">Technische specificaties en ontwikkelingsproces van dit project</p>
              <?php else: ?>
              <p class="text-muted lead mb-4">Creatief proces en ontwikkelingsdetails van dit project</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Technical Specifications Cards -->
          <?php 
          // Determine layout based on project type
          $isDevelopment = in_array($project['category'], ['development', 'web', 'mobile']);
          $cardClass = $isDevelopment ? 'col-lg-6' : 'col-lg-8 mx-auto';
          ?>
          <div class="row d-flex align-items-stretch mb-5 justify-content-center">
            
            <!-- Technical Stack Card -->
            <div class="<?php echo $cardClass; ?> mb-4" data-aos="fade-right" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-primary">
                    <i class="lnr lnr-layers text-white"></i>
                  </div>
                  <?php if ($isDevelopment): ?>
                  <h4 class="card-title">Technische Stack</h4>
                  <?php else: ?>
                  <h4 class="card-title">Tools & Software</h4>
                  <?php endif; ?>
                </div>
                <div class="card-body p-4">
                  <?php if ($isDevelopment): ?>
                  <p class="card-text mb-4">
                    Dit project gebruikt moderne web technologieën en best practices voor optimale prestaties en gebruikerservaring.
                  </p>
                  <?php else: ?>
                  <p class="card-text mb-4">
                    Voor dit creatieve project zijn professionele design tools en software gebruikt voor het beste resultaat.
                  </p>
                  <?php endif; ?>
                  
                  <!-- Tech Categories -->
                  <div class="tech-categories">
                    <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                    <div class="tech-category mb-4">
                      <h6 class="tech-category-title">
                        <i class="lnr lnr-code text-primary"></i>
                        Frontend Development
                      </h6>
                      <div class="tech-tags">
                        <span class="skill-tag">HTML5</span>
                        <span class="skill-tag">CSS3</span>
                        <span class="skill-tag">JavaScript ES6+</span>
                        <span class="skill-tag">Bootstrap 5</span>
                        <span class="skill-tag">AOS Animations</span>
                      </div>
                    </div>
                    
                    <div class="tech-category mb-4">
                      <h6 class="tech-category-title">
                        <i class="lnr lnr-database text-success"></i>
                        Backend & Database
                      </h6>
                      <div class="tech-tags">
                        <span class="skill-tag">PHP 8.0</span>
                        <span class="skill-tag">MySQL</span>
                        <span class="skill-tag">PDO</span>
                        <span class="skill-tag">OOP Principles</span>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="tech-category">
                      <h6 class="tech-category-title">
                        <i class="lnr lnr-magic-wand text-info"></i>
                        <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                        Design & UX
                        <?php else: ?>
                        Design Tools & Techniques
                        <?php endif; ?>
                      </h6>
                      <div class="tech-tags">
                        <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                        <span class="skill-tag">Responsive Design</span>
                        <span class="skill-tag">Glassmorphism</span>
                        <span class="skill-tag">Modern UI</span>
                        <span class="skill-tag">Accessibility</span>
                        <?php else: ?>
                        <span class="skill-tag">Adobe Creative Suite</span>
                        <span class="skill-tag">Figma</span>
                        <span class="skill-tag">Color Theory</span>
                        <span class="skill-tag">Typography</span>
                        <span class="skill-tag">Brand Identity</span>
                        <span class="skill-tag">Visual Composition</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
            <!-- Features & Functionality Card -->
            <div class="col-lg-6 mb-4" data-aos="fade-left" data-aos-delay="300">
              <div class="card visual-card h-100">
                <div class="card-header-visual">
                  <div class="icon-wrapper bg-gradient-success">
                    <i class="lnr lnr-star text-white"></i>
                  </div>
                  <h4 class="card-title">Features & Functionaliteit</h4>
                </div>
                <div class="card-body p-4">
                  
                  <!-- Feature List -->
                  <div class="feature-list">
                    <div class="feature-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="feature-icon">
                        <i class="lnr lnr-screen text-primary"></i>
                      </div>
                      <div class="feature-content">
                        <h6 class="feature-title">Responsive Design</h6>
                        <p class="feature-desc">Optimaal weergegeven op alle apparaten en schermformaten</p>
                      </div>
                    </div>
                    
                    <div class="feature-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="feature-icon">
                        <i class="lnr lnr-layers text-success"></i>
                      </div>
                      <div class="feature-content">
                        <h6 class="feature-title">Portfolio Filtering</h6>
                        <p class="feature-desc">Dynamische filtering van projecten op categorie en technologie</p>
                      </div>
                    </div>
                    
                    <div class="feature-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="feature-icon">
                        <i class="lnr lnr-envelope text-info"></i>
                      </div>
                      <div class="feature-content">
                        <h6 class="feature-title">Contact Systeem</h6>
                        <p class="feature-desc">Werkend contactformulier met email validatie en spam bescherming</p>
                      </div>
                    </div>
                    
                    <div class="feature-item mb-3" data-aos="fade-up" data-aos-delay="300">
                      <div class="feature-icon">
                        <i class="lnr lnr-rocket text-warning"></i>
                      </div>
                      <div class="feature-content">
                        <h6 class="feature-title">Performance</h6>
                        <p class="feature-desc">Geoptimaliseerd voor snelheid en zoekmachine indexering</p>
                      </div>
                    </div>
                    
                    <div class="feature-item" data-aos="fade-up" data-aos-delay="300">
                      <div class="feature-icon">
                        <i class="lnr lnr-cog text-purple"></i>
                      </div>
                      <div class="feature-content">
                        <h6 class="feature-title">Admin Panel</h6>
                        <p class="feature-desc">Backend systeem voor content management en portfolio updates</p>
                      </div>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Development Process Section -->
          <div class="row mt-5" data-aos="fade-up" data-aos-delay="300">
            <div class="col-12">
              <div class="card visual-card">
                <div class="card-header-visual text-center">
                  <div class="icon-wrapper bg-gradient-primary mx-auto">
                    <i class="lnr lnr-chart-bars text-white"></i>
                  </div>
                  <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                  <h4 class="card-title">Ontwikkelingsproces</h4>
                  <?php else: ?>
                  <h4 class="card-title">Creatief Proces</h4>
                  <?php endif; ?>
                </div>
                <div class="card-body p-5">
                  
                  <!-- Progress Timeline -->
                  <div class="row">
                    <div class="col-lg-8">
                      <div class="development-timeline">
                        
                        <!-- Dynamic Timeline Phases from Admin Panel -->
                          <?php foreach ($timelinePhases as $index => $phase): ?>
                            <?php 
                            // Parse JSON fields
                            $tasks = !empty($phase['tasks']) ? json_decode($phase['tasks'], true) : [];
                            $deliverables = !empty($phase['deliverables']) ? json_decode($phase['deliverables'], true) : [];
                            
                            // Status badge classes and text
                            $statusClasses = [
                                'planned' => 'badge-secondary',
                                'in_progress' => 'badge-warning',
                                'completed' => 'badge-success',
                                'on_hold' => 'badge-danger'
                            ];
                            
                            $statusTexts = [
                                'planned' => 'Gepland',
                                'in_progress' => 'In uitvoering',
                                'completed' => 'Voltooid',
                                'on_hold' => 'On hold'
                            ];
                            
                            // Ensure we have a valid status - check for empty or null values
                            $phaseStatus = (!empty($phase['phase_status']) && trim($phase['phase_status']) !== '') ? $phase['phase_status'] : 'completed';
                            $statusClass = isset($statusClasses[$phaseStatus]) ? $statusClasses[$phaseStatus] : 'badge-success';
                            $statusText = isset($statusTexts[$phaseStatus]) ? $statusTexts[$phaseStatus] : 'Voltooid';
                            
                            // Debug output for troubleshooting
                            $rawStatus = isset($phase['phase_status']) ? $phase['phase_status'] : 'NULL';
                            error_log("Phase: " . $phase['phase_name'] . " - Raw Status: '" . $rawStatus . "' - Final Status: '" . $phaseStatus . "' - Badge Text: '" . $statusText . "'");
                            
                            // Timeline marker classes
                            $markerClass = $phaseStatus === 'completed' ? 'completed' : ($phaseStatus === 'in_progress' ? 'in-progress' : 'pending');
                            
            // Icon selection based on phase type or default
            $icons = [
                'planning' => 'lnr lnr-calendar-full',
                'design' => 'lnr lnr-magic-wand',
                'development' => 'lnr lnr-laptop',
                'testing' => 'lnr lnr-bug',
                'deployment' => 'lnr lnr-rocket',
                'challenge' => 'lnr lnr-question-circle',
                'approach' => 'lnr lnr-cog',
                'solution' => 'lnr lnr-checkmark-circle'
            ];                            // Font Awesome fallback icons
                            $fallbackIcons = [
                                'planning' => 'fas fa-calendar-alt',
                                'design' => 'fas fa-magic',
                                'development' => 'fas fa-laptop-code',
                                'testing' => 'fas fa-bug',
                                'deployment' => 'fas fa-rocket',
                                'challenge' => 'fas fa-question-circle',
                                'approach' => 'fas fa-cogs',
                                'solution' => 'fas fa-check-circle'
                            ];
                            
            $phaseIcon = 'lnr lnr-star'; // Default Linear icon
            $phaseIconFallback = 'fas fa-star'; // Default Font Awesome icon                            // First, check if we have a phase_type field
                            if (!empty($phase['phase_type']) && isset($icons[$phase['phase_type']])) {
                                $phaseIcon = $icons[$phase['phase_type']];
                                $phaseIconFallback = $fallbackIcons[$phase['phase_type']];
                            } else {
                                // Fall back to keyword search in phase name for backward compatibility
                                foreach ($icons as $key => $icon) {
                                    if (stripos($phase['phase_name'], $key) !== false) {
                                        $phaseIcon = $icon;
                                        $phaseIconFallback = $fallbackIcons[$key];
                                        break;
                                    }
                                }
                            }
                            ?>
                            
                            <div class="timeline-item <?php echo $markerClass; ?> mb-4 expandable-timeline" data-aos="fade-up" data-aos-delay="<?php echo 300 + ($index * 100); ?>" data-phase="phase-<?php echo $phase['id']; ?>">
                              <div class="timeline-marker <?php echo $markerClass; ?>">
                                <i class="<?php echo $phaseIcon; ?> text-white timeline-icon" 
                                   data-fallback="<?php echo $phaseIconFallback; ?>" 
                                   style="font-size: 16px;"></i>
                              </div>
                              <div class="timeline-content">
                                <div class="timeline-header" style="cursor: pointer;">
                                  <h6 class="timeline-title"><?php echo htmlspecialchars($phase['phase_name']); ?> <i class="lnr lnr-chevron-down expand-icon"></i></h6>
                                  <?php if (!empty($phase['description'])): ?>
                                  <p class="timeline-desc"><?php echo htmlspecialchars($phase['description']); ?></p>
                                  <?php endif; ?>
                                  <span class="timeline-date badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                    <!-- Debug: <?php echo "Status: " . $phaseStatus . ", Class: " . $statusClass . ", Text: " . $statusText; ?> -->
                                  </span>
                                </div>
                                <div class="timeline-details" style="display: none;">
                                  <div class="detail-content mt-3 p-3 bg-light rounded">
                                    
                                    <!-- Phase Description -->
                                    <?php if (!empty($phase['description'])): ?>
                                    <h6 class="text-primary mb-3"><i class="<?php echo $phaseIcon; ?>"></i> <?php echo htmlspecialchars($phase['phase_name']); ?></h6>
                                    <p class="text-dark"><?php echo nl2br(htmlspecialchars($phase['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                      <!-- Phase Planning Info -->
                                      <?php if (!empty($phase['start_date']) || !empty($phase['end_date'])): ?>
                                      <div class="col-md-6">
                                        <div class="phase-planning-info">
                                          <h6 class="text-success mb-2"><i class="lnr lnr-calendar-full"></i> Planning:</h6>
                                          <?php if (!empty($phase['start_date'])): ?>
                                          <p class="mb-1"><strong>Start:</strong> <?php echo date('d M Y', strtotime($phase['start_date'])); ?></p>
                                          <?php endif; ?>
                                          <?php if (!empty($phase['end_date'])): ?>
                                          <p class="mb-1"><strong>Eind:</strong> <?php echo date('d M Y', strtotime($phase['end_date'])); ?></p>
                                          <?php endif; ?>
                                        </div>
                                      </div>
                                      <?php endif; ?>
                                      
                                      <!-- Tasks List -->
                                      <?php if (!empty($tasks)): ?>
                                      <div class="col-md-6">
                                        <h6 class="text-success mb-2"><i class="lnr lnr-checkmark-circle"></i> Taken:</h6>
                                        <ul class="feature-list mb-0">
                                          <?php foreach ($tasks as $task): ?>
                                          <li><i class="lnr lnr-checkmark-circle text-success me-1"></i><?php echo htmlspecialchars($task); ?></li>
                                          <?php endforeach; ?>
                                        </ul>
                                      </div>
                                      <?php endif; ?>
                                    </div>
                                    
                                    <!-- Deliverables -->
                                    <?php if (!empty($deliverables)): ?>
                                    <div class="mt-3">
                                      <h6 class="text-info mb-2"><i class="lnr lnr-gift"></i> Deliverables:</h6>
                                      <ul class="feature-list mb-0">
                                        <?php foreach ($deliverables as $deliverable): ?>
                                        <li><i class="lnr lnr-star text-info me-1"></i><?php echo htmlspecialchars($deliverable); ?></li>
                                        <?php endforeach; ?>
                                      </ul>
                                    </div>
                                    <?php endif; ?>
                                    
                                  </div>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        
                        <!-- Development Process (Content-based detection) -->
                        <?php 
                        // Show development phase primarily for development categories
                        // For design/other categories, only show if there's substantial technical content
                        $hasDevelopmentContent = ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile') ||
                                               (($project['category'] == 'design' || $project['category'] == 'vintage' || $project['category'] == 'other') &&
                                                (!empty($project['github_url']) && 
                                                 ($project['lines_of_code'] > 1000 || !empty($project['challenges']))));
                        ?>
                        <?php if ($hasDevelopmentContent): ?>
                        <div class="timeline-item completed mb-4 expandable-timeline" data-aos="fade-up" data-aos-delay="300" data-phase="development">
                          <div class="timeline-marker completed">
                            <i class="lnr lnr-laptop text-white"></i>
                          </div>
                          <div class="timeline-content">
                            <div class="timeline-header" style="cursor: pointer;">
                              <h6 class="timeline-title">Development & Implementation <i class="lnr lnr-chevron-down expand-icon"></i></h6>
                              <p class="timeline-desc">Code ontwikkeling, testing en implementatie</p>
                              <span class="timeline-date badge badge-primary">Fase 3A</span>
                            </div>
                            <div class="timeline-details" style="display: none;">
                              <div class="detail-content mt-3 p-3 bg-light rounded">
                                <h6 class="text-primary mb-3"><i class="lnr lnr-laptop"></i> Ontwikkelingsproces</h6>
                                <div class="row">
                                  <?php if (!empty($project['features'])): ?>
                                  <div class="col-md-6">
                                    <h6 class="text-success mb-2">Technische Features:</h6>
                                    <ul class="feature-list">
                                      <?php foreach (array_slice($project['features'], 0, 5) as $feature): ?>
                                      <li><i class="lnr lnr-checkmark-circle text-success me-1"></i><?php echo htmlspecialchars(trim($feature)); ?></li>
                                      <?php endforeach; ?>
                                    </ul>
                                  </div>
                                  <?php endif; ?>
                                  
                                  <div class="col-md-6">
                                    <h6 class="text-success mb-2">Project Statistieken:</h6>
                                    <div class="stats-grid">
                                      <?php if ($project['development_weeks']): ?>
                                      <div class="stat-item mb-2">
                                        <i class="lnr lnr-calendar-full text-info me-1"></i>
                                        <strong><?php echo $project['development_weeks']; ?> weken</strong> ontwikkeling
                                      </div>
                                      <?php endif; ?>
                                      <?php if ($project['lines_of_code']): ?>
                                      <div class="stat-item mb-2">
                                        <i class="lnr lnr-code text-info me-1"></i>
                                        <strong><?php echo number_format($project['lines_of_code']); ?></strong> regels code
                                      </div>
                                      <?php endif; ?>
                                      <?php if ($project['performance_score']): ?>
                                      <div class="stat-item mb-2">
                                        <i class="lnr lnr-rocket text-info me-1"></i>
                                        <strong><?php echo $project['performance_score']; ?>/100</strong> performance score
                                      </div>
                                      <?php endif; ?>
                                    </div>
                                  </div>
                                </div>
                                
                                <?php if (!empty($project['challenges'])): ?>
                                <div class="mt-3">
                                  <h6 class="text-danger mb-2"><i class="lnr lnr-warning"></i> Technische Uitdagingen:</h6>
                                  <p class="text-dark"><?php echo nl2br(htmlspecialchars($project['challenges'])); ?></p>
                                </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Creative Process: Challenge, Approach, Solution -->
                        <?php 
                        // Check if we have timeline phases with creative process types
                        $timelineCreativePhases = [];
                        if ($timelinePhases) {
                            foreach ($timelinePhases as $phase) {
                                if (in_array($phase['phase_type'], ['challenge', 'approach', 'solution'])) {
                                    $timelineCreativePhases[] = $phase;
                                }
                            }
                        }
                        
                        // If we have timeline phases with creative types, use those; otherwise use creative fields
                        if (!empty($timelineCreativePhases)) {
                            // Use timeline phases for creative process
                            $creativePhases = [];
                            foreach ($timelineCreativePhases as $phase) {
                                $badgeClass = $phase['phase_type'] === 'challenge' ? 'badge-warning' : 
                                            ($phase['phase_type'] === 'approach' ? 'badge-info' : 'badge-success');
                                $phaseIcon = $phase['phase_type'] === 'challenge' ? 'lnr-question-circle' : 
                                           ($phase['phase_type'] === 'approach' ? 'lnr-cog' : 'lnr-checkmark-circle');
                                
                                $creativePhases[] = [
                                    'type' => $phase['phase_type'],
                                    'title' => $phase['phase_name'],
                                    'icon' => $phaseIcon,
                                    'content' => $phase['phase_description'],
                                    'details' => $phase['phase_details'],
                                    'description' => $phase['phase_description'],
                                    'badge' => ucfirst($phase['phase_type']),
                                    'badge_class' => $badgeClass,
                                    'tasks' => $phase['tasks'] ? json_decode($phase['tasks'], true) : [],
                                    'deliverables' => $phase['deliverables'] ? json_decode($phase['deliverables'], true) : []
                                ];
                            }
                            $hasCreativeProcess = !empty($creativePhases);
                        } else {
                            // Use creative fields from project
                            $hasCreativeProcess = !empty($project['creative_challenge']) || !empty($project['creative_approach']) || !empty($project['creative_solution']);
                            $creativePhases = [];
                            
                            // Build creative phases array from project fields
                            if (!empty($project['creative_challenge'])) {
                                $creativePhases[] = [
                                    'type' => 'challenge',
                                    'title' => 'Uitdaging & Probleemdefinitie',
                                    'icon' => 'lnr-question-circle',
                                    'content' => $project['creative_challenge'],
                                    'details' => null,
                                    'description' => 'Identificatie en analyse van de kernuitdaging',
                                    'badge' => 'Challenge',
                                    'badge_class' => 'badge-warning',
                                    'tasks' => [],
                                    'deliverables' => []
                                ];
                            }
                            
                            if (!empty($project['creative_approach'])) {
                                $creativePhases[] = [
                                    'type' => 'approach',
                                    'title' => 'Aanpak & Methodologie',
                                    'icon' => 'lnr-cog',
                                    'content' => $project['creative_approach'],
                                    'details' => null,
                                    'description' => 'Strategische aanpak en gekozen methodologie',
                                    'badge' => 'Approach',
                                    'badge_class' => 'badge-info',
                                    'tasks' => [],
                                    'deliverables' => []
                                ];
                            }
                            
                            if (!empty($project['creative_solution'])) {
                                $creativePhases[] = [
                                    'type' => 'solution',
                                    'title' => 'Oplossing & Resultaat',
                                    'icon' => 'lnr-checkmark-circle',
                                    'content' => $project['creative_solution'],
                                    'details' => null,
                                    'description' => 'Eindoplossing en behaalde resultaten',
                                    'badge' => 'Solution',
                                    'badge_class' => 'badge-success',
                                    'tasks' => [],
                                    'deliverables' => []
                                ];
                            }
                        }
                        
                        // Calculate base delay
                        $creativeBaseDelay = 600;
                        if ($hasDevelopmentContent && $hasDesignContent) $creativeBaseDelay = 700;
                        ?>
                        
                        <?php if ($hasCreativeProcess): ?>
                        <?php foreach ($creativePhases as $index => $phase): ?>
                        <div class="timeline-item completed mb-4 expandable-timeline" data-aos="fade-up" data-aos-delay="<?php echo $creativeBaseDelay + ($index * 100); ?>" data-phase="<?php echo $phase['type']; ?>">
                          <div class="timeline-marker completed">
                            <i class="<?php echo $phase['icon']; ?> text-white"></i>
                          </div>
                          <div class="timeline-content">
                            <div class="timeline-header" style="cursor: pointer;">
                              <h6 class="timeline-title"><?php echo $phase['title']; ?> <i class="lnr lnr-chevron-down expand-icon"></i></h6>
                              <p class="timeline-desc"><?php echo $phase['description']; ?></p>
                              <span class="timeline-date badge <?php echo $phase['badge_class']; ?>"><?php echo $phase['badge']; ?></span>
                            </div>
                            <div class="timeline-details" style="display: none;">
                              <div class="detail-content mt-3 p-3 bg-light rounded">
                                <h6 class="text-primary mb-3"><i class="<?php echo $phase['icon']; ?>"></i> <?php echo $phase['title']; ?></h6>
                                <p class="text-dark"><?php echo nl2br(htmlspecialchars($phase['content'])); ?></p>
                                
                                <?php if (!empty($phase['details'])): ?>
                                <div class="mt-3">
                                  <h6 class="text-info mb-2"><i class="lnr lnr-text-align-left"></i> Gedetailleerde Beschrijving:</h6>
                                  <p class="text-dark"><?php echo nl2br(htmlspecialchars($phase['details'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($phase['tasks'])): ?>
                                <div class="mt-3">
                                  <h6 class="text-success mb-2"><i class="lnr lnr-list"></i> Taken:</h6>
                                  <ul class="mb-0">
                                    <?php foreach ($phase['tasks'] as $task): ?>
                                    <li><?php echo htmlspecialchars($task); ?></li>
                                    <?php endforeach; ?>
                                  </ul>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($phase['deliverables'])): ?>
                                <div class="mt-3">
                                  <h6 class="text-warning mb-2"><i class="lnr lnr-gift"></i> Opgeleverd:</h6>
                                  <ul class="mb-0">
                                    <?php foreach ($phase['deliverables'] as $deliverable): ?>
                                    <li><?php echo htmlspecialchars($deliverable); ?></li>
                                    <?php endforeach; ?>
                                  </ul>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($phase['type'] === 'solution' && !empty($project['lessons_learned'])): ?>
                                <div class="mt-3">
                                  <h6 class="text-success mb-2"><i class="lnr lnr-graduation-hat"></i> Geleerde Lessen:</h6>
                                  <p class="text-muted"><?php echo htmlspecialchars($project['lessons_learned']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($phase['type'] === 'solution'): ?>
                                <div class="mt-3">
                                  <h6 class="text-success mb-2"><i class="lnr lnr-calendar-full"></i> Project Status:</h6>
                                  <div class="project-status">
                                    <span class="badge badge-<?php 
                                      echo $project['status'] == 'completed' ? 'success' : 
                                          ($project['status'] == 'in_progress' ? 'warning' : 
                                          ($project['status'] == 'planned' ? 'info' : 'secondary')); 
                                    ?>">
                                      <?php 
                                        echo $project['status'] == 'completed' ? 'Voltooid' : 
                                            ($project['status'] == 'in_progress' ? 'In uitvoering' : 
                                            ($project['status'] == 'planned' ? 'Gepland' : 'Gearchiveerd')); 
                                      ?>
                                    </span>
                                    <?php if ($project['completion_date']): ?>
                                    <span class="text-muted ms-2">
                                      <i class="lnr lnr-calendar-full"></i> 
                                      <?php echo date('F Y', strtotime($project['completion_date'])); ?>
                                    </span>
                                    <?php endif; ?>
                                  </div>
                                </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <!-- Progress Statistics -->
                    <div class="col-lg-4" data-aos="fade-left" data-aos-delay="300">
                      <div class="progress-stats">
                        <h6 class="text-muted mb-4">Project Statistieken</h6>
                        
                        <div class="stat-item mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-label">Totale Voortgang</span>
                            <span class="stat-value">100%</span>
                          </div>
                          <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </div>
                        
                        <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                        <div class="stat-item mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-label">Code Kwaliteit</span>
                            <span class="stat-value"><?php echo $project['code_quality'] ?: 'A+'; ?></span>
                          </div>
                          <div class="progress">
                            <?php 
                            $qualityScore = $project['code_quality'] ? (
                                $project['code_quality'] === 'A+' ? 95 : (
                                    $project['code_quality'] === 'A' ? 90 : (
                                        $project['code_quality'] === 'B+' ? 85 : (
                                            $project['code_quality'] === 'B' ? 80 : 75
                                        )
                                    )
                                )
                            ) : 95;
                            ?>
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $qualityScore; ?>%" aria-valuenow="<?php echo $qualityScore; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['category'] == 'development' || $project['category'] == 'web' || $project['category'] == 'mobile'): ?>
                        <div class="stat-item mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-label">Performance Score</span>
                            <span class="stat-value"><?php echo $project['performance_score'] ?: '92'; ?>/100</span>
                          </div>
                          <div class="progress">
                            <?php $perfScore = $project['performance_score'] ?: 92; ?>
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $perfScore; ?>%" aria-valuenow="<?php echo $perfScore; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </div>
                        
                        <div class="project-metrics mt-4">
                          <div class="metric-item">
                            <i class="lnr lnr-clock text-primary"></i>
                            <span>Ontwikkelingstijd: <?php echo $project['development_weeks'] ? $project['development_weeks'] . ' weken' : '5 weken'; ?></span>
                          </div>
                          <div class="metric-item">
                            <i class="lnr lnr-code text-success"></i>
                            <span>Lines of Code: <?php echo $project['lines_of_code'] ? number_format($project['lines_of_code']) . '+' : '2500+'; ?></span>
                          </div>
                          <div class="metric-item">
                            <i class="lnr lnr-layers text-info"></i>
                            <span>Componenten: <?php echo $project['components_count'] ? $project['components_count'] . '+' : '25+'; ?></span>
                          </div>
                        </div>
                        <?php else: ?>
                        <div class="project-metrics mt-4">
                          <div class="metric-item">
                            <i class="lnr lnr-clock text-primary"></i>
                            <span>Ontwikkelingstijd: <?php echo $project['development_weeks'] ? $project['development_weeks'] . ' weken' : '5 weken'; ?></span>
                          </div>
                          <?php if ($project['category'] === 'design' || $project['category'] === 'vintage'): ?>
                          <div class="metric-item">
                            <i class="lnr lnr-magic-wand text-success"></i>
                            <span>Design Iteraties: <?php echo $project['components_count'] ? $project['components_count'] : '15'; ?>+</span>
                          </div>
                          <div class="metric-item">
                            <i class="lnr lnr-picture text-info"></i>
                            <span>Creatieve Varianten: <?php echo $project['lines_of_code'] ? round($project['lines_of_code'] / 100) : '25'; ?>+</span>
                          </div>
                          <?php endif; ?>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </section>
    <!-- Related Projects Section -->
    <section id="related-projects" class="bg-light">
      <div class="container">
        <div class="section-content">
          
          <!-- Section Header -->
          <div class="row mb-5" data-aos="fade-up">
            <div class="col-12 text-center">
              <h2 class="section-title mb-3">
                <span class="text-primary">Gerelateerde</span> <b>Projecten</b>
              </h2>
              <p class="text-muted lead mb-4">Ontdek andere projecten uit mijn portfolio die mijn vaardigheden demonstreren</p>
            </div>
          </div>

          <!-- Related Projects Grid -->
          <div class="row">
            <?php if (!empty($relatedProjects)): ?>
              <?php foreach ($relatedProjects as $index => $relatedProject): ?>
              <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo 100 + ($index * 100); ?>">
                <div class="portfolio-card visual-card">
                  <div class="portfolio-image-container">
                    <img src="<?php echo htmlspecialchars($relatedProject['image_url'] ?: 'img/app-profile-mockup.png'); ?>" alt="<?php echo htmlspecialchars($relatedProject['title']); ?>" class="portfolio-image">
                    <div class="image-overlay">
                      <div class="overlay-content">
                        <a href="detail.php?id=<?php echo $relatedProject['id']; ?>" class="btn btn-light btn-sm">
                          <i class="lnr lnr-eye"></i> Bekijk Project
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="portfolio-content p-4">
                    <div class="portfolio-badges mb-3">
                      <span class="badge badge-<?php echo $relatedProject['category']; ?>"><?php echo ucfirst($relatedProject['category']); ?></span>
                      <?php 
                      $relatedTools = json_decode($relatedProject['tools'] ?: '[]', true);
                      if (!empty($relatedTools)): 
                        foreach (array_slice($relatedTools, 0, 2) as $tool): ?>
                          <span class="badge badge-<?php echo strtolower(str_replace([' ', '.', '+'], '', $tool)); ?>"><?php echo htmlspecialchars($tool); ?></span>
                        <?php endforeach; 
                      endif; ?>
                      <?php if ($relatedProject['year'] || $relatedProject['completion_date']): ?>
                        <span class="badge badge-date">
                          <?php echo $relatedProject['year'] ? $relatedProject['year'] : date('Y', strtotime($relatedProject['completion_date'])); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <h5 class="portfolio-title mb-2"><?php echo htmlspecialchars($relatedProject['title']); ?></h5>
                    <p class="portfolio-description text-muted mb-3">
                      <?php echo htmlspecialchars($relatedProject['short_description'] ?: substr($relatedProject['description'], 0, 100) . '...'); ?>
                    </p>
                    <div class="portfolio-actions">
                      <a href="detail.php?id=<?php echo $relatedProject['id']; ?>" class="btn btn-primary btn-sm w-100">
                        <i class="lnr lnr-arrow-right"></i> Bekijk Details
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <!-- No related projects found -->
              <div class="col-12 text-center">
                <div class="no-related-projects py-5">
                  <i class="lnr lnr-layers" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                  <h5 class="text-muted">Geen gerelateerde projecten gevonden</h5>
                  <p class="text-muted">Ontdek meer projecten in ons portfolio.</p>
                  <a href="index.php#portfolio" class="btn btn-primary">
                    <i class="lnr lnr-briefcase me-2"></i> Bekijk Portfolio
                  </a>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Back to Portfolio Button -->
          <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="300">
            <a href="index.php#portfolio" class="btn btn-primary btn-lg">
              <i class="lnr lnr-arrow-left me-2"></i> Bekijk Alle Projecten
            </a>
          </div>
          
        </div>
      </div>
    </section>

    <!-- Modern Footer -->
    <footer class="modern-footer">
      <!-- Back to top button -->
      <a href="#home" class="back-to-top" aria-label="Terug naar boven">
        <i class="lnr lnr-chevron-up"></i>
      </a>
      
      <div class="footer-content">
        <div class="container">
          
          <!-- Footer Main -->
          <div class="footer-main py-5">
            <div class="row">
              
              <!-- Brand Section -->
              <div class="col-lg-4 col-md-6 mb-4">
                <div class="footer-brand">
                  <h4 class="brand-title mb-3">
                    <span class="text-primary"><?php echo explode(' ', $brandName)[0]; ?></span> <?php echo implode(' ', array_slice(explode(' ', $brandName), 1)); ?>
                  </h4>
                  <p class="brand-description mb-4">
                    Digital Experience Designer gepassioneerd door het creëren van 
                    moderne weboplossingen en visueel design die impact maken.
                  </p>
                  <div class="brand-stats">
                    <div class="row text-center">
                      <div class="col-4">
                        <div class="stat-item">
                          <div class="stat-number"><?php echo $dynamicStats['total_projects']; ?>+</div>
                          <div class="stat-label">Projecten</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-item">
                          <div class="stat-number"><?php echo $dynamicStats['years_experience']; ?>+</div>
                          <div class="stat-label">Jaar Ervaring</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="stat-item">
                          <div class="stat-number"><?php echo $dynamicStats['passion_percentage']; ?>%</div>
                          <div class="stat-label">Passie</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Quick Links -->
              <div class="col-lg-2 col-md-6 mb-4">
                <div class="footer-links text-center">
                  <h5 class="links-title mb-3">Navigatie</h5>
                  <ul class="links-list">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#who-we-are">Over Mij</a></li>
                    <li><a href="#portfolio">Portfolio</a></li>
                    <li><a href="#contact">Contact</a></li>
                  </ul>
                </div>
              </div>
              
              <!-- Services -->
              <div class="col-lg-6 col-md-6 mb-4">
                <div class="footer-links text-center">
                  <h5 class="links-title mb-3">Diensten</h5>
                  <ul class="links-list">
                    <li><a href="#portfolio">Web Development</a></li>
                    <li><a href="#portfolio">Grafisch Design</a></li>
                    <li><a href="#portfolio">UI/UX Design</a></li>
                    <li><a href="#portfolio">Branding</a></li>
                    <li><a href="#contact">Consultatie</a></li>
                  </ul>

              </div>
              

              
            </div>
          </div>
          
          <!-- Footer Bottom -->
          <div class="py-4">
            <div class="row align-items-center">
              <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <p class="copyright mb-0">
                  © 2025 <strong><?php echo htmlspecialchars($brandName); ?></strong>. Alle rechten voorbehouden.
                </p>
              </div>
              <div class="col-md-6 text-center text-md-end">
                <div class="footer-meta">
                  <span class="meta-item">
                    <i class="lnr lnr-heart text-danger me-1"></i>
                    Gemaakt met passie
                  </span>
                  <span class="meta-separator">•</span>
                  <span class="meta-item">
                    <i class="lnr lnr-code text-primary me-1"></i>
                    Handcrafted Code
                  </span>
                </div>
              </div>
            </div>
          </div>
          
        </div>
      </div>
    </footer>
  </body>
  <!-- External JS -->
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"
    integrity="sha512-3gJwYpMe3QewGELv8k/BX9vcqhryRdzRMxVfq6ngyWXwo03GFEzjsUm8Q7RZcHPHksttq7/GFoxjCVUjkjvPdw=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  ></script>
  <script src="vendor/bootstrap/popper.min.js"></script>
  <script src="vendor/bootstrap/bootstrap.min.js"></script>
  <script src="vendor/select2/select2.min.js "></script>
  <script src="vendor/owlcarousel/owl.carousel.min.js"></script>
  <script src="vendor/isotope/isotope.min.js"></script>
  <script src="vendor/lightcase/lightcase.js"></script>
  <script src="vendor/waypoints/waypoint.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script src="stellar/jquery.stellar.js"></script>

  <!-- Main JS -->
  <script src="js/app.min.js "></script>
  
  <!-- Contact Form JS -->
  <script src="contactform/contactform.js"></script>
  
  <!-- Auto-close navbar script -->
  <script>
    $(document).ready(function() {
      // Auto-close navbar when clicking on navigation links
      $('.navbar-nav .nav-link').on('click', function() {
        // Check if navbar is expanded (mobile view)
        if ($('.navbar-toggler').is(':visible')) {
          // Collapse the navbar
          $('.navbar-collapse').collapse('hide');
        }
      });
      
      // Initialize AOS
      AOS.init({
        duration: 1000,
        once: true
      });
      
      // Debug timeline badges and icons
      console.log('Timeline badges:', $('.timeline-date.badge').length);
      $('.timeline-date.badge').each(function(index) {
        console.log('Badge', index, 'text:', $(this).text(), 'classes:', $(this).attr('class'));
      });
      
      // Check for missing icons and add fallbacks
      $('.timeline-marker i[class*="lnr"]').each(function() {
        const $icon = $(this);
        const fallback = $icon.data('fallback');
        
        // Check if the Linear icon is rendering properly
        setTimeout(function() {
          const iconWidth = $icon.width();
          const iconHeight = $icon.height();
          
          console.log('Icon check:', $icon.attr('class'), 'Width:', iconWidth, 'Height:', iconHeight);
          
          // If icon has no dimensions, use fallback
          if ((iconWidth <= 1 || iconHeight <= 1) && fallback) {
            console.log('Switching to fallback icon:', fallback);
            $icon.removeClass().addClass(fallback + ' text-white');
          }
        }, 1000);
      });
      
      // Function to check and update badge status
      function updateTimelineBadges() {
        $('.timeline-date.badge').each(function(index) {
          const $badge = $(this);
          const text = $badge.text().trim();
          console.log('Badge', index, 'current text:', text);
          
          // If badge is empty, set default
          if (!text || text === '') {
            $badge.text('Voltooid').removeClass().addClass('timeline-date badge badge-success');
            console.log('Updated empty badge to Voltooid');
          }
        });
      }
      
      // Update badges on page load
      updateTimelineBadges();
      
      // Listen for timeline updates (if you have dynamic updates)
      $(document).on('timelineUpdated', function() {
        updateTimelineBadges();
      });
      
      // Timeline Expansion functionality
      $('.expandable-timeline .timeline-header').on('click', function() {
        const $timelineItem = $(this).closest('.expandable-timeline');
        const $details = $timelineItem.find('.timeline-details');
        const $icon = $(this).find('.expand-icon');
        const isExpanded = $details.is(':visible');
        
        // Close all other expanded items
        $('.expandable-timeline .timeline-details').not($details).slideUp(300);
        $('.expandable-timeline .expand-icon').not($icon).removeClass('expanded').addClass('lnr-chevron-down').removeClass('lnr-chevron-up');
        $('.expandable-timeline').not($timelineItem).removeClass('timeline-expanded');
        
        // Toggle current item
        if (isExpanded) {
          // Collapse
          $details.slideUp(300);
          $icon.removeClass('expanded').addClass('lnr-chevron-down').removeClass('lnr-chevron-up');
          $timelineItem.removeClass('timeline-expanded');
        } else {
          // Expand
          $details.slideDown(300);
          $icon.addClass('expanded').removeClass('lnr-chevron-down').addClass('lnr-chevron-up');
          $timelineItem.addClass('timeline-expanded');
          
          // Scroll to the expanded item for better visibility
          setTimeout(function() {
            $('html, body').animate({
              scrollTop: $timelineItem.offset().top - 100
            }, 400);
          }, 150);
        }
      });
      
      // Hover effects for timeline items
      $('.expandable-timeline .timeline-header').hover(
        function() {
          $(this).closest('.timeline-item').addClass('timeline-hover');
        },
        function() {
          $(this).closest('.timeline-item').removeClass('timeline-hover');
        }
      );
    });
  </script>
  
  <!-- Timeline Styles -->
  <style>
    /* Timeline Marker Styles */
    .timeline-marker {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-top: 5px;
      position: relative;
      z-index: 2;
    }
    
    .timeline-marker.completed {
      background: linear-gradient(135deg, #28a745, #20c997);
      box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
    }
    
    .timeline-marker.in-progress {
      background: linear-gradient(135deg, #ffc107, #fd7e14);
      box-shadow: 0 3px 10px rgba(255, 193, 7, 0.3);
    }
    
    .timeline-marker.pending {
      background: linear-gradient(135deg, #6c757d, #adb5bd);
      box-shadow: 0 3px 10px rgba(108, 117, 125, 0.3);
    }
    
    .timeline-marker i {
      font-size: 16px;
      color: white;
      line-height: 1;
      display: inline-block;
      width: 16px;
      height: 16px;
      text-align: center;
    }
    
    /* Ensure icons are visible */
    .timeline-marker i:before {
      display: inline-block;
      width: 16px;
      height: 16px;
      text-align: center;
      line-height: 16px;
    }
    
    /* Fallback for when Linear Icons fail to load */
    .timeline-marker i.fas {
      font-family: "Font Awesome 6 Free" !important;
      font-weight: 900;
    }
    
    /* Debug styling to see if icons are loading */
    .timeline-marker i.timeline-icon {
      background-color: rgba(255,255,255,0.1);
      border-radius: 2px;
      min-width: 16px;
      min-height: 16px;
    }
    
    /* Timeline Content */
    .timeline-content {
      flex: 1;
      padding-left: 20px;
      max-width: calc(100% - 60px);
    }
    
    /* Badge styling */
    .badge {
      font-size: 0.75rem;
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
    }
    
    .badge-secondary {
      background-color: #6c757d;
      color: white;
    }
    
    .badge-warning {
      background-color: #ffc107;
      color: #ffff;
    }
    
    .badge-success {
      background-color: #28a745;
      color: white;
    }
    
    .badge-danger {
      background-color: #dc3545;
      color: white;
    }
    
    /* Enhanced timeline for creative process phases */
    .timeline-item[data-phase="challenge"] .timeline-marker {
      background: linear-gradient(135deg, #ffc107, #fd7e14);
      border: 3px solid #fff3cd;
    }
    
    .timeline-item[data-phase="approach"] .timeline-marker {
      background: linear-gradient(135deg, #17a2b8, #0dcaf0);
      border: 3px solid #cff4fc;
    }
    
    .timeline-item[data-phase="solution"] .timeline-marker {
      background: linear-gradient(135deg, #28a745, #20c997);
      border: 3px solid #d1e7dd;
    }
    
    /* Enhanced creative process styling */
    .timeline-item[data-phase="challenge"] {
      border-left: 4px solid #ffc107;
      background: rgba(255, 193, 7, 0.05);
      border-radius: 8px;
      margin: 15px 0;
      padding: 10px;
    }
    
    .timeline-item[data-phase="approach"] {
      border-left: 4px solid #17a2b8;
      background: rgba(23, 162, 184, 0.05);
      border-radius: 8px;
      margin: 15px 0;
      padding: 10px;
    }
    
    .timeline-item[data-phase="solution"] {
      border-left: 4px solid #28a745;
      background: rgba(40, 167, 69, 0.05);
      border-radius: 8px;
      margin: 15px 0;
      padding: 10px;
    }
    
    .expandable-timeline {
      transition: all 0.3s ease;
      /* Use flexbox to position marker and content side by side */
      display: flex !important;
      align-items: flex-start;
      gap: 15px;
      width: 100% !important;
      max-width: 100% !important;
      overflow: hidden;
    }
    
    .expandable-timeline .timeline-header {
      transition: all 0.3s ease;
      border-radius: 8px;
      padding: 10px;
      /* Remove negative margins that cause width expansion */
      margin: 0;
      /* Prevent width expansion on hover */
      box-sizing: border-box;
      width: 100%;
    }
    
    .expandable-timeline .timeline-header:hover {
      background: rgba(0, 123, 255, 0.05);
      /* Removed transform: translateX(5px) to prevent width changes */
    }
    
    .timeline-hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      /* Maintain flex layout on hover */
      display: flex !important;
      align-items: flex-start !important;
      width: 100% !important;
      max-width: 100% !important;
      min-width: 100% !important;
      box-sizing: border-box !important;
    }
    
    .timeline-expanded {
      background: rgba(0, 123, 255, 0.02);
      border-radius: 12px;
      padding: 15px;
      /* Completely prevent width expansion */
      margin: 0 !important;
      border-left: 3px solid #007bff;
      /* Ensure content stays within bounds */
      box-sizing: border-box !important;
      width: 100% !important;
      max-width: 100% !important;
      overflow: hidden;
    }
    
    .expand-icon {
      float: right;
      transition: all 0.3s ease;
      color: #007bff;
      font-size: 14px;
    }
    
    .expand-icon.expanded {
      transform: rotate(180deg);
      color: #28a745;
    }
    
    .timeline-details {
      margin-top: 15px;
    }
    
    .detail-content {
      border-left: 4px solid #007bff;
      animation: fadeInDetail 0.3s ease-out;
    }
    
    @keyframes fadeInDetail {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .task-item {
      padding: 5px 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .task-item i {
      flex-shrink: 0;
    }
    
    .week-deliverables, .week-stats, .final-results {
      background: rgba(255, 255, 255, 0.7);
      border-radius: 8px;
      padding: 15px;
    }
    
    .tech-badges .badge {
      margin-bottom: 5px;
    }
    
    .stat-number {
      font-size: 1.2em;
      font-weight: bold;
      color: #007bff;
    }
    
    /* Timeline Container Width Control - Override all potential conflicts */
    .timeline-item.expandable-timeline {
      width: 100% !important;
      max-width: 100% !important;
      min-width: 100% !important;
      box-sizing: border-box !important;
      display: flex !important; /* Use flex for proper alignment */
      align-items: flex-start !important;
      gap: 15px !important;
      margin-left: 0 !important;
      margin-right: 0 !important;
    }
    
    /* Also control the timeline content container */
    .expandable-timeline .timeline-content {
      flex: 1; /* Take up remaining space */
      width: auto !important;
      max-width: none !important;
      box-sizing: border-box !important;
      overflow: hidden;
    }
    
    /* Timeline marker positioning */
    .expandable-timeline .timeline-marker {
      flex-shrink: 0; /* Don't shrink the marker */
      position: static; /* Remove any absolute positioning */
      margin-top: 5px; /* Align with text */
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .timeline-item.expandable-timeline {
        gap: 10px !important; /* Smaller gap on mobile */
      }
      
      .timeline-expanded {
        margin: 0 !important; /* Remove negative margins on mobile */
        padding: 10px;
        box-sizing: border-box !important;
        width: 100% !important;
        max-width: 100% !important;
      }
      
      .expandable-timeline .timeline-header {
        margin: 0 !important; /* Remove all negative margins */
        padding: 5px;
        width: 100% !important;
        box-sizing: border-box !important;
      }
      
      .expandable-timeline .timeline-marker {
        margin-top: 2px; /* Better alignment on mobile */
      }
      
      .detail-content {
        padding: 15px !important;
      }
    }
    
    /* Design-specific timeline badges */
    .badge-figma {
      background-color: #F24E1E;
      color: white;
    }
    
    .badge-photoshop {
      background-color: #31A8FF;
      color: white;
    }
    
    .badge-illustrator {
      background-color: #FF9A00;
      color: white;
    }
    
    .badge-indesign {
      background-color: #FF3366;
      color: white;
    }
    
    /* Creative Process Timeline Styling */
    .timeline-item[data-phase="challenge"] .timeline-marker {
      background: linear-gradient(45deg, #ffc107, #ff8f00);
      border: 2px solid #fff;
      box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.2);
    }
    
    .timeline-item[data-phase="approach"] .timeline-marker {
      background: linear-gradient(45deg, #17a2b8, #0d8aa8);
      border: 2px solid #fff;
      box-shadow: 0 0 0 4px rgba(23, 162, 184, 0.2);
    }
    
    .timeline-item[data-phase="development"] .timeline-marker {
      background: linear-gradient(45deg, #007bff, #0056b3);
      border: 2px solid #fff;
      box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.2);
    }
    
    .timeline-item[data-phase="design"] .timeline-marker {
      background: linear-gradient(45deg, #28a745, #1e7e34);
      border: 2px solid #fff;
      box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
    }
    
    .timeline-item[data-phase="solution"] .timeline-marker {
      background: linear-gradient(45deg, #28a745, #20c997);
      border: 2px solid #fff;
      box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
    }
    
    /* Creative process badge styling */
    .badge-warning {
      background-color: #ffc107;
      color: #fff;
      font-weight: 600;
    }
    
    .badge-info {
      background-color: #17a2b8;
      color: #fff;
      font-weight: 600;
    }
    
    .badge-success {
      background-color: #28a745;
      color: #fff;
      font-weight: 600;
    }
    
    /* Enhanced timeline content styling for creative process */
    .timeline-item[data-phase="challenge"] .timeline-content {
      border-left: 3px solid #ffc107;
      padding-left: 20px;
    }
    
    .timeline-item[data-phase="approach"] .timeline-content {
      border-left: 3px solid #17a2b8;
      padding-left: 20px;
    }
    
    .timeline-item[data-phase="solution"] .timeline-content {
      border-left: 3px solid #28a745;
      padding-left: 20px;
    }
    
    /* Timeline marker states for dynamic phases */
    .timeline-marker.completed {
      background: linear-gradient(45deg, #28a745, #20c997);
      box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
    }
    
    .timeline-marker.in-progress {
      background: linear-gradient(45deg, #ffc107, #ff8f00);
      box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.2);
      animation: pulse-warning 2s infinite;
    }
    
    .timeline-marker.pending {
      background: linear-gradient(45deg, #6c757d, #495057);
      box-shadow: 0 0 0 4px rgba(108, 117, 125, 0.2);
      opacity: 0.7;
    }
    
    /* Pulsing animation for in-progress phases */
    @keyframes pulse-warning {
      0%, 100% {
        box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.2);
      }
      50% {
        box-shadow: 0 0 0 8px rgba(255, 193, 7, 0.1);
      }
    }
    
    .feature-list {
      list-style: none;
      padding: 0;
    }
    
    .feature-list li {
      padding: 4px 0;
      border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    
    .feature-list li:last-child {
      border-bottom: none;
    }
    
    .stats-grid .stat-item {
      display: flex;
      align-items: center;
      font-size: 0.9rem;
    }
    
    .design-specs .spec-item {
      display: flex;
      align-items: center;
      font-size: 0.9rem;
      margin-bottom: 8px;
    }
    
    .project-status {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
    }
    
    /* Enhanced timeline badges */
    .timeline-date.badge-warning {
      background-color: #ffc107;
      color: #fff;
    }
    
    .timeline-date.badge-info {
      background-color: #17a2b8;
      color: white;
    }
    
    .timeline-date.badge-success {
      background-color: #28a745;
      color: white;
    }
    
    .timeline-date.badge-primary {
      background-color: #007bff;
      color: white;
    }

    /* Overlay Content Styles - Matching index.php */
    .overlay-content {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
      z-index: 10;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .image-overlay:hover .overlay-content,
    .gallery-overlay:hover .overlay-content {
      opacity: 1;
    }

    .gallery-overlay,
    .image-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(0, 123, 255, 0.9), rgba(0, 86, 179, 0.95));;
      opacity: 0;
      transition: opacity 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .portfolio-image-container:hover .image-overlay,
    .gallery-image-wrapper:hover .gallery-overlay {
      opacity: 1;
    }

    .gallery-image-wrapper,
    .portfolio-image-container {
      position: relative;
      overflow: hidden;
    }

    .gallery-zoom {
      color: white;
      font-size: 24px;
      text-decoration: none;
    }

    .gallery-zoom:hover {
      color: #fff;
      text-decoration: none;
    }
  </style>
</body>
</html>