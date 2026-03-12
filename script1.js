/**
 * Modern Dashboard JavaScript - Final Version
 * Features: Theme Toggle, Animations, Photo Management, Form Validation
 */

// Global variables
let currentTheme = 'light';
let isAnimating = false;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    initTheme();
    initNavbar();
    initAnimations();
    initPhotoManagement();
    initFormValidation();
    initTableInteractions();
    initTooltipsAndPopovers();
    initCharts();
    initSearchFunctionality();
    initBulkActions();
    initNotifications();
    
    console.log('Modern Dashboard initialized successfully');
}

/**
 * Theme Management
 */
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const minimalThemeToggle = document.getElementById('minimalThemeToggle');
    
    // Get saved theme or default to light
    currentTheme = localStorage.getItem('theme') || 'light';
    
    // Apply initial theme
    applyTheme(currentTheme);
    
    // Theme toggle event listeners
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    if (minimalThemeToggle) {
        minimalThemeToggle.addEventListener('click', toggleTheme);
    }
    
    // System theme preference detection
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && !localStorage.getItem('theme')) {
        toggleTheme();
    }
}

function toggleTheme() {
    if (isAnimating) return;
    
    isAnimating = true;
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    // Add transition effect
    document.body.style.transition = 'all 0.3s ease';
    
    applyTheme(currentTheme);
    
    // Reset animation flag
    setTimeout(() => {
        isAnimating = false;
        document.body.style.transition = '';
    }, 300);
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('theme', theme);
    updateThemeIcons(theme);
    
    // Update meta theme color for mobile browsers
    updateMetaThemeColor(theme);
}

function updateThemeIcons(theme) {
    const toggles = [
        document.getElementById('themeToggle'),
        document.getElementById('minimalThemeToggle')
    ];
    
    toggles.forEach(toggle => {
        if (toggle) {
            toggle.innerHTML = theme === 'light' ? 
                '<i class="fas fa-moon"></i>' : 
                '<i class="fas fa-sun"></i>';
        }
    });
}

function updateMetaThemeColor(theme) {
    let themeColorMeta = document.querySelector('meta[name="theme-color"]');
    if (!themeColorMeta) {
        themeColorMeta = document.createElement('meta');
        themeColorMeta.name = 'theme-color';
        document.getElementsByTagName('head')[0].appendChild(themeColorMeta);
    }
    
    themeColorMeta.content = theme === 'light' ? '#667eea' : '#818cf8';
}

/**
 * Navbar Management
 */
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    // Scroll effect
    let lastScrollTop = 0;
    window.addEventListener('scroll', debounce(function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll (mobile only)
        if (window.innerWidth <= 768) {
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
        }
        
        lastScrollTop = scrollTop;
    }, 100));
    
    // Fix navbar logo link based on user role
    fixNavbarLogoLink();
    
    // Mobile menu enhancements
    const navbarToggler = navbar.querySelector('.navbar-toggler');
    const navbarCollapse = navbar.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        navbarToggler.addEventListener('click', function() {
            setTimeout(() => {
                if (navbarCollapse.classList.contains('show')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }, 100);
        });
    }
}

function fixNavbarLogoLink() {
    const navbarBrand = document.querySelector('.navbar-brand');
    if (!navbarBrand) return;
    
    const userRole = document.body.getAttribute('data-user-role');
    
    const roleRoutes = {
        'student': '../student/student-dashboard.php',
        'company': '../company/company-dashboard.php',
        'admin': '../admin/admin-dashboard.php'
    };
    
    if (roleRoutes[userRole]) {
        navbarBrand.href = roleRoutes[userRole];
    }
}

/**
 * Animation Management
 */
function initAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                
                // Add stagger effect for multiple elements
                const delay = Array.from(entry.target.parentNode.children).indexOf(entry.target) * 100;
                entry.target.style.transitionDelay = `${delay}ms`;
            }
        });
    }, observerOptions);
    
    // Observe all elements with animate-on-scroll class
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
    
    // Parallax effect for hero sections
    const heroElements = document.querySelectorAll('.hero-section, .stats-card');
    window.addEventListener('scroll', debounce(function() {
        const scrolled = window.pageYOffset;
        heroElements.forEach(el => {
            const rate = scrolled * -0.5;
            el.style.transform = `translate3d(0, ${rate}px, 0)`;
        });
    }, 16));
    
    // Card hover animations
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
}

