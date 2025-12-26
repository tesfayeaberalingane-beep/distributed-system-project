<?php
require_once 'db.php';

class JobScheduler {
    private $db;
    private $maxConcurrentJobs = 5;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getNextJob() {
        // Get next pending job based on priority and FIFO
        $sql = "SELECT j.* FROM jobs j 
                WHERE j.status = 'pending' 
                AND (j.start_time IS NULL OR TIMESTAMPDIFF(SECOND, j.start_time, NOW()) > 300)
                ORDER BY 
                    FIELD(j.priority, 'high', 'medium', 'low'),
                    j.submit_time ASC
                LIMIT 1 FOR UPDATE SKIP LOCKED";
        
        $this->db->beginTransaction();
        $job = $this->db->query($sql);
        
        if ($job && count($job) > 0) {
            $job = $job[0];
            
            // Check if we have capacity
            $runningCount = $this->getRunningJobCount();
            if ($runningCount >= $this->maxConcurrentJobs) {
                $this->db->rollback();
                return null;
            }
            
            // Update job status to running
            $updateSql = "UPDATE jobs SET status = 'running', start_time = NOW() WHERE job_id = ?";
            $this->db->query($updateSql, [$job['job_id']]);
            
            // Log status change
            $logSql = "INSERT INTO job_logs (job_id, status, message) VALUES (?, 'running', 'Job started execution')";
            $this->db->query($logSql, [$job['job_id']]);
            
            $this->db->commit();
            return $job;
        }
        
        $this->db->rollback();
        return null;
    }
    
    public function getRunningJobCount() {
        $sql = "SELECT COUNT(*) as count FROM jobs WHERE status = 'running'";
        $result = $this->db->query($sql);
        return $result ? (int)$result[0]['count'] : 0;
    }
    
    public function retryFailedJobs() {
        $sql = "SELECT * FROM jobs 
                WHERE status = 'failed' 
                AND retry_count < max_retries 
                AND TIMESTAMPDIFF(MINUTE, end_time, NOW()) > 5";
        
        $failedJobs = $this->db->query($sql);
        
        foreach ($failedJobs as $job) {
            $updateSql = "UPDATE jobs 
                         SET status = 'pending', 
                             retry_count = retry_count + 1,
                             start_time = NULL,
                             end_time = NULL
                         WHERE job_id = ?";
            
            $this->db->query($updateSql, [$job['job_id']]);
            
            $logSql = "INSERT INTO job_logs (job_id, status, message) 
                      VALUES (?, 'pending', 'Job queued for retry (attempt {$job['retry_count']})')";
            $this->db->query($logSql, [$job['job_id']]);
        }
        
        return count($failedJobs);
    }
    
    public function cleanupStaleJobs() {
        // Mark jobs as failed if they've been running too long
        $sql = "UPDATE jobs 
                SET status = 'failed', 
                    end_time = NOW(),
                    result = 'Job timeout'
                WHERE status = 'running' 
                AND TIMESTAMPDIFF(SECOND, start_time, NOW()) > 300";
        
        return $this->db->query($sql);
    }
}

// CLI Execution endpoint
if (php_sapi_name() === 'cli' || isset($_GET['run_scheduler'])) {
    $scheduler = new JobScheduler();
    
    // Cleanup stale jobs
    $cleaned = $scheduler->cleanupStaleJobs();
    echo "Cleaned up $cleaned stale jobs\n";
    
    // Retry failed jobs
    $retried = $scheduler->retryFailedJobs();
    echo "Retried $retried failed jobs\n";
    
    // Execute next job
    $job = $scheduler->getNextJob();
    if ($job) {
        echo "Executing job ID: {$job['job_id']}\n";
        
        // Simulate job execution
        $executor = new JobExecutor();
        $result = $executor->executeJob($job);
        
        echo "Job completed with status: {$result['status']}\n";
    } else {
        echo "No jobs to execute\n";
    }
}
?>