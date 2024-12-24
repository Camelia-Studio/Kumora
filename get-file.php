<?php
// get-file.php
require_once 'auth.php';

$auth = new Auth();

// Vérifier l'authentification
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    exit;
}

// Vérifier si un fichier est spécifié
if (!isset($_GET['file'])) {
    http_response_code(400);
    exit;
}

$filename = $_GET['file'];
$filepath = './' . $filename;

// Vérifier que le fichier existe et est dans le dossier courant
if (!file_exists($filepath) || !is_file($filepath) || dirname(realpath($filepath)) !== realpath('.')) {
    http_response_code(404);
    exit;
}

// Fichiers système à ne pas servir
$forbidden_files = ['index.html', 'list-files.php', 'auth.php', 'config.php', 'get-file.php'];
if (in_array($filename, $forbidden_files)) {
    http_response_code(403);
    exit;
}

// Servir le fichier
$mime_type = mime_content_type($filepath);
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
readfile($filepath);
?>
