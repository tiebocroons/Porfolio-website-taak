<?php
header('Content-Type: application/xml; charset=utf-8');

// Include database configuration
require_once 'database.php';

// Get the current domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain = $protocol . '://' . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<!-- Homepage -->
<url>
    <loc><?php echo $domain; ?>/</loc>
    <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
</url>

<!-- Main sections -->
<url>
    <loc><?php echo $domain; ?>/#home</loc>
    <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
</url>

<url>
    <loc><?php echo $domain; ?>/#about</loc>
    <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
</url>

<url>
    <loc><?php echo $domain; ?>/#portfolio</loc>
    <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
</url>

<url>
    <loc><?php echo $domain; ?>/#contact</loc>
    <lastmod><?php echo date('Y-m-d'); ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
</url>

<?php
// Add individual project pages dynamically
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT id, title, updated_at FROM projects WHERE is_deleted = 0 ORDER BY id");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($projects as $project) {
        $lastmod = $project['updated_at'] ? date('Y-m-d', strtotime($project['updated_at'])) : date('Y-m-d');
        echo "<url>\n";
        echo "    <loc>" . $domain . "/detail.php?id=" . $project['id'] . "</loc>\n";
        echo "    <lastmod>" . $lastmod . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "</url>\n\n";
    }
} catch (Exception $e) {
    // Fallback if database is not available
    error_log("Dynamic sitemap generation error: " . $e->getMessage());
}
?>

</urlset>