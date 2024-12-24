<?php
// Database.php
class Database {
    private $db;
    private static $instance = null;

    private function __construct() {
        $config = require 'config.php';
        $this->db = new SQLite3($config['db']['path']);
        $this->db->enableExceptions(true);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function logActivity($userId, $actionType, $details = '') {
        $stmt = $this->db->prepare('
            INSERT INTO activity_logs (user_id, action_type, details, ip_address)
            VALUES (:user_id, :action_type, :details, :ip)
        ');
        
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':action_type', $actionType, SQLITE3_TEXT);
        $stmt->bindValue(':details', $details, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
        
        return $stmt->execute();
    }

    public function getActivityLogs($filters = []) {
        $query = '
            SELECT 
                al.*, 
                u.username, 
                u.role
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1
        ';
        
        $params = [];
        
        if (!empty($filters['action_type'])) {
            $query .= ' AND action_type = :action_type';
            $params[':action_type'] = $filters['action_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $query .= ' AND created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= ' AND created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        $query .= ' ORDER BY created_at ' . 
            (!empty($filters['order']) && $filters['order'] === 'asc' ? 'ASC' : 'DESC');
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $result = $stmt->execute();
        $logs = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logs[] = $row;
        }
        
        return $logs;
    }

    public function checkLoginAttempts($username) {
        $config = require 'config.php';
        $window = $config['security']['attempt_window'];
        
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = :username 
            AND attempt_time > datetime("now", "-' . $window . ' seconds")
        ');
        
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray();
        
        return $row['attempts'];
    }

    public function logLoginAttempt($username) {
        $stmt = $this->db->prepare('
            INSERT INTO login_attempts (username, ip_address)
            VALUES (:username, :ip)
        ');
        
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
        
        return $stmt->execute();
    }
}
?>