/**
 * Photo Management
 */
function initPhotoManagement() {
    // Image upload handlers
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', handleImageUpload);
    });
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Photo modal functionality
    document.querySelectorAll('.photo-container, .student-avatar, .company-logo').forEach(photo => {
        photo.addEventListener('click', openPhotoModal);
    });
}

function handleImageUpload(event) {
    const input = event.target;
    const file = input.files[0];
    
    if (!file) return;
    
    // Validate file type
    if (!file.type.match('image.*')) {
        showNotification('Please select a valid image file', 'error');
        input.value = '';
        return;
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showNotification('Image size must be less than 5MB', 'error');
        input.value = '';
        return;
    }
    
    // Show loading state
    const previewId = input.getAttribute('data-preview');
    const preview = previewId ? document.getElementById(previewId) : null;
    
    if (preview) {
        preview.classList.add('loading');
    }
    
    // Process image
    processImage(file, preview, input);
}

function processImage(file, preview, input) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const img = new Image();
        img.src = e.target.result;
        
        img.onload = function() {
            // Compress if needed
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            let { width, height } = calculateDimensions(img.width, img.height, 800, 600);
            
            canvas.width = width;
            canvas.height = height;
            
            // Draw and compress
            ctx.drawImage(img, 0, 0, width, height);
            const compressedDataUrl = canvas.toDataURL('image/jpeg', 0.8);
            
            if (preview) {
                preview.src = compressedDataUrl;
                preview.style.display = 'block';
                preview.classList.remove('loading');
                
                // Add upload success animation
                preview.style.animation = 'fadeInUp 0.5s ease';
            }
            
            showNotification('Image uploaded successfully', 'success');
        };
    };
    
    reader.onerror = function() {
        showNotification('Error reading image file', 'error');
        input.value = '';
        if (preview) {
            preview.classList.remove('loading');
        }
    };
    
    reader.readAsDataURL(file);
}

function calculateDimensions(srcWidth, srcHeight, maxWidth, maxHeight) {
    let width = srcWidth;
    let height = srcHeight;
    
    if (width > maxWidth) {
        height = (height * maxWidth) / width;
        width = maxWidth;
    }
    
    if (height > maxHeight) {
        width = (width * maxHeight) / height;
        height = maxHeight;
    }
    
    return { width: Math.round(width), height: Math.round(height) };
}

function openPhotoModal(event) {
    const img = event.target;
    if (!img.src || img.src.includes('placeholder')) return;
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'photo-modal';
    modal.innerHTML = `
        <div class="photo-modal-backdrop">
            <div class="photo-modal-content">
                <img src="${img.src}" alt="Photo" class="photo-modal-image">
                <button class="photo-modal-close">&times;</button>
            </div>
        </div>
    `;
    
    // Add styles
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.3s ease;
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Close handlers
    modal.addEventListener('click', function(e) {
        if (e.target === modal || e.target.classList.contains('photo-modal-backdrop') || e.target.classList.contains('photo-modal-close')) {
            closePhotoModal(modal);
        }
    });
    
    // Keyboard handler
    const keyHandler = function(e) {
        if (e.key === 'Escape') {
            closePhotoModal(modal);
            document.removeEventListener('keydown', keyHandler);
        }
    };
    document.addEventListener('keydown', keyHandler);
}

