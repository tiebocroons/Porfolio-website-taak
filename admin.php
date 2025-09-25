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
            
        case 'get_statistics':
            echo json_encode(getStatistics($pdo));
            exit;
            
        case 'save_statistics':
            echo json_encode(saveStatistics($pdo, $_POST));
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
        $timeline = isset($data['timeline']) ? json_encode($data['timeline']) : json_encode([]);
        $tools = json_encode(array_filter(explode(',', isset($data['tools']) ? trim($data['tools']) : '')));
        $features = json_encode(array_filter(explode("\n", isset($data['features']) ? trim($data['features']) : '')));
        
        // Handle gallery images
        $gallery_images = json_encode(array_filter(explode("\n", isset($data['gallery_images']) ? trim($data['gallery_images']) : '')));
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing project
            $stmt = $pdo->prepare("UPDATE projects SET 
                title = ?, description = ?, short_description = ?, category = ?, status = ?,
                tools = ?, live_url = ?, demo_url = ?, features = ?, image_url = ?, 
                client_name = ?, project_duration = ?, completion_date = ?, 
                is_featured = ?, timeline = ?, gallery_images = ?,
                github_url = ?, api_docs_url = ?, challenges = ?,
                design_concept = ?, color_palette = ?, typography = ?,
                design_category = ?, design_style = ?, 
                performance_score = ?, code_quality = ?, lines_of_code = ?, 
                components_count = ?, development_weeks = ?,
                creative_challenge = ?, creative_approach = ?, creative_solution = ?,
                inspiration_source = ?, lessons_learned = ?, 
                project_types = ?, is_hybrid = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
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
                isset($data['design_category']) ? $data['design_category'] : null,
                isset($data['design_style']) ? $data['design_style'] : null,
                // Statistical fields
                isset($data['performance_score']) ? (int)$data['performance_score'] : null,
                isset($data['code_quality']) ? $data['code_quality'] : null,
                isset($data['lines_of_code']) ? (int)$data['lines_of_code'] : null,
                isset($data['components_count']) ? (int)$data['components_count'] : null,
                isset($data['development_weeks']) ? (int)$data['development_weeks'] : null,
                // Creative Process fields
                isset($data['creative_challenge']) ? $data['creative_challenge'] : '',
                isset($data['creative_approach']) ? $data['creative_approach'] : '',
                isset($data['creative_solution']) ? $data['creative_solution'] : '',
                isset($data['inspiration_source']) ? $data['inspiration_source'] : '',
                isset($data['lessons_learned']) ? $data['lessons_learned'] : '',
                // New hybrid project fields
                isset($data['project_types']) ? $data['project_types'] : json_encode([$data['category']]),
                isset($data['is_hybrid']) ? (bool)$data['is_hybrid'] : false,
                $data['id']
            ]);
            return ['success' => true, 'message' => 'Project succesvol bijgewerkt!'];
        } else {
            // Create new project
            $stmt = $pdo->prepare("INSERT INTO projects 
                (title, description, short_description, category, status, tools, live_url, demo_url, 
                 features, image_url, client_name, project_duration, completion_date, is_featured, 
                 timeline, gallery_images, github_url, api_docs_url, challenges, design_concept, 
                 color_palette, typography, design_category, design_style, performance_score, 
                 code_quality, lines_of_code, components_count, development_weeks,
                 creative_challenge, creative_approach, creative_solution, inspiration_source, lessons_learned,
                 project_types, is_hybrid, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
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
                isset($data['design_category']) ? $data['design_category'] : null,
                isset($data['design_style']) ? $data['design_style'] : null,
                // Statistical fields
                isset($data['performance_score']) ? (int)$data['performance_score'] : null,
                isset($data['code_quality']) ? $data['code_quality'] : null,
                isset($data['lines_of_code']) ? (int)$data['lines_of_code'] : null,
                isset($data['components_count']) ? (int)$data['components_count'] : null,
                isset($data['development_weeks']) ? (int)$data['development_weeks'] : null,
                // Creative Process fields
                isset($data['creative_challenge']) ? $data['creative_challenge'] : '',
                isset($data['creative_approach']) ? $data['creative_approach'] : '',
                isset($data['creative_solution']) ? $data['creative_solution'] : '',
                isset($data['inspiration_source']) ? $data['inspiration_source'] : '',
                isset($data['lessons_learned']) ? $data['lessons_learned'] : '',
                // New hybrid project fields
                isset($data['project_types']) ? $data['project_types'] : json_encode([$data['category']]),
                isset($data['is_hybrid']) ? (bool)$data['is_hybrid'] : false
            ]);
            return ['success' => true, 'message' => 'Project succesvol toegevoegd!'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteProject($pdo, $id) {
    try {
        $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
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
                'development' => isset($actualCategoryCounts['development']) ? $actualCategoryCounts['development'] : 0,
                'design' => isset($actualCategoryCounts['design']) ? $actualCategoryCounts['design'] : 0,
                'vintage' => isset($actualCategoryCounts['vintage']) ? $actualCategoryCounts['vintage'] : 0
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

function saveStatistics($pdo, $data) {
    try {
        $statisticsToUpdate = [
            'stats_years_experience' => isset($data['years_experience']) ? (int)$data['years_experience'] : 3,
            'stats_total_projects' => isset($data['total_projects']) ? (int)$data['total_projects'] : null,
            'stats_tools_count' => isset($data['tools_count']) ? (int)$data['tools_count'] : 5,
            'filter_development_count' => isset($data['development_count']) ? (int)$data['development_count'] : null,
            'filter_design_count' => isset($data['design_count']) ? (int)$data['design_count'] : null,
            'filter_photography_count' => isset($data['photography_count']) ? (int)$data['photography_count'] : null,
            'filter_all_count' => isset($data['all_count']) ? (int)$data['all_count'] : null,
            'github_username' => isset($data['github_username']) ? trim($data['github_username']) : null,
            'github_token' => isset($data['github_token']) ? trim($data['github_token']) : null,
            // Auto-calculation flags
            'filter_development_count_auto' => isset($data['development_count_auto']) ? '1' : '0',
            'filter_design_count_auto' => isset($data['design_count_auto']) ? '1' : '0',
            'filter_photography_count_auto' => isset($data['photography_count_auto']) ? '1' : '0',
            'filter_all_count_auto' => isset($data['all_count_auto']) ? '1' : '0'
        ];
        
        foreach ($statisticsToUpdate as $key => $value) {
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
    
    <!-- Bootstrap CSS -->
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    
    <!-- Icon Libraries -->
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="css/style.min.css" rel="stylesheet">
    
    <!-- Admin specific styles -->
    <style>
        /* Auto-disabled input styling */
        .form-control.auto-disabled {
            background-color: #e9ecef;
            opacity: 0.65;
            cursor: not-allowed;
        }
        
        .input-group-text {
            font-size: 0.875rem;
        }
        
        .input-group-text .form-check-input {
            margin-right: 0.25rem;
        }
        
        /* Phase type selector enhancement */
        #phaseType {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        #phaseType:focus {
            border-left-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        /* Form field update animation */
        .form-updated {
            animation: fieldUpdate 0.6s ease-in-out;
        }
        
        @keyframes fieldUpdate {
            0% { background-color: #e3f2fd; }
            50% { background-color: #bbdefb; }
            100% { background-color: #fff; }
        }
        
        /* Preset indicator */
        .preset-indicator {
            position: relative;
        }
        
        .preset-indicator::after {
            content: '✨';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            opacity: 0.6;
            pointer-events: none;
        }
        
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
            color: #2c2c2c;
        }
        
        .admin-header h1 {
            color: #2c2c2c !important;
            font-family: "Montserrat", sans-serif;
            font-weight: 600;
        }
        
        .admin-header p {
            color: #495057 !important;
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
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Alert styles with improved contrast */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #0f5132;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #58151c;
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
        
        /* Project category badges */
        .badge-development {
            background-color: #007bff;
            color: white;
        }
        
        .badge-design {
            background-color: #e83e8c;
            color: white;
        }
        
        .badge-vintage {
            background-color: #6f42c1;
            color: white;
        }
        
        .badge-web {
            background-color: #28a745;
            color: white;
        }
        
        .badge-mobile {
            background-color: #fd7e14;
            color: white;
        }
        
        .badge-other {
            background-color: #6c757d;
            color: white;
        }
        
        /* Project list styling */
        .project-item {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .project-item:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* Dynamic form section styling */
        #developmentFields, #designFields {
            border: 2px dashed rgba(27, 177, 220, 0.3);
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            background: rgba(27, 177, 220, 0.05);
            position: relative;
            transition: all 0.3s ease;
        }
        
        #developmentFields:before {
            content: "💻 Development Project";
            position: absolute;
            top: -12px;
            left: 20px;
            background: rgba(27, 177, 220, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1bb1dc;
            border: 1px solid rgba(27, 177, 220, 0.3);
        }
        
        #designFields:before {
            content: "🎨 Design Project";
            position: absolute;
            top: -12px;
            left: 20px;
            background: rgba(255, 20, 147, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: hsl(312, 100%, 50%);
            border: 1px solid rgba(255, 20, 147, 0.3);
        }
        
        #designFields {
            border-color: rgba(255, 20, 147, 0.3);
            background: rgba(255, 20, 147, 0.05);
        }
        
        /* Form section icons with better contrast */
        .form-label i {
            margin-right: 8px;
            color: #495057;
            width: 16px;
            text-align: center;
        }
        
        #developmentFields .form-label i {
            color: #1bb1dc;
        }
        
        #designFields .form-label i {
            color: hsl(312, 100%, 50%);
        }
        
        /* Hybrid project info styling */
        #hybridProjectInfo {
            border-left: 4px solid #17a2b8;
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(23, 162, 184, 0.05));
            border: 1px solid rgba(23, 162, 184, 0.2);
            border-radius: 8px;
        }
        
        #hybridProjectInfo .lnr {
            color: #17a2b8;
            font-size: 1.2em;
        }
        
        /* Image gallery section */
        #imageGallerySection {
            border: 2px dashed rgba(255, 193, 7, 0.4);
            border-radius: 8px;
            padding: 20px;
            background: rgba(255, 193, 7, 0.05);
        }
        
        /* Enhanced form validation styling */
        .required-conditional {
            border-left: 3px solid #28a745 !important;
        }
        
        .required-conditional:focus {
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15) !important;
        }
        
        /* Category hint styling with better contrast */
        #categoryHint {
            font-style: italic;
            color: #0056b3 !important;
            font-weight: 500;
        }
        
        /* Success state for filled sections */
        .form-section-completed {
            border-color: rgba(40, 167, 69, 0.5) !important;
            background-color: rgba(40, 167, 69, 0.05) !important;
        }
        
        .form-section-completed:before {
            color: #28a745 !important;
        }
        
        /* Timeline Phase Management Styles */
        #timelineManagementSection {
            background: rgba(74, 144, 226, 0.02);
            border: 1px solid rgba(74, 144, 226, 0.1);
            border-radius: 12px;
        }
        
        #timelinePhaseForm {
            border: 2px dashed rgba(74, 144, 226, 0.3);
            border-radius: 10px;
            background: rgba(74, 144, 226, 0.05);
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        #timelinePhaseForm.show {
            border-style: solid;
            background: rgba(74, 144, 226, 0.08);
        }
        
        .timeline-phases {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .timeline-phases::-webkit-scrollbar {
            width: 8px;
        }
        
        .timeline-phases::-webkit-scrollbar-track {
            background: rgba(74, 144, 226, 0.1);
            border-radius: 4px;
        }
        
        .timeline-phases::-webkit-scrollbar-thumb {
            background: rgba(74, 144, 226, 0.3);
            border-radius: 4px;
        }
        
        .timeline-phases::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 144, 226, 0.5);
        }
        
        .phase-item {
            position: relative;
            margin-bottom: 1.5rem !important;
            transform: translateX(0);
            transition: all 0.3s ease;
        }
        
        .phase-item:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.15);
        }
        
        .phase-item .admin-card {
            border-left: 4px solid var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .phase-item:hover .admin-card {
            border-left-width: 6px;
        }
        
        .phase-item .card-header {
            background: linear-gradient(45deg, rgba(74, 144, 226, 0.05), rgba(74, 144, 226, 0.1));
            border-bottom: 1px solid rgba(74, 144, 226, 0.1);
        }
        
        .phase-item .card-header h5 {
            color: var(--accent-color);
            font-weight: 600;
        }
        
        .phase-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0.5rem;
        }
        
        .status-planned {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.2);
        }
        
        .status-in_progress {
            background: rgba(255, 193, 7, 0.15);
            color: #664d03;
            border: 1px solid rgba(255, 193, 7, 0.4);
        }
        
        .status-completed {
            background: rgba(40, 167, 69, 0.15);
            color: #0f5132;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }
        
        .status-on_hold {
            background: rgba(220, 53, 69, 0.15);
            color: #58151c;
            border: 1px solid rgba(220, 53, 69, 0.4);
        }
        
        .phase-item .btn-sm {
            transition: all 0.2s ease;
        }
        
        .phase-item .btn-outline-light:hover {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            transform: scale(1.05);
        }
        
        .phase-item .btn-outline-danger:hover {
            transform: scale(1.05);
        }
        
        .phase-planning-info {
            background: rgba(74, 144, 226, 0.05);
            border-radius: 6px;
            padding: 0.75rem;
            border-left: 3px solid var(--accent-color);
        }
        
        .phase-tasks-list {
            background: rgba(40, 167, 69, 0.05);
            border-radius: 6px;
            padding: 0.75rem;
            border-left: 3px solid #28a745;
        }
        
        .phase-deliverables-list {
            background: rgba(255, 193, 7, 0.05);
            border-radius: 6px;
            padding: 0.75rem;
            border-left: 3px solid #ffc107;
        }
        
        .phase-tasks-list ul,
        .phase-deliverables-list ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
        
        .phase-tasks-list li,
        .phase-deliverables-list li {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        /* Timeline selection styles */
        #timelineProjectSelect {
            border: 2px solid rgba(74, 144, 226, 0.2);
            transition: all 0.3s ease;
        }
        
        #timelineProjectSelect:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.15);
        }
        
        /* Add Phase Button */
        #addTimelinePhase {
            background: linear-gradient(45deg, var(--accent-color), #5a9bd4);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        #addTimelinePhase:hover {
            background: linear-gradient(45deg, #5a9bd4, var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }
        
        /* Empty state styling with improved contrast */
        .timeline-empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(74, 144, 226, 0.02);
            border: 2px dashed rgba(74, 144, 226, 0.2);
            border-radius: 12px;
            margin: 2rem 0;
        }
        
        .timeline-empty-state i {
            font-size: 4rem !important;
            color: rgba(74, 144, 226, 0.4);
            margin-bottom: 1rem;
        }
        
        .timeline-empty-state p {
            color: #495057;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Phase form animations */
        #timelinePhaseForm {
            transition: all 0.4s ease;
        }
        
        .phase-form-slide-down {
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive timeline styles */
        @media (max-width: 768px) {
            .phase-item {
                margin-bottom: 1rem !important;
            }
            
            .phase-item .card-header {
                padding: 0.75rem;
            }
            
            .phase-item .card-header h5 {
                font-size: 1rem;
            }
            
            .phase-status-badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
            
            .timeline-phases {
                max-height: 400px;
            }
        }
        
        /* Additional contrast improvements */
        body {
            color: #2c2c2c;
        }
        
        h1, h2, h3, h4, h5, h6 {
            color: #2c2c2c !important;
        }
        
        .form-label, label {
            color: #2c2c2c !important;
            font-weight: 600;
        }
        
        .text-secondary {
            color: #495057 !important;
        }
        
        /* Input placeholder contrast */
        .form-control::placeholder,
        .form-select::placeholder {
            color: #6c757d;
            opacity: 1;
        }
        
        /* Button text contrast */
        .btn {
            font-weight: 600;
        }
        
        .btn-light {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #2c2c2c;
        }
        
        .btn-light:hover {
            background-color: #e2e6ea;
            border-color: #dae0e5;
            color: #2c2c2c;
        }
        
        /* Card text contrast */
        .card-text, .small {
            color: #495057;
        }
        
        /* Link contrast */
        a {
            color: #0056b3;
        }
        
        a:hover {
            color: #004085;
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
                    <button type="button" class="btn btn-outline-info me-2" id="manageTimelineBtn">
                        <i class="lnr lnr-calendar-full"></i>
                        Timeline Beheer
                    </button>
                    <button type="button" class="btn btn-outline-danger me-2" id="logoutBtn">
                        <i class="lnr lnr-exit"></i>
                        Uitloggen
                    </button>
                    <a href="index.php" class="btn btn-outline-primary">
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
                            <div class="stat-number" id="vintageProjects"><?php echo $stats['vintage']; ?></div>
                            <div class="stat-label">Vintage</div>
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
                                <div class="col-md-6">
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
                        <button type="button" class="btn btn-outline-light btn-sm" id="refreshProjects">
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

        <!-- Timeline Management Section -->
        <div class="row mt-4" id="timelineManagementSection" style="display: none;">
            <div class="col-12">
                <div class="admin-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">
                                <i class="lnr lnr-calendar-full"></i>
                                Project Timeline Beheer
                            </h3>
                            <p class="mb-0 text-muted">Beheer timeline fasen voor gedetailleerde project voortgang</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-light btn-sm" id="addTimelinePhase">
                                <i class="lnr lnr-plus-circle"></i>
                                Nieuwe Fase
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        
                        <!-- Project Selection for Timeline -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="timelineProjectSelect" class="form-label">Project Selecteren</label>
                                <select class="form-select" id="timelineProjectSelect">
                                    <option value="">Selecteer een project voor timeline beheer</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary" id="loadProjectTimeline">
                                    <i class="lnr lnr-eye"></i>
                                    Timeline Laden
                                </button>
                            </div>
                        </div>
                        
                        <!-- Timeline Phase Form -->
                        <div id="timelinePhaseForm" style="display: none;">
                            <div class="admin-card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="lnr lnr-pencil"></i>
                                        <span id="phaseFormTitle">Nieuwe Timeline Fase</span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form id="phaseForm">
                                        <input type="hidden" name="action" value="save_timeline_phase">
                                        <input type="hidden" id="phaseId" name="phase_id">
                                        <input type="hidden" id="phaseProjectId" name="project_id">
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="phaseName" class="form-label">Fase Naam</label>
                                                <input type="text" class="form-control" id="phaseName" name="phase_name" required 
                                                       placeholder="bijv. Design & Planning">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="phaseType" class="form-label">Fase Type</label>
                                                <div class="input-group">
                                                    <select class="form-select" id="phaseType" name="phase_type">
                                                        <option value="">Selecteer type...</option>
                                                        <option value="planning" data-icon="calendar">Planning</option>
                                                        <option value="design" data-icon="magic-wand">Design</option>
                                                        <option value="development" data-icon="laptop">Development</option>
                                                        <option value="testing" data-icon="bug">Testing</option>
                                                        <option value="deployment" data-icon="rocket">Deployment</option>
                                                        <option value="challenge" data-icon="question-circle">Challenge</option>
                                                        <option value="approach" data-icon="cog">Approach</option>
                                                        <option value="solution" data-icon="checkmark-circle">Solution</option>
                                                    </select>
                                                    <button type="button" class="btn btn-outline-secondary" id="forceFillBtn" 
                                                            title="Force fill all fields with preset data" disabled>
                                                        <i class="lnr lnr-magic-wand"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Automatically fills empty fields</small>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="phaseWeekNumber" class="form-label">Week Nummer</label>
                                                <input type="number" class="form-control" id="phaseWeekNumber" name="week_number" 
                                                       min="1" max="52" placeholder="1">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phaseDescription" class="form-label">Korte Beschrijving</label>
                                            <textarea class="form-control" id="phaseDescription" name="phase_description" rows="2" 
                                                      placeholder="Korte samenvatting van deze fase"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phaseDetails" class="form-label">Gedetailleerde Beschrijving</label>
                                            <textarea class="form-control" id="phaseDetails" name="phase_details" rows="4" 
                                                      placeholder="Gedetailleerde uitleg van activiteiten en processen in deze fase"></textarea>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="phaseStatus" class="form-label">Status</label>
                                                <select class="form-select" id="phaseStatus" name="phase_status">
                                                    <option value="planned">Gepland</option>
                                                    <option value="in_progress">In Uitvoering</option>
                                                    <option value="completed">Voltooid</option>
                                                    <option value="skipped">Overgeslagen</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="phaseStartDate" class="form-label">Start Datum</label>
                                                <input type="date" class="form-control" id="phaseStartDate" name="start_date">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="phaseEndDate" class="form-label">Eind Datum</label>
                                                <input type="date" class="form-control" id="phaseEndDate" name="end_date">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="phaseTasks" class="form-label">Taken</label>
                                                <textarea class="form-control" id="phaseTasks" name="tasks" rows="4" 
                                                          placeholder="Elke taak op een nieuwe regel:&#10;Taak 1&#10;Taak 2&#10;Taak 3"></textarea>
                                                <small class="text-muted">Elke taak op een nieuwe regel</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="phaseDeliverables" class="form-label">Opgeleverd</label>
                                                <textarea class="form-control" id="phaseDeliverables" name="deliverables" rows="4" 
                                                          placeholder="Elke deliverable op een nieuwe regel:&#10;Document 1&#10;Prototype&#10;Code repository"></textarea>
                                                <small class="text-muted">Elke deliverable op een nieuwe regel</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="lnr lnr-checkmark-circle"></i>
                                                <span id="phaseSubmitText">Fase Opslaan</span>
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="cancelPhaseForm">
                                                <i class="lnr lnr-cross-circle"></i>
                                                Annuleren
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timeline Phases List -->
                        <div id="timelinePhasesList">
                            <div class="text-center text-muted py-5">
                                <i class="lnr lnr-calendar-full" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-3">Selecteer een project om de timeline te beheren</p>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Management Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="admin-card">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="lnr lnr-chart-bars"></i>
                            Website Statistieken Beheer
                        </h3>
                        <p class="mb-0 text-muted">Beheer de getallen die op je portfolio website worden getoond</p>
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
                                        <h6><i class="lnr lnr-database"></i> Huidige Database Waardes (ter referentie)</h6>
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

                // Auto-calculation toggle handlers
                $('#developmentCountAuto, #designCountAuto, #photographyCountAuto, #allCountAuto').on('change', () => {
                    this.toggleAutoInputs();
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
                if (selectedCategory && (isDevelopment || isDesign)) {
                    $('#projectTypeGuide').slideDown(300);
                } else {
                    $('#projectTypeGuide').slideUp(300);
                }
                
                // Check current visibility to prevent unnecessary animations
                const isDevFieldsVisible = $('#developmentFields').is(':visible');
                const isDesignFieldsVisible = $('#designFields').is(':visible');
                const shouldShowDev = isDevelopment || hasDevContent || isHybridChecked;
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
                    const response = await this.makeRequest('get_statistics');
                    if (response.success) {
                        const settings = response.settings;
                        const actualCounts = response.actual_counts;
                        
                        // Fill form fields with current settings
                        $('#yearsExperience').val(settings.stats_years_experience || '');
                        $('#totalProjects').val(settings.stats_total_projects || '');
                        $('#toolsCount').val(settings.stats_tools_count || '');
                        $('#developmentCount').val(settings.filter_development_count || '');
                        $('#designCount').val(settings.filter_design_count || '');
                        $('#photographyCount').val(settings.filter_photography_count || '');
                        $('#allCount').val(settings.filter_all_count || '');
                        
                        // Fill auto-calculation checkboxes
                        $('#developmentCountAuto').prop('checked', settings.filter_development_count_auto === '1');
                        $('#designCountAuto').prop('checked', settings.filter_design_count_auto === '1');
                        $('#photographyCountAuto').prop('checked', settings.filter_photography_count_auto === '1');
                        $('#allCountAuto').prop('checked', settings.filter_all_count_auto === '1');
                        
                        // Disable/enable inputs based on auto checkboxes
                        this.toggleAutoInputs();
                        
                        // Fill GitHub settings
                        $('#githubUsernameStats').val(settings.github_username || '');
                        $('#githubTokenStats').val(settings.github_token || '');
                        
                        // Update actual counts display
                        $('#actualTotal').text(actualCounts.total_projects);
                        $('#actualDevelopment').text(actualCounts.development);
                        $('#actualDesign').text(actualCounts.design);
                        $('#actualVintage').text(actualCounts.vintage);
                        
                    } else {
                        this.showAlert('Fout bij laden statistieken: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij laden statistieken: ' + error.message, 'danger');
                }
            }

            toggleAutoInputs() {
                // Disable/enable inputs based on auto checkbox state
                const autoFields = [
                    ['#developmentCountAuto', '#developmentCount'],
                    ['#designCountAuto', '#designCount'],
                    ['#photographyCountAuto', '#photographyCount'],
                    ['#allCountAuto', '#allCount']
                ];
                
                autoFields.forEach(([checkboxId, inputId]) => {
                    const isAuto = $(checkboxId).is(':checked');
                    $(inputId).prop('disabled', isAuto);
                    if (isAuto) {
                        $(inputId).addClass('auto-disabled');
                    } else {
                        $(inputId).removeClass('auto-disabled');
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
                
                // Add auto-calculation flags
                if ($('#developmentCountAuto').is(':checked')) formData.append('development_count_auto', '1');
                if ($('#designCountAuto').is(':checked')) formData.append('design_count_auto', '1');
                if ($('#photographyCountAuto').is(':checked')) formData.append('photography_count_auto', '1');
                if ($('#allCountAuto').is(':checked')) formData.append('all_count_auto', '1');

                try {
                    const response = await this.makeRequest('save_statistics', formData);
                    if (response.success) {
                        this.showAlert('Statistieken succesvol bijgewerkt! Wijzigingen zijn direct zichtbaar op de website.', 'success');
                        // Reload current statistics to refresh actual counts
                        setTimeout(() => {
                            this.loadStatistics();
                        }, 1000);
                    } else {
                        this.showAlert('Fout bij opslaan statistieken: ' + response.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij opslaan statistieken: ' + error.message, 'danger');
                }
            }

            // Timeline Management Methods
            setupTimelineManagement() {
                // Timeline management button
                $('#manageTimelineBtn').on('click', () => {
                    this.toggleTimelineSection();
                });
                
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
                
                // Load projects for timeline selection
                this.loadProjectsForTimeline();
            }
            
            toggleTimelineSection() {
                const section = $('#timelineManagementSection');
                const isVisible = section.is(':visible');
                
                if (!isVisible) {
                    section.slideDown(300);
                    $('#manageTimelineBtn').html('<i class="lnr lnr-cross-circle"></i> Timeline Sluiten');
                } else {
                    section.slideUp(300);
                    $('#manageTimelineBtn').html('<i class="lnr lnr-calendar-full"></i> Timeline Beheer');
                }
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
                        </div>
                    `;
                });
                html += '</div>';
                
                container.html(html);
            }
            
            getStatusBadge(status) {
                const badges = {
                    'planned': '<span class="phase-status-badge status-planned">Gepland</span>',
                    'in_progress': '<span class="phase-status-badge status-in_progress">In Uitvoering</span>',
                    'completed': '<span class="phase-status-badge status-completed">Voltooid</span>',
                    'on_hold': '<span class="phase-status-badge status-on_hold">On Hold</span>'
                };
                return badges[status] || '';
            }
            
            showPhaseForm(projectId, phaseData = null) {
                $('#phaseProjectId').val(projectId);
                
                if (phaseData) {
                    // Edit mode
                    $('#phaseFormTitle').text('Timeline Fase Bewerken');
                    $('#phaseSubmitText').text('Wijzigingen Opslaan');
                    $('#phaseId').val(phaseData.id);
                    $('#phaseName').val(phaseData.phase_name);
                    $('#phaseType').val(phaseData.phase_type || '');
                    $('#phaseWeekNumber').val(phaseData.week_number);
                    $('#phaseDescription').val(phaseData.phase_description);
                    $('#phaseDetails').val(phaseData.phase_details);
                    $('#phaseStatus').val(phaseData.phase_status);
                    $('#phaseStartDate').val(phaseData.start_date);
                    $('#phaseEndDate').val(phaseData.end_date);
                    
                    // Handle tasks and deliverables
                    const tasks = phaseData.tasks ? JSON.parse(phaseData.tasks).join('\n') : '';
                    const deliverables = phaseData.deliverables ? JSON.parse(phaseData.deliverables).join('\n') : '';
                    $('#phaseTasks').val(tasks);
                    $('#phaseDeliverables').val(deliverables);
                } else {
                    // Add mode
                    $('#phaseFormTitle').text('Nieuwe Timeline Fase');
                    $('#phaseSubmitText').text('Fase Opslaan');
                    $('#phaseForm')[0].reset();
                    $('#phaseProjectId').val(projectId);
                }
                
                $('#timelinePhaseForm').slideDown(300);
            }
            
            hidePhaseForm() {
                $('#timelinePhaseForm').slideUp(300);
                $('#phaseForm')[0].reset();
            }
            
            async saveTimelinePhase() {
                const formData = new FormData($('#phaseForm')[0]);
                
                try {
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        this.hidePhaseForm();
                        // Reload timeline
                        const projectId = $('#timelineProjectSelect').val();
                        if (projectId) {
                            this.loadProjectTimeline(projectId);
                        }
                    } else {
                        this.showAlert('Fout bij opslaan fase: ' + result.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij opslaan fase: ' + error.message, 'danger');
                }
            }
            
            async editPhase(phaseId) {
                // Get current timeline phases to find this phase
                const projectId = $('#timelineProjectSelect').val();
                if (!projectId) return;
                
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
                        const phase = result.data.find(p => p.id == phaseId);
                        if (phase) {
                            this.showPhaseForm(projectId, phase);
                        }
                    }
                } catch (error) {
                    this.showAlert('Error loading phase data: ' + error.message, 'danger');
                }
            }
            
            async deletePhase(phaseId) {
                if (!confirm('Weet je zeker dat je deze timeline fase wilt verwijderen?')) {
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_timeline_phase');
                    formData.append('phase_id', phaseId);
                    
                    const response = await fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showAlert(result.message, 'success');
                        // Reload timeline
                        const projectId = $('#timelineProjectSelect').val();
                        if (projectId) {
                            this.loadProjectTimeline(projectId);
                        }
                    } else {
                        this.showAlert('Fout bij verwijderen fase: ' + result.error, 'danger');
                    }
                } catch (error) {
                    this.showAlert('Network error bij verwijderen fase: ' + error.message, 'danger');
                }
            }

            showAlert(message, type = 'success', duration = 5000) {
                const alertClass = type === 'success' ? 'alert-success' : 
                                  type === 'danger' ? 'alert-danger' : 
                                  type === 'info' ? 'alert-info' : 'alert-secondary';
                const icon = type === 'success' ? 'lnr-checkmark-circle' : 
                            type === 'danger' ? 'lnr-warning' : 
                            type === 'info' ? 'lnr-info' : 'lnr-circle-minus';
                
                const html = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        <i class="lnr ${icon}"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                $('#alertContainer').html(html);
                
                // Auto hide after specified duration
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, duration);
            }
        }
        
        // Phase type form customization
        const phaseTypePresets = {
            'planning': {
                name: 'Planning & Analyse',
                description: 'Projectplanning, requirement analyse en strategische voorbereidingen',
                tasks: 'Stakeholder interviews\nRequirement gathering\nProject scope definitie\nTimeline planning\nResource allocatie',
                deliverables: 'Project plan\nRequirement document\nStakeholder mapping\nRisk assessment'
            },
            'design': {
                name: 'Design & Conceptualisatie',
                description: 'Visueel ontwerp, UX/UI design en creative conceptontwikkeling',
                tasks: 'User research\nWireframing\nVisual design\nPrototyping\nDesign system',
                deliverables: 'Design mockups\nPrototype\nStyle guide\nDesign specifications'
            },
            'development': {
                name: 'Development & Implementatie',
                description: 'Technische ontwikkeling, programmeren en system implementatie',
                tasks: 'Code development\nDatabase setup\nAPI integration\nFeature implementation\nCode review',
                deliverables: 'Working code\nDatabase schema\nAPI endpoints\nUnit tests\nTechnical documentation'
            },
            'testing': {
                name: 'Testing & Kwaliteitsborging',
                description: 'Uitgebreide testing, bug fixes en kwaliteitscontrole',
                tasks: 'Unit testing\nIntegration testing\nUser acceptance testing\nBug fixing\nPerformance testing',
                deliverables: 'Test reports\nBug fixes\nQuality assurance document\nTest coverage report'
            },
            'deployment': {
                name: 'Deployment & Go-Live',
                description: 'Live deployment, server configuratie en productie launch',
                tasks: 'Server setup\nProduction deployment\nDNS configuration\nSSL setup\nMonitoring setup',
                deliverables: 'Live website\nServer documentation\nDeployment guide\nMonitoring dashboard'
            },
            'challenge': {
                name: 'Uitdaging & Probleemdefinitie',
                description: 'Identificatie en analyse van de kernuitdaging of probleem',
                tasks: 'Problem identification\nStakeholder analysis\nCurrent state assessment\nPain point mapping\nSuccess criteria definition',
                deliverables: 'Problem statement\nStakeholder map\nCurrent state analysis\nSuccess metrics'
            },
            'approach': {
                name: 'Aanpak & Methodologie',
                description: 'Strategische aanpak en methodologie voor probleemoplossing',
                tasks: 'Strategy formulation\nMethodology selection\nApproach documentation\nResource planning\nRisk mitigation',
                deliverables: 'Strategy document\nMethodology guide\nApproach framework\nResource plan'
            },
            'solution': {
                name: 'Oplossing & Resultaat',
                description: 'Eindoplossing, implementatie en behaalde resultaten',
                tasks: 'Solution implementation\nResult measurement\nImpact assessment\nDocumentation\nLessons learned',
                deliverables: 'Final solution\nResults report\nImpact analysis\nLesson learned document'
            }
        };
        
        // Handle phase type selection change
        $(document).on('change', '#phaseType', function() {
            const selectedType = $(this).val();
            const forceFillBtn = $('#forceFillBtn');
            
            if (selectedType && phaseTypePresets[selectedType]) {
                // Enable force fill button
                forceFillBtn.prop('disabled', false).attr('title', `Force fill with ${selectedType} preset`);
                
                const preset = phaseTypePresets[selectedType];
                
                // Add visual feedback
                const fieldsToUpdate = [];
                
                // Only update if fields are empty (don't overwrite user input)
                if (!$('#phaseName').val()) {
                    $('#phaseName').val(preset.name);
                    fieldsToUpdate.push($('#phaseName'));
                }
                if (!$('#phaseDescription').val()) {
                    $('#phaseDescription').val(preset.description);
                    fieldsToUpdate.push($('#phaseDescription'));
                }
                if (!$('#phaseTasks').val()) {
                    $('#phaseTasks').val(preset.tasks);
                    fieldsToUpdate.push($('#phaseTasks'));
                }
                if (!$('#phaseDeliverables').val()) {
                    $('#phaseDeliverables').val(preset.deliverables);
                    fieldsToUpdate.push($('#phaseDeliverables'));
                }
                
                // Add visual feedback for updated fields
                fieldsToUpdate.forEach(field => {
                    field.addClass('form-updated preset-indicator');
                    setTimeout(() => {
                        field.removeClass('form-updated');
                    }, 600);
                });
                
                // Update placeholders for better UX
                $('#phaseName').attr('placeholder', preset.name);
                $('#phaseDescription').attr('placeholder', preset.description);
                $('#phaseTasks').attr('placeholder', preset.tasks.split('\n').slice(0,2).join('\n') + '\n...');
                $('#phaseDeliverables').attr('placeholder', preset.deliverables.split('\n').slice(0,2).join('\n') + '\n...');
                
                // Show success message if fields were filled
                if (fieldsToUpdate.length > 0) {
                    const message = `Auto-filled ${fieldsToUpdate.length} field${fieldsToUpdate.length > 1 ? 's' : ''} for ${selectedType} phase`;
                    portfolioAdmin.showAlert(message, 'success', 2000);
                }
            } else {
                // Disable force fill button
                forceFillBtn.prop('disabled', true).attr('title', 'Select a phase type first');
                
                // Reset placeholders to default
                $('#phaseName').attr('placeholder', 'bijv. Design & Planning');
                $('#phaseDescription').attr('placeholder', 'Korte samenvatting van deze fase');
                $('#phaseTasks').attr('placeholder', 'Elke taak op een nieuwe regel:\nTaak 1\nTaak 2\nTaak 3');
                $('#phaseDeliverables').attr('placeholder', 'Elke deliverable op een nieuwe regel:\nDocument 1\nPrototype\nCode repository');
                
                // Remove preset indicators
                $('.preset-indicator').removeClass('preset-indicator');
            }
        });
        
        // Handle force fill button click
        $(document).on('click', '#forceFillBtn', function() {
            const selectedType = $('#phaseType').val();
            if (!selectedType || !phaseTypePresets[selectedType]) return;
            
            if (!confirm('This will overwrite all current field values. Are you sure?')) return;
            
            const preset = phaseTypePresets[selectedType];
            
            // Force fill all fields
            const fieldsUpdated = [];
            $('#phaseName').val(preset.name);
            $('#phaseDescription').val(preset.description);
            $('#phaseTasks').val(preset.tasks);
            $('#phaseDeliverables').val(preset.deliverables);
            
            // Add visual feedback
            const allFields = [$('#phaseName'), $('#phaseDescription'), $('#phaseTasks'), $('#phaseDeliverables')];
            allFields.forEach(field => {
                field.addClass('form-updated preset-indicator');
                setTimeout(() => {
                    field.removeClass('form-updated');
                }, 600);
            });
            
            portfolioAdmin.showAlert(`Force-filled all fields with ${selectedType} preset`, 'info', 3000);
        });

        // Initialize when document is ready
        let portfolioAdmin;
        $(document).ready(() => {
            portfolioAdmin = new PortfolioAdmin();
        });
    </script>
</body>
</html>