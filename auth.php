<?php
// auth.php
session_start();

class Auth {
    private $config;
    
    public function __construct() {
        $this->config = require 'config.php';
    }
    
    public function isAuthenticated() {
        if (!isset($_SESSION['auth_time']) || !isset($_SESSION['username'])) {
            return false;
        }
        
        // Vérifier si la session n'a pas expiré
        $elapsed = time() - $_SESSION['auth_time'];
        if ($elapsed > $this->config['session_duration']) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function login($username, $password) {
        if (!isset($this->config['users'][$username])) {
            return false;
        }

        if ($this->config['users'][$username]['password'] === $password) {
            $_SESSION['auth_time'] = time();
            $_SESSION['username'] = $username;
            $_SESSION['user_description'] = $this->config['users'][$username]['description'];
            return true;
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
    }

    public function getCurrentUser() {
        if ($this->isAuthenticated()) {
            return [
                'username' => $_SESSION['username'],
                'description' => $_SESSION['user_description']
            ];
        }
        return null;
    }
}

// Point d'entrée API pour l'authentification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $auth = new Auth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'login':
                if (isset($data['username']) && isset($data['password'])) {
                    $success = $auth->login($data['username'], $data['password']);
                    if ($success) {
                        $user = $auth->getCurrentUser();
                        echo json_encode(['success' => true, 'user' => $user]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Identifiants incorrects']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Identifiants manquants']);
                }
                break;
                
            case 'logout':
                $auth->logout();
                echo json_encode(['success' => true]);
                break;
                
            case 'check':
                $isAuthenticated = $auth->isAuthenticated();
                $user = $isAuthenticated ? $auth->getCurrentUser() : null;
                echo json_encode([
                    'authenticated' => $isAuthenticated,
                    'user' => $user
                ]);
                break;
        }
    }
    exit;
}
?>