function closePhotoModal(modal) {
    modal.style.animation = 'fadeOut 0.3s ease';
    setTimeout(() => {
        if (modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
        document.body.style.overflow = '';
    }, 300);
}

/**
 * Form Validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (validateForm(form)) {
                // Form is valid, you can submit it here
                showNotification('Form submitted successfully!', 'success');
                // form.submit(); // Uncomment to actually submit
            } else {
                showNotification('Please correct the errors in the form', 'error');
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', debounce(function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            }, 300));
        });
    });
    
    // Password strength checker
    document.querySelectorAll('input[type="password"]').forEach(input => {
        const strengthMeter = createPasswordStrengthMeter(input);
        input.addEventListener('input', function() {
            updatePasswordStrength(this, strengthMeter);
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    let isValid = true;
    const value = field.value.trim();
    
    // Required field validation
    if (field.required && !value) {
        setFieldError(field, 'This field is required');
        isValid = false;
    }
    // Email validation
    else if (field.type === 'email' && value && !isValidEmail(value)) {
        setFieldError(field, 'Please enter a valid email address');
        isValid = false;
    }
    // Phone validation
    else if (field.type === 'tel' && value && !isValidPhone(value)) {
        setFieldError(field, 'Please enter a valid phone number');
        isValid = false;
    }
    // Password validation
    else if (field.type === 'password' && value && value.length < 8) {
        setFieldError(field, 'Password must be at least 8 characters long');
        isValid = false;
    }
    // Custom pattern validation
    else if (field.pattern && value && !new RegExp(field.pattern).test(value)) {
        setFieldError(field, field.title || 'Please match the required format');
        isValid = false;
    }
    else {
        setFieldSuccess(field);
    }
    
    return isValid;
}

function setFieldError(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    
    let feedback = field.parentNode.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.parentNode.appendChild(feedback);
    }
    feedback.textContent = message;
}

function setFieldSuccess(field) {
    field.classList.add('is-valid');
    field.classList.remove('is-invalid');
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
    return /^[\+]?[1-9][\d]{0,15}$/.test(phone.replace(/\s/g, ''));
}

function createPasswordStrengthMeter(passwordInput) {
    const meter = document.createElement('div');
    meter.className = 'password-strength-meter';
    meter.innerHTML = `
        <div class="strength-bar">
            <div class="strength-fill"></div>
        </div>
        <div class="strength-text">Password strength: <span>Weak</span></div>
    `;
    
    passwordInput.parentNode.appendChild(meter);
    return meter;
}

function updatePasswordStrength(passwordInput, meter) {
    const password = passwordInput.value;
    const strength = calculatePasswordStrength(password);
    const fill = meter.querySelector('.strength-fill');
    const text = meter.querySelector('.strength-text span');
    
    const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['#ef4444', '#f59e0b', '#eab308', '#10b981', '#059669'];
    
    fill.style.width = `${(strength / 4) * 100}%`;
    fill.style.backgroundColor = colors[strength];
    text.textContent = levels[strength];
}

function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[^A-Za-z0-9]+/)) strength++;
    
    return Math.min(strength, 4);
}

/**
 * Table Interactions
 */
function initTableInteractions() {
    // Enhanced table hover effects
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.zIndex = '1';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.zIndex = '';
        });
    });
    
    // Sortable tables
    document.querySelectorAll('.sortable-table th').forEach(header => {
        if (!header.classList.contains('no-sort')) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(this);
            });
        }
    });
    
    // Row selection
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            toggleSelectAll(this);
        });
    }
}

function sortTable(header) {
    const table = header.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    const currentOrder = header.getAttribute('data-order') || 'asc';
    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    
    // Update header
    table.querySelectorAll('th').forEach(th => th.removeAttribute('data-order'));
    header.setAttribute('data-order', newOrder);
    
    // Sort rows
    rows.sort((a, b) => {
        const aVal = a.children[columnIndex].textContent.trim();
        const bVal = b.children[columnIndex].textContent.trim();
        
        let comparison = 0;
        if (isNumeric(aVal) && isNumeric(bVal)) {
            comparison = parseFloat(aVal) - parseFloat(bVal);
        } else {
            comparison = aVal.localeCompare(bVal);
        }
        
        return newOrder === 'asc' ? comparison : -comparison;
    });
    
    // Reorder DOM
    rows.forEach(row => tbody.appendChild(row));
    
    // Add visual feedback
    header.style.animation = 'pulse 0.3s ease';
}

