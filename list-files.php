<?php
// list-files.php
require_once 'auth.php';

$auth = new Auth();

// Vérifier l'authentification
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

header('Content-Type: application/json');

function scanDirectory($dir = '.') {
    $files = [];
    $scan = scandir($dir);
    
    foreach ($scan as $file) {
        // Ignore les fichiers cachés, système et les fichiers de configuration
        if ($file[0] === '.' || in_array($file, ['index.html', 'list-files.php', 'auth.php', 'config.php'])) {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_file($path)) {
            $files[] = [
                'name' => $file,
                'size' => filesize($path),
                'date' => date('Y-m-d', filemtime($path)),
                'path' => 'get-file.php?file=' . rawurlencode($file)
            ];
        }
    }
    
    return $files;
}

try {
    $files = scanDirectory('.');
    echo json_encode($files);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
