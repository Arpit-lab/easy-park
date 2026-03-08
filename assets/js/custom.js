/**
 * EasyPark - Custom JavaScript
 * Handles all custom functionality for the EasyPark system
 */

// Global variables
let currentUser = null;
let notificationInterval = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    initializePopovers();
    initializeToastNotifications();
    initializeFormValidation();
    initializeAutoComplete();
    initializeTheme();
    
    // Start checking for notifications if user is logged in
    if (document.body.classList.contains('user-logged-in')) {
        startNotificationPolling();
    }
});

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize Bootstrap popovers
 */
function initializePopovers() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize toast notifications
 */
function initializeToastNotifications() {
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    var toastList = toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        });
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    // Confirm password match
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword) {
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
}

/**
 * Check password strength
 */
function checkPasswordStrength() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    
    if (!strengthBar) return;
    
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    strengthBar.className = 'progress-bar';
    strengthBar.style.width = (strength * 20) + '%';
    
    if (strength <= 2) {
        strengthBar.classList.add('bg-danger');
        if (strengthText) strengthText.textContent = 'Weak';
    } else if (strength <= 4) {
        strengthBar.classList.add('bg-warning');
        if (strengthText) strengthText.textContent = 'Medium';
    } else {
        strengthBar.classList.add('bg-success');
        if (strengthText) strengthText.textContent = 'Strong';
    }
}

/**
 * Check if passwords match
 */
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (!matchDiv) return;
    
    if (confirm.length === 0) {
        matchDiv.innerHTML = '';
    } else if (password === confirm) {
        matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
}

/**
 * Initialize autocomplete for search
 */
function initializeAutoComplete() {
    const searchInput = document.getElementById('searchLocation');
    if (!searchInput) return;
    
    let timeout = null;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const query = this.value;
            if (query.length > 2) {
                searchLocations(query);
            }
        }, 500);
    });
}

/**
 * Search locations using OpenStreetMap Nominatim
 */
function searchLocations(query) {
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
        .then(response => response.json())
        .then(data => {
            displayLocationSuggestions(data);
        })
        .catch(error => console.error('Error searching locations:', error));
}

/**
 * Display location suggestions
 */
function displayLocationSuggestions(locations) {
    const suggestionsDiv = document.getElementById('locationSuggestions');
    if (!suggestionsDiv) return;
    
    suggestionsDiv.innerHTML = '';
    
    locations.forEach(location => {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'list-group-item list-group-item-action';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${location.display_name}</strong>
                </div>
                <small class="text-muted">${location.lat}, ${location.lon}</small>
            </div>
        `;
        item.addEventListener('click', (e) => {
            e.preventDefault();
            selectLocation(location);
        });
        suggestionsDiv.appendChild(item);
    });
}

/**
 * Select location from suggestions
 */
function selectLocation(location) {
    document.getElementById('searchLocation').value = location.display_name;
    document.getElementById('latitude').value = location.lat;
    document.getElementById('longitude').value = location.lon;
    document.getElementById('locationSuggestions').innerHTML = '';
}

/**
 * Initialize theme (light/dark mode)
 */
function initializeTheme() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update icon
        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    });
}

/**
 * Start polling for notifications
 */
function startNotificationPolling() {
    notificationInterval = setInterval(checkNotifications, 30000); // Check every 30 seconds
}

/**
 * Check for new notifications
 */
function checkNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                updateNotificationBadge(data.count);
                showNotificationToast(data.notifications);
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline' : 'none';
    }
}

/**
 * Show notification toast
 */
function showNotificationToast(notifications) {
    notifications.forEach(notification => {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${notification.type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${notification.title}</strong><br>
                    ${notification.message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.getElementById('toastContainer').appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    });
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-NP', {
        style: 'currency',
        currency: 'NPR',
        minimumFractionDigits: 2
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(date, format = 'medium') {
    const d = new Date(date);
    
    const options = {
        'short': { year: 'numeric', month: 'numeric', day: 'numeric' },
        'medium': { year: 'numeric', month: 'short', day: 'numeric' },
        'long': { year: 'numeric', month: 'long', day: 'numeric' },
        'full': { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
    };
    
    return d.toLocaleDateString('en-US', options[format]);
}

/**
 * Format time
 */
function formatTime(date) {
    const d = new Date(date);
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    element.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
}

/**
 * Hide loading spinner
 */
function hideLoading(element, content) {
    element.innerHTML = content;
}

/**
 * Handle AJAX errors
 */
function handleAjaxError(error) {
    console.error('AJAX Error:', error);
    
    // Show error toast
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-danger border-0';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                An error occurred. Please try again.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

/**
 * Print receipt
 */
function printReceipt(receiptId) {
    const printContent = document.getElementById(receiptId).innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

/**
 * Export data to CSV
 */
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.href = url;
    a.download = filename;
    a.click();
    
    window.URL.revokeObjectURL(url);
}

/**
 * Convert data to CSV
 */
function convertToCSV(data) {
    const headers = Object.keys(data[0]);
    const rows = data.map(obj => headers.map(header => obj[header]).join(','));
    return [headers.join(','), ...rows].join('\n');
}

/**
 * Debounce function for performance
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function for performance
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (notificationInterval) {
        clearInterval(notificationInterval);
    }
});