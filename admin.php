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
        SUM(CASE WHEN category = 'vintage' THEN 1 ELSE 0 END) as vintage
        FROM projects WHERE is_deleted = 0");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'development' => 0, 'design' => 0, 'vintage' => 0];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_projects':
            echo json_encode(getProjects($pdo));
            exit;
            
        case 'check_errors':
            if (function_exists('error_get_last')) {
                $lastError = error_get_last();
                echo json_encode(array('success' => true, 'last_error' => $lastError));
            } else {
                echo json_encode(array('success' => true, 'last_error' => null));
            }
            exit;
            
        case 'test_db':
            try {
                $stmt = $pdo->query("DESCRIBE projects");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array('success' => true, 'columns' => $columns));
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
            exit;
            
        case 'save_project':
            error_log("Save project called with data: " . print_r($_POST, true));
            if (!empty($_FILES)) {
                error_log("Files uploaded: " . print_r($_FILES, true));
            }
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
            
        case 'get_statistics':
            echo json_encode(getStatistics($pdo));
            exit;
            
        case 'save_statistics':
            echo json_encode(saveStatistics($pdo, $_POST));
            exit;
            
        case 'clear_manual_setting':
            echo json_encode(clearManualSetting($pdo, $_POST['setting_key']));
            exit;
            
        case 'regenerate_sitemap':
            $result = regenerateSitemap();
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Sitemap succesvol gegenereerd!' : 'Fout bij genereren sitemap'
            ]);
            exit;
            
        case 'get_timeline_phases':
            echo json_encode(getTimelinePhases($pdo, $_POST['project_id']));
            exit;
            
        case 'save_timeline_phase':
            echo json_encode(saveTimelinePhase($pdo, $_POST));
            exit;
            
        case 'delete_timeline_phase':
            echo json_encode(deleteTimelinePhase($pdo, $_POST['phase_id']));
            exit;
    }
}

