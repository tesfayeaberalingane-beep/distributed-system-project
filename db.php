<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            error_log("Database Error: " . $e->getMessage());
            json_response(false, null, "Database connection failed", 500);
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->connection->error);
            }
            
            if (!empty($params)) {
                $types = '';
                $values = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_string($param)) {
                        $types .= 's';
                    } else {
                        $types .= 'b';
                    }
                    $values[] = $param;
                }
                
                array_unshift($values, $types);
                call_user_func_array([$stmt, 'bind_param'], $this->refValues($values));
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result === FALSE) {
                // For INSERT, UPDATE, DELETE queries
                $affectedRows = $stmt->affected_rows;
                $stmt->close();
                return $affectedRows;
            }
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            $stmt->close();
            return $data;
            
        } catch (Exception $e) {
            error_log("Query Error: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
    
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
?>