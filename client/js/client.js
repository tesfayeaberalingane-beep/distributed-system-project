document.addEventListener('DOMContentLoaded', () => {
    // Check if API_BASE_URL is defined in config.js
    if (typeof API_BASE_URL === 'undefined') {
        console.error("Error: API_BASE_URL is not defined. Ensure client/js/config.js is loaded correctly.");
        return;
    }

    // --- LOGIC FOR index.html (Authentication) ---
    if (window.location.pathname.endsWith('index.html') || window.location.pathname.endsWith('/')) {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const messageElement = document.getElementById('message');
        
        // UI Toggles for Login/Register
        document.getElementById('showRegister').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerHeader').style.display = 'block';
            document.getElementById('registerForm').style.display = 'block';
            document.getElementById('showRegister').style.display = 'none';
            document.getElementById('showLoginP').style.display = 'block';
            document.getElementById('auth-form').querySelector('h2').textContent = 'Register';
            messageElement.textContent = ''; // Clear message on switch
        });

        document.getElementById('showLogin').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerHeader').style.display = 'none';
            document.getElementById('registerForm').style.display = 'none';
            document.getElementById('showRegister').style.display = 'block';
            document.getElementById('showLoginP').style.display = 'none';
            document.getElementById('auth-form').querySelector('h2').textContent = 'Login';
            messageElement.textContent = ''; // Clear message on switch
        });

        // Handle Registration Submission
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('register-username').value;
            const password = document.getElementById('register-password').value;
            
            messageElement.textContent = 'Registering...';

            try {
                const response = await fetch(API_BASE_URL + 'register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await response.json();
                
                messageElement.textContent = data.message;
                if (data.success) {
                    registerForm.reset();
                    // Automatically switch to login form after successful registration
                    document.getElementById('showLogin').click();
                }
            } catch (error) {
                messageElement.textContent = 'Network error. Could not connect to server.';
            }
        });

        // Handle Login Submission
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            
            messageElement.textContent = 'Logging in...';

            try {
                const response = await fetch(API_BASE_URL + 'login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await response.json();
                
                messageElement.textContent = data.message;
                if (data.success) {
                    // Store authentication info in localStorage (for client-side routing)
                    localStorage.setItem('role', data.role);
                    localStorage.setItem('username', username);
                    
                    // Redirect based on role
                    if (data.role === 'admin') {
                        window.location.href = 'admin.html'; 
                    } else {
                        window.location.href = 'user.html'; 
                    }
                }
            } catch (error) {
                messageElement.textContent = 'Network error. Could not connect to server.';
            }
        });
    }

    // --- LOGIC FOR user.html (Job Submission and Polling) ---

    // Note: The admin.html logic is in a separate file (admin.js)
    if (window.location.pathname.endsWith('user.html')) {
        const jobSubmissionForm = document.getElementById('jobSubmissionForm');
        const jobMessage = document.getElementById('jobMessage');
        const jobTableBody = document.getElementById('jobTableBody');
        const logoutBtn = document.getElementById('logoutBtn');

        // 1. Job Submission Handler
        jobSubmissionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const title = document.getElementById('jobTitle').value;
            const description = document.getElementById('jobDescription').value;
            const priority = document.getElementById('jobPriority').value;
           const maxRuntimeInput = document.getElementById('max_runtime');
        const max_runtime = maxRuntimeInput ? maxRuntimeInput.value : 3600;
            jobMessage.textContent = 'Submitting job...';

            try {
                const response = await fetch(API_BASE_URL + 'submit_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title, description, priority, max_runtime})
                });
                const data = await response.json();
                
                jobMessage.textContent = data.message;
                if (data.success) {
                    jobSubmissionForm.reset();
                    fetchJobs(); // Refresh the job list immediately
                }
            } catch (error) {
                jobMessage.textContent = 'Network error during job submission.';
            }
        });

        // 2. Function to Fetch and Display Jobs (Polling Target)
        async function fetchJobs() {
            // Check session before fetching (simple protection against direct access)
            if (!localStorage.getItem('role') || localStorage.getItem('role') !== 'user') return;
            
            try {
                const response = await fetch(API_BASE_URL + 'get_jobs.php', {
                    method: 'GET'
                });
                const data = await response.json();
                
                if (data.success) {
                    renderJobs(data.jobs);
                } else {
                    jobTableBody.innerHTML = `<tr><td colspan="6">Error: ${data.message}</td></tr>`;
                }
            } catch (error) {
                jobTableBody.innerHTML = '<tr><td colspan="6">Network connection failed.</td></tr>';
            }
        }

        // 3. Function to Render Jobs to the Table
        function renderJobs(jobs) {
            jobTableBody.innerHTML = '';
            if (jobs.length === 0) {
                jobTableBody.innerHTML = '<tr><td colspan="6">No jobs submitted yet.</td></tr>';
                return;
            }

            jobs.forEach(job => {
                const row = jobTableBody.insertRow();
                row.insertCell().textContent = job.job_id;
                row.insertCell().textContent = job.title;
                row.insertCell().textContent = job.priority;
                row.insertCell().innerHTML = `<span class="status-${job.status}">${job.status.toUpperCase()}</span>`;
                row.insertCell().textContent = new Date(job.submit_time).toLocaleString();
                
                const resultCell = row.insertCell();
                if (job.status === 'completed' || job.status === 'failed') {
                    // Truncate result for table view
                    resultCell.textContent = job.result ? job.result.substring(0, 50) + (job.result.length > 50 ? '...' : '') : 'N/A';
                } else {
                    resultCell.textContent = '-';
                }
            });
        }

        // 4. Logout Handler
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await fetch(API_BASE_URL + 'logout.php'); 
            } catch (error) {
                console.warn('Logout API call failed, proceeding with client-side cleanup.', error);
            } finally {
                localStorage.removeItem('role');
                localStorage.removeItem('username');
                window.location.href = 'index.html';
            }
        });

        // Initial load and Polling setup
        fetchJobs();
        // Set up a refresh interval to automatically check job status (every 5 seconds)
        setInterval(fetchJobs, 5000); 
    }
});