// Project management functions
function getProjects($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM projects WHERE is_deleted = 0 ORDER BY updated_at DESC");
        return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function saveProject($pdo, $data) {
    try {
        error_log("Starting saveProject with data keys: " . implode(', ', array_keys($data)));
        
        $timeline = isset($data['timeline']) ? json_encode($data['timeline']) : json_encode(array());
        $tools = json_encode(array_filter(explode(',', isset($data['tools']) ? trim($data['tools']) : '')));
        $features = json_encode(array_filter(explode("\n", isset($data['features']) ? trim($data['features']) : '')));
        
        // Handle gallery images - support both file uploads and manual URLs
        $gallery_images_array = array();
        
        // Handle uploaded files first
        if (isset($_FILES['gallery_files']) && !empty($_FILES['gallery_files']['name'][0])) {
            $upload_dir = 'img/uploads/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $uploaded_files = $_FILES['gallery_files'];
            $file_count = count($uploaded_files['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $uploaded_files['name'][$i];
                    $file_tmp = $uploaded_files['tmp_name'][$i];
                    $file_size = $uploaded_files['size'][$i];
                    $file_type = $uploaded_files['type'][$i];
                    
                    // Validate file type
                    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
                    if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) { // 5MB limit
                        
                        // Generate unique filename
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = 'gallery_' . time() . '_' . $i . '.' . $file_extension;
                        $target_path = $upload_dir . $unique_name;
                        
                        // Move uploaded file
                        if (move_uploaded_file($file_tmp, $target_path)) {
                            $gallery_images_array[] = array(
                                'url' => $target_path,
                                'alt' => 'Geüploade project afbeelding',
                                'caption' => pathinfo($file_name, PATHINFO_FILENAME)
                            );
                            error_log("Gallery file uploaded: " . $target_path);
                        } else {
                            error_log("Failed to move uploaded file: " . $file_name);
                        }
                    } else {
                        error_log("Invalid file type or size for: " . $file_name);
                    }
                }
            }
        }
        
        // Add manual URLs if provided (these will be added after uploaded files)
        if (isset($data['gallery_images']) && !empty(trim($data['gallery_images']))) {
            $manual_urls = array_filter(array_map('trim', explode("\n", $data['gallery_images'])));
            foreach ($manual_urls as $url) {
                $gallery_images_array[] = array(
                    'url' => $url,
                    'alt' => 'Project afbeelding',
                    'caption' => ''
                );
            }
        }
        
        $gallery_images = json_encode($gallery_images_array);
        error_log("Gallery images processed: " . $gallery_images);
        
        if (isset($data['id']) && !empty($data['id'])) {
            error_log("Updating existing project with ID: " . $data['id']);
            // Update existing project - simplified version
            $stmt = $pdo->prepare("UPDATE projects SET 
                title = ?, description = ?, short_description = ?, category = ?, status = ?,
                tools = ?, live_url = ?, demo_url = ?, features = ?, image_url = ?, 
                client_name = ?, project_duration = ?, completion_date = ?, 
                is_featured = ?, timeline = ?, gallery_images = ?,
                github_url = ?, api_docs_url = ?, challenges = ?,
                design_concept = ?, color_palette = ?, typography = ?,
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute(array(
                $data['title'], 
                $data['description'], 
                isset($data['short_description']) ? $data['short_description'] : '',
                $data['category'], 
                $data['status'],
                $tools, 
                isset($data['live_url']) ? $data['live_url'] : '', 
                isset($data['demo_url']) ? $data['demo_url'] : '', 
                $features, 
                isset($data['image_url']) ? $data['image_url'] : '', 
                isset($data['client_name']) ? $data['client_name'] : '',
                isset($data['project_duration']) ? $data['project_duration'] : '',
                isset($data['completion_date']) ? $data['completion_date'] : null,
                isset($data['is_featured']) ? 1 : 0,
                $timeline,
                $gallery_images,
                // Development fields
                isset($data['github_url']) ? $data['github_url'] : '',
                isset($data['api_docs_url']) ? $data['api_docs_url'] : '',
                isset($data['challenges']) ? $data['challenges'] : '',
                // Design fields
                isset($data['design_concept']) ? $data['design_concept'] : '',
                isset($data['color_palette']) ? $data['color_palette'] : '',
                isset($data['typography']) ? $data['typography'] : '',
                $data['id']
            ));
            
            error_log("Project updated successfully");
            return array('success' => true, 'message' => 'Project succesvol bijgewerkt!');
        } else {
            error_log("Creating new project");
            // Create new project - simplified version
            $stmt = $pdo->prepare("INSERT INTO projects 
                (title, description, short_description, category, status, tools, live_url, demo_url, 
                 features, image_url, client_name, project_duration, completion_date, is_featured, 
                 timeline, gallery_images, github_url, api_docs_url, challenges, design_concept, 
                 color_palette, typography, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute(array(
                $data['title'], 
                $data['description'], 
                isset($data['short_description']) ? $data['short_description'] : '',
                $data['category'], 
                $data['status'],
                $tools, 
                isset($data['live_url']) ? $data['live_url'] : '', 
                isset($data['demo_url']) ? $data['demo_url'] : '', 
                $features, 
                isset($data['image_url']) ? $data['image_url'] : '', 
                isset($data['client_name']) ? $data['client_name'] : '',
                isset($data['project_duration']) ? $data['project_duration'] : '',
                isset($data['completion_date']) ? $data['completion_date'] : null,
                isset($data['is_featured']) ? 1 : 0,
                $timeline,
                $gallery_images,
                // Development fields
                isset($data['github_url']) ? $data['github_url'] : '',
                isset($data['api_docs_url']) ? $data['api_docs_url'] : '',
                isset($data['challenges']) ? $data['challenges'] : '',
                // Design fields
                isset($data['design_concept']) ? $data['design_concept'] : '',
                isset($data['color_palette']) ? $data['color_palette'] : '',
                isset($data['typography']) ? $data['typography'] : ''
            ));
            
            error_log("New project created successfully");
            return array('success' => true, 'message' => 'Project succesvol toegevoegd!');
        }
    } catch (PDOException $e) {
        error_log("Database error in saveProject: " . $e->getMessage());
        return array('success' => false, 'message' => 'Database fout: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log("General error in saveProject: " . $e->getMessage());
        return array('success' => false, 'message' => 'Fout bij opslaan: ' . $e->getMessage());
    }
}

// Function to regenerate static sitemap.xml from sitemap.php
function regenerateSitemap() {
    try {
        // Capture output from sitemap.php
        ob_start();
        include 'sitemap.php';
        $sitemapContent = ob_get_clean();
        
        // Write to sitemap.xml
        $result = file_put_contents('sitemap.xml', $sitemapContent);
        
        if ($result !== false) {
            error_log("Sitemap regenerated successfully");
            return true;
        } else {
            error_log("Failed to write sitemap.xml");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error regenerating sitemap: " . $e->getMessage());
        return false;
    }
}

function deleteProject($pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Regenerate sitemap after deleting project
        regenerateSitemap();
        return ['success' => true, 'message' => 'Project succesvol verwijderd!'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function getProject($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($project) {
            $project['tools'] = json_decode($project['tools'], true);
            $project['features'] = json_decode($project['features'], true);
            $project['timeline'] = json_decode($project['timeline'], true);
            $project['gallery_images'] = json_decode($project['gallery_images'], true);
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
            
            // Regenerate sitemap after importing GitHub project
            regenerateSitemap();
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

// Statistics management functions
function getStatistics($pdo) {
    try {
        // Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $stmt = $pdo->query("
            SELECT setting_key, setting_value 
            FROM settings 
            WHERE setting_key IN (
                'stats_years_experience', 
                'stats_total_projects', 
                'stats_tools_count',
                'filter_development_count',
                'filter_design_count',
                'filter_photography_count',
                'filter_all_count',
                'github_username',
                'github_token',
                'filter_development_count_auto',
                'filter_design_count_auto',
                'filter_photography_count_auto',
                'filter_all_count_auto'
            )
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get actual counts from database for reference
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE is_deleted = 0");
        $actualProjectCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->query("
            SELECT 
                category,
                COUNT(*) as count
            FROM projects 
            WHERE is_deleted = 0 
            GROUP BY category
        ");
        $actualCategoryCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return [
            'success' => true, 
            'settings' => $settings,
            'actual_counts' => [
                'total_projects' => $actualProjectCount,
                'development' => (isset($actualCategoryCounts['development']) ? $actualCategoryCounts['development'] : 0) + 
                                (isset($actualCategoryCounts['web']) ? $actualCategoryCounts['web'] : 0),
                'design' => isset($actualCategoryCounts['design']) ? $actualCategoryCounts['design'] : 0,
                'vintage' => isset($actualCategoryCounts['vintage']) ? $actualCategoryCounts['vintage'] : 0
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function clearManualSetting($pdo, $settingKey) {
    try {
        $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?");
        $stmt->execute([$settingKey]);
        return ['success' => true, 'message' => 'Manual setting cleared successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function saveStatistics($pdo, $data) {
    try {
        // Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $statisticsToUpdate = [
            'stats_years_experience' => isset($data['years_experience']) ? (int)$data['years_experience'] : 3,
            'stats_total_projects' => isset($data['total_projects']) ? (int)$data['total_projects'] : null,
            'stats_tools_count' => isset($data['tools_count']) ? (int)$data['tools_count'] : 5,
            'github_username' => isset($data['github_username']) ? trim($data['github_username']) : null,
            'github_token' => isset($data['github_token']) ? trim($data['github_token']) : null,
            // Auto-calculation flags (always save these)
            'filter_development_count_auto' => isset($data['development_count_auto']) ? '1' : '0',
            'filter_design_count_auto' => isset($data['design_count_auto']) ? '1' : '0',
            'filter_photography_count_auto' => isset($data['photography_count_auto']) ? '1' : '0',
            'filter_all_count_auto' => isset($data['all_count_auto']) ? '1' : '0'
        ];
        
        // Only save manual count values if auto mode is disabled
        if (!isset($data['development_count_auto'])) {
            $statisticsToUpdate['filter_development_count'] = isset($data['development_count']) ? (int)$data['development_count'] : null;
        }
        if (!isset($data['design_count_auto'])) {
            $statisticsToUpdate['filter_design_count'] = isset($data['design_count']) ? (int)$data['design_count'] : null;
        }
        if (!isset($data['photography_count_auto'])) {
            $statisticsToUpdate['filter_photography_count'] = isset($data['photography_count']) ? (int)$data['photography_count'] : null;
        }
        if (!isset($data['all_count_auto'])) {
            $statisticsToUpdate['filter_all_count'] = isset($data['all_count']) ? (int)$data['all_count'] : null;
        }
        
        foreach ($statisticsToUpdate as $key => $value) {
            // Always save auto flags and non-empty values
            if ($value !== null && ($value > 0 || in_array($key, ['github_username', 'github_token']) || strpos($key, '_auto') !== false)) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, (string)$value]);
            }
        }
        
        return ['success' => true, 'message' => 'Statistics and GitHub settings updated successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// Timeline Phase Management Functions
function getTimelinePhases($pdo, $project_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM timeline_phases 
            WHERE project_id = ? 
            ORDER BY week_number ASC, phase_name ASC
        ");
        $stmt->execute([$project_id]);
        return [
            'success' => true, 
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function saveTimelinePhase($pdo, $data) {
    try {
        // Convert arrays to JSON strings
        $tasks = isset($data['tasks']) ? $data['tasks'] : '';
        $deliverables = isset($data['deliverables']) ? $data['deliverables'] : '';
        
        // Convert text areas to arrays and then to JSON
        if ($tasks) {
            $tasksArray = array_filter(array_map('trim', explode("\n", $tasks)));
            $tasks = json_encode($tasksArray);
        } else {
            $tasks = json_encode([]);
        }
        
        if ($deliverables) {
            $deliverablesArray = array_filter(array_map('trim', explode("\n", $deliverables)));
            $deliverables = json_encode($deliverablesArray);
        } else {
            $deliverables = json_encode([]);
        }
        
        if (isset($data['phase_id']) && !empty($data['phase_id'])) {
            // Update existing phase
            $stmt = $pdo->prepare("
                UPDATE timeline_phases SET 
                    phase_name = ?, phase_type = ?, phase_description = ?, phase_details = ?, 
                    week_number = ?, phase_status = ?, start_date = ?, end_date = ?,
                    tasks = ?, deliverables = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['phase_name'],
                isset($data['phase_type']) && $data['phase_type'] !== '' ? $data['phase_type'] : null,
                isset($data['phase_description']) ? $data['phase_description'] : '',
                isset($data['phase_details']) ? $data['phase_details'] : '',
                isset($data['week_number']) ? (int)$data['week_number'] : null,
                isset($data['phase_status']) ? $data['phase_status'] : 'planned',
                isset($data['start_date']) && $data['start_date'] ? $data['start_date'] : null,
                isset($data['end_date']) && $data['end_date'] ? $data['end_date'] : null,
                $tasks,
                $deliverables,
                $data['phase_id']
            ]);
            return ['success' => true, 'message' => 'Timeline fase succesvol bijgewerkt!'];
        } else {
            // Create new phase
            $stmt = $pdo->prepare("
                INSERT INTO timeline_phases 
                (project_id, phase_name, phase_type, phase_description, phase_details, week_number, 
                 phase_status, start_date, end_date, tasks, deliverables, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $data['project_id'],
                $data['phase_name'],
                isset($data['phase_type']) && $data['phase_type'] !== '' ? $data['phase_type'] : null,
                isset($data['phase_description']) ? $data['phase_description'] : '',
                isset($data['phase_details']) ? $data['phase_details'] : '',
                isset($data['week_number']) ? (int)$data['week_number'] : null,
                isset($data['phase_status']) ? $data['phase_status'] : 'planned',
                isset($data['start_date']) && $data['start_date'] ? $data['start_date'] : null,
                isset($data['end_date']) && $data['end_date'] ? $data['end_date'] : null,
                $tasks,
                $deliverables
            ]);
            return ['success' => true, 'message' => 'Nieuwe timeline fase succesvol toegevoegd!'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteTimelinePhase($pdo, $phase_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM timeline_phases WHERE id = ?");
        $stmt->execute([$phase_id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Timeline fase succesvol verwijderd!'];
        } else {
            return ['success' => false, 'error' => 'Fase niet gevonden'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Admin - Project Management</title>
    
    <!-- Preload Fonts - Portfolio Theme -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Work+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Work+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <!-- CSS Preloading -->
    <link rel="preload" href="vendor/bootstrap/bootstrap.min.css" as="style">
    <link rel="preload" href="css/style.min.css" as="style">
    <link rel="preload" href="css/admin.css" as="style">
    
    <!-- Bootstrap CSS -->
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    
    <!-- Core Button & Base Styles (needed for admin buttons) -->
    <link rel="stylesheet" href="css/style.min.css">
    
    <!-- Icon Libraries -->
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin CSS (loads last to override others) -->
    <link href="css/admin.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" href="img/favicon-32x32.png" type="image/png">
</head>

<body class="admin-page">
    <div class="container-fluid admin-container">
        
        <!-- Portfolio-style Hero Section -->
        <div class="admin-hero">
            <div class="admin-hero-content">
                <h1>Portfolio Admin</h1>
                <p>Beheer je projecten, update content en bekijk statistieken met moderne stijl</p>
                <div class="admin-nav">
                    <a href="index.php" class="admin-nav-btn portfolio-btn">
                        <i class="lnr lnr-home"></i>
                        Portfolio
                    </a>
                    <button type="button" class="admin-nav-btn logout" id="logoutBtn">
                        <i class="lnr lnr-exit"></i>
                        Uitloggen
                    </button>
                </div>
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
                            <div class="stat-number" id="vintageProjects"><?php echo $stats['vintage']; ?></div>
                            <div class="stat-label">Vintage</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Main Admin Sections -->
        <div class="admin-section">
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
                        
                        <!-- Project Type Guide -->
                        <div class="alert alert-info mb-4" id="projectTypeGuide" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="lnr lnr-code"></i> Development Projects</h6>
                                    <small>Voor web apps, websites, mobile apps, en software projecten. Inclusief technische specificaties, GitHub links, en API documentatie.</small>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="lnr lnr-magic-wand"></i> Design Projects</h6>
                                    <small>Voor logo's, branding, print design, en visuele projecten. Inclusief kleurenpalet, typografie, en creatieve highlights.</small>
                                </div>
                            </div>
                        </div>
                        
                        <form id="projectForm">
                            <input type="hidden" id="projectId" name="id">
                            
                            <div class="mb-3">
                                <label for="projectTitle" class="form-label">Project Titel</label>
                                <input type="text" class="form-control" id="projectTitle" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectDescription" class="form-label">Volledige Beschrijving</label>
                                <textarea class="form-control" id="projectDescription" name="description" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectShortDescription" class="form-label">Korte Beschrijving</label>
                                <textarea class="form-control" id="projectShortDescription" name="short_description" rows="2" placeholder="Korte samenvatting voor previews"></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectCategory" class="form-label">Categorie</label>
                                    <select class="form-select" id="projectCategory" name="category" required>
                                        <option value="">Selecteer primaire categorie</option>
                                        <option value="development" data-type="dev">💻 Development</option>
                                        <option value="web" data-type="dev">🌐 Web Development</option>
                                        <option value="mobile" data-type="dev">📱 Mobile App</option>
                                        <option value="design" data-type="design">🎨 Design</option>
                                        <option value="vintage" data-type="design">📸 Vintage/Photography</option>
                                        <option value="other" data-type="basic">📄 Overig</option>
                                    </select>
                                    <small class="text-muted" id="categoryHint">
                                        Selecteer primaire categorie. Development en Design velden kunnen beide gebruikt worden voor hybride projecten.
                                    </small>
                                </div>
                                <div class="col-md-6" id="projectTypeSection" style="display: none;">
                                    <label class="form-label">Project Type (Multi-selectie)</label>
                                    <div class="project-type-checkboxes mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="typeWeb" value="web">
                                            <label class="form-check-label" for="typeWeb">🌐 Web</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="typeMobile" value="mobile">
                                            <label class="form-check-label" for="typeMobile">📱 Mobile</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="typeDesign" value="design">
                                            <label class="form-check-label" for="typeDesign">🎨 Design</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="typeDevelopment" value="development">
                                            <label class="form-check-label" for="typeDevelopment">💻 Development</label>
                                        </div>
                                    </div>
                                    <small class="text-muted">Selecteer alle toepasselijke types voor hybride projecten</small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectYear" class="form-label">Jaar</label>
                                    <input type="number" class="form-control" id="projectYear" name="year" min="2000" max="2030">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="isHybridProject">
                                        <label class="form-check-label" for="isHybridProject">
                                            <strong>🔄 Hybride Project</strong>
                                            <small class="d-block text-muted">Toon zowel development als design velden</small>
                                        </label>
                                        <div id="hybridIndicator" class="mt-2 text-success small" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectStatus" class="form-label">Status</label>
                                    <select class="form-select" id="projectStatus" name="status">
                                        <option value="completed">Voltooid</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="planned">Gepland</option>
                                        <option value="archived">Gearchiveerd</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="projectLiveUrl" class="form-label">Live URL (optioneel)</label>
                                    <input type="url" class="form-control" id="projectLiveUrl" name="live_url">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="projectDemoUrl" class="form-label">Demo URL (optioneel)</label>
                                    <input type="url" class="form-control" id="projectDemoUrl" name="demo_url">
                                </div>
                                <div class="col-md-6">
                                    <label for="projectClientName" class="form-label">Klant (optioneel)</label>
                                    <input type="text" class="form-control" id="projectClientName" name="client_name" placeholder="Naam van de klant">
                                </div>
                            </div>
                            
                            <!-- Gallery Checkbox Section -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enableGallery" name="enable_gallery">
                                        <label class="form-check-label" for="enableGallery">
                                            <strong>📸 Image Gallery</strong>
                                            <small class="d-block text-muted">Toon een afbeeldingengalerij voor dit project (aanbevolen voor design projecten of projecten zonder live demo)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gallery Upload Section -->
                            <div id="gallerySection" class="mb-4" style="display: none;">
                                <label class="form-label">
                                    <i class="lnr lnr-picture"></i>
                                    Project Afbeeldingen
                                </label>
                                <div class="gallery-upload-area">
                                    <input type="file" id="galleryUpload" name="gallery_files[]" multiple accept="image/*" style="display: none;">
                                    <div id="galleryPreview" class="gallery-preview">
                                        <div class="gallery-item upload-placeholder" onclick="$('#galleryUpload').click()">
                                            <i class="lnr lnr-plus-circle"></i>
                                            <span>Afbeeldingen toevoegen</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Manual Gallery URLs -->
                                <div class="mt-3">
                                    <label for="galleryUrls" class="form-label">
                                        <i class="lnr lnr-link"></i>
                                        Galerij URLs (handmatig)
                                    </label>
                                    <textarea class="form-control" id="galleryUrls" name="gallery_images" rows="3" 
                                              placeholder="img/project1.jpg&#10;img/project2.png&#10;img/project3.jpg"></textarea>
                                    <small class="text-muted">
                                        Een URL per regel. Deze worden gecombineerd met geüploade afbeeldingen.
                                    </small>
                                </div>
                                
                                <small class="text-muted">
                                    <i class="lnr lnr-question-circle"></i>
                                    Upload screenshots, mockups, of andere visuele elementen van je project. Ondersteunt JPG, PNG, GIF (max 5MB per afbeelding).
                                </small>
                            </div>
                            
                            <!-- Hybrid Project Info -->
                            <div id="hybridProjectInfo" class="alert alert-info" style="display: none;">
                                <div class="d-flex">
                                    <i class="lnr lnr-layers me-2 mt-1"></i>
                                    <div>
                                        <strong>Hybride Project Tip:</strong><br>
                                        <small>
                                            Vul zowel development als design velden in om beide fasen in de project timeline te tonen. 
                                            Je project kan bijvoorbeeld zowel technische development als creatieve design aspecten hebben.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dynamic Form Sections Based on Category -->
                            
                            <!-- Development-specific fields -->
                            <div id="developmentFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="projectTools" class="form-label">
                                        <i class="lnr lnr-code"></i>
                                        Technologieën/Frameworks
                                    </label>
                                    <input type="text" class="form-control" id="projectTools" name="tools" 
                                           placeholder="React, Node.js, MongoDB, AWS (gescheiden door komma's)">
                                    <small class="text-muted">Programmeer talen, frameworks, databases, cloud services</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="projectFeatures" class="form-label">
                                        <i class="lnr lnr-list"></i>
                                        Technische Features
                                    </label>
                                    <textarea class="form-control" id="projectFeatures" name="features" rows="4" 
                                              placeholder="User authentication&#10;REST API integration&#10;Real-time notifications&#10;Responsive design"></textarea>
                                    <small class="text-muted">Elke functionaliteit op een nieuwe regel</small>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="projectGitHub" class="form-label">
                                            <i class="fab fa-github"></i>
                                            GitHub Repository
                                        </label>
                                        <input type="url" class="form-control" id="projectGitHub" name="github_url" 
                                               placeholder="https://github.com/username/repo">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="projectApiDocs" class="form-label">
                                            <i class="lnr lnr-book"></i>
                                            API Documentatie
                                        </label>
                                        <input type="url" class="form-control" id="projectApiDocs" name="api_docs_url" 
                                               placeholder="Link naar API documentatie">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="projectChallenges" class="form-label">
                                        <i class="lnr lnr-warning"></i>
                                        Technische Uitdagingen & Oplossingen
                                    </label>
                                    <textarea class="form-control" id="projectChallenges" name="challenges" rows="3" 
                                              placeholder="Welke problemen heb je opgelost en hoe?"></textarea>
                                </div>
                                
                                <!-- Project Statistics -->
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="lnr lnr-chart-bars"></i>
                                        Project Statistieken
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="performanceScore" class="form-label">
                                                <i class="lnr lnr-rocket"></i>
                                                Performance Score
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="performanceScore" name="performance_score" 
                                                       min="1" max="100" value="92" placeholder="92">
                                                <span class="input-group-text">/100</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="codeQuality" class="form-label">
                                                <i class="lnr lnr-checkmark-circle"></i>
                                                Code Kwaliteit
                                            </label>
                                            <select class="form-select" id="codeQuality" name="code_quality">
                                                <option value="A+">A+ (Excellent)</option>
                                                <option value="A">A (Very Good)</option>
                                                <option value="B+">B+ (Good)</option>
                                                <option value="B">B (Satisfactory)</option>
                                                <option value="C">C (Needs Improvement)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label for="linesOfCode" class="form-label">
                                                <i class="lnr lnr-code"></i>
                                                Lines of Code
                                            </label>
                                            <input type="number" class="form-control" id="linesOfCode" name="lines_of_code" 
                                                   min="1" value="2500" placeholder="2500">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="componentsCount" class="form-label">
                                                <i class="lnr lnr-layers"></i>
                                                Componenten
                                            </label>
                                            <input type="number" class="form-control" id="componentsCount" name="components_count" 
                                                   min="1" value="25" placeholder="25">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="developmentWeeks" class="form-label">
                                                <i class="lnr lnr-calendar-full"></i>
                                                Ontwikkelingstijd (weken)
                                            </label>
                                            <input type="number" class="form-control" id="developmentWeeks" name="development_weeks" 
                                                   min="1" max="52" value="5" placeholder="5">
                                        </div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="lnr lnr-question-circle"></i>
                                        Deze statistieken worden gebruikt voor de project detail pagina's
                                    </small>
                                </div>
                                
                                <!-- Development Process Section -->
                                <div id="developmentProcessSection" style="display: none;">
                                    <hr class="my-4">
                                    <h6 class="text-primary mb-3">
                                        <i class="lnr lnr-rocket"></i>
                                        Development Proces
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label for="developmentMethodology" class="form-label">
                                            <i class="lnr lnr-layers"></i>
                                            Ontwikkelmethodologie
                                        </label>
                                        <select class="form-select" id="developmentMethodology" name="development_methodology">
                                            <option value="">Selecteer methodologie</option>
                                            <option value="agile">Agile/Scrum</option>
                                            <option value="waterfall">Waterfall</option>
                                            <option value="kanban">Kanban</option>
                                            <option value="iterative">Iterative Development</option>
                                            <option value="custom">Custom Approach</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="developmentPhases" class="form-label">
                                            <i class="lnr lnr-list"></i>
                                            Ontwikkelingsfasen
                                        </label>
                                        <textarea class="form-control" id="developmentPhases" name="development_phases" rows="4" 
                                                  placeholder="1. Requirements Analysis&#10;2. Design & Prototyping&#10;3. Development&#10;4. Testing & QA&#10;5. Deployment"></textarea>
                                        <small class="text-muted">Beschrijf de belangrijkste fasen van het ontwikkelproces</small>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="testingStrategy" class="form-label">
                                                <i class="lnr lnr-checkmark-circle"></i>
                                                Testing Strategie
                                            </label>
                                            <input type="text" class="form-control" id="testingStrategy" name="testing_strategy" 
                                                   placeholder="Unit tests, Integration tests, E2E tests">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="deploymentMethod" class="form-label">
                                                <i class="lnr lnr-cloud-upload"></i>
                                                Deployment Methode
                                            </label>
                                            <input type="text" class="form-control" id="deploymentMethod" name="deployment_method" 
                                                   placeholder="CI/CD, Docker, Vercel, AWS">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Design-specific fields -->
                            <div id="designFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="designTools" class="form-label">
                                        <i class="lnr lnr-magic-wand"></i>
                                        Design Tools
                                    </label>
                                    <input type="text" class="form-control" id="designTools" name="tools" 
                                           placeholder="Figma, Photoshop, Illustrator, InDesign (gescheiden door komma's)">
                                    <small class="text-muted">Design software en tools die je hebt gebruikt</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="designConcept" class="form-label">
                                        <i class="lnr lnr-lightbulb"></i>
                                        Design Concept & Inspiratie
                                    </label>
                                    <textarea class="form-control" id="designConcept" name="design_concept" rows="3" 
                                              placeholder="Wat was je inspiratie? Welke stijl heb je gehanteerd?"></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="colorPalette" class="form-label">
                                            <i class="lnr lnr-drop"></i>
                                            Kleurenpalet
                                        </label>
                                        <input type="text" class="form-control" id="colorPalette" name="color_palette" 
                                               placeholder="#FF5733, #33FF57, #3357FF">
                                        <small class="text-muted">Hoofdkleuren van het ontwerp</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="typography" class="form-label">
                                            <i class="lnr lnr-text-format"></i>
                                            Typografie
                                        </label>
                                        <input type="text" class="form-control" id="typography" name="typography" 
                                               placeholder="Montserrat, Open Sans, Arial">
                                        <small class="text-muted">Gebruikte fonts</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="designProcess" class="form-label">
                                        <i class="lnr lnr-layers"></i>
                                        Creatieve Highlights
                                    </label>
                                    <textarea class="form-control" id="designProcess" name="features" rows="4" 
                                              placeholder="Uniek logo ontwerp&#10;Moderne kleurencombinatie&#10;Minimalistische stijl&#10;Responsive layout"></textarea>
                                    <small class="text-muted">Elke highlight op een nieuwe regel</small>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="designCategory" class="form-label">
                                            <i class="lnr lnr-tag"></i>
                                            Design Categorie
                                        </label>
                                        <select class="form-select" id="designCategory" name="design_category">
                                            <option value="">Selecteer subcategorie</option>
                                            <option value="logo">Logo Design</option>
                                            <option value="web">Web Design</option>
                                            <option value="print">Print Design</option>
                                            <option value="branding">Branding</option>
                                            <option value="illustration">Illustratie</option>
                                            <option value="other">Overig</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="designStyle" class="form-label">
                                            <i class="lnr lnr-star"></i>
                                            Design Stijl
                                        </label>
                                        <select class="form-select" id="designStyle" name="design_style">
                                            <option value="">Selecteer stijl</option>
                                            <option value="minimalist">Minimalistisch</option>
                                            <option value="modern">Modern</option>
                                            <option value="vintage">Vintage</option>
                                            <option value="creative">Creatief</option>
                                            <option value="professional">Professioneel</option>
                                            <option value="artistic">Artistiek</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Universal fields (shown for both) -->
                            <div id="universalFields">
                                <!-- Creative Process Section -->
                                <div class="mb-4">
                                    <h6 class="text-info mb-3">
                                        <i class="lnr lnr-magic-wand"></i>
                                        Creatief Proces
                                    </h6>
                                    
                                    <div class="mb-3">
                                        <label for="creativeChallenge" class="form-label">
                                            <i class="lnr lnr-question-circle"></i>
                                            Uitdaging & Doelstelling
                                        </label>
                                        <textarea class="form-control" id="creativeChallenge" name="creative_challenge" rows="2" 
                                                  placeholder="Wat was de hoofduitdaging van dit project? Wat wilde je bereiken?"></textarea>
                                        <small class="text-muted">Beschrijf het probleem dat je wilde oplossen of het doel dat je wilde bereiken</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="creativeApproach" class="form-label">
                                            <i class="lnr lnr-bubble"></i>
                                            Aanpak & Methode
                                        </label>
                                        <textarea class="form-control" id="creativeApproach" name="creative_approach" rows="3" 
                                                  placeholder="Hoe ben je het project aangepakt? Welke methoden heb je gebruikt?"></textarea>
                                        <small class="text-muted">Leg uit je werkwijze en de stappen die je hebt genomen</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="creativeSolution" class="form-label">
                                            <i class="lnr lnr-checkmark-circle"></i>
                                            Oplossing & Resultaat
                                        </label>
                                        <textarea class="form-control" id="creativeSolution" name="creative_solution" rows="3" 
                                                  placeholder="Wat was je uiteindelijke oplossing? Wat heb je bereikt?"></textarea>
                                        <small class="text-muted">Beschrijf het eindresultaat en wat je hebt geleerd</small>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="inspirationSource" class="form-label">
                                                <i class="lnr lnr-lightbulb"></i>
                                                Inspiratie Bron
                                            </label>
                                            <input type="text" class="form-control" id="inspirationSource" name="inspiration_source" 
                                                   placeholder="Dribbble, andere websites, natuur, etc.">
                                            <small class="text-muted">Waar heb je inspiratie opgedaan?</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lessonsLearned" class="form-label">
                                                <i class="lnr lnr-graduation-hat"></i>
                                                Geleerde Lessen
                                            </label>
                                            <input type="text" class="form-control" id="lessonsLearned" name="lessons_learned" 
                                                   placeholder="Nieuwe skills, insights, verbeterpunten">
                                            <small class="text-muted">Wat heb je geleerd tijdens dit project?</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="projectImage" class="form-label">Afbeelding URL</label>
                                <input type="text" class="form-control" id="projectImage" name="image_url" value="img/Logo.png">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="projectDuration" class="form-label">Project Duur (optioneel)</label>
                                    <input type="text" class="form-control" id="projectDuration" name="project_duration" placeholder="bv. 3 maanden">
                                </div>
                                <div class="col-md-4">
                                    <label for="projectCompletionDate" class="form-label">Voltooiingsdatum</label>
                                    <input type="date" class="form-control" id="projectCompletionDate" name="completion_date">
                                </div>
                            </div>
                            
                            <!-- Timeline Management Section -->
                            <div class="mb-4" id="timelineSection">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="lnr lnr-calendar-full"></i>
                                        Project Timeline
                                    </h5>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addTimelinePhaseBtn">
                                        <i class="lnr lnr-plus-circle"></i>
                                        Fase Toevoegen
                                    </button>
                                </div>
                                
                                <!-- Timeline Phases Container -->
                                <div id="timelinePhasesContainer">
                                    <div class="timeline-empty text-center text-muted py-3" id="timelineEmpty">
                                        <i class="lnr lnr-calendar-full" style="font-size: 2rem; opacity: 0.3;"></i>
                                        <p class="mb-0 mt-2">Geen timeline fasen toegevoegd</p>
                                        <small>Klik op "Fase Toevoegen" om te beginnen</small>
                                    </div>
                                </div>
                                
                                <!-- Timeline Phase Form (Hidden by default) -->
                                <div id="timelinePhaseFormInline" style="display: none;">
                                    <div class="border rounded p-3 mt-3" style="background-color: #f8f9fa;">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">
                                                <i class="lnr lnr-pencil"></i>
                                                <span id="inlinePhaseFormTitle">Nieuwe Timeline Fase</span>
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelInlinePhaseForm">
                                                <i class="lnr lnr-cross"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label for="inlinePhaseName" class="form-label form-label-sm">Fase Naam</label>
                                                <input type="text" class="form-control form-control-sm" id="inlinePhaseName" 
                                                       placeholder="bijv. Design & Planning">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="inlinePhaseType" class="form-label form-label-sm">Type</label>
                                                <select class="form-select form-select-sm" id="inlinePhaseType">
                                                    <option value="">Selecteer...</option>
                                                    <option value="planning">Planning</option>
                                                    <option value="design">Design</option>
                                                    <option value="development">Development</option>
                                                    <option value="testing">Testing</option>
                                                    <option value="deployment">Deployment</option>
                                                    <option value="challenge">Challenge</option>
                                                    <option value="approach">Approach</option>
                                                    <option value="solution">Solution</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="inlinePhaseWeek" class="form-label form-label-sm">Week</label>
                                                <input type="number" class="form-control form-control-sm" id="inlinePhaseWeek" 
                                                       min="1" max="52" placeholder="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label for="inlinePhaseDescription" class="form-label form-label-sm">Korte Beschrijving</label>
                                            <textarea class="form-control form-control-sm" id="inlinePhaseDescription" rows="2" 
                                                      placeholder="Korte samenvatting van deze fase"></textarea>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label for="inlinePhaseDetails" class="form-label form-label-sm">Gedetailleerde Beschrijving</label>
                                            <textarea class="form-control form-control-sm" id="inlinePhaseDetails" rows="3" 
                                                      placeholder="Gedetailleerde uitleg van activiteiten en processen"></textarea>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label for="inlinePhaseStatus" class="form-label form-label-sm">Status</label>
                                                <select class="form-select form-select-sm" id="inlinePhaseStatus">
                                                    <option value="planned">Gepland</option>
                                                    <option value="in_progress">In Uitvoering</option>
                                                    <option value="completed">Voltooid</option>
                                                    <option value="skipped">Overgeslagen</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="inlinePhaseStartDate" class="form-label form-label-sm">Start Datum</label>
                                                <input type="date" class="form-control form-control-sm" id="inlinePhaseStartDate">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="inlinePhaseEndDate" class="form-label form-label-sm">Eind Datum</label>
                                                <input type="date" class="form-control form-control-sm" id="inlinePhaseEndDate">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="inlinePhaseTasks" class="form-label form-label-sm">Taken</label>
                                                <textarea class="form-control form-control-sm" id="inlinePhaseTasks" rows="3" 
                                                          placeholder="Elke taak op een nieuwe regel"></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="inlinePhaseDeliverables" class="form-label form-label-sm">Opgeleverd</label>
                                                <textarea class="form-control form-control-sm" id="inlinePhaseDeliverables" rows="3" 
                                                          placeholder="Elke deliverable op een nieuwe regel"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm" id="saveInlinePhase">
                                                <i class="lnr lnr-checkmark-circle"></i>
                                                Fase Opslaan
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" id="cancelInlinePhase">
                                                <i class="lnr lnr-cross-circle"></i>
                                                Annuleren
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="projectFeatured" name="is_featured" value="1">
                                    <label class="form-check-label" for="projectFeatured">
                                        Uitgelicht Project (tonen op homepage)
                                    </label>
                                </div>
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
                        <button type="button" class="btn btn-primary btn-sm" id="refreshProjects">
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
        </div>
            <!-- Statistics Management Section -->
        <div class="admin-section">
            <div class="row mt-4">
                <div class="col-12">
                    <div class="admin-card">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="lnr lnr-chart-bars"></i>
                            Website Statistieken Beheer
                        </h3>
                        <p class="mb-0 text-white">Beheer de getallen die op je portfolio website worden getoond</p>
                    </div>
                    <div class="card-body p-4">
                        <form id="statisticsForm">
                            <div class="row">
                                <!-- Homepage Statistics -->
                                <div class="col-lg-6 mb-4">
                                    <h5><i class="lnr lnr-home"></i> Homepage Statistieken</h5>
                                    <div class="mb-3">
                                        <label for="yearsExperience" class="form-label">Jaar Ervaring</label>
                                        <input type="number" class="form-control" id="yearsExperience" min="1" max="50" placeholder="3">
                                        <small class="form-text text-muted">Wordt getoond als "X+ Jaar Ervaring"</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="totalProjects" class="form-label">Totaal Projecten</label>
                                        <input type="number" class="form-control" id="totalProjects" min="1" max="999" placeholder="15">
                                        <small class="form-text text-muted">Overschrijft database telling. Laat leeg voor automatische telling</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="toolsCount" class="form-label">Aantal Tools</label>
                                        <input type="number" class="form-control" id="toolsCount" min="1" max="50" placeholder="5">
                                        <small class="form-text text-muted">Wordt getoond als "X+ Tools"</small>
                                    </div>
                                </div>
                                
                                <!-- Filter Counts -->
                                <div class="col-lg-6 mb-4">
                                    <h5><i class="lnr lnr-funnel"></i> Portfolio Filter Aantallen</h5>
                                    
                                    <div class="mb-3">
                                        <label for="developmentCount" class="form-label">Development Projecten</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="developmentCount" min="0" max="999" placeholder="2">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="form-check-input" id="developmentCountAuto" title="Auto-berekenen">
                                                <label class="form-check-label ms-1" for="developmentCountAuto">Auto</label>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Aantal development projecten in filter (Auto = automatisch berekend)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="designCount" class="form-label">Design Projecten</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="designCount" min="0" max="999" placeholder="12">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="form-check-input" id="designCountAuto" title="Auto-berekenen">
                                                <label class="form-check-label ms-1" for="designCountAuto">Auto</label>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Aantal design projecten in filter (Auto = automatisch berekend)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="photographyCount" class="form-label">Fotografie/Persoonlijke Projecten</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="photographyCount" min="0" max="999" placeholder="4">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="form-check-input" id="photographyCountAuto" title="Auto-berekenen">
                                                <label class="form-check-label ms-1" for="photographyCountAuto">Auto</label>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Aantal vrije tijd/fotografie projecten in filter (Auto = automatisch berekend)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="allCount" class="form-label">Alle Projecten (Filter)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="allCount" min="0" max="999" placeholder="7">
                                            <div class="input-group-text">
                                                <input type="checkbox" class="form-check-input" id="allCountAuto" title="Auto-berekenen">
                                                <label class="form-check-label ms-1" for="allCountAuto">Auto</label>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Totaal aantal projecten in "Alle" filter (Auto = automatisch berekend)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- GitHub Settings -->
                            <div class="row">
                                <div class="col-12 mb-4">
                                    <h5><i class="fab fa-github"></i> GitHub Instellingen</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="githubUsernameStats" class="form-label">GitHub Gebruikersnaam</label>
                                                <input type="text" class="form-control" id="githubUsernameStats" placeholder="tiebocroons">
                                                <small class="form-text text-muted">Voor GitHub API integratie</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="githubTokenStats" class="form-label">Personal Access Token</label>
                                                <input type="password" class="form-control" id="githubTokenStats" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
                                                <small class="form-text text-muted">Voor verhoogde API limits</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Current Database Values Reference -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-light">
                                        <h6>
                                            <i class="lnr lnr-database"></i> 
                                            Huidige Database Waardes (ter referentie)
                                            <small class="text-muted float-end" id="statsLoading" style="display: none;">
                                                <i class="lnr lnr-sync rotating"></i> Bijwerken...
                                            </small>
                                        </h6>
                                        <div class="row" id="actualCounts">
                                            <div class="col-md-3">
                                                <strong>Totaal Projecten:</strong> <span id="actualTotal">-</span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Development:</strong> <span id="actualDevelopment">-</span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Design:</strong> <span id="actualDesign">-</span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Vintage:</strong> <span id="actualVintage">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="lnr lnr-checkmark-circle"></i>
                                        Statistieken Bijwerken
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="loadCurrentStats">
                                        <i class="lnr lnr-sync"></i>
                                        Huidige Waardes Laden
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" id="forceAutoUpdate" title="Force update auto fields">
                                        <i class="lnr lnr-magic-wand"></i>
                                        Auto Bijwerken
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" id="regenerateSitemap" title="Regenereer sitemap.xml voor Google Search Console">
                                        <i class="lnr lnr-map"></i>
                                        Sitemap Regenereren
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="lnr lnr-warning"></i>
                                    Wijzigingen zijn direct zichtbaar op de website
                                </small>
                            </div>
                        </form>
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
                        <button type="button" class="btn btn-outline-light btn-sm" id="loadGitHubRepos">
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
            this.hybridCheckTimeout = null;
            this.init();
        }            init() {
                this.bindEvents();
                this.loadProjects();
                this.loadGitHubSettings();
                this.setupTimelineManagement();
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

                // Statistics management
                $('#statisticsForm').on('submit', (e) => {
                    e.preventDefault();
                    this.saveStatistics();
                });

                $('#loadCurrentStats').on('click', () => {
                    this.loadStatistics();
                });

                $('#forceAutoUpdate').on('click', () => {
                    this.forceUpdateAutoFields();
                });

                $('#regenerateSitemap').on('click', () => {
                    this.regenerateSitemap();
                });

                // Auto-calculation toggle handlers
                $('#developmentCountAuto, #designCountAuto, #photographyCountAuto, #allCountAuto').on('change', (e) => {
                    const $checkbox = $(e.target);
                    const isChecked = $checkbox.is(':checked');
                    const checkboxId = $checkbox.attr('id');
                    
                    // Add visual feedback
                    $checkbox.parent().toggleClass('text-primary', isChecked);
                    
                    // Apply input toggle
                    this.toggleAutoInputs();
                    
                    // Brief visual feedback
                    $checkbox.parent().addClass('form-updated');
                    setTimeout(() => {
                        $checkbox.parent().removeClass('form-updated');
                    }, 300);
                    
                    // If switching to auto mode, clear any saved manual value
                    if (isChecked) {
                        this.clearSavedManualValue(checkboxId);
                    }
                });

                // Load statistics on init
                this.loadStatistics();

                // Logout
                $('#logoutBtn').on('click', () => {
                    if (confirm('Weet je zeker dat je wilt uitloggen?')) {
                        window.location.href = 'logout.php';
                    }
                });

                // Set current year
                $('#projectYear').val(new Date().getFullYear());
                
                // Category change handler for dynamic form sections
                $('#projectCategory').on('change', () => {
                    this.toggleFormSections();
                });
                
                // Project type checkboxes change handler
                $('.project-type-checkboxes input').on('change', () => {
                    this.toggleFormSections();
                    this.updateHybridProjectIndicator();
                });
                
                // Hybrid project checkbox change handler
                $('#isHybridProject').on('change', () => {
                    this.toggleFormSections();
                });
                
                // Gallery checkbox change handler
                $('#enableGallery').on('change', () => {
                    if ($('#enableGallery').is(':checked')) {
                        $('#gallerySection').slideDown(300);
                    } else {
                        $('#gallerySection').slideUp(300);
                    }
                });
                
                // Live URL field change handler (affects gallery visibility)
                $('#projectLiveUrl').on('input blur', () => {
                    setTimeout(() => {
                        this.toggleFormSections();
                    }, 200);
                });
                
                // Hybrid project detection - listen for changes in key fields
                const hybridTriggerFields = '#projectGitHub, #projectChallenges, #linesOfCode, #developmentWeeks, #designConcept, #colorPalette, #typography, #designStyle, #designProcess';
                $(hybridTriggerFields).on('input change blur', (e) => {
                    // Prevent any accidental form submission
                    e.stopPropagation();
                    // Only update hybrid indicator on input/change, not form sections
                    clearTimeout(this.hybridCheckTimeout);
                    this.hybridCheckTimeout = setTimeout(() => {
                        this.updateHybridProjectIndicator();
                        // Only toggle sections on blur or significant changes
                        if (e.type === 'blur' || e.type === 'change') {
                            this.toggleFormSections();
                        }
                    }, 200);
                });
                
                // Initialize form sections
                this.toggleFormSections();
                
                // Initialize hybrid project indicator
                this.updateHybridProjectIndicator();
                
                // Prevent Enter key from submitting form on input fields
                $('#projectForm input[type="text"], #projectForm input[type="url"], #projectForm input[type="number"], #projectForm select').on('keypress', function(e) {
                    if (e.which === 13) { // Enter key
                        e.preventDefault();
                        return false;
                    }
                });
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
                    
                    // Collect selected project types
                    const selectedTypes = [];
                    $('.project-type-checkboxes input:checked').each(function() {
                        selectedTypes.push($(this).val());
                    });
                    formData.append('project_types', JSON.stringify(selectedTypes));
                    
                    // Add hybrid project indicator
                    formData.append('is_hybrid', $('#isHybridProject').is(':checked') ? '1' : '0');
                    
                    // Add timeline data
                    const timelineData = this.getTimelineDataForSave();
                    formData.append('timeline', JSON.stringify(timelineData));

                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.resetForm();
                        this.loadProjects();
                        // Update statistics counts after save with small delay
                        setTimeout(() => {
                            this.loadStatistics();
                        }, 500);
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
                        $('#projectShortDescription').val(project.short_description || '');
                        $('#projectCategory').val(project.category);
                        $('#projectStatus').val(project.status);
                        $('#projectLiveUrl').val(project.live_url || '');
                        $('#projectDemoUrl').val(project.demo_url || '');
                        $('#projectImage').val(project.image_url || '');
                        $('#projectClientName').val(project.client_name || '');
                        $('#projectDuration').val(project.project_duration || '');
                        $('#projectCompletionDate').val(project.completion_date || '');
                        $('#projectYear').val(project.year || new Date().getFullYear());
                        $('#projectFeatured').prop('checked', project.is_featured == 1);
                        
                        // Handle tools for both development and design
                        if (project.tools && Array.isArray(project.tools)) {
                            const toolsArray = JSON.parse(project.tools);
                            $('#projectTools, #designTools').val(toolsArray.join(', '));
                        }
                        
                        // Handle features/highlights
                        if (project.features && Array.isArray(project.features)) {
                            const featuresArray = JSON.parse(project.features);
                            $('#projectFeatures, #designProcess').val(featuresArray.join('\n'));
                        }
                        
                        // Handle design-specific fields if they exist
                        $('#designConcept').val(project.design_concept || '');
                        $('#colorPalette').val(project.color_palette || '');
                        $('#typography').val(project.typography || '');
                        $('#designCategory').val(project.design_category || '');
                        $('#designStyle').val(project.design_style || '');
                        
                        // Handle development-specific fields
                        $('#projectGitHub').val(project.github_url || '');
                        $('#projectApiDocs').val(project.api_docs_url || '');
                        $('#projectChallenges').val(project.challenges || '');
                        
                        // Handle statistical fields
                        $('#performanceScore').val(project.performance_score || 92);
                        $('#codeQuality').val(project.code_quality || 'A+');
                        $('#linesOfCode').val(project.lines_of_code || 2500);
                        $('#componentsCount').val(project.components_count || 25);
                        $('#developmentWeeks').val(project.development_weeks || 5);
                        
                        // Handle Creative Process fields
                        $('#creativeChallenge').val(project.creative_challenge || '');
                        $('#creativeApproach').val(project.creative_approach || '');
                        $('#creativeSolution').val(project.creative_solution || '');
                        $('#inspirationSource').val(project.inspiration_source || '');
                        $('#lessonsLearned').val(project.lessons_learned || '');
                        
                        // Handle project types and hybrid project data
                        if (project.project_types) {
                            const projectTypes = Array.isArray(project.project_types) ? 
                                project.project_types : JSON.parse(project.project_types);
                            
                            // Uncheck all project type checkboxes first
                            $('.project-type-checkboxes input').prop('checked', false);
                            
                            // Check the appropriate project types
                            projectTypes.forEach(type => {
                                $(`#type${type.charAt(0).toUpperCase() + type.slice(1)}`).prop('checked', true);
                            });
                        }
                        
                        // Handle hybrid project indicator
                        $('#isHybridProject').prop('checked', project.is_hybrid == 1);

                        this.editingId = id;
                        $('#formTitle').html('<i class="lnr lnr-pencil"></i> Project Bewerken');
                        $('#submitText').text('Wijzigingen Opslaan');
                        
                        // Toggle form sections based on category
                        this.toggleFormSections();
                        
                        // Load timeline data for this project
                        this.loadTimelineFromProject(project);
                        
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
                            // Update statistics counts after deletion with small delay
                            setTimeout(() => {
                                this.loadStatistics();
                            }, 500);
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
                
                // Reset timeline data
                this.inlineTimelinePhases = [];
                this.renderInlineTimelinePhases();
                this.hideInlinePhaseForm();
                
                // Reset form sections
                this.toggleFormSections();
            }
            
            toggleFormSections() {
                const selectedCategory = $('#projectCategory').val();
                const isDevelopment = ['development', 'web', 'mobile'].includes(selectedCategory);
                const isDesign = ['design', 'vintage'].includes(selectedCategory);
                const isHybridChecked = $('#isHybridProject').is(':checked');
                
                // Check selected project types
                const selectedTypes = [];
                $('.project-type-checkboxes input:checked').each(function() {
                    selectedTypes.push($(this).val());
                });
                
                const hasWebType = selectedTypes.includes('web') || selectedTypes.includes('development');
                const hasMobileType = selectedTypes.includes('mobile');
                const hasDesignType = selectedTypes.includes('design');
                const hasDevelopmentType = selectedTypes.includes('development');
                
                // Show/hide Project Type (Multi-selectie) section when Hybride project is checked
                if (isHybridChecked) {
                    $('#projectTypeSection').slideDown(300);
                } else {
                    $('#projectTypeSection').slideUp(300);
                }
                
                // Show/hide Development Process section when Development is selected in project types
                if (hasDevelopmentType && $('#projectTypeSection').is(':visible')) {
                    $('#developmentProcessSection').slideDown(300);
                } else {
                    $('#developmentProcessSection').slideUp(300);
                }
                
                // Check if project has content in both development and design fields (hybrid project)
                const hasDevContent = $('#projectGitHub').val() || $('#projectChallenges').val() || 
                                     $('#linesOfCode').val() > 0 || $('#developmentWeeks').val() > 0 || 
                                     isDevelopment || hasWebType || hasMobileType || isHybridChecked;
                const hasDesignContent = $('#designConcept').val() || $('#colorPalette').val() || 
                                        $('#typography').val() || $('#designStyle').val() || 
                                        $('#designProcess').val() || isDesign || hasDesignType || isHybridChecked;
                const isHybridProject = (hasDevContent && hasDesignContent) || isHybridChecked;
                
                // Show/hide hybrid project info
                if (isHybridProject) {
                    $('#hybridProjectInfo').slideDown(300);
                } else {
                    $('#hybridProjectInfo').slideUp(300);
                }
                
                // Show/hide project type guide
                if (selectedCategory && (isDevelopment || (isDesign && isHybridChecked))) {
                    $('#projectTypeGuide').slideDown(300);
                } else {
                    $('#projectTypeGuide').slideUp(300);
                }
                
                // Check current visibility to prevent unnecessary animations
                const isDevFieldsVisible = $('#developmentFields').is(':visible');
                const isDesignFieldsVisible = $('#designFields').is(':visible');
                
                // Special case: Design-only project (not hybrid)
                const isDesignOnly = isDesign && !isHybridChecked;
                
                // Updated logic for showing development and design fields
                const shouldShowDev = isDesignOnly ? false : (isDevelopment || hasDevContent || isHybridChecked);
                const shouldShowDesign = isDesign || hasDesignContent || isHybridChecked;
                
                if (shouldShowDev && !isDevFieldsVisible) {
                    // Show development-specific fields
                    $('#developmentFields').addClass('form-section-enter').show();
                    
                    // Update labels and placeholders for development
                    $('#projectTools').attr('placeholder', 'React, Node.js, MongoDB, AWS (gescheiden door komma\'s)');
                    
                    // Show technical sections
                    $('label[for="projectLiveUrl"]').html('<i class="lnr lnr-globe"></i> Live Demo URL');
                    $('label[for="projectDemoUrl"]').html('<i class="lnr lnr-eye"></i> GitHub Pages/Preview URL');
                    
                } else if (!shouldShowDev && isDevFieldsVisible) {
                    // Hide development fields
                    $('#developmentFields').hide();
                }
                
                if (shouldShowDesign && !isDesignFieldsVisible) {
                    // Show design-specific fields
                    $('#designFields').addClass('form-section-enter').show();
                    
                    // Update labels for design projects
                    if (!shouldShowDev) { // Only change labels if not a hybrid project
                        $('label[for="projectLiveUrl"]').html('<i class="lnr lnr-picture"></i> Behance/Dribbble URL');
                    }
                    $('label[for="projectDemoUrl"]').html('<i class="lnr lnr-eye"></i> Preview URL');
                    
                } else if (!shouldShowDesign && isDesignFieldsVisible) {
                    // Hide design fields
                    $('#designFields').hide();
                }
                
                if (!shouldShowDev && !shouldShowDesign) {
                    // For other categories, show basic fields
                    $('#universalFields').show();
                    
                    // Reset labels to default
                    $('label[for="projectLiveUrl"]').html('Live URL (optioneel)');
                    $('label[for="projectDemoUrl"]').html('Demo URL (optioneel)');
                }
                
                // Always show universal fields
                $('#universalFields').show();
                
                // Gallery checkbox logic: show when no live URL or design category
                const currentLiveUrl = $('#projectLiveUrl').val();
                const shouldShowGalleryCheckbox = !currentLiveUrl || isDesign || hasDesignType;
                
                if (shouldShowGalleryCheckbox) {
                    $('#enableGallery').closest('.row').slideDown(300);
                    
                    // Auto-check gallery for design-only projects
                    if (isDesignOnly) {
                        $('#enableGallery').prop('checked', true);
                        $('#gallerySection').slideDown(300);
                        
                        // Update label for design-only projects
                        $('#enableGallery').next('label').find('small').text('Aanbevolen voor design projecten - toon je creativiteit!');
                    } else {
                        // Reset to default label
                        $('#enableGallery').next('label').find('small').text('Toon een afbeeldingengalerij voor dit project (aanbevolen voor design projecten of projecten zonder live demo)');
                    }
                } else {
                    $('#enableGallery').closest('.row').slideUp(300);
                    // Also hide gallery section if checkbox is hidden
                    $('#gallerySection').slideUp(300);
                    $('#enableGallery').prop('checked', false);
                }
                
                // Add visual feedback
                this.addSectionAnimations();
                
                // Update form validation
                this.updateFormValidation(isDevelopment, isDesign);
            }
            
            updateHybridProjectIndicator() {
                const hasDevContent = $('#projectGitHub').val() || $('#projectChallenges').val() || 
                                     $('#linesOfCode').val() > 0 || $('#developmentWeeks').val() > 0;
                const hasDesignContent = $('#designConcept').val() || $('#colorPalette').val() || 
                                        $('#typography').val() || $('#designStyle').val() || 
                                        $('#designProcess').val();
                const selectedCategory = $('#projectCategory').val();
                const isDevelopment = ['development', 'web', 'mobile'].includes(selectedCategory);
                const isDesign = ['design', 'vintage'].includes(selectedCategory);
                const isHybridChecked = $('#isHybridProject').is(':checked');
                
                // Get selected project types
                const selectedTypes = [];
                $('.project-type-checkboxes input:checked').each(function() {
                    selectedTypes.push($(this).val());
                });
                const hasMultipleTypes = selectedTypes.length > 1 || 
                                       (selectedTypes.includes('design') && (selectedTypes.includes('web') || selectedTypes.includes('mobile') || selectedTypes.includes('development')));
                
                if (hasDevContent && hasDesignContent || hasMultipleTypes || isHybridChecked) {
                    $('#hybridIndicator').html('<i class="lnr lnr-magic-wand" style="color: #9b59b6;"></i> Hybride project gedetecteerd').show();
                    if (!isHybridChecked) {
                        $('#isHybridProject').prop('checked', true).trigger('change');
                    }
                } else {
                    $('#hybridIndicator').hide();
                }
            }
            
            updateFormValidation(isDevelopment, isDesign) {
                // Remove all custom validation
                $('.form-control, .form-select').removeClass('required-conditional');
                
                // Update category hint
                const hints = {
                    development: 'Toont: GitHub link, technologieën, API docs, uitdagingen',
                    web: 'Toont: Live demo, technische stack, features, GitHub repository', 
                    mobile: 'Toont: App store links, frameworks, device compatibility',
                    design: 'Toont: Kleurenpalet, typografie, design tools, galerij',
                    vintage: 'Toont: Fotografie details, stijl, creatieve highlights',
                    other: 'Toont: Basis project informatie en beschrijving'
                };
                
                const selectedCategory = $('#projectCategory').val();
                const hint = hints[selectedCategory] || 'Selecteer het type project voor aangepaste velden';
                $('#categoryHint').text(hint);
                
                if (isDevelopment) {
                    // Make development fields more important
                    $('#projectTools, #projectFeatures').addClass('required-conditional');
                } else if (isDesign) {
                    // Make design fields more important
                    $('#designTools, #designProcess').addClass('required-conditional');
                }
            }
            
            addSectionAnimations() {
                // Add slide-down animation to visible sections
                $('#developmentFields:visible, #designFields:visible, #imageGallerySection:visible').hide().slideDown(300);
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
                    
                    // Extract year from completion_date or created_at
                    let projectYear = 'N/A';
                    if (project.completion_date) {
                        projectYear = new Date(project.completion_date).getFullYear();
                    } else if (project.created_at) {
                        projectYear = new Date(project.created_at).getFullYear();
                    }
                    
                    return `
                        <div class="project-item" data-category="${project.category}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">${project.title}</h5>
                                    <span class="badge ${badgeClass} me-2">${project.category}</span>
                                    <span class="badge bg-secondary">${projectYear}</span>
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
                        // Update statistics counts after import with small delay
                        setTimeout(() => {
                            this.loadStatistics();
                        }, 500);
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

            async loadStatistics() {
                try {
                    $('#statsLoading').show();
                    const response = await this.makeRequest('get_statistics');
                    if (response.success) {
                        const settings = response.settings;
                        const actualCounts = response.actual_counts;
                        
                        // Fill form fields with current settings ONLY if auto mode is disabled
                        $('#yearsExperience').val(settings.stats_years_experience || '');
                        $('#totalProjects').val(settings.stats_total_projects || '');
                        $('#toolsCount').val(settings.stats_tools_count || '');
                        
                        // Fill auto-calculation checkboxes first
                        $('#developmentCountAuto').prop('checked', settings.filter_development_count_auto === '1');
                        $('#designCountAuto').prop('checked', settings.filter_design_count_auto === '1');
                        $('#photographyCountAuto').prop('checked', settings.filter_photography_count_auto === '1');
                        $('#allCountAuto').prop('checked', settings.filter_all_count_auto === '1');
                        
                        // Now fill fields based on auto mode status
                        if (settings.filter_development_count_auto === '1') {
                            $('#developmentCount').val(actualCounts.development);
                        } else {
                            $('#developmentCount').val(settings.filter_development_count || '');
                        }
                        
                        if (settings.filter_design_count_auto === '1') {
                            $('#designCount').val(actualCounts.design);
                        } else {
                            $('#designCount').val(settings.filter_design_count || '');
                        }
                        
                        if (settings.filter_photography_count_auto === '1') {
                            $('#photographyCount').val(actualCounts.vintage);
                        } else {
                            $('#photographyCount').val(settings.filter_photography_count || '');
                        }
                        
                        if (settings.filter_all_count_auto === '1') {
                            $('#allCount').val(actualCounts.total_projects);
                        } else {
                            $('#allCount').val(settings.filter_all_count || '');
                        }
                        
                        // Disable/enable inputs based on auto checkboxes
                        this.toggleAutoInputs();
                        
                        // Fill GitHub settings
                        $('#githubUsernameStats').val(settings.github_username || '');
                        $('#githubTokenStats').val(settings.github_token || '');
                        
                        // Update actual counts display with animation
                        $('#actualTotal').text(actualCounts.total_projects).addClass('form-updated');
                        $('#actualDevelopment').text(actualCounts.development).addClass('form-updated');
                        $('#actualDesign').text(actualCounts.design).addClass('form-updated');
                        $('#actualVintage').text(actualCounts.vintage).addClass('form-updated');
                        
                        // Remove animation class after animation completes
                        setTimeout(() => {
                            $('.form-updated').removeClass('form-updated');
                        }, 600);
                        
                    } else {
                        this.showAlert('Fout bij laden statistieken: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij laden statistieken: ' + error.message, 'danger');
                } finally {
                    $('#statsLoading').hide();
                }
            }

            async loadActualCounts() {
                try {
                    $('#statsLoading').show();
                    const response = await this.makeRequest('get_statistics');
                    if (response.success) {
                        const actualCounts = response.actual_counts;
                        
                        // Update actual counts display with animation
                        $('#actualTotal').text(actualCounts.total_projects).addClass('form-updated');
                        $('#actualDevelopment').text(actualCounts.development).addClass('form-updated');
                        $('#actualDesign').text(actualCounts.design).addClass('form-updated');
                        $('#actualVintage').text(actualCounts.vintage).addClass('form-updated');
                        
                        // Update auto fields with fresh counts if auto mode is enabled
                        if ($('#developmentCountAuto').is(':checked')) {
                            $('#developmentCount').val(actualCounts.development);
                        }
                        if ($('#designCountAuto').is(':checked')) {
                            $('#designCount').val(actualCounts.design);
                        }
                        if ($('#photographyCountAuto').is(':checked')) {
                            $('#photographyCount').val(actualCounts.vintage);
                        }
                        if ($('#allCountAuto').is(':checked')) {
                            $('#allCount').val(actualCounts.total_projects);
                        }
                        
                        // Remove animation class after animation completes
                        setTimeout(() => {
                            $('.form-updated').removeClass('form-updated');
                        }, 600);
                        
                    } else {
                        this.showAlert('Fout bij laden statistieken: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij laden statistieken: ' + error.message, 'danger');
                } finally {
                    $('#statsLoading').hide();
                }
            }

            async forceUpdateAutoFields() {
                try {
                    // First get fresh counts
                    const response = await this.makeRequest('get_statistics');
                    if (response.success) {
                        const actualCounts = response.actual_counts;
                        
                        // Force update all auto fields regardless of settings
                        if ($('#allCountAuto').is(':checked')) {
                            $('#allCount').val(actualCounts.total_projects).addClass('form-updated');
                            console.log('Updated allCount to:', actualCounts.total_projects);
                        }
                        if ($('#developmentCountAuto').is(':checked')) {
                            $('#developmentCount').val(actualCounts.development).addClass('form-updated');
                        }
                        if ($('#designCountAuto').is(':checked')) {
                            $('#designCount').val(actualCounts.design).addClass('form-updated');
                        }
                        if ($('#photographyCountAuto').is(':checked')) {
                            $('#photographyCount').val(actualCounts.vintage).addClass('form-updated');
                        }
                        
                        // Also update the reference display
                        $('#actualTotal').text(actualCounts.total_projects);
                        $('#actualDevelopment').text(actualCounts.development);
                        $('#actualDesign').text(actualCounts.design);
                        $('#actualVintage').text(actualCounts.vintage);
                        
                        // Remove animation after delay
                        setTimeout(() => {
                            $('.form-updated').removeClass('form-updated');
                        }, 600);
                        
                        this.showAlert('Auto velden bijgewerkt!', 'success');
                    }
                } catch (error) {
                    this.showAlert('Fout bij bijwerken auto velden: ' + error.message, 'danger');
                }
            }

            async regenerateSitemap() {
                try {
                    // Show loading state
                    const $button = $('#regenerateSitemap');
                    const originalText = $button.html();
                    $button.prop('disabled', true).html('<i class="lnr lnr-sync"></i> Bezig...');
                    
                    const response = await this.makeRequest('regenerate_sitemap');
                    
                    if (response.success) {
                        this.showAlert('Sitemap succesvol gegenereerd! Google Search Console kan nu de bijgewerkte sitemap.xml lezen.', 'success');
                        
                        // Brief success state
                        $button.html('<i class="lnr lnr-checkmark-circle"></i> Voltooid!');
                        setTimeout(() => {
                            $button.prop('disabled', false).html(originalText);
                        }, 2000);
                    } else {
                        this.showAlert('Fout bij genereren sitemap: ' + (response.message || 'Onbekende fout'), 'danger');
                        $button.prop('disabled', false).html(originalText);
                    }
                } catch (error) {
                    this.showAlert('Network error bij regenereren sitemap: ' + error.message, 'danger');
                    const $button = $('#regenerateSitemap');
                    $button.prop('disabled', false).html('<i class="lnr lnr-map"></i> Sitemap Regenereren');
                }
            }

            async clearSavedManualValue(checkboxId) {
                const settingMapping = {
                    'developmentCountAuto': 'filter_development_count',
                    'designCountAuto': 'filter_design_count',
                    'photographyCountAuto': 'filter_photography_count',
                    'allCountAuto': 'filter_all_count'
                };
                
                const settingKey = settingMapping[checkboxId];
                if (settingKey) {
                    try {
                        // Clear the manual value from database
                        const formData = new FormData();
                        formData.append('action', 'clear_manual_setting');
                        formData.append('setting_key', settingKey);
                        
                        await this.makeRequest('clear_manual_setting', { setting_key: settingKey });
                        console.log('Cleared manual value for:', settingKey);
                    } catch (error) {
                        console.log('Error clearing manual value:', error);
                    }
                }
            }

            async toggleAutoInputs() {
                // Get fresh actual counts from server to ensure accuracy
                try {
                    const response = await this.makeRequest('get_statistics');
                    if (response.success) {
                        const actualCounts = response.actual_counts;
                        
                        // Use server counts for auto-population
                        const currentCounts = {
                            total: actualCounts.total_projects,
                            development: actualCounts.development,
                            design: actualCounts.design,
                            vintage: actualCounts.vintage
                        };
                        
                        // Disable/enable inputs based on auto checkbox state
                        const autoFields = [
                            ['#developmentCountAuto', '#developmentCount', 'development'],
                            ['#designCountAuto', '#designCount', 'design'],
                            ['#photographyCountAuto', '#photographyCount', 'vintage'],
                            ['#allCountAuto', '#allCount', 'total']
                        ];
                        
                        autoFields.forEach(([checkboxId, inputId, countType]) => {
                            const isAuto = $(checkboxId).is(':checked');
                            const $input = $(inputId);
                            const $checkbox = $(checkboxId);
                            
                            // Update input state
                            $input.prop('disabled', isAuto);
                            
                            // Update visual styling and populate value
                            if (isAuto) {
                                $input.addClass('auto-disabled');
                                $checkbox.parent().addClass('text-primary');
                                
                                // Auto-populate with current actual count from server
                                $input.val(currentCounts[countType]);
                                $input.addClass('form-updated');
                                setTimeout(() => {
                                    $input.removeClass('form-updated');
                                }, 600);
                            } else {
                                $input.removeClass('auto-disabled');
                                $checkbox.parent().removeClass('text-primary');
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error getting fresh counts for auto toggle:', error);
                    // Fallback to display values if server request fails
                    this.toggleAutoInputsFallback();
                }
            }
            
            toggleAutoInputsFallback() {
                // Fallback method using display values (original implementation)
                const currentCounts = {
                    total: $('#actualTotal').text() !== '-' ? $('#actualTotal').text() : '0',
                    development: $('#actualDevelopment').text() !== '-' ? $('#actualDevelopment').text() : '0',
                    design: $('#actualDesign').text() !== '-' ? $('#actualDesign').text() : '0',
                    vintage: $('#actualVintage').text() !== '-' ? $('#actualVintage').text() : '0'
                };
                
                // Disable/enable inputs based on auto checkbox state
                const autoFields = [
                    ['#developmentCountAuto', '#developmentCount', 'development'],
                    ['#designCountAuto', '#designCount', 'design'],
                    ['#photographyCountAuto', '#photographyCount', 'vintage'],
                    ['#allCountAuto', '#allCount', 'total']
                ];
                
                autoFields.forEach(([checkboxId, inputId, countType]) => {
                    const isAuto = $(checkboxId).is(':checked');
                    const $input = $(inputId);
                    const $checkbox = $(checkboxId);
                    
                    // Update input state
                    $input.prop('disabled', isAuto);
                    
                    // Update visual styling and populate value
                    if (isAuto) {
                        $input.addClass('auto-disabled');
                        $checkbox.parent().addClass('text-primary');
                        
                        // Auto-populate with current actual count
                        $input.val(currentCounts[countType]);
                        $input.addClass('form-updated');
                        setTimeout(() => {
                            $input.removeClass('form-updated');
                        }, 600);
                    } else {
                        $input.removeClass('auto-disabled');
                        $checkbox.parent().removeClass('text-primary');
                    }
                });
            }

            async saveStatistics() {
                const formData = new FormData();
                formData.append('action', 'save_statistics');
                formData.append('years_experience', $('#yearsExperience').val());
                formData.append('total_projects', $('#totalProjects').val());
                formData.append('tools_count', $('#toolsCount').val());
                formData.append('development_count', $('#developmentCount').val());
                formData.append('design_count', $('#designCount').val());
                formData.append('photography_count', $('#photographyCount').val());
                formData.append('all_count', $('#allCount').val());
                formData.append('github_username', $('#githubUsernameStats').val());
                formData.append('github_token', $('#githubTokenStats').val());
                
                // Add auto-calculation flags with visual feedback
                const autoCheckboxes = [
                    ['#developmentCountAuto', 'development_count_auto'],
                    ['#designCountAuto', 'design_count_auto'],
                    ['#photographyCountAuto', 'photography_count_auto'],
                    ['#allCountAuto', 'all_count_auto']
                ];
                
                autoCheckboxes.forEach(([checkboxId, fieldName]) => {
                    if ($(checkboxId).is(':checked')) {
                        formData.append(fieldName, '1');
                        // Add visual feedback for auto checkboxes being saved
                        $(checkboxId).parent().addClass('text-success').removeClass('text-primary');
                        setTimeout(() => {
                            $(checkboxId).parent().removeClass('text-success').addClass('text-primary');
                        }, 1000);
                    }
                });

                try {
                    const response = await this.makeRequest('save_statistics', formData);
                    if (response.success) {
                        this.showAlert('Statistieken succesvol bijgewerkt! Auto-instellingen zijn opgeslagen.', 'success');
                        // Reload the entire statistics form to show saved auto checkbox states
                        setTimeout(() => {
                            this.loadStatistics();
                        }, 500);
                    } else {
                        this.showAlert('Fout bij opslaan statistieken: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij opslaan statistieken: ' + error.message, 'danger');
                }
            }

            // Timeline Management Methods
            setupTimelineManagement() {
                // Load project timeline button
                $('#loadProjectTimeline').on('click', () => {
                    const projectId = $('#timelineProjectSelect').val();
                    if (projectId) {
                        this.loadProjectTimeline(projectId);
                    } else {
                        this.showAlert('Selecteer eerst een project', 'danger');
                    }
                });
                
                // Add new phase button
                $('#addTimelinePhase').on('click', () => {
                    const projectId = $('#timelineProjectSelect').val();
                    if (projectId) {
                        this.showPhaseForm(projectId);
                    } else {
                        this.showAlert('Selecteer eerst een project', 'danger');
                    }
                });
                
                // Phase form submission
                $('#phaseForm').on('submit', (e) => {
                    e.preventDefault();
                    this.saveTimelinePhase();
                });
                
                // Cancel phase form
                $('#cancelPhaseForm').on('click', () => {
                    this.hidePhaseForm();
                });
                
                // Inline timeline form handlers (in project form)
                $('#addTimelinePhaseBtn').on('click', () => {
                    this.showInlinePhaseForm();
                });
                
                $('#cancelInlinePhaseForm, #cancelInlinePhase').on('click', () => {
                    this.hideInlinePhaseForm();
                });
                
                $('#saveInlinePhase').on('click', () => {
                    this.saveInlineTimelinePhase();
                });
                
                // Load projects for timeline selection
                this.loadProjectsForTimeline();
                
                // Initialize inline timeline
                this.inlineTimelinePhases = [];
            }
            
            async loadProjectsForTimeline() {
                try {
                    const response = await this.makeRequest('get_projects');
                    if (response.success) {
                        const select = $('#timelineProjectSelect');
                        select.empty().append('<option value="">Selecteer een project voor timeline beheer</option>');
                        
                        response.data.forEach(project => {
                            select.append(`<option value="${project.id}">${project.title}</option>`);
                        });
                    }
                } catch (error) {
                    console.error('Error loading projects for timeline:', error);
                }
            }
            
            async loadProjectTimeline(projectId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_timeline_phases');
                    formData.append('project_id', projectId);
                    
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.renderTimelinePhases(result.data, projectId);
                    } else {
                        this.showAlert('Fout bij laden timeline: ' + result.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij laden timeline: ' + error.message, 'danger');
                }
            }
            
            renderTimelinePhases(phases, projectId) {
                const container = $('#timelinePhasesList');
                
                if (phases.length === 0) {
                    container.html(`
                        <div class="timeline-empty-state">
                            <i class="lnr lnr-calendar-full"></i>
                            <p>Nog geen timeline fasen toegevoegd voor dit project</p>
                            <button class="btn btn-primary" onclick="portfolioAdmin.showPhaseForm(${projectId})">
                                <i class="lnr lnr-plus-circle"></i>
                                Eerste Fase Toevoegen
                            </button>
                        </div>
                    `);
                    return;
                }
                
                // Phase type icon mapping
                const phaseIcons = {
                    'planning': 'lnr-calendar-full',
                    'design': 'lnr-magic-wand',
                    'development': 'lnr-laptop',
                    'testing': 'lnr-bug',
                    'deployment': 'lnr-rocket',
                    'challenge': 'lnr-question-circle',
                    'approach': 'lnr-cog',
                    'solution': 'lnr-checkmark-circle'
                };
                
                let html = '<div class="timeline-phases">';
                phases.forEach(phase => {
                    const statusBadge = this.getStatusBadge(phase.phase_status);
                    const tasks = phase.tasks ? JSON.parse(phase.tasks) : [];
                    const deliverables = phase.deliverables ? JSON.parse(phase.deliverables) : [];
                    
                    // Get icon for phase type
                    let phaseIcon = 'lnr-star'; // Default icon
                    if (phase.phase_type && phaseIcons[phase.phase_type]) {
                        phaseIcon = phaseIcons[phase.phase_type];
                    }
                    
                    html += `
                        <div class="phase-item mb-3">
                            <div class="admin-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="${phaseIcon}" style="color: #007bff; margin-right: 8px;"></i>
                                            ${phase.week_number ? `Week ${phase.week_number}:` : ''} ${phase.phase_name}
                                            ${statusBadge}
                                        </h5>
                                        ${phase.phase_description ? `<small class="text-muted">${phase.phase_description}</small>` : ''}
                                        ${phase.phase_type ? `<small class="badge bg-secondary ms-2">${phase.phase_type}</small>` : ''}
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-light me-2" onclick="portfolioAdmin.editPhase(${phase.id})">
                                            <i class="lnr lnr-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="portfolioAdmin.deletePhase(${phase.id})">
                                            <i class="lnr lnr-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    ${phase.phase_details ? `<p><strong>Details:</strong> ${phase.phase_details}</p>` : ''}
                                    
                                    <div class="row">
                                        ${phase.start_date || phase.end_date ? `
                                        <div class="col-md-6">
                                            <div class="phase-planning-info">
                                                <strong><i class="lnr lnr-calendar-full"></i> Planning:</strong><br>
                                                ${phase.start_date ? `Start: ${new Date(phase.start_date).toLocaleDateString('nl-NL')}` : ''}
                                                ${phase.start_date && phase.end_date ? '<br>' : ''}
                                                ${phase.end_date ? `Eind: ${new Date(phase.end_date).toLocaleDateString('nl-NL')}` : ''}
                                            </div>
                                        </div>` : ''}
                                        
                                        ${tasks.length > 0 ? `
                                        <div class="col-md-6">
                                            <div class="phase-tasks-list">
                                                <strong><i class="lnr lnr-checkmark-circle"></i> Taken:</strong>
                                                <ul class="mb-0">
                                                    ${tasks.map(task => `<li>${task}</li>`).join('')}
                                                </ul>
                                            </div>
                                        </div>` : ''}
                                    </div>
                                    
                                    ${deliverables.length > 0 ? `
                                    <div class="mt-2">
                                        <div class="phase-deliverables-list">
                                            <strong><i class="lnr lnr-gift"></i> Deliverables:</strong>
                                            <ul class="mb-0">
                                                ${deliverables.map(item => `<li>${item}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>` : ''}
                                </div>
                            </div>
                        `;
            });
            
                            $('#projectTimeline').html(html);
        }
        
        // Inline Timeline Management Methods (for project form)
        showInlinePhaseForm() {
            $('#timelinePhaseFormInline').slideDown(300);
            $('#addTimelinePhaseBtn').prop('disabled', true);
            $('#inlinePhaseName').focus();
            this.clearInlinePhaseForm();
        }
        
        hideInlinePhaseForm() {
            $('#timelinePhaseFormInline').slideUp(300);
            $('#addTimelinePhaseBtn').prop('disabled', false);
            this.clearInlinePhaseForm();
        }
        
        clearInlinePhaseForm() {
            $('#inlinePhaseName').val('');
            $('#inlinePhaseType').val('');
            $('#inlinePhaseWeek').val('');
            $('#inlinePhaseDescription').val('');
            $('#inlinePhaseDetails').val('');
            $('#inlinePhaseStatus').val('planned');
            $('#inlinePhaseStartDate').val('');
            $('#inlinePhaseEndDate').val('');
            $('#inlinePhaseTasks').val('');
            $('#inlinePhaseDeliverables').val('');
            $('#inlinePhaseFormTitle').text('Nieuwe Timeline Fase');
        }
        
        saveInlineTimelinePhase() {
            const phaseName = $('#inlinePhaseName').val().trim();
            const phaseType = $('#inlinePhaseType').val();
            
            if (!phaseName) {
                this.showAlert('Fase naam is verplicht', 'danger');
                $('#inlinePhaseName').focus();
                return;
            }
            
            const phaseData = {
                id: 'temp_' + Date.now(), // Temporary ID for new phases
                phase_name: phaseName,
                phase_type: phaseType,
                week_number: $('#inlinePhaseWeek').val() || null,
                phase_description: $('#inlinePhaseDescription').val(),
                phase_details: $('#inlinePhaseDetails').val(),
                phase_status: $('#inlinePhaseStatus').val(),
                start_date: $('#inlinePhaseStartDate').val() || null,
                end_date: $('#inlinePhaseEndDate').val() || null,
                tasks: $('#inlinePhaseTasks').val().split('\n').filter(task => task.trim() !== ''),
                deliverables: $('#inlinePhaseDeliverables').val().split('\n').filter(item => item.trim() !== '')
            };
            
            // Add to temporary phases array
            if (!this.inlineTimelinePhases) {
                this.inlineTimelinePhases = [];
            }
            
            this.inlineTimelinePhases.push(phaseData);
            this.renderInlineTimelinePhases();
            this.hideInlinePhaseForm();
            
            this.showAlert('Timeline fase toegevoegd. Sla het project op om permanent te maken.', 'success');
        }
        
        renderInlineTimelinePhases() {
            const container = $('#timelinePhasesContainer');
            
            if (!this.inlineTimelinePhases || this.inlineTimelinePhases.length === 0) {
                container.html(`
                    <div class="timeline-empty text-center text-muted py-3" id="timelineEmpty">
                        <i class="lnr lnr-calendar-full" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mb-0 mt-2">Geen timeline fasen toegevoegd</p>
                        <small>Klik op "Fase Toevoegen" om te beginnen</small>
                    </div>
                `);
                return;
            }
            
            // Phase type icon mapping
            const phaseIcons = {
                'planning': 'lnr-calendar-full',
                'design': 'lnr-magic-wand',
                'development': 'lnr-laptop',
                'testing': 'lnr-bug',
                'deployment': 'lnr-rocket',
                'challenge': 'lnr-question-circle',
                'approach': 'lnr-cog',
                'solution': 'lnr-checkmark-circle'
            };
            
            const statusClasses = {
                'planned': 'bg-secondary',
                'in_progress': 'bg-primary',
                'completed': 'bg-success',
                'skipped': 'bg-warning'
            };
            
            const statusLabels = {
                'planned': 'Gepland',
                'in_progress': 'In Uitvoering',
                'completed': 'Voltooid',
                'skipped': 'Overgeslagen'
            };
            
            let html = '<div class="inline-timeline-phases">';
            
            this.inlineTimelinePhases.forEach((phase, index) => {
                const phaseIcon = phaseIcons[phase.phase_type] || 'lnr-star';
                const statusClass = statusClasses[phase.phase_status] || 'bg-secondary';
                const statusLabel = statusLabels[phase.phase_status] || 'Gepland';
                
                html += `
                    <div class="card mb-2" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="lnr ${phaseIcon} me-2" style="color: #4103b3;"></i>
                                        <h6 class="mb-0 me-2">${phase.phase_name}</h6>
                                        ${phase.week_number ? `<small class="text-muted">Week ${phase.week_number}</small>` : ''}
                                        <span class="badge ${statusClass} ms-2">${statusLabel}</span>
                                    </div>
                                    
                                    ${phase.phase_description ? `<p class="text-muted mb-2 small">${phase.phase_description}</p>` : ''}
                                    
                                    ${phase.start_date || phase.end_date ? `
                                    <div class="text-muted small mb-2">
                                        ${phase.start_date ? `Start: ${new Date(phase.start_date).toLocaleDateString('nl-NL')}` : ''}
                                        ${phase.start_date && phase.end_date ? ' - ' : ''}
                                        ${phase.end_date ? `Eind: ${new Date(phase.end_date).toLocaleDateString('nl-NL')}` : ''}
                                    </div>` : ''}
                                    
                                    ${phase.tasks && phase.tasks.length > 0 ? `
                                    <div class="mb-2">
                                        <small class="text-muted"><strong>Taken:</strong> ${phase.tasks.join(', ')}</small>
                                    </div>` : ''}
                                    
                                    ${phase.deliverables && phase.deliverables.length > 0 ? `
                                    <div class="mb-2">
                                        <small class="text-muted"><strong>Deliverables:</strong> ${phase.deliverables.join(', ')}</small>
                                    </div>` : ''}
                                </div>
                                
                                <div class="ms-2">
                                    <button type="button" class="btn btn-sm btn-outline-light me-1" onclick="portfolioAdmin.editInlinePhase(${index})" title="Bewerken">
                                        <i class="lnr lnr-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="portfolioAdmin.removeInlinePhase(${index})" title="Verwijderen">
                                        <i class="lnr lnr-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.html(html);
        }
        
        editInlinePhase(index) {
            const phase = this.inlineTimelinePhases[index];
            if (!phase) return;
            
            // Populate form with phase data
            $('#inlinePhaseName').val(phase.phase_name);
            $('#inlinePhaseType').val(phase.phase_type);
            $('#inlinePhaseWeek').val(phase.week_number || '');
            $('#inlinePhaseDescription').val(phase.phase_description);
            $('#inlinePhaseDetails').val(phase.phase_details);
            $('#inlinePhaseStatus').val(phase.phase_status);
            $('#inlinePhaseStartDate').val(phase.start_date || '');
            $('#inlinePhaseEndDate').val(phase.end_date || '');
            $('#inlinePhaseTasks').val(phase.tasks ? phase.tasks.join('\n') : '');
            $('#inlinePhaseDeliverables').val(phase.deliverables ? phase.deliverables.join('\n') : '');
            
            $('#inlinePhaseFormTitle').text('Timeline Fase Bewerken');
            
            // Store editing index
            this.editingInlinePhaseIndex = index;
            
            // Update save button text
            $('#saveInlinePhase').html('<i class="lnr lnr-checkmark-circle"></i> Wijzigingen Opslaan');
            
            // Modify save handler
            $('#saveInlinePhase').off('click').on('click', () => {
                this.updateInlineTimelinePhase(index);
            });
            
            this.showInlinePhaseForm();
        }
        
        updateInlineTimelinePhase(index) {
            const phaseName = $('#inlinePhaseName').val().trim();
            
            if (!phaseName) {
                this.showAlert('Fase naam is verplicht', 'danger');
                $('#inlinePhaseName').focus();
                return;
            }
            
            // Update the phase data
            this.inlineTimelinePhases[index] = {
                ...this.inlineTimelinePhases[index],
                phase_name: phaseName,
                phase_type: $('#inlinePhaseType').val(),
                week_number: $('#inlinePhaseWeek').val() || null,
                phase_description: $('#inlinePhaseDescription').val(),
                phase_details: $('#inlinePhaseDetails').val(),
                phase_status: $('#inlinePhaseStatus').val(),
                start_date: $('#inlinePhaseStartDate').val() || null,
                end_date: $('#inlinePhaseEndDate').val() || null,
                tasks: $('#inlinePhaseTasks').val().split('\n').filter(task => task.trim() !== ''),
                deliverables: $('#inlinePhaseDeliverables').val().split('\n').filter(item => item.trim() !== '')
            };
            
            this.renderInlineTimelinePhases();
            this.hideInlinePhaseForm();
            
            // Reset save button
            $('#saveInlinePhase').html('<i class="lnr lnr-checkmark-circle"></i> Fase Opslaan');
            $('#saveInlinePhase').off('click').on('click', () => {
                this.saveInlineTimelinePhase();
            });
            
            this.showAlert('Timeline fase bijgewerkt', 'success');
        }
        
        removeInlinePhase(index) {
            if (confirm('Weet je zeker dat je deze timeline fase wilt verwijderen?')) {
                this.inlineTimelinePhases.splice(index, 1);
                this.renderInlineTimelinePhases();
                this.showAlert('Timeline fase verwijderd', 'success');
            }
        }
        
        loadTimelineFromProject(projectData) {
            // Load existing timeline phases when editing a project
            if (projectData && projectData.timeline) {
                try {
                    this.inlineTimelinePhases = JSON.parse(projectData.timeline) || [];
                    this.renderInlineTimelinePhases();
                } catch (e) {
                    console.error('Error parsing timeline data:', e);
                    this.inlineTimelinePhases = [];
                    this.renderInlineTimelinePhases();
                }
            } else {
                this.inlineTimelinePhases = [];
                this.renderInlineTimelinePhases();
            }
        }
        
        getTimelineDataForSave() {
            // Return timeline data to be saved with the project
            return this.inlineTimelinePhases || [];
        }
    }
    
    // Global variables
    let currentProject = null;
    let galleryImages = [];
    let timelineItems = [];
    let portfolioAdmin = null;    // Initialize admin panel
    $(document).ready(function() {
            // Initialize the PortfolioAdmin class
            portfolioAdmin = new PortfolioAdmin();
            
            // Scroll to Top Button functionality
            const scrollToTopBtn = $('#scrollToTop');
            
            // Show/hide button based on scroll position
            $(window).scroll(function() {
                if ($(window).scrollTop() > 300) {
                    scrollToTopBtn.addClass('show');
                } else {
                    scrollToTopBtn.removeClass('show');
                }
            });
            
            // Scroll to top when button is clicked
            scrollToTopBtn.on('click', function() {
                $('html, body').animate({
                    scrollTop: 0
                }, 600);
            });
            
            loadProjects();
            
            // Form submission
            $('#projectForm').on('submit', function(e) {
                e.preventDefault();
                saveProject();
            });
            
            // Gallery upload handler
            $('#galleryUpload').on('change', function(e) {
                handleGalleryUpload(e.target.files);
            });
        });
        
        // Load projects list
        function loadProjects() {
            $.post('admin.php', {action: 'get_projects'}, function(response) {
                if (response.success && response.data) {
                    displayProjects(response.data);
                }
            }, 'json');
        }
        
        // Display projects in cards
        function displayProjects(projects) {
            let html = '';
            projects.forEach(project => {
                const statusBadge = project.status === 'completed' ? 'success' : 
                                  project.status === 'in-progress' ? 'warning' : 'secondary';
                const featuredBadge = project.is_featured == 1 ? '<span class="badge badge-primary ml-2">Uitgelicht</span>' : '';
                
                html += `
                    <div class="project-card card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="card-title mb-2">${project.title} ${featuredBadge}</h6>
                                    <p class="card-text text-muted small mb-2">${project.short_description || project.description.substring(0, 100) + '...'}</p>
                                    <div class="mb-2">
                                        <span class="badge badge-${statusBadge}">${project.status}</span>
                                        <span class="badge badge-info ml-1">${project.category}</span>
                                    </div>
                                </div>
                                <div class="project-actions">
                                    <button class="btn btn-sm btn-outline-primary mr-1" onclick="editProject(${project.id})">
                                        <i class="lnr lnr-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProject(${project.id})">
                                        <i class="lnr lnr-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#projectsList').html(html);
        }
        
        // Show project form
        function showProjectForm(projectId = null) {
            currentProject = projectId;
            
            if (projectId) {
                // Load project data
                $.post('admin.php', {action: 'get_project', id: projectId}, function(response) {
                    if (response.success && response.data) {
                        populateForm(response.data);
                    }
                }, 'json');
                $('#formTitle').html('<i class="lnr lnr-pencil mr-2"></i>Project Bewerken');
            } else {
                // Clear form for new project
                $('#projectForm')[0].reset();
                $('#projectId').val('');
                
                // Clear gallery data
                galleryImages = [];
                $('#galleryUrls').val('');
                $('#enableGallery').prop('checked', false);
                $('#gallerySection').hide();
                updateGalleryPreview();
                
                // Clear timeline data
                timelineItems = [];
                updateTimelinePreview();
                
                $('#formTitle').html('<i class="lnr lnr-plus-circle mr-2"></i>Nieuw Project');
            }
            
            $('#projectFormCard').show();
        }
        
        // Hide project form
        function hideProjectForm() {
            $('#projectFormCard').hide();
            currentProject = null;
        }
        
        // Populate form with project data
        function populateForm(project) {
            // Basic project information
            $('#projectId').val(project.id);
            $('#projectTitle').val(project.title);
            $('#projectShortDescription').val(project.short_description);
            $('#projectDescription').val(project.description);
            $('#projectCategory').val(project.category);
            $('#projectYear').val(project.year);
            $('#projectStatus').val(project.status);
            $('#projectLiveUrl').val(project.live_url);
            $('#projectDemoUrl').val(project.demo_url);
            $('#projectClientName').val(project.client_name);
            $('#projectImage').val(project.image_url);
            $('#projectDuration').val(project.project_duration);
            $('#projectCompletionDate').val(project.completion_date);
            $('#projectFeatured').prop('checked', project.is_featured == 1);
            
            // Development fields
            $('#projectTools').val(JSON.parse(project.tools || '[]').join(', '));
            $('#projectFeatures').val(JSON.parse(project.features || '[]').join('\n'));
            $('#projectGitHub').val(project.github_url);
            $('#projectApiDocs').val(project.api_docs_url);
            $('#projectChallenges').val(project.challenges);
            
            // Development statistics
            $('#performanceScore').val(project.performance_score);
            $('#codeQuality').val(project.code_quality);
            $('#linesOfCode').val(project.lines_of_code);
            $('#componentsCount').val(project.components_count);
            $('#developmentWeeks').val(project.development_weeks);
            $('#developmentMethodology').val(project.development_methodology);
            $('#developmentPhases').val(project.development_phases);
            $('#testingStrategy').val(project.testing_strategy);
            $('#deploymentMethod').val(project.deployment_method);
            
            // Design fields
            $('#designTools').val(JSON.parse(project.tools || '[]').join(', '));
            $('#designConcept').val(project.design_concept);
            $('#colorPalette').val(project.color_palette);
            $('#typography').val(project.typography);
            $('#designProcess').val(JSON.parse(project.features || '[]').join('\n'));
            $('#designCategory').val(project.design_category);
            $('#designStyle').val(project.design_style);
            
            // Creative process fields
            $('#creativeChallenge').val(project.creative_challenge);
            $('#creativeApproach').val(project.creative_approach);
            $('#creativeSolution').val(project.creative_solution);
            $('#inspirationSource').val(project.inspiration_source);
            $('#lessonsLearned').val(project.lessons_learned);
            
            // Load gallery images
            galleryImages = JSON.parse(project.gallery_images || '[]');
            
            // Populate manual gallery URLs textarea
            if (galleryImages && galleryImages.length > 0) {
                const galleryUrls = galleryImages.map(img => img.url || img).join('\n');
                $('#galleryUrls').val(galleryUrls);
                $('#enableGallery').prop('checked', true);
                $('#gallerySection').show();
            }
            
            updateGalleryPreview();
            
            // Load timeline
            timelineItems = JSON.parse(project.timeline || '[]');
            updateTimelinePreview();
            
            // Trigger form section updates based on category
            if (typeof portfolioAdmin !== 'undefined' && portfolioAdmin.toggleFormSections) {
                portfolioAdmin.toggleFormSections();
            }
        }
        
        // Save project
        function saveProject() {
            const formData = new FormData($('#projectForm')[0]);
            formData.append('action', 'save_project');
            
            // Don't override gallery_images field - let the file uploads and manual textarea work
            // The PHP backend will handle both uploaded files and manual URLs
            
            formData.append('timeline', JSON.stringify(timelineItems));
            
            // Debug: Log what we're sending
            console.log('Saving project with timeline items:', timelineItems);
            console.log('Form data keys:', Array.from(formData.keys()));
            
            $.ajax({
                url: 'admin.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log('Server response:', response);
                    if (response.success) {
                        alert('Project opgeslagen!');
                        hideProjectForm();
                        loadProjects();
                    } else {
                        alert('Fout bij opslaan: ' + (response.message || response.error || 'Onbekende fout'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    alert('Fout bij opslaan van project: ' + error);
                }
            });
        }
        
        // Edit project
        function editProject(id) {
            showProjectForm(id);
        }
        
        // Delete project
        function deleteProject(id) {
            if (confirm('Weet je zeker dat je dit project wilt verwijderen?')) {
                $.post('admin.php', {action: 'delete_project', id: id}, function(response) {
                    if (response.success) {
                        alert('Project verwijderd!');
                        loadProjects();
                    } else {
                        alert('Fout bij verwijderen: ' + (response.message || 'Onbekende fout'));
                    }
                }, 'json');
            }
        }
        
        // Handle gallery upload
        function handleGalleryUpload(files) {
            // Show preview of selected files without converting to base64
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Add to preview (this is just for display)
                        galleryImages.push({
                            preview: e.target.result,
                            name: file.name,
                            isFile: true
                        });
                        updateGalleryPreview();
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Update gallery preview
        function updateGalleryPreview() {
            let html = '';
            galleryImages.forEach((image, index) => {
                const imageSrc = (typeof image === 'object' && image.preview) ? image.preview : image;
                const imageTitle = (typeof image === 'object' && image.name) ? image.name : `Gallery ${index + 1}`;
                const fileIndicator = (typeof image === 'object' && image.isFile) ? '<small class="text-primary">Nieuw geüpload</small>' : '';
                
                html += `
                    <div class="gallery-item">
                        <img src="${imageSrc}" alt="${imageTitle}">
                        <button type="button" class="remove-gallery-item" onclick="removeGalleryItem(${index})">
                            <i class="lnr lnr-cross"></i>
                        </button>
                        ${fileIndicator}
                    </div>
                `;
            });
            
            // Add upload placeholder
            html += `
                <div class="gallery-item upload-placeholder" onclick="$('#galleryUpload').click()">
                    <i class="lnr lnr-plus-circle"></i>
                    <span>Toevoegen</span>
                </div>
            `;
            
            $('#galleryPreview').html(html);
        }
        
        // Remove gallery item
        function removeGalleryItem(index) {
            galleryImages.splice(index, 1);
            updateGalleryPreview();
        }
        
        // Add timeline item
        function addTimelineItem() {
            const item = {
                id: Date.now(),
                title: '',
                description: '',
                date: '',
                status: 'planned'
            };
            timelineItems.push(item);
            updateTimelinePreview();
        }
        
        // Update timeline preview
        function updateTimelinePreview() {
            let html = '';
            timelineItems.forEach((item, index) => {
                html += `
                    <div class="timeline-item" data-index="${index}">
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" class="form-control form-control-sm mb-2" 
                                       placeholder="Titel" value="${item.title}" 
                                       onchange="updateTimelineItem(${index}, 'title', this.value)">
                            </div>
                            <div class="col-md-4">
                                <input type="date" class="form-control form-control-sm mb-2" 
                                       value="${item.date}" 
                                       onchange="updateTimelineItem(${index}, 'date', this.value)">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="removeTimelineItem(${index})">
                                    <i class="lnr lnr-trash"></i>
                                </button>
                            </div>
                        </div>
                        <textarea class="form-control form-control-sm" rows="2" 
                                  placeholder="Beschrijving" 
                                  onchange="updateTimelineItem(${index}, 'description', this.value)">${item.description}</textarea>
                    </div>
                `;
            });
            
            if (timelineItems.length === 0) {
                html = '<p class="text-muted text-center">Geen tijdlijn items. Klik op "Voeg Stap Toe" om te beginnen.</p>';
            }
            
            $('#timelineItems').html(html);
        }
        
        // Update timeline item
        function updateTimelineItem(index, field, value) {
            if (timelineItems[index]) {
                timelineItems[index][field] = value;
            }
        }
        
        // Remove timeline item
        function removeTimelineItem(index) {
            timelineItems.splice(index, 1);
            updateTimelinePreview();
        }
    </script>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="scroll-to-top-btn" title="Scroll naar boven">
        <i class="lnr lnr-chevron-up"></i>
    </button>

</body>
</html>