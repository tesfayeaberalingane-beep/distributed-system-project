<?php
require_once __DIR__ . '/../config/config.php';
require_once 'db.php';

// Check authentication and admin role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

class SystemMetrics {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getMetrics() {
        $metrics = [];
        
        // Total jobs
        $result = $this->db->query("SELECT COUNT(*) as total FROM jobs");
        $metrics['total_jobs'] = $result->fetch_assoc()['total'];
        
        // Active jobs (pending + running)
        $result = $this->db->query("SELECT COUNT(*) as active FROM jobs WHERE status IN ('pending', 'running')");
        $metrics['active_jobs'] = $result->fetch_assoc()['active'];
        
        // Success rate
        $result = $this->db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM jobs WHERE status IN ('completed', 'failed')");
        $row = $result->fetch_assoc();
        $metrics['success_rate'] = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 2) : 0;
        
        // Average execution time
        $result = $this->db->query("SELECT AVG(execution_time) as avg_time FROM jobs WHERE status = 'completed'");
        $metrics['avg_execution_time'] = round($result->fetch_assoc()['avg_time'] ?? 0, 2);
        
        // System load simulation
        $metrics['cpu_usage'] = $this->simulateCpuUsage();
        $metrics['memory_usage'] = $this->simulateMemoryUsage();
        $metrics['system_load'] = $this->calculateSystemLoad($metrics);
        $metrics['queue_load'] = min(100, round(($metrics['active_jobs'] / MAX_CONCURRENT_JOBS) * 100));
        
        // Get chart data
        $metrics['chart_data'] = $this->getChartData();
        
        return $metrics;
    }
    
    private function simulateCpuUsage() {
        // Simulate CPU usage based on active jobs
        $result = $this->db->query("SELECT COUNT(*) as running FROM jobs WHERE status = 'running'");
        $runningJobs = $result->fetch_assoc()['running'];
        
        $baseUsage = 20; // Base system usage
        $perJobUsage = 15; // Each job adds 15%
        
        $usage = $baseUsage + ($runningJobs * $perJobUsage);
        
        // Add some randomness
        $usage += rand(-10, 10);
        
        return min(100, max(5, round($usage)));
    }
    
    private function simulateMemoryUsage() {
        // Similar simulation for memory
        $result = $this->db->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'running'");
        $runningJobs = $result->fetch_assoc()['total'];
        
        $baseUsage = 30;
        $perJobUsage = 8;
        
        $usage = $baseUsage + ($runningJobs * $perJobUsage);
        $usage += rand(-5, 5);
        
        return min(100, max(20, round($usage)));
    }
    
    private function calculateSystemLoad($metrics) {
        // Weighted average of various metrics
        $load = (
            $metrics['cpu_usage'] * 0.4 +
            $metrics['memory_usage'] * 0.3 +
            $metrics['queue_load'] * 0.3
        );
        
        return round($load);
    }
    
    private function getChartData() {
        // Get job statistics for the last 7 days
        $sql = "SELECT 
            DATE(submit_time) as date,
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
            AVG(execution_time) as avg_time
            FROM jobs 
            WHERE submit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(submit_time)
            ORDER BY date";
        
        $result = $this->db->query($sql);
        $chartData = [
            'labels' => [],
            'datasets' => []
        ];
        
        $totals = [];
        $completed = [];
        $failed = [];
        $avgTimes = [];
        
        while ($row = $result->fetch_assoc()) {
            $chartData['labels'][] = $row['date'];
            $totals[] = $row['total_jobs'];
            $completed[] = $row['completed_jobs'];
            $failed[] = $row['failed_jobs'];
            $avgTimes[] = round($row['avg_time'] ?? 0);
        }
        
        $chartData['datasets'] = [
            [
                'label' => 'Total Jobs',
                'data' => $totals,
                'borderColor' => '#667eea'
            ],
            [
                'label' => 'Completed',
                'data' => $completed,
                'borderColor' => '#38a169'
            ],
            [
                'label' => 'Failed',
                'data' => $failed,
                'borderColor' => '#e53e3e'
            ]
        ];
        
        $chartData['avg_times'] = $avgTimes;
        
        return $chartData;
    }
    
    public function getUserStats() {
        $sql = "SELECT 
            u.username,
            COUNT(j.job_id) as total_jobs,
            SUM(CASE WHEN j.status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
            SUM(CASE WHEN j.status = 'running' THEN 1 ELSE 0 END) as running_jobs,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN j.status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
            CASE 
                WHEN COUNT(j.job_id) = 0 THEN 0
                ELSE ROUND((SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) / COUNT(j.job_id)) * 100, 2)
            END as success_rate
            FROM users u
            LEFT JOIN jobs j ON u.user_id = j.user_id
            WHERE u.is_active = TRUE
            GROUP BY u.user_id
            ORDER BY total_jobs DESC";
        
        $result = $this->db->query($sql);
        $stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    public function cleanupOldLogs($days = 30) {
        $sql = "DELETE FROM job_logs 
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND job_id IN (SELECT job_id FROM jobs WHERE status IN ('completed', 'failed'))";
        
        $result = $this->db->query($sql, [$days]);
        
        return [
            'success' => true,
            'message' => 'Old logs cleaned up',
           'affected_rows' => $this->db->getConnection()->affected_rows
        ];
    }
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $metrics = new SystemMetrics();
    $action = $_GET['action'] ?? 'metrics';
    
    switch ($action) {
        case 'metrics':
            $data = $metrics->getMetrics();
            echo json_encode(['success' => true, 'metrics' => $data]);
            break;
            
        case 'user_stats':
            $stats = $metrics->getUserStats();
            echo json_encode(['success' => true, 'user_stats' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $metrics = new SystemMetrics();
    
    switch ($action) {
        case 'cleanup_logs':
            $days = $input['days'] ?? 30;
            $result = $metrics->cleanupOldLogs($days);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>