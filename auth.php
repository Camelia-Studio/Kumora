<?php
// auth.php
session_start();
require_once 'Database.php';

class Auth {
    private $config;
    private $db;
    
    public function __construct() {
        $this->config = require 'config.php';
        $this->db = Database::getInstance();
    }
    
    public function login($username, $password) {
        // Vérifier les tentatives de connexion
        $attempts = $this->db->checkLoginAttempts($username);
        if ($attempts >= $this->config['security']['max_login_attempts']) {
            return ['success' => false, 'error' => 'too_many_attempts'];
        }
        
        $stmt = $this->db->prepare('
            SELECT id, username, password_hash, role, description 
            FROM users 
            WHERE username = :username
        ');
        
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        // Enregistrer la tentative
        $this->db->logLoginAttempt($username);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['auth_time'] = time();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['description'] = $user['description'];
            
            // Log de connexion réussie
            $this->db->logActivity($user['id'], 'login');
            
            return ['success' => true, 'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'description' => $user['description']
            ]];
        }
        
        return ['success' => false, 'error' => 'invalid_credentials'];
    }
    
    public function isAuthenticated() {
        if (!isset($_SESSION['auth_time']) || !isset($_SESSION['user_id'])) {
            return false;
        }
        
        $elapsed = time() - $_SESSION['auth_time'];
        if ($elapsed > $this->config['security']['session_duration']) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function hasPermission($action) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $role = $_SESSION['role'];
        return isset($this->config['roles'][$role][$action]) && 
               $this->config['roles'][$role][$action];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->db->logActivity($_SESSION['user_id'], 'logout');
        }
        session_destroy();
    }
}

// Point d'entrée API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $auth = new Auth();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'login':
                if (isset($data['username']) && isset($data['password'])) {
                    $result = $auth->login($data['username'], $data['password']);
                    echo json_encode($result);
                }
                break;
                
            case 'logout':
                $auth->logout();
                echo json_encode(['success' => true]);
                break;
                
            case 'check':
                echo json_encode([
                    'authenticated' => $auth->isAuthenticated(),
                    'user' => $auth->isAuthenticated() ? [
                        'username' => $_SESSION['username'],
                        'role' => $_SESSION['role'],
                        'description' => $_SESSION['description']
                    ] : null
                ]);
                break;
        }
    }
    exit;
}
?>