function isNumeric(str) {
    return !isNaN(str) && !isNaN(parseFloat(str));
}

/**
 * Tooltips and Popovers
 */
function initTooltipsAndPopovers() {
    // Initialize Bootstrap tooltips
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
}

/**
 * Charts Initialization
 */
function initCharts() {
    if (typeof Chart === 'undefined') return;
    
    // Dashboard overview chart
    const dashboardChart = document.getElementById('dashboardChart');
    if (dashboardChart) {
        createDashboardChart(dashboardChart);
    }
    
    // Application status chart
    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        createStatusChart(statusChart);
    }
}

function createDashboardChart(ctx) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Applications',
                data: [12, 19, 3, 5, 2, 3],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    radius: 6,
                    hoverRadius: 8,
                    backgroundColor: '#667eea'
                }
            }
        }
    });
}

function createStatusChart(ctx) {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending', 'Rejected'],
            datasets: [{
                data: [65, 25, 10],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

/**
 * Search Functionality
 */
function initSearchFunctionality() {
    const searchInputs = document.querySelectorAll('input[type="search"], .search-input');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            performSearch(this);
        }, 300));
    });
}

function performSearch(input) {
    const searchTerm = input.value.toLowerCase();
    const targetTable = document.querySelector(input.getAttribute('data-target') || '.table tbody');
    
    if (!targetTable) return;
    
    const rows = targetTable.querySelectorAll('tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const isVisible = text.includes(searchTerm);
        
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
        
        // Add highlight effect
        if (searchTerm && isVisible) {
            row.classList.add('search-match');
        } else {
            row.classList.remove('search-match');
        }
    });
    
    // Update search results count
    updateSearchResultsCount(input, visibleCount);
}

function updateSearchResultsCount(input, count) {
    let counter = input.parentNode.querySelector('.search-results-count');
    if (!counter) {
        counter = document.createElement('small');
        counter.className = 'search-results-count text-muted';
        input.parentNode.appendChild(counter);
    }
    
    if (input.value.trim()) {
        counter.textContent = `${count} result${count !== 1 ? 's' : ''} found`;
        counter.style.display = 'block';
    } else {
        counter.style.display = 'none';
    }
}

/**
 * Bulk Actions
 */
function initBulkActions() {
    const bulkActionSelect = document.getElementById('bulkAction');
    const applyButton = document.querySelector('[onclick*="applyBulkAction"]');
    
    if (bulkActionSelect && applyButton) {
        bulkActionSelect.addEventListener('change', function() {
            applyButton.disabled = !this.value;
            
            if (this.value) {
                applyButton.classList.add('pulse-animation');
                setTimeout(() => {
                    applyButton.classList.remove('pulse-animation');
                }, 1000);
            }
        });
    }
}

/**
 * Global Functions (used in HTML)
 */
window.applyBulkAction = function() {
    const bulkAction = document.getElementById('bulkAction');
    const selectedItems = document.querySelectorAll('input[name*="ids[]"]:checked');
    
    if (!bulkAction || !bulkAction.value) {
        showNotification('Please select an action', 'warning');
        return;
    }
    
    if (selectedItems.length === 0) {
        showNotification('Please select at least one item', 'warning');
        return;
    }
    
    const actionText = bulkAction.options[bulkAction.selectedIndex].text;
    
    showConfirmDialog(
        `Are you sure you want to ${actionText.toLowerCase()} ${selectedItems.length} item${selectedItems.length > 1 ? 's' : ''}?`,
        function() {
            // Add loading state
            const form = document.getElementById('bulkForm');
            if (form) {
                showNotification('Processing...', 'info');
                form.submit();
            }
        }
    );
};

window.toggleSelectAll = function(checkbox) {
    const checkboxes = document.querySelectorAll('input[name*="ids[]"]');
    
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
        
        // Add visual feedback
        const row = cb.closest('tr');
        if (row) {
            if (cb.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        }
    });
    
    // Update bulk action button state
    const bulkActionSelect = document.getElementById('bulkAction');
    const applyButton = document.querySelector('[onclick*="applyBulkAction"]');
    
    if (applyButton) {
        applyButton.disabled = checkboxes.length === 0 || !bulkActionSelect?.value;
    }
};

