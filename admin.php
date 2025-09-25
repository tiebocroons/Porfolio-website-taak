<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database configuration
require_once 'database.php';

// Initialize database connection
$pdo = getDatabaseConnection();

// Get projects count and stats
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN category = 'development' THEN 1 ELSE 0 END) as development,
        SUM(CASE WHEN category = 'design' THEN 1 ELSE 0 END) as design,
        SUM(CASE WHEN category = 'photography' THEN 1 ELSE 0 END) as photography
        FROM projects WHERE deleted_at IS NULL");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'development' => 0, 'design' => 0, 'photography' => 0];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_projects':
            echo json_encode(getProjects($pdo));
            exit;
            
        case 'save_project':
            echo json_encode(saveProject($pdo, $_POST));
            exit;
            
        case 'delete_project':
            echo json_encode(deleteProject($pdo, $_POST['id']));
            exit;
            
        case 'get_project':
            echo json_encode(getProject($pdo, $_POST['id']));
            exit;
            
        case 'get_github_repos':
            $token = isset($_POST['token']) ? $_POST['token'] : '';
            echo json_encode(getGitHubRepos($_POST['username'], $token));
            exit;
            
        case 'import_github_project':
            echo json_encode(importGitHubProject($pdo, $_POST['repo_data']));
            exit;
    }
}

