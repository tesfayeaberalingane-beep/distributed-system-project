document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    // --- Dark Mode Functionality ---
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme) {
        body.className = currentTheme;
    } else {
        // Default to light mode
        body.className = 'light-mode';
    }

    darkModeToggle.addEventListener('click', () => {
        if (body.classList.contains('light-mode')) {
            body.classList.replace('light-mode', 'dark-mode');
            localStorage.setItem('theme', 'dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>'; // Change icon to sun
        } else {
            body.classList.replace('dark-mode', 'light-mode');
            localStorage.setItem('theme', 'light-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>'; // Change icon to moon
        }
    });

    // Set initial icon state
    if (body.classList.contains('dark-mode')) {
        darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
        darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    }

    // --- Mobile Menu Functionality ---
    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true' || false;
        menuToggle.setAttribute('aria-expanded', !isExpanded);
        mainNav.classList.toggle('active');
        
        // Change hamburger icon for better UX
        const icon = menuToggle.querySelector('i');
        icon.classList.toggle('fa-bars');
        icon.classList.toggle('fa-times');
    });
});