/**
 * Notifications System
 */
function initNotifications() {
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            dismissAlert(alert);
        }, 5000);
        
        // Add close button if not present
        if (!alert.querySelector('.btn-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.addEventListener('click', () => dismissAlert(alert));
            alert.appendChild(closeBtn);
        }
    });
}

function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto dismiss
    setTimeout(() => {
        dismissAlert(notification);
    }, duration);
    
    // Manual dismiss
    notification.querySelector('.btn-close').addEventListener('click', () => {
        dismissAlert(notification);
    });
}

function dismissAlert(alert) {
    alert.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 300);
}

function showConfirmDialog(message, onConfirm, onCancel = null) {
    const dialog = document.createElement('div');
    dialog.className = 'confirm-dialog-overlay';
    dialog.innerHTML = `
        <div class="confirm-dialog">
            <div class="confirm-dialog-content">
                <h5>Confirm Action</h5>
                <p>${message}</p>
                <div class="confirm-dialog-buttons">
                    <button class="btn btn-secondary cancel-btn">Cancel</button>
                    <button class="btn btn-primary confirm-btn">Confirm</button>
                </div>
            </div>
        </div>
    `;
    
    // Add styles
    dialog.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
        animation: fadeIn 0.3s ease;
    `;
    
    document.body.appendChild(dialog);
    document.body.style.overflow = 'hidden';
    
    // Event handlers
    dialog.querySelector('.cancel-btn').addEventListener('click', () => {
        closeDialog(dialog);
        if (onCancel) onCancel();
    });
    
    dialog.querySelector('.confirm-btn').addEventListener('click', () => {
        closeDialog(dialog);
        if (onConfirm) onConfirm();
    });
    
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) {
            closeDialog(dialog);
            if (onCancel) onCancel();
        }
    });
}

function closeDialog(dialog) {
    dialog.style.animation = 'fadeOut 0.3s ease';
    setTimeout(() => {
        if (dialog.parentNode) {
            dialog.parentNode.removeChild(dialog);
        }
        document.body.style.overflow = '';
    }, 300);
}

/**
 * Utility Functions
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

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Export Functions
 */
window.exportData = function(format, elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
        showNotification('Export element not found', 'error');
        return;
    }
    
    let content, filename, mimeType;
    
    try {
        switch (format.toLowerCase()) {
            case 'csv':
                content = tableToCSV(element);
                filename = `export_${new Date().toISOString().split('T')[0]}.csv`;
                mimeType = 'text/csv;charset=utf-8;';
                break;
            case 'excel':
                content = tableToExcel(element);
                filename = `export_${new Date().toISOString().split('T')[0]}.xls`;
                mimeType = 'application/vnd.ms-excel';
                break;
            case 'json':
                content = tableToJSON(element);
                filename = `export_${new Date().toISOString().split('T')[0]}.json`;
                mimeType = 'application/json';
                break;
            default:
                showNotification('Unsupported export format', 'error');
                return;
        }
        
        downloadFile(content, filename, mimeType);
        showNotification(`Data exported as ${format.toUpperCase()}`, 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Export failed. Please try again.', 'error');
    }
};

function tableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csvContent = Array.from(rows).map(row => {
        const cells = row.querySelectorAll('th, td');
        return Array.from(cells).map(cell => {
            // Clean cell content and escape quotes
            const content = cell.textContent.replace(/"/g, '""').trim();
            return `"${content}"`;
        }).join(',');
    }).join('\n');
    
    return csvContent;
}

function tableToExcel(table) {
    const tableHtml = table.outerHTML;
    return `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" 
              xmlns:x="urn:schemas-microsoft-com:office:excel" 
              xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="UTF-8">
            <style>
                table { border-collapse: collapse; }
                th, td { border: 1px solid #ccc; padding: 8px; }
                th { background-color: #f0f0f0; font-weight: bold; }
            </style>
        </head>
        <body>${tableHtml}</body>
        </html>
    `;
}

function tableToJSON(table) {
    const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent.trim());
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    
    const data = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        const rowData = {};
        
        headers.forEach((header, index) => {
            if (cells[index]) {
                rowData[header] = cells[index].textContent.trim();
            }
        });
        
        return rowData;
    });
    
    return JSON.stringify(data, null, 2);
}

function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    
    const downloadLink = document.createElement('a');
    downloadLink.href = url;
    downloadLink.download = filename;
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
    
    // Clean up the URL object
    setTimeout(() => URL.revokeObjectURL(url), 100);
}

/**
 * Application Status Timeline
 */
window.showStatusTimeline = function(applicationId) {
    if (!applicationId) {
        showNotification('Invalid application ID', 'error');
        return;
    }
    
    // Show loading state
    showNotification('Loading application status...', 'info');
    
    // Simulate API call (replace with actual endpoint)
    fetchApplicationStatus(applicationId)
        .then(data => {
            displayStatusTimeline(data);
        })
        .catch(error => {
            console.error('Error fetching timeline:', error);
            showNotification('Error loading application status', 'error');
        });
};

function fetchApplicationStatus(applicationId) {
    // This would be replaced with actual API call
    return new Promise((resolve, reject) => {
        setTimeout(() => {
            // Mock data
            const mockData = [
                {
                    date: '2024-01-15',
                    status: 'Application Submitted',
                    description: 'Your application has been successfully submitted and is under review.',
                    icon: 'fas fa-paper-plane',
                    type: 'success'
                },
                {
                    date: '2024-01-18',
                    status: 'Document Verification',
                    description: 'All documents have been verified and approved.',
                    icon: 'fas fa-check-circle',
                    type: 'success'
                },
                {
                    date: '2024-01-22',
                    status: 'Interview Scheduled',
                    description: 'Interview has been scheduled for January 25th at 10:00 AM.',
                    icon: 'fas fa-calendar-alt',
                    type: 'warning'
                },
                {
                    date: '2024-01-25',
                    status: 'Interview Completed',
                    description: 'Interview completed. Results will be announced soon.',
                    icon: 'fas fa-user-check',
                    type: 'info'
                }
            ];
            
            resolve(mockData);
        }, 1000);
    });
}

function displayStatusTimeline(data) {
    const timelineHtml = generateTimelineHTML(data);
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Status Timeline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="timeline-container">
                        ${timelineHtml}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Show modal
    if (typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Clean up when modal is hidden
        modal.addEventListener('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    }
}

function generateTimelineHTML(data) {
    return `
        <div class="timeline">
            ${data.map((item, index) => `
                <div class="timeline-item ${item.type}" style="animation-delay: ${index * 0.1}s">
                    <div class="timeline-marker">
                        <i class="${item.icon}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-date">${formatDate(item.date)}</div>
                        <h6 class="timeline-title">${item.status}</h6>
                        <p class="timeline-description">${item.description}</p>
                    </div>
                </div>
            `).join('')}
        </div>
        
        <style>
            .timeline {
                position: relative;
                padding: 20px 0;
            }
            
            .timeline::before {
                content: '';
                position: absolute;
                left: 30px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: var(--border-color);
            }
            
            .timeline-item {
                position: relative;
                margin-bottom: 30px;
                padding-left: 60px;
                opacity: 0;
                animation: fadeInLeft 0.6s ease forwards;
            }
            
            .timeline-marker {
                position: absolute;
                left: -30px;
                top: 0;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: var(--bg-card);
                border: 3px solid var(--accent-teal);
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--accent-teal);
                font-size: 14px;
            }
            
            .timeline-item.success .timeline-marker {
                border-color: #10b981;
                color: #10b981;
            }
            
            .timeline-item.warning .timeline-marker {
                border-color: #f59e0b;
                color: #f59e0b;
            }
            
            .timeline-item.info .timeline-marker {
                border-color: #3b82f6;
                color: #3b82f6;
            }
            
            .timeline-content {
                background: var(--bg-card);
                padding: 20px;
                border-radius: 12px;
                box-shadow: var(--shadow);
                border-left: 4px solid var(--accent-teal);
            }
            
            .timeline-item.success .timeline-content {
                border-left-color: #10b981;
            }
            
            .timeline-item.warning .timeline-content {
                border-left-color: #f59e0b;
            }
            
            .timeline-item.info .timeline-content {
                border-left-color: #3b82f6;
            }
            
            .timeline-date {
                font-size: 12px;
                color: var(--text-secondary);
                margin-bottom: 5px;
            }
            
            .timeline-title {
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--text-primary);
            }
            
            .timeline-description {
                color: var(--text-secondary);
                margin: 0;
                font-size: 14px;
                line-height: 1.5;
            }
        </style>
    `;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

/**
 * Performance Monitoring
 */
function initPerformanceMonitoring() {
    // Monitor page load time
    window.addEventListener('load', function() {
        setTimeout(() => {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            console.log(`Page loaded in ${loadTime}ms`);
            
            // Show warning if load time is too long
            if (loadTime > 3000) {
                console.warn('Page load time is slower than expected');
            }
        }, 0);
    });
    
    // Monitor JavaScript errors
    window.addEventListener('error', function(e) {
        console.error('JavaScript error:', e.error);
        
        // You could send this to a logging service
        // logError(e.error, e.filename, e.lineno);
    });
    
    // Monitor unhandled promise rejections
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
    });
}

/**
 * Accessibility Enhancements
 */
function initAccessibility() {
    // Add keyboard navigation for custom dropdowns
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // Add ARIA labels to interactive elements
    document.querySelectorAll('button:not([aria-label]):not([aria-labelledby])').forEach(button => {
        if (!button.textContent.trim() && button.innerHTML.includes('fa-')) {
            // Try to determine button purpose from icon
            const icon = button.querySelector('i[class*="fa-"]');
            if (icon) {
                const iconClass = Array.from(icon.classList).find(cls => cls.startsWith('fa-'));
                const label = iconClass ? iconClass.replace('fa-', '').replace('-', ' ') : 'Button';
                button.setAttribute('aria-label', label);
            }
        }
    });
    
    // Add focus indicators for keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigation');
    });
}

/**
 * Initialize everything when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    initPerformanceMonitoring();
    initAccessibility();
});

/**
 * Service Worker Registration (for PWA features)
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed');
            });
    });
}

/**
 * Add CSS animations dynamically
 */
function addDynamicStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .search-match {
            background-color: rgba(102, 126, 234, 0.1) !important;
            transition: background-color 0.3s ease;
        }
        
        .selected {
            background-color: rgba(16, 185, 129, 0.1) !important;
        }
        
        .keyboard-navigation *:focus {
            outline: 3px solid var(--accent-teal) !important;
            outline-offset: 2px !important;
        }
        
        .photo-modal-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
        }
        
        .photo-modal-image {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .photo-modal-close {
            position: absolute;
            top: -15px;
            right: -15px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .photo-modal-close:hover {
            background: #f0f0f0;
            transform: scale(1.1);
        }
        
        .confirm-dialog {
            background: var(--bg-card);
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-hover);
        }
        
        .confirm-dialog-content {
            padding: 2rem;
        }
        
        .confirm-dialog-content h5 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .confirm-dialog-content p {
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
        }
        
        .confirm-dialog-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .password-strength-meter {
            margin-top: 0.5rem;
        }
        
        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
    `;
    
    document.head.appendChild(style);
}

// Add styles when script loads
addDynamicStyles();

// Export for global access
window.ModernDashboard = {
    showNotification,
    showConfirmDialog,
    toggleTheme,
    applyBulkAction: window.applyBulkAction,
    toggleSelectAll: window.toggleSelectAll,
    exportData: window.exportData,
    showStatusTimeline: window.showStatusTimeline
};

console.log('Modern Dashboard JavaScript loaded successfully');