// Project management functions
function getProjects($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY updated_at DESC");
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function saveProject($pdo, $data) {
    try {
        $timeline = isset($data['timeline']) ? json_encode($data['timeline']) : json_encode([]);
        $tools = json_encode(explode(',', isset($data['tools']) ? $data['tools'] : ''));
        $features = json_encode(explode("\n", isset($data['features']) ? $data['features'] : ''));
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing project
            $stmt = $pdo->prepare("UPDATE projects SET 
                title = ?, description = ?, category = ?, year = ?, status = ?,
                tools = ?, url = ?, features = ?, image = ?, timeline = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $data['title'], $data['description'], $data['category'], $data['year'], $data['status'],
                $tools, $data['url'], $features, $data['image'], $timeline, $data['id']
            ]);
            return ['success' => true, 'message' => 'Project succesvol bijgewerkt!'];
        } else {
            // Create new project
            $stmt = $pdo->prepare("INSERT INTO projects 
                (title, description, category, year, status, tools, url, features, image, timeline, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $data['title'], $data['description'], $data['category'], $data['year'], $data['status'],
                $tools, $data['url'], $features, $data['image'], $timeline
            ]);
            return ['success' => true, 'message' => 'Project succesvol toegevoegd!'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteProject($pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE projects SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Project succesvol verwijderd!'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function getProject($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            $project['tools'] = json_decode($project['tools'], true);
            $project['features'] = json_decode($project['features'], true);
            $project['timeline'] = json_decode($project['timeline'], true);
        }
        
        return ['success' => true, 'data' => $project];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function getGitHubRepos($username, $token = '') {
    if (empty($username)) {
        return ['success' => false, 'error' => 'GitHub gebruikersnaam is verplicht'];
    }
    
    $headers = [
        'User-Agent: Portfolio-Admin',
        'Accept: application/vnd.github.v3+json'
    ];
    
    if (!empty($token)) {
        $headers[] = "Authorization: token $token";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/users/$username/repos?per_page=100&sort=updated");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        $errorMsg = $httpCode === 403 ? 'GitHub API rate limit bereikt. Gebruik een Personal Access Token.' : 'Fout bij ophalen GitHub repositories';
        return ['success' => false, 'error' => $errorMsg];
    }
}

function importGitHubProject($pdo, $repoData) {
    try {
        $repoInfo = json_decode($repoData, true);
        
        // Check if project already exists
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE github_repo = ? AND deleted_at IS NULL");
        $stmt->execute([$repoInfo['full_name']]);
        $existingProject = $stmt->fetch();
        
        $tools = [isset($repoInfo['language']) ? $repoInfo['language'] : 'Unknown'];
        $features = [
            "GitHub Repository: " . $repoInfo['name'],
            "Stars: " . $repoInfo['stargazers_count'],
            "Forks: " . $repoInfo['forks_count'],
            "Laatst bijgewerkt: " . date('d-m-Y', strtotime($repoInfo['updated_at']))
        ];
        
        $title = ucwords(str_replace(['-', '_'], ' ', $repoInfo['name']));
        $description = $repoInfo['description'] ?: "GitHub repository: " . $repoInfo['name'];
        $category = detectProjectCategory($repoInfo);
        $year = date('Y', strtotime($repoInfo['created_at']));
        
        if ($existingProject) {
            // Update existing project
            $stmt = $pdo->prepare("UPDATE projects SET 
                title = ?, description = ?, category = ?, year = ?, 
                tools = ?, url = ?, features = ?, github_url = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $title, $description, $category, $year,
                json_encode($tools), $repoInfo['homepage'] ?: $repoInfo['html_url'], 
                json_encode($features), $repoInfo['html_url'], $existingProject['id']
            ]);
            return ['success' => true, 'message' => 'GitHub project succesvol bijgewerkt!'];
        } else {
            // Create new project
            $stmt = $pdo->prepare("INSERT INTO projects 
                (title, description, category, year, status, tools, url, features, image, github_repo, github_url, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'completed', ?, ?, ?, 'img/Logo.png', ?, ?, NOW(), NOW())");
            $stmt->execute([
                $title, $description, $category, $year,
                json_encode($tools), $repoInfo['homepage'] ?: $repoInfo['html_url'],
                json_encode($features), $repoInfo['full_name'], $repoInfo['html_url']
            ]);
            return ['success' => true, 'message' => 'GitHub project succesvol geïmporteerd!'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function detectProjectCategory($repoInfo) {
    $name = strtolower($repoInfo['name']);
    $description = strtolower(isset($repoInfo['description']) ? $repoInfo['description'] : '');
    $language = strtolower(isset($repoInfo['language']) ? $repoInfo['language'] : '');
    
    // Development keywords
    if (in_array($language, ['javascript', 'html', 'css', 'php', 'python', 'java']) ||
        strpos($name, 'app') !== false || strpos($name, 'website') !== false ||
        strpos($description, 'application') !== false || strpos($description, 'website') !== false) {
        return 'development';
    }
    
    // Design keywords
    if (strpos($name, 'design') !== false || strpos($description, 'design') !== false) {
        return 'design';
    }
    
    return 'development';
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Admin - Project Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    
    <!-- Icon Libraries -->
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="css/style.min.css" rel="stylesheet">
    
    <!-- Admin specific styles -->
    <style>
        /* Base styles matching portfolio design */
        body {
            background: #f5f8fd url("img/intro-bg.jpg") center top no-repeat;
            background-size: cover;
            font-family: "Open Sans", sans-serif;
            color: #444;
            min-height: 100vh;
        }
        
        /* Background overlay for admin */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(245, 248, 253, 0.95);
            z-index: -1;
        }
        
        .admin-container {
            min-height: 100vh;
            padding: 20px 0;
        }
        
        /* Admin header matching visual-card style */
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: none;
            margin-bottom: 30px;
            padding: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .admin-header:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        /* Admin cards matching visual-card style */
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: none;
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        /* Card headers matching portfolio theme */
        .card-header {
            background: hsl(312, 100%, 50%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 20px;
        }
        
        .card-header h3 {
            color: white !important;
            margin: 0;
            font-family: "Montserrat", sans-serif;
            font-weight: 600;
        }
        
        /* Form elements matching portfolio style */
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            font-family: "Open Sans", sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #1bb1dc;
            box-shadow: 0 0 0 0.2rem rgba(27, 177, 220, 0.25);
            outline: none;
        }
        
        /* Button styles matching portfolio theme */
        .btn-primary {
            background: #1bb1dc;
            border-color: #1bb1dc;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            font-family: "Montserrat", sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #0a98c0;
            border-color: #0a98c0;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(27, 177, 220, 0.3);
        }
        
        /* Project items matching portfolio card style */
        .project-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .project-item:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        /* Badge styles matching portfolio badges */
        .badge-development { 
            background: #1bb1dc; 
            color: white; 
        }
        .badge-design { 
            background: hsl(312, 100%, 50%); 
            color: white; 
        }
        .badge-photography { 
            background: #17a2b8; 
            color: white; 
        }
        
        /* Stats section matching portfolio stats */
        .stats-row {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: hsl(312, 100%, 50%);
            font-family: "Montserrat", sans-serif;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Alert styles */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Loading spinner */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner-border {
            width: 2rem;
            height: 2rem;
            color: hsl(312, 100%, 50%);
        }
    </style>
</head>

<body>
    <div class="container-fluid admin-container">
        
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="lnr lnr-cog"></i>
                        Portfolio Admin Dashboard
                    </h1>
                    <p class="text-muted mb-0">Beheer je projecten, update content en bekijk statistieken</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-outline-danger me-2" id="logoutBtn">
                        <i class="lnr lnr-exit"></i>
                        Uitloggen
                    </button>
                    <a href="index.html" class="btn btn-outline-primary">
                        <i class="lnr lnr-home"></i>
                        Naar Portfolio
                    </a>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-row mt-4">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number" id="totalProjects"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Totaal Projecten</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number" id="developmentProjects"><?php echo $stats['development']; ?></div>
                            <div class="stat-label">Development</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number" id="designProjects"><?php echo $stats['design']; ?></div>
                            <div class="stat-label">Design</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-number" id="photographyProjects"><?php echo $stats['photography']; ?></div>
                            <div class="stat-label">Photography</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <div class="row">
            <!-- Project Form -->
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-header">
                        <h3 class="mb-0" id="formTitle">
                            <i class="lnr lnr-plus-circle"></i>
                            Nieuw Project Toevoegen
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <form id="projectForm">
                            <input type="hidden" id="projectId" name="id">
                            
                            <div class="mb-3">
                                <label for="projectTitle" class="form-label">Project Titel</label>
                                <input type="text" class="form-control" id="projectTitle" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectDescription" class="form-label">Beschrijving</label>
                                <textarea class="form-control" id="projectDescription" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectCategory" class="form-label">Categorie</label>
                                    <select class="form-select" id="projectCategory" name="category" required>
                                        <option value="">Selecteer categorie</option>
                                        <option value="development">Development</option>
                                        <option value="design">Design</option>
                                        <option value="photography">Photography</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="projectYear" class="form-label">Jaar</label>
                                    <input type="number" class="form-control" id="projectYear" name="year" min="2000" max="2030">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectStatus" class="form-label">Status</label>
                                    <select class="form-select" id="projectStatus" name="status">
                                        <option value="completed">Voltooid</option>
                                        <option value="in-progress">In Progress</option>
                                        <option value="planned">Gepland</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="projectUrl" class="form-label">URL (optioneel)</label>
                                    <input type="url" class="form-control" id="projectUrl" name="url">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectTools" class="form-label">Tools/Technologieën</label>
                                <input type="text" class="form-control" id="projectTools" name="tools" placeholder="HTML, CSS, JavaScript (gescheiden door komma's)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectFeatures" class="form-label">Kenmerken</label>
                                <textarea class="form-control" id="projectFeatures" name="features" rows="3" placeholder="Elke feature op een nieuwe regel"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectImage" class="form-label">Afbeelding Pad</label>
                                <input type="text" class="form-control" id="projectImage" name="image" value="img/Logo.png">
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <span id="submitText">Project Opslaan</span>
                                </button>
                                <button type="button" class="btn btn-secondary" id="resetForm">
                                    Formulier Wissen
                                </button>
                            </div>
                        </form>
                        
                        <div class="loading" id="formLoading">
                            <div class="spinner-border" role="status"></div>
                            <p class="mt-2">Bezig met opslaan...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects List -->
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="lnr lnr-list"></i>
                            Projecten Overzicht
                        </h3>
                        <button class="btn btn-outline-light btn-sm" id="refreshProjects">
                            <i class="lnr lnr-sync"></i>
                            Vernieuwen
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <!-- Search and Filter -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" id="searchProjects" placeholder="Zoek projecten...">
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="filterCategory">
                                    <option value="">Alle categorieën</option>
                                    <option value="development">Development</option>
                                    <option value="design">Design</option>
                                    <option value="photography">Photography</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="projectsList">
                            <!-- Projects will be loaded here -->
                        </div>
                        
                        <div class="loading" id="projectsLoading">
                            <div class="spinner-border" role="status"></div>
                            <p class="mt-2">Projecten laden...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GitHub Projects Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="admin-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">
                            <i class="fab fa-github"></i>
                            GitHub Projecten
                        </h3>
                        <button class="btn btn-outline-light btn-sm" id="loadGitHubRepos">
                            <i class="lnr lnr-sync"></i>
                            Repositories Laden
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <!-- GitHub Configuration -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="githubUsername" class="form-label">GitHub Gebruikersnaam</label>
                                <input type="text" class="form-control" id="githubUsername" value="tiebocroons" placeholder="Je GitHub gebruikersnaam">
                            </div>
                            <div class="col-md-6">
                                <label for="githubToken" class="form-label">Personal Access Token (Aanbevolen)</label>
                                <input type="password" class="form-control" id="githubToken" placeholder="Voor meer API calls">
                                <small class="text-muted">
                                    Verhoogt API limiet naar 5000/uur. 
                                    <a href="https://github.com/settings/tokens" target="_blank" class="text-info">Maak token aan →</a>
                                </small>
                            </div>
                        </div>
                        
                        <div id="githubReposList">
                            <div class="text-center text-muted py-5">
                                <i class="fab fa-github" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-3">Klik op "Repositories Laden" om je GitHub projecten te bekijken</p>
                            </div>
                        </div>
                        
                        <div class="loading" id="githubLoading">
                            <div class="spinner-border" role="status"></div>
                            <p class="mt-2">GitHub repositories laden...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="vendor/bootstrap/bootstrap.min.js"></script>
    
    <!-- Admin JavaScript -->
    <script>
        class PortfolioAdmin {
            constructor() {
                this.projects = [];
                this.editingId = null;
                this.init();
            }

            init() {
                this.bindEvents();
                this.loadProjects();
                this.loadGitHubSettings();
            }

            bindEvents() {
                // Form submission
                $('#projectForm').on('submit', (e) => {
                    e.preventDefault();
                    this.saveProject();
                });

                // Reset form
                $('#resetForm').on('click', () => {
                    this.resetForm();
                });

                // Search and filter
                $('#searchProjects, #filterCategory').on('input change', () => {
                    this.filterProjects();
                });

                // Refresh projects
                $('#refreshProjects').on('click', () => {
                    this.loadProjects();
                });

                // GitHub functionality
                $('#loadGitHubRepos').on('click', () => {
                    this.loadGitHubRepos();
                });

                // Save GitHub settings
                $('#githubUsername, #githubToken').on('change', () => {
                    this.saveGitHubSettings();
                });

                // Logout
                $('#logoutBtn').on('click', () => {
                    if (confirm('Weet je zeker dat je wilt uitloggen?')) {
                        window.location.href = 'logout.php';
                    }
                });

                // Set current year
                $('#projectYear').val(new Date().getFullYear());
            }

            async loadProjects() {
                $('#projectsLoading').show();
                
                try {
                    const response = await this.makeRequest('get_projects');
                    if (response.success) {
                        this.projects = response.data;
                        this.renderProjects();
                        this.updateStats();
                    } else {
                        this.showAlert('Fout bij laden projecten: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Fout bij laden projecten: ' + error.message, 'danger');
                } finally {
                    $('#projectsLoading').hide();
                }
            }

            async saveProject() {
                $('#formLoading').show();
                $('#projectForm').hide();

                try {
                    const formData = new FormData(document.getElementById('projectForm'));
                    formData.append('action', 'save_project');

                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.resetForm();
                        this.loadProjects();
                    } else {
                        this.showAlert('Fout bij opslaan: ' + result.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Fout bij opslaan: ' + error.message, 'danger');
                } finally {
                    $('#formLoading').hide();
                    $('#projectForm').show();
                }
            }

            async editProject(id) {
                try {
                    const response = await this.makeRequest('get_project', { id: id });
                    if (response.success && response.data) {
                        const project = response.data;
                        
                        $('#projectId').val(project.id);
                        $('#projectTitle').val(project.title);
                        $('#projectDescription').val(project.description);
                        $('#projectCategory').val(project.category);
                        $('#projectYear').val(project.year);
                        $('#projectStatus').val(project.status);
                        $('#projectUrl').val(project.url);
                        $('#projectImage').val(project.image);
                        
                        if (project.tools && Array.isArray(project.tools)) {
                            $('#projectTools').val(project.tools.join(', '));
                        }
                        
                        if (project.features && Array.isArray(project.features)) {
                            $('#projectFeatures').val(project.features.join('\n'));
                        }

                        this.editingId = id;
                        $('#formTitle').html('<i class="lnr lnr-pencil"></i> Project Bewerken');
                        $('#submitText').text('Wijzigingen Opslaan');
                        
                        // Scroll to form
                        $('html, body').animate({
                            scrollTop: $('#projectForm').offset().top - 100
                        }, 500);
                    }
                } catch (error) {
                    this.showAlert('Fout bij laden project: ' + error.message, 'danger');
                }
            }

            async deleteProject(id) {
                if (confirm('Weet je zeker dat je dit project wilt verwijderen?')) {
                    try {
                        const response = await this.makeRequest('delete_project', { id: id });
                        if (response.success) {
                            this.showAlert(response.message, 'success');
                            this.loadProjects();
                        } else {
                            this.showAlert('Fout bij verwijderen: ' + response.error, 'danger');
                        }
                    } catch (error) {
                        this.showAlert('Fout bij verwijderen: ' + error.message, 'danger');
                    }
                }
            }

            resetForm() {
                document.getElementById('projectForm').reset();
                this.editingId = null;
                $('#formTitle').html('<i class="lnr lnr-plus-circle"></i> Nieuw Project Toevoegen');
                $('#submitText').text('Project Opslaan');
                $('#projectYear').val(new Date().getFullYear());
            }

            renderProjects() {
                const container = document.getElementById('projectsList');
                
                if (this.projects.length === 0) {
                    container.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="lnr lnr-database" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3">Nog geen projecten toegevoegd</p>
                        </div>
                    `;
                    return;
                }

                const html = this.projects.map(project => {
                    const tools = Array.isArray(project.tools) ? JSON.parse(project.tools) : [];
                    const badgeClass = `badge-${project.category}`;
                    
                    return `
                        <div class="project-item" data-category="${project.category}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">${project.title}</h5>
                                    <span class="badge ${badgeClass} me-2">${project.category}</span>
                                    <span class="badge bg-secondary">${project.year}</span>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-info btn-sm edit-project" data-id="${project.id}">
                                        <i class="lnr lnr-pencil"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-project" data-id="${project.id}">
                                        <i class="lnr lnr-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-muted mb-2">${project.description}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="lnr lnr-clock"></i>
                                    ${new Date(project.updated_at).toLocaleDateString('nl-NL')}
                                </small>
                                <span class="badge bg-light text-dark">${project.status}</span>
                            </div>
                        </div>
                    `;
                }).join('');

                container.innerHTML = html;

                // Bind edit/delete events
                $('.edit-project').on('click', (e) => {
                    const id = $(e.currentTarget).data('id');
                    this.editProject(id);
                });

                $('.delete-project').on('click', (e) => {
                    const id = $(e.currentTarget).data('id');
                    this.deleteProject(id);
                });
            }

            filterProjects() {
                const search = $('#searchProjects').val().toLowerCase();
                const category = $('#filterCategory').val();
                
                $('.project-item').each(function() {
                    const $item = $(this);
                    const title = $item.find('h5').text().toLowerCase();
                    const description = $item.find('.text-muted').first().text().toLowerCase();
                    const itemCategory = $item.data('category');
                    
                    const matchesSearch = !search || title.includes(search) || description.includes(search);
                    const matchesCategory = !category || itemCategory === category;
                    
                    $item.toggle(matchesSearch && matchesCategory);
                });
            }

            updateStats() {
                const total = this.projects.length;
                const dev = this.projects.filter(p => p.category === 'development').length;
                const design = this.projects.filter(p => p.category === 'design').length;
                const photo = this.projects.filter(p => p.category === 'photography').length;

                $('#totalProjects').text(total);
                $('#developmentProjects').text(dev);
                $('#designProjects').text(design);
                $('#photographyProjects').text(photo);
            }

            async loadGitHubRepos() {
                const username = $('#githubUsername').val().trim();
                const token = $('#githubToken').val().trim();
                
                if (!username) {
                    this.showAlert('Voer eerst je GitHub gebruikersnaam in', 'danger');
                    return;
                }

                $('#githubLoading').show();
                $('#githubReposList').hide();

                try {
                    const response = await this.makeRequest('get_github_repos', { 
                        username: username, 
                        token: token 
                    });
                    
                    if (response.success) {
                        this.renderGitHubRepos(response.data);
                    } else {
                        this.showAlert('Fout bij laden GitHub repos: ' + response.error, 'danger');
                        $('#githubReposList').html(`
                            <div class="text-center text-muted py-5">
                                <i class="lnr lnr-warning" style="font-size: 3rem; color: #dc3545;"></i>
                                <p class="mt-3">Fout bij laden repositories</p>
                                <small>${response.error}</small>
                            </div>
                        `);
                    }
                } catch (error) {
                    this.showAlert('Fout bij laden GitHub repos: ' + error.message, 'danger');
                } finally {
                    $('#githubLoading').hide();
                    $('#githubReposList').show();
                }
            }

            renderGitHubRepos(repos) {
                if (repos.length === 0) {
                    $('#githubReposList').html(`
                        <div class="text-center text-muted py-5">
                            <i class="fab fa-github" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3">Geen repositories gevonden</p>
                        </div>
                    `);
                    return;
                }

                const html = repos.map(repo => {
                    const updatedDate = new Date(repo.updated_at).toLocaleDateString('nl-NL');
                    const language = repo.language || 'Unknown';
                    
                    return `
                        <div class="project-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">
                                        <a href="${repo.html_url}" target="_blank" class="text-decoration-none">
                                            ${repo.name}
                                        </a>
                                        ${repo.fork ? '<small class="text-muted">(fork)</small>' : ''}
                                    </h5>
                                    <span class="badge bg-primary me-2">${language}</span>
                                    <span class="badge bg-success">⭐ ${repo.stargazers_count}</span>
                                    <span class="badge bg-info">🍴 ${repo.forks_count}</span>
                                </div>
                                <button class="btn btn-outline-primary btn-sm import-repo" 
                                        data-repo='${JSON.stringify(repo)}'>
                                    <i class="lnr lnr-download"></i> Importeren
                                </button>
                            </div>
                            <p class="text-muted mb-2">${repo.description || 'Geen beschrijving'}</p>
                            <small class="text-muted">
                                <i class="lnr lnr-calendar-full"></i>
                                Bijgewerkt: ${updatedDate}
                            </small>
                        </div>
                    `;
                }).join('');

                $('#githubReposList').html(html);

                // Bind import events
                $('.import-repo').on('click', (e) => {
                    const repoData = $(e.currentTarget).data('repo');
                    this.importGitHubProject(repoData);
                });
            }

            async importGitHubProject(repoData) {
                try {
                    const response = await this.makeRequest('import_github_project', { 
                        repo_data: JSON.stringify(repoData)
                    });
                    
                    if (response.success) {
                        this.showAlert(response.message, 'success');
                        this.loadProjects();
                    } else {
                        this.showAlert('Fout bij importeren: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Fout bij importeren: ' + error.message, 'danger');
                }
            }

            loadGitHubSettings() {
                const savedUsername = localStorage.getItem('githubUsername');
                const savedToken = localStorage.getItem('githubToken');
                
                if (savedUsername) $('#githubUsername').val(savedUsername);
                if (savedToken) $('#githubToken').val(savedToken);
            }

            saveGitHubSettings() {
                localStorage.setItem('githubUsername', $('#githubUsername').val());
                localStorage.setItem('githubToken', $('#githubToken').val());
            }

            async makeRequest(action, data = {}) {
                const formData = new FormData();
                formData.append('action', action);
                
                Object.entries(data).forEach(([key, value]) => {
                    formData.append(key, value);
                });

                const response = await fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            }

            showAlert(message, type = 'success') {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                const icon = type === 'success' ? 'lnr-checkmark-circle' : 'lnr-warning';
                
                const html = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        <i class="lnr ${icon}"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                $('#alertContainer').html(html);
                
                // Auto hide after 5 seconds
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            }
        }

        // Initialize when document is ready
        $(document).ready(() => {
            new PortfolioAdmin();
        });
    </script>
</body>
</html>