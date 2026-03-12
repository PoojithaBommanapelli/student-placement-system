<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is on a login/register page
$current_page = $_SERVER['PHP_SELF'] ?? '';
$is_login_page = strpos($current_page, 'login') !== false || 
                 strpos($current_page, 'register') !== false ||
                 strpos($current_page, 'signup') !== false ||
                 strpos($current_page, 'signin') !== false;

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get current page to determine active link
$current_script = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <?php if (!$is_logged_in || $is_login_page): ?>
            <!-- Show full navbar for non-logged-in users and login pages -->
            <a class="navbar-brand fw-bold text-dark" href="../index.html">
                <i class="bi bi-briefcase me-2"></i> Student Placement System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        <?php else: ?>
            <!-- For logged-in users on non-login pages -->
            <a class="navbar-brand fw-bold text-dark" href="<?php 
                if ($_SESSION['role'] === 'student') echo '../student/student-dashboard.php';
                elseif ($_SESSION['role'] === 'company') echo '../company/company-dashboard.php';
                elseif ($_SESSION['role'] === 'admin') echo '../admin/admin-dashboard.php';
            ?>">
                <i class="bi bi-briefcase me-2"></i> Student Placement System
            </a>
            
            <!-- Show toggler for mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        <?php endif; ?>
        
        <div class="collapse navbar-collapse" id="navbarContent">
            <!-- Navigation items - Hidden on desktop, visible on mobile -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 d-lg-none">
                <?php if (!$is_logged_in || $is_login_page): ?>
                    <!-- Navigation for non-logged-in users and login pages -->
                    
                <?php else: ?>
                    <!-- Navigation for logged-in users (mobile only) -->
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'student-dashboard.php') ? 'active fw-bold' : ''; ?>" href="../student/student-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'student-profile.php') ? 'active fw-bold' : ''; ?>" href="../student/student-profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'student-resume.php') ? 'active fw-bold' : ''; ?>" href="../student/student-resume.php">
                                <i class="bi bi-file-earmark-text me-2"></i>Resume Builder
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'student-applied.php') ? 'active fw-bold' : ''; ?>" href="../student/student-applied.php">
                                <i class="bi bi-list-check me-2"></i>Applied Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'student-jobs.php') ? 'active fw-bold' : ''; ?>" href="../student/student-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>Job Listings
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'company'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'company-dashboard.php') ? 'active fw-bold' : ''; ?>" href="../company/company-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'company-profile.php') ? 'active fw-bold' : ''; ?>" href="../company/company-profile.php">
                                <i class="bi bi-building me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'company-postjob.php') ? 'active fw-bold' : ''; ?>" href="../company/company-postjob.php">
                                <i class="bi bi-plus-circle me-2"></i>Post Job
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'company-jobs.php') ? 'active fw-bold' : ''; ?>" href="../company/company-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'company-applicants.php') ? 'active fw-bold' : ''; ?>" href="../company/company-applicants.php">
                                <i class="bi bi-people me-2"></i>Applicants
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'admin-dashboard.php') ? 'active fw-bold' : ''; ?>" href="../admin/admin-dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'admin-students.php') ? 'active fw-bold' : ''; ?>" href="../admin/admin-students.php">
                                <i class="bi bi-people me-2"></i>Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'admin-companies.php') ? 'active fw-bold' : ''; ?>" href="../admin/admin-companies.php">
                                <i class="bi bi-buildings me-2"></i>Companies
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'admin-jobs.php') ? 'active fw-bold' : ''; ?>" href="../admin/admin-jobs.php">
                                <i class="bi bi-briefcase me-2"></i>Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark <?php echo ($current_script == 'admin-reports.php') ? 'active fw-bold' : ''; ?>" href="../admin/admin-reports.php">
                                <i class="bi bi-bar-chart me-2"></i>Reports
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex align-items-center ms-auto">
                <?php if ($is_logged_in && !$is_login_page): ?>
                    <!-- Portal text on the right side (desktop only) -->
                    <div class="me-3 d-none d-lg-block">
                        <span class="text-dark fw-bold">
                            <?php if ($_SESSION['role'] === 'student'): ?>
                                Student Portal
                            <?php elseif ($_SESSION['role'] === 'company'): ?>
                                Company Portal
                            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                Admin Portal
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <!-- User dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo $_SESSION['name'] ?? 'User'; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <?php if ($_SESSION['role'] === 'student'): ?>
                                    <a class="dropdown-item text-dark" href="../student/student-logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                <?php elseif ($_SESSION['role'] === 'company'): ?>
                                    <a class="dropdown-item text-dark" href="../company/company-logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                    <a class="dropdown-item text-dark" href="../admin/admin-logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Login buttons for non-logged-in users and login pages -->
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <a href="../student/student-login.php" class="btn btn-outline-primary text-dark">Student Login</a>
                        <a href="../company/company-login.php" class="btn btn-outline-secondary text-dark">Company Login</a>
                        <a href="../admin/admin-login.php" class="btn btn-outline-primary text-dark">Admin Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Color variables */
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --text-dark: #212529;
        --text-light: #6c757d;
    }
    
    /* Mobile specific styles */
    @media (max-width: 991.98px) {
        /* Navbar styling */
        .navbar {
            background-color: white !important;
        }
        
        .navbar-brand {
            color: var(--text-dark) !important;
        }
        
        /* Navigation links - black text */
        .navbar-nav .nav-link {
            color: var(--text-dark) !important;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin: 0.125rem 0;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: #f8f9fa;
            color: var(--text-dark) !important;
        }
        
        /* Active link styling - blue background with white text */
        .navbar-nav .nav-link.active {
            background-color: var(--primary-color) !important;
            color: white !important;
            font-weight: 600;
        }
        
        /* User dropdown button - blue background with white text */
        .navbar .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white !important;
        }
        
        .navbar .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white !important;
        }
        
        /* Dropdown menu styling */
        .dropdown-menu {
            background-color: white;
            border: 1px solid rgba(0,0,0,0.15);
        }
        
        .dropdown-item {
            color: var(--text-dark);
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--text-dark);
        }
        
        /* Login buttons styling */
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-outline-secondary {
            color: var(--text-light);
            border-color: var(--text-light);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--text-light);
            color: white;
        }
        
        .btn-outline-dark {
            color: var(--text-dark);
            border-color: var(--text-dark);
        }
        
        .btn-outline-dark:hover {
            background-color: var(--text-dark);
            color: white;
        }
        
        /* Adjust layout for mobile */
        .navbar-collapse {
            padding: 1rem 0;
        }
        
        .d-flex.align-items-center {
            margin-top: 1rem;
            width: 100%;
        }
        
        .dropdown {
            width: 100%;
        }
        
        .dropdown .btn {
            width: 100%;
            text-align: left;
        }
        
        /* Login buttons container */
        .d-flex.flex-column.flex-md-row.gap-2 {
            width: 100%;
        }
        
        .d-flex.flex-column.flex-md-row.gap-2 .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .d-flex.flex-column.flex-md-row.gap-2 {
                flex-direction: row !important;
            }
            
            .d-flex.flex-column.flex-md-row.gap-2 .btn {
                width: auto;
                margin-bottom: 0;
            }
        }
    }
    
    /* Desktop specific styles */
    @media (min-width: 992px) {
        /* Hide navigation items on desktop */
        .navbar-nav.d-lg-none {
            display: none !important;
        }
        
        /* Push content to the right on desktop */
        .ms-auto {
            margin-left: auto !important;
        }
        
        /* Hide portal text on mobile */
        .d-none.d-lg-block {
            display: none;
        }
        
        @media (min-width: 992px) {
            .d-none.d-lg-block {
                display: block !important;
            }
        }
        
        /* Normal navbar behavior for desktop */
        .navbar-nav .nav-link {
            color: var(--text-dark) !important;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        .navbar-nav .nav-link.active {
            color: var(--primary-color) !important;
            font-weight: 600;
            background-color: transparent !important;
        }
    }
    
    /* Common styles for all devices */
    .navbar-toggler {
        border: none;
    }
    
    .navbar-toggler:focus {
        box-shadow: none;
    }
</style>

<script>
    // Add data attributes to navbar for logged-in state and page type
    document.addEventListener('DOMContentLoaded', function() {
        const navbar = document.querySelector('.navbar');
        <?php if ($is_logged_in): ?>
            navbar.setAttribute('data-loggedin', 'true');
        <?php else: ?>
            navbar.setAttribute('data-loggedin', 'false');
        <?php endif; ?>
        
        <?php if ($is_login_page): ?>
            navbar.setAttribute('data-loginpage', 'true');
        <?php else: ?>
            navbar.setAttribute('data-loginpage', 'false');
        <?php endif; ?>
    });
</script>