
 document.addEventListener('DOMContentLoaded', () => {
    const jobTableBody = document.getElementById('adminJobTableBody');
    const adminMessage = document.getElementById('adminMessage');
    const stats = {
        totalJobs: document.getElementById('totalJobs'),
        pendingJobs: document.getElementById('pendingJobs'),
        runningJobs: document.getElementById('runningJobs'),
        completedJobs: document.getElementById('completedJobs'),
        failedJobs: document.getElementById('failedJobs')
    };

    const modal = document.getElementById('jobModal');
    const closeBtn = document.querySelector('.close-btn');
    const adminUpdateForm = document.getElementById('adminUpdateForm');
    let currentJobId = null;

    // --- Modal Handlers ---
    closeBtn.onclick = () => { modal.style.display = 'none'; }
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // --- 1. Function to Fetch and Display All Jobs ---
    async function fetchAllJobs() {
        // We don't clear the whole innerHTML every time to prevent flickering during polling
        try {
            const response = await fetch(API_BASE_URL.replace('api/', 'admin/') + 'getjobs.php', {
                method: 'GET'
            });
            const data = await response.json();
            
            if (data.success) {
                renderAllJobs(data.jobs);
                updateStats(data.stats);
            } else {
                jobTableBody.innerHTML = `<tr><td colspan="7">Error: ${data.message}</td></tr>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
        }
    }
    
    // --- 2. Render Jobs and Setup Actions ---
    function renderAllJobs(jobs) {
        jobTableBody.innerHTML = '';
        if (jobs.length === 0) {
            jobTableBody.innerHTML = '<tr><td colspan="7">No jobs in the system.</td></tr>';
            return;
        }

        jobs.forEach(job => {
            const row = jobTableBody.insertRow();
            row.insertCell().textContent = job.job_id;
            row.insertCell().textContent = job.username;
            row.insertCell().textContent = job.title;
            row.insertCell().textContent = job.priority;
            
            // --- UPDATED: TIMEOUT Highlighting Logic ---
            const status = job.status.toLowerCase();
            const statusClass = status === 'timeout' ? 'status-timeout' : `status-${status}`;
            row.insertCell().innerHTML = `<span class="${statusClass}">${status.toUpperCase()}</span>`;
            
            row.insertCell().textContent = new Date(job.submit_time).toLocaleString();
            
            // --- UPDATED: Actio// --- NEW DURATION CELL --
            const actionCell = row.insertCell();
            
            // Audit Trail Button
            const logButton = document.createElement('button');
            logButton.innerHTML = '<i class="fas fa-history"></i> Log';
            logButton.className = 'small-button secondary';
            logButton.style.marginRight = '5px';
            logButton.onclick = () => displayJobLog(job.job_id);
            
            // Manage Button
            const editButton = document.createElement('button');
            editButton.textContent = 'Manage';
            editButton.className = 'small-button';
            editButton.onclick = () => openJobModal(job);
            
            actionCell.appendChild(logButton);
            actionCell.appendChild(editButton);
        });
    }

    // --- 3. Update Statistics ---
    function updateStats(statsData) {
        stats.totalJobs.textContent = statsData.total;
        stats.pendingJobs.textContent = statsData.pending;
        stats.runningJobs.textContent = statsData.running;
        stats.completedJobs.textContent = statsData.completed;
        stats.failedJobs.textContent = statsData.failed;
    }

    // --- 4. Open Modal for Management ---
    function openJobModal(job) {
        document.getElementById('modalJobId').textContent = job.job_id;
        document.getElementById('modalJobIdInput').value = job.job_id;
        document.getElementById('newStatus').value = job.status;
        document.getElementById('newResult').value = job.result || '';
        currentJobId = job.job_id;
        modal.style.display = 'block';
    }

    // --- 5. NEW FUNCTION: Fetch and display Audit Trail ---
    async function displayJobLog(jobId) {
        try {
            // Point to the new endpoint created in Phase 3
            const response = await fetch(`${API_BASE_URL}get_job_log.php?job_id=${jobId}`);
            const data = await response.json();

            let logContent = `<div class="audit-trail-container">
                                <h3>Audit Trail: Job #${jobId}</h3>`;
            
            if (data.success && data.logs.length > 0) {
                logContent += `<table class="log-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Transition</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                data.logs.forEach(log => {
                    logContent += `<tr>
                        <td><small>${new Date(log.timestamp).toLocaleString()}</small></td>
                        <td><strong>${log.old_status}</strong> &rarr; <strong>${log.new_status}</strong></td>
                        <td>${log.message}</td>
                    </tr>`;
                });
                logContent += `</tbody></table>`;
            } else {
                logContent += '<p>No log entries found for this job.</p>';
            }
            logContent += `</div>`;

            // Display in your existing modal
            document.getElementById('modalJobId').textContent = `${jobId} (Audit Log)`;
            const formContainer = document.getElementById('adminUpdateForm');
            
            // Temporary display: we hide the form and show the log
            const originalContent = formContainer.innerHTML;
            formContainer.innerHTML = logContent + `<button type="button" class="cta-button" onclick="fetchAllJobs(); document.getElementById('jobModal').style.display='none';">Close Log</button>`;
            
            modal.style.display = 'block';

            // Reset modal when closed so the form comes back
            closeBtn.onclick = () => { 
                modal.style.display = 'none'; 
                location.reload(); // Simplest way to restore the update form
            }

        } catch (error) {
            alert('Failed to retrieve audit trail.');
        }
    }

    // --- 6. Handle Manual Status Update Submission ---
    adminUpdateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const job_id = document.getElementById('modalJobIdInput').value;
        const status = document.getElementById('newStatus').value;
        const result = document.getElementById('newResult').value;
        
        adminMessage.textContent = `Updating Job #${job_id}...`;
        
        try {
            const response = await fetch(API_BASE_URL.replace('api/', 'admin/') + 'adminupdatejobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id, status, result })
            });
            const data = await response.json();
            
            adminMessage.textContent = data.message;
            if (data.success) {
                modal.style.display = 'none';
                fetchAllJobs();
            }
        } catch (error) {
            adminMessage.textContent = 'Network error during status update.';
        }
    });

    fetchAllJobs();
    setInterval(fetchAllJobs, 5000); 
});
 
 
 /*document.addEventListener('DOMContentLoaded', () => {
    const jobTableBody = document.getElementById('adminJobTableBody');
    const adminMessage = document.getElementById('adminMessage');
    const stats = {
        totalJobs: document.getElementById('totalJobs'),
        pendingJobs: document.getElementById('pendingJobs'),
        runningJobs: document.getElementById('runningJobs'),
        completedJobs: document.getElementById('completedJobs'),
        failedJobs: document.getElementById('failedJobs')
    };

    const modal = document.getElementById('jobModal');
    const closeBtn = document.querySelector('.close-btn');
    const adminUpdateForm = document.getElementById('adminUpdateForm');
    let currentJobId = null;

    // --- Modal Handlers ---
    closeBtn.onclick = () => { modal.style.display = 'none'; }
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // --- 1. Function to Fetch and Display All Jobs ---
    async function fetchAllJobs() {
        jobTableBody.innerHTML = '<tr><td colspan="7">Fetching all jobs...</td></tr>';
        
        try {
            // Note: We use a separate admin API path for clarity, even if PHP logic is similar
            const response = await fetch(API_BASE_URL.replace('api/', 'admin/') + 'getjobs.php', {
                method: 'GET'
            });
            const data = await response.json();
            
            if (data.success) {
                renderAllJobs(data.jobs);
                updateStats(data.stats);
            } else {
                jobTableBody.innerHTML = `<tr><td colspan="7">Error: ${data.message}</td></tr>`;
            }
        } catch (error) {
            jobTableBody.innerHTML = '<tr><td colspan="7">Network connection failed.</td></tr>';
        }
    }
    
    // --- 2. Render Jobs and Setup Actions ---
    function renderAllJobs(jobs) {
        jobTableBody.innerHTML = '';
        if (jobs.length === 0) {
            jobTableBody.innerHTML = '<tr><td colspan="7">No jobs in the system.</td></tr>';
            return;
        }

        jobs.forEach(job => {
            const row = jobTableBody.insertRow();
            row.insertCell().textContent = job.job_id;
            row.insertCell().textContent = job.username; // Display the username
            row.insertCell().textContent = job.title;
            row.insertCell().textContent = job.priority;
            row.insertCell().innerHTML = `<span class="status-${job.status}">${job.status.toUpperCase()}</span>`;
            row.insertCell().textContent = new Date(job.submit_time).toLocaleString();
            
            // Action Cell
            const actionCell = row.insertCell();
            const editButton = document.createElement('button');
            editButton.textContent = 'Manage';
            editButton.className = 'small-button';
            editButton.onclick = () => openJobModal(job);
            actionCell.appendChild(editButton);
        });
    }

    // --- 3. Update Statistics ---
    function updateStats(statsData) {
        stats.totalJobs.textContent = statsData.total;
        stats.pendingJobs.textContent = statsData.pending;
        stats.runningJobs.textContent = statsData.running;
        stats.completedJobs.textContent = statsData.completed;
        stats.failedJobs.textContent = statsData.failed;
    }

    // --- 4. Open Modal for Management ---
    function openJobModal(job) {
        document.getElementById('modalJobId').textContent = job.job_id;
        document.getElementById('modalJobIdInput').value = job.job_id;
        document.getElementById('newStatus').value = job.status;
        document.getElementById('newResult').value = job.result || '';
        currentJobId = job.job_id;
        modal.style.display = 'block';
    }

    // --- 5. Handle Manual Status Update Submission ---
    adminUpdateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const job_id = document.getElementById('modalJobIdInput').value;
        const status = document.getElementById('newStatus').value;
        const result = document.getElementById('newResult').value;
        
        adminMessage.textContent = `Updating Job #${job_id}...`;
        
        try {
            const response = await fetch(API_BASE_URL.replace('api/', 'admin/') + 'adminupdatejobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id, status, result })
            });
            const data = await response.json();
            
            adminMessage.textContent = data.message;
            if (data.success) {
                modal.style.display = 'none';
                fetchAllJobs(); // Refresh the queue
            }
        } catch (error) {
            adminMessage.textContent = 'Network error during status update.';
        }
    });

    // Initial load and polling
    fetchAllJobs();
    setInterval(fetchAllJobs, 5000); // Polling every 5 seconds
